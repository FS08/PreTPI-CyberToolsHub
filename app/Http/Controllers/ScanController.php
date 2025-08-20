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
     * look up SPF for the sender domain, and submit up to N URLs to urlscan.io
     * (rate‑limited, non‑blocking).
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

        // 10) SPF lookup (best effort)
        $spf = $fromDomain ? $this->lookupSpfForDomain($fromDomain) : [
            'found' => false,
            'records' => [],
            'note' => 'No sender domain detected',
        ];

        // 11) Build payload for UI (no body content)
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
                'spf'         => $spf,   // <-- show SPF summary in the UI if you want
            ],
        ];

        // 12) Persist minimal, privacy‑friendly metadata (+ SPF JSON)
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
            'spf_json'          => $spf,   // <-- new
        ]);

        // 13) Submit URLs to urlscan.io (limited, non‑blocking)
        $submitted  = [];
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
                    // DO NOT wait here (keep request fast)
                    $resp = $this->urlscan->submit($u, $visibility);

                    $row->update([
                        'status'       => 'submitted',
                        'result_uuid'  => $resp['uuid']   ?? null,
                        'result_url'   => $resp['result'] ?? null,
                        'error_message'=> null,
                    ]);
                    $submitted[] = [
                        'url'    => $u,
                        'uuid'   => $resp['uuid']   ?? null,
                        'result' => $resp['result'] ?? null,
                        'status' => 'submitted',
                    ];
                } catch (\Throwable $e) {
                    $row->update([
                        'status'        => 'error',
                        'error_message' => $e->getMessage(),
                    ]);
                    $submitted[] = [
                        'url'    => $u,
                        'status' => 'error',
                        'error'  => $e->getMessage(),
                    ];
                }

                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }
            }
        }

        return back()
            ->with('ok', 'File parsed, saved to history, SPF checked, and URLs submitted to urlscan (non‑blocking).')
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
            'extra'       => [
                'dateIso' => optional($scan->date_iso)->toIso8601String(),
                'spf'     => $scan->spf_json,   // available for display if you want
            ],
        ];

        $scan->load('urls');

        return view('scans.show', ['scan' => $scan, 'results' => $results]);
    }

    /* ------------------------ helpers ------------------------ */

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

    /**
     * SPF lookup for a domain.
     * Returns:
     *  [
     *    'found'   => bool,
     *    'records' => [ 'v=spf1 include:_spf.example.com ~all', ... ],
     *    'parsed'  => [
     *        ['record' => 'v=spf1 ...', 'mechanisms' => [...], 'all' => '~all', 'redirect' => '...', ...],
     *    ],
     *    'error'   => string|null
     *  ]
     */
    private function lookupSpfForDomain(string $domain): array
    {
        $out = ['found' => false, 'records' => [], 'parsed' => [], 'error' => null];

        // dns_get_record may be disabled in some hostings
        if (!function_exists('dns_get_record')) {
            $out['error'] = 'dns_get_record() not available on this PHP environment';
            return $out;
        }

        try {
            $txts = @dns_get_record($domain, DNS_TXT);
            if (!$txts || !is_array($txts)) {
                return $out;
            }

            $spfRecords = [];
            foreach ($txts as $row) {
                $txt = $row['txt'] ?? '';
                if (is_array($txt)) {
                    $txt = implode('', $txt);
                }
                $txt = trim($txt);
                if (stripos($txt, 'v=spf1') === 0) {
                    $spfRecords[] = $txt;
                }
            }

            if (empty($spfRecords)) {
                return $out;
            }

            $out['found'] = true;
            $out['records'] = $spfRecords;

            // parse each SPF record into mechanisms
            foreach ($spfRecords as $rec) {
                $out['parsed'][] = $this->parseSpfRecord($rec);
            }

            return $out;
        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();
            return $out;
        }
    }

    /**
     * Very light SPF parser: splits mechanisms and captures common ones.
     * Example: "v=spf1 ip4:203.0.113.0/24 include:_spf.example.com a mx ~all"
     */
    private function parseSpfRecord(string $record): array
    {
        // remove the leading v=spf1
        $rest = trim(preg_replace('/^v=spf1\s*/i', '', $record));

        $parts = preg_split('/\s+/', $rest);
        $mechanisms = [];
        $all = null;
        $redirect = null;
        $exp = null;

        foreach ($parts as $p) {
            if ($p === '') continue;

            // "all" mechanism (often with qualifier: -all, ~all, ?all, +all)
            if (preg_match('/^([~+\-?])?all$/i', $p, $m)) {
                $all = $m[0];
                $mechanisms[] = ['type' => 'all', 'raw' => $m[0]];
                continue;
            }

            // redirect= / exp=
            if (stripos($p, 'redirect=') === 0) {
                $redirect = substr($p, 9);
                $mechanisms[] = ['type' => 'redirect', 'value' => $redirect];
                continue;
            }
            if (stripos($p, 'exp=') === 0) {
                $exp = substr($p, 4);
                $mechanisms[] = ['type' => 'exp', 'value' => $exp];
                continue;
            }

            // ip4:, ip6:, include:, a, mx, ptr, exists:
            if (preg_match('/^(ip4|ip6|include|exists|ptr):(.+)$/i', $p, $m)) {
                $mechanisms[] = ['type' => strtolower($m[1]), 'value' => $m[2]];
                continue;
            }
            if (in_array(strtolower($p), ['a', 'mx'], true)) {
                $mechanisms[] = ['type' => strtolower($p)];
                continue;
            }

            // anything else, keep raw
            $mechanisms[] = ['type' => 'other', 'raw' => $p];
        }

        return [
            'record'     => $record,
            'mechanisms' => $mechanisms,
            'all'        => $all,
            'redirect'   => $redirect,
            'exp'        => $exp,
        ];
    }
}