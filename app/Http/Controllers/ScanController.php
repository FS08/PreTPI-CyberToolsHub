<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use ZBateson\MailMimeParser\MailMimeParser;    // <-- new parser
use ZBateson\MailMimeParser\Message;           // (optional type hint)

class ScanController extends Controller
{
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

        // 5) Bodies (store only lengths)
        $textBody = $message->getTextContent() ?? '';
        $htmlBody = $message->getHtmlContent() ?? '';

        // 6) Attachments count
        $attachCount = count(iterator_to_array($message->getAllAttachmentParts()));

        // 7) Sender domain (best‑effort)
        $fromDomain = $this->extractDomainFromAddress($from);

        // 8) Build result payload
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
        ];

        return back()->with('ok', 'File parsed in memory.')->with('results', $results);
    }

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
}