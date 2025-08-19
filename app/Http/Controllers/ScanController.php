<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Scan;                           // <-- new (model from step 4.2)
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message;
use Carbon\Carbon;

class ScanController extends Controller
{
    /**
     * Handle upload, parse in memory, extract indicators + metadata,
     * then persist a minimal Scan record (no email body stored).
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

        // 2) Read from PHP temp (no persistence of the file itself)
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
        } catch (\Throwable $e) {
            $dateIso = null;
        }

        // 5) Bodies (lengths only)
        $textBody = $message->getTextContent() ?? '';
        $htmlBody = $message->getHtmlContent() ?? '';

        // 6) Attachments (count only here)
        $attachCount = count(iterator_to_array($message->getAllAttachmentParts()));

        // 7) Sender domain (best-effort)
        $fromDomain = $this->extractDomainFromAddress($from);

        // 8) URL extraction
        $combined = $textBody . "\n" . strip_tags($htmlBody);
        $urls     = $this->extractUrls($combined);

        // 9) Extra metadata
        $messageId   = $message->getHeaderValue('message-id') ?? null;
        $contentType = $message->getHeaderValue('content-type') ?? null;

        $received = [];
        foreach ($message->getAllHeadersByName('received') as $h) {
            $received[] = (string) $h->getValue();
        }

        // 10) Build payload for the UI (no body content)
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
            'attachments' => [
                'count' => $attachCount,
            ],
            'urls'        => $urls,
            'extra'       => [
                'messageId'   => $messageId,
                'contentType' => $contentType,
                'received'    => $received,
                'dateIso'     => $dateIso,
            ],
        ];

        // 11) Persist minimal, privacy-friendly metadata in DB
        //     (requires scans table + Scan model from step 4.2)
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
            'urls_json'         => $urls,   // optional convenience; no bodies stored
        ]);

        return back()
            ->with('ok', 'File parsed in memory and saved to history.')
            ->with('results', $results)
            ->with('scanId', $scan->id);
    }

    /**
     * History page: list scans for the authenticated user (paginated).
     */
    public function history()
    {
        $scans = Scan::where('user_id', auth()->id())
            ->latest()
            ->paginate(10);

        return view('history', compact('scans'));
    }

    /** Best-effort domain extraction from a From: header. */
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

    /** Extract http/https URLs, normalize and dedupe. */
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

    /** Normalize URL (trim trailing punct, lower-case host, http/https only). */
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