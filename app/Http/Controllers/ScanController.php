<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message;

class ScanController extends Controller
{
    /**
     * Handle upload, parse in memory, and extract basic indicators (URLs).
     */
    public function store(Request $request)
    {
        // 1) Validation unchanged
        $request->validate(
            ['eml' => ['required', 'file', 'mimetypes:message/rfc822', 'max:15360']],
            [
                'eml.required'  => 'Please select a file.',
                'eml.file'      => 'The upload must be a file.',
                'eml.mimetypes' => 'Only .eml files are allowed (MIME message/rfc822).',
                'eml.max'       => 'The email must not exceed 15MB.',
            ]
        );

        // 2) Read from PHP's temp path (no persistence)
        $tmpPath = $request->file('eml')->getRealPath();
        $raw     = file_get_contents($tmpPath);

        // 3) Parse in memory
        $parser  = new MailMimeParser();
        /** @var Message $message */
        $message = $parser->parse($raw, false);

        // 4) Extract simple metadata
        $from    = $message->getHeaderValue('from');     // e.g. Alice <alice@example.com>
        $to      = $message->getHeaderValue('to');
        $subject = $message->getHeaderValue('subject');
        $date    = $message->getHeaderValue('date');

        // 5) Bodies (keep both for indicator extraction; store only lengths)
        $textBody = $message->getTextContent() ?? '';
        $htmlBody = $message->getHtmlContent() ?? '';

        // 6) Attachments count
        $attachCount = count(iterator_to_array($message->getAllAttachmentParts()));

        // 7) Sender domain (best‑effort)
        $fromDomain = $this->extractDomainFromAddress($from);

        // 8) URL extraction (V1: regex on text + stripped HTML)
        $combined = $textBody . "\n" . strip_tags($htmlBody);
        $urls     = $this->extractUrls($combined);   // normalized + deduped list

        // 9) Build result payload
        $results = [
            'from'        => $from,
            'fromDomain'  => $fromDomain,
            'to'          => $to,
            'subject'     => $subject,
            'date'        => $date,
            'bodies'      => [
                'textLength' => mb_strlen($textBody, 'UTF-8'),
                'htmlLength' => mb_strlen($htmlBody, 'UTF-8'),
            ],
            'attachments' => [
                'count' => $attachCount,
            ],
            'urls'        => $urls, // <-- new
        ];

        // Flash results back to the page
        return back()
            ->with('ok', 'File parsed in memory.')
            ->with('results', $results);
    }

    /**
     * Best‑effort domain extraction from a From: header.
     */
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

    /**
     * Extracts http/https URLs from a string, normalizes, dedupes, and filters schemes.
     */
    private function extractUrls(string $text): array
    {
        $urls = [];

        // Basic regex for http/https (avoid quotes/brackets/whitespace)
        if (preg_match_all('~\bhttps?://[^\s<>"\'(){}\[\]]+~i', $text, $m)) {
            foreach ($m[0] as $raw) {
                $url = $this->normalizeUrl($raw);
                if ($url !== null) {
                    $urls[] = $url;
                }
            }
        }

        // Deduplicate while preserving order
        $urls = array_values(array_unique($urls));

        return $urls;
    }

    /**
     * Normalizes a URL:
     *  - trims trailing punctuation
     *  - lowercases host only (path/query casing kept)
     *  - filters to http/https schemes
     */
    private function normalizeUrl(string $raw): ?string
    {
        // Trim common trailing punctuation (.),),;,]
        $raw = rtrim($raw, ".,);]");

        $parts = parse_url($raw);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null; // drop javascript:, data:, etc.
        }

        // Lowercase host; rebuild URL
        $host   = strtolower($parts['host']);
        $path   = $parts['path']  ?? '';
        $query  = isset($parts['query']) ? ('?' . $parts['query']) : '';
        $frag   = isset($parts['fragment']) ? ('#' . $parts['fragment']) : '';

        return $scheme . '://' . $host . $path . $query . $frag;
    }
}