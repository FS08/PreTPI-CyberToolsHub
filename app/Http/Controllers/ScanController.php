<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Scan;
use App\Models\ScanUrl;
use App\Services\UrlscanClient;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message;
use Carbon\Carbon;

class ScanController extends Controller
{
    public function __construct(private UrlscanClient $urlscan) {}

    /**
     * Handle upload, parse in memory, extract indicators + metadata,
     * persist a minimal Scan record (no email body stored),
     * and submit up to N URLs to urlscan.io (rate‑limited + short polling).
     */
    public function store(Request $request)
    {
        // 1) Validate: .eml, <= 15MB
        $request->validate(
            ['eml' => ['required', 'file', 'mimetypes:message/rfc822', 'max:15360']],
            [
                'eml.required'  => 'Please select a file.',
                'eml.file'      => 'The upload must be a file.',
                'eml.mimetypes' => 'Only .eml files are allowed (MIME message/rfc822).',
                'eml.max'       => 'The email must not exceed 15MB.',
            ]
        );

        // 2) Read from PHP temp (no file persistence)
        $tmpPath = $request->file('eml')->getRealPath();
        $raw     = file_get_contents($tmpPath);

        // 3) Parse in memory
        $parser  = new MailMimeParser();
        /** @var Message $message */
        $message = $parser->parse($raw, false);

        // 4) Basic headers
        $from    = $message->getHeaderValue('from');
        $to      = $message->getHeaderValue('to');
        $subject = $message->getHeaderValue('subject');
        $dateRaw = $message->getHeaderValue('date');

        // Normalize date → ISO-8601 (best effort)
        $dateIso = null;
        try {
            if (!empty($dateRaw)) {
                $dateIso = Carbon::parse($dateRaw)->toIso8601String();
            }
        } catch (\Throwable) {
            $dateIso = null;
        }

        // 5) Bodies (lengths only)
        $textBody = $message->getTextContent() ?? '';
        $htmlBody = $message->getHtmlContent() ?? '';

        // 6) Attachments (count only)
        $attachCount = count(iterator_to_array($message->getAllAttachmentParts()));

        // 7) Sender domain (best‑effort)
        $fromDomain = $this->extractDomainFromAddress($from);

        // 8) URL extraction (normalized + deduped)
        $combined = $textBody . "\n" . strip_tags($htmlBody);
        $urls     = $this->extractUrls($combined);

        // 9) Extra metadata
        $messageId   = $message->getHeaderValue('message-id') ?? null;
        $contentType = $message->getHeaderValue('content-type') ?? null;

        $received = [];
        foreach ($message->getAllHeadersByName('received') as $h) {
            $received[] = (string) $h->getValue();
        }

        // 10) Build payload for UI (no body content)
        $results = [
            'from'        => $from,
            'fromDomain'  => $fromDomain,
            'to'          => $to,
            'subject'     => $subject,
            'dateRaw'     => $dateRaw,
            'bodies'      => [
                'textLength' => mb_strlen($textBody, 'UTF-8'),
                'htmlLength' => mb_strlen($htmlBody, 'UTF-8'),
                'rawSize'    => strlen($raw),
            ],
            'attachments' => ['count' => $attachCount],
            'urls'        => $urls,
            'extra'       => [
                'messageId'   => $messageId,
                'contentType' => $contentType,
                'received'    => $received,
                'dateIso'     => $dateIso,
            ],
        ];

        // 11) Persist minimal, privacy‑friendly metadata
        $scan = Scan::create([
            'user_id'           => Auth::id(),
            'from'              => $from,
            'from_domain'       => $fromDomain,
            'to'                => $to,
            'subject'           => $subject,
            'date_raw'          => $dateRaw,
            'date_iso'          => $dateIso,
            'text_length'       => $results['bodies']['textLength'] ?? 0,
            'html_length'       => $results['bodies']['htmlLength'] ?? 0,
            'raw_size'          => $results['bodies']['rawSize'] ?? 0,
            'attachments_count' => $attachCount,
            'urls_count'        => count($urls),
            'urls_json'         => $urls,
        ]);

        // 12) Submit URLs to urlscan.io (limited, rate‑limited) + short polling
        $submitted = [];
        $enabled    = (bool) config('urlscan.enabled', true);
        $maxPerScan = (int)  config('urlscan.max_per_scan', 5);
        $sleepMs    = (int)  config('urlscan.rate_sleep_ms', 300);
        $visibility = (string) config('urlscan.visibility', 'unlisted'); // public|unlisted|private

        if ($enabled && !empty($urls)) {
            $batch = array_slice($urls, 0, max(0, $maxPerScan));

            foreach ($batch as $u) {
                $row = ScanUrl::create([
                    'scan_id'    => $scan->id,
                    'url'        => $u,
                    'host'       => parse_url($u, PHP_URL_HOST) ?: null,
                    'visibility' => $visibility,
                    'status'     => 'queued',
                ]);

                try {
                    $resp = $this->urlscan->submit($u, $visibility);

                    $uuid      = $resp['uuid']   ?? null;
                    $resultUrl = $resp['result'] ?? null;

                    $row->update([
                        'status'       => 'submitted',
                        'result_uuid'  => $uuid,
                        'result_url'   => $resultUrl,
                        'error_message'=> null,
                    ]);

                    // --- Short polling (wait up to ~15s) ---
                    if ($uuid) {
                        $final = $this->urlscan->waitForResult($uuid, maxSeconds: 15, intervalSeconds: 2);
                        if ($final) {
                            $row->update(['status' => 'finished']);
                        }
                    }

                    $submitted[] = [
                        'url'    => $u,
                        'uuid'   => $uuid,
                        'result' => $resultUrl,
                        'status' => $row->status, // submitted | finished
                    ];
                } catch (\Throwable $e) {
                    $row->update([
                        'status'        => 'error',
                        'error_message' => $e->getMessage(),
                    ]);
                    $submitted[] = ['url' => $u, 'status' => 'error', 'error' => $e->getMessage()];
                }

                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }
            }
        }

        return back()
            ->with('ok', 'File parsed, saved to history, and URLs submitted to urlscan (limited).')
            ->with('results', $results)
            ->with('scanId', $scan->id)
            ->with('urlscanSubmitted', $submitted);
    }

    public function history()
    {
        $scans = Scan::where('user_id', auth()->id())->latest()->paginate(10);
        return view('history', compact('scans'));
    }

    public function show(Scan $scan)
    {
        abort_if($scan->user_id !== auth()->id(), 403, 'Not authorized.');

        $results = [
            'from'        => $scan->from,
            'fromDomain'  => $scan->from_domain,
            'to'          => $scan->to,
            'subject'     => $scan->subject,
            'dateRaw'     => $scan->date_raw,
            'bodies'      => [
                'textLength' => (int) $scan->text_length,
                'htmlLength' => (int) $scan->html_length,
                'rawSize'    => (int) $scan->raw_size,
            ],
            'attachments' => ['count' => (int) $scan->attachments_count],
            'urls'        => $scan->urls_json ?? [],
            'extra'       => ['dateIso' => optional($scan->date_iso)->toIso8601String()],
        ];

        $scan->load('urls'); // ensure hasMany ScanUrl

        return view('scans.show', ['scan' => $scan, 'results' => $results]);
    }

    /** Helpers */
    private function extractDomainFromAddress(string $from): ?string
    {
        if (preg_match('/<([^>]+)>/', $from, $m)) {
            $email = $m[1];
        } elseif (preg_match('/[A-Z0-9._%+-]+@([A-Z0-9.-]+\.[A-Z]{2,})/i', $from, $m)) {
            return strtolower($m[1]);
        } else {
            return null;
        }
        $parts = explode('@', $email);
        return count($parts) === 2 ? strtolower($parts[1]) : null;
    }

    private function extractUrls(string $text): array
    {
        $urls = [];
        if (preg_match_all('~\bhttps?://[^\s<>"\'(){}\[\]]+~i', $text, $m)) {
            foreach ($m[0] as $raw) {
                $url = $this->normalizeUrl($raw);
                if ($url !== null) $urls[] = $url;
            }
        }
        return array_values(array_unique($urls));
    }

    private function normalizeUrl(string $raw): ?string
    {
        $raw = rtrim($raw, ".,);]");
        $parts = parse_url($raw);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) return null;

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) return null;

        $host  = strtolower($parts['host']);
        $path  = $parts['path']  ?? '';
        $query = isset($parts['query']) ? ('?' . $parts['query']) : '';
        $frag  = isset($parts['fragment']) ? ('#' . $parts['fragment']) : '';

        return $scheme . '://' . $host . $path . $query . $frag;
    }
}