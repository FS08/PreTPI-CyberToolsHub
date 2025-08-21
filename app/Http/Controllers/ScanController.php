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

    public function store(Request $request)
    {
        // 1) Validate EML upload
        $request->validate(
            ['eml' => ['required', 'file', 'mimetypes:message/rfc822', 'max:15360']],
            [
                'eml.required'  => 'Please select a file.',
                'eml.file'      => 'The upload must be a file.',
                'eml.mimetypes' => 'Only .eml files are allowed (MIME message/rfc822).',
                'eml.max'       => 'The email must not exceed 15MB.',
            ]
        );

        // 2) Read (no file persistence)
        $tmpPath = $request->file('eml')->getRealPath();
        $raw     = file_get_contents($tmpPath);

        // 3) Parse in memory
        $parser  = new MailMimeParser();
        /** @var Message $message */
        $message = $parser->parse($raw, false);

        // 4) Basic headers (cast to string, trim; they can be null)
        $from    = trim((string) $message->getHeaderValue('from'));
        $to      = trim((string) $message->getHeaderValue('to'));
        $subject = trim((string) $message->getHeaderValue('subject'));
        $dateRaw = trim((string) $message->getHeaderValue('date'));

        // Normalize date → ISO-8601 (best effort)
        $dateIso = null;
        try {
            if ($dateRaw !== '') {
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
        $received    = [];
        foreach ($message->getAllHeadersByName('received') as $h) {
            $received[] = (string) $h->getValue();
        }

        // 10) SPF + DMARC lookup (best effort)
        $spf   = $fromDomain ? $this->lookupSpfForDomain($fromDomain)   : ['found' => false, 'records' => [], 'note' => 'No sender domain'];
        $dmarc = $fromDomain ? $this->lookupDmarcForDomain($fromDomain) : ['found' => false, 'records' => [], 'note' => 'No sender domain'];

        // 11) Heuristics (5.4) — first rule: brand/display vs real domain
        $heuristics = $this->computeHeuristics(from: $from, realDomain: $fromDomain, spf: $spf, dmarc: $dmarc);

        // 12) Build payload for UI
        $results = [
            'from'        => $from !== '' ? $from : '—',
            'fromDomain'  => $fromDomain ?? '—',
            'to'          => $to !== '' ? $to : '—',
            'subject'     => $subject !== '' ? $subject : '—',
            'dateRaw'     => $dateRaw !== '' ? $dateRaw : '—',
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
                'spf'         => $spf,
                'dmarc'       => $dmarc,
                'heuristics'  => $heuristics,
            ],
        ];

        // 13) Persist
        $scan = Scan::create([
            'user_id'           => Auth::id(),
            'from'              => $from !== '' ? $from : null,
            'from_domain'       => $fromDomain,
            'to'                => $to !== '' ? $to : null,
            'subject'           => $subject !== '' ? $subject : null,
            'date_raw'          => $dateRaw !== '' ? $dateRaw : null,
            'date_iso'          => $dateIso,
            'text_length'       => $results['bodies']['textLength'] ?? 0,
            'html_length'       => $results['bodies']['htmlLength'] ?? 0,
            'raw_size'          => $results['bodies']['rawSize'] ?? 0,
            'attachments_count' => $attachCount,
            'urls_count'        => count($urls),
            'urls_json'         => $urls,
            'spf_json'          => $spf,
            'dmarc_json'        => $dmarc,
            'heuristics_json'   => $heuristics, // <-- NEW
        ]);

        // 14) Submit URLs to urlscan.io (non‑blocking)
        $submitted  = [];
        $enabled    = (bool) config('urlscan.enabled', true);
        $maxPerScan = (int)  config('urlscan.max_per_scan', 5);
        $sleepMs    = (int)  config('urlscan.rate_sleep_ms', 300);
        $visibility = (string) config('urlscan.visibility', 'unlisted');

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

                    $row->update([
                        'status'       => 'submitted',
                        'result_uuid'  => $resp['uuid']   ?? null,
                        'result_url'   => $resp['result'] ?? null,
                        'error_message'=> null,
                    ]);

                    $submitted[] = [
                        'url' => $u, 'uuid' => $resp['uuid'] ?? null, 'result' => $resp['result'] ?? null, 'status' => 'submitted',
                    ];
                } catch (\Throwable $e) {
                    $row->update([
                        'status'        => 'error',
                        'error_message' => $e->getMessage(),
                    ]);

                    $submitted[] = [
                        'url' => $u, 'status' => 'error', 'error' => $e->getMessage(),
                    ];
                }

                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }
            }
        }

        return back()
            ->with('ok', 'File parsed, saved, SPF/DMARC checked, heuristics computed, and URLs submitted (non‑blocking).')
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
            'from'        => $scan->from ?? '—',
            'fromDomain'  => $scan->from_domain ?? '—',
            'to'          => $scan->to ?? '—',
            'subject'     => $scan->subject ?? '—',
            'dateRaw'     => $scan->date_raw ?? '—',
            'bodies'      => [
                'textLength' => (int) $scan->text_length,
                'htmlLength' => (int) $scan->html_length,
                'rawSize'    => (int) $scan->raw_size,
            ],
            'attachments' => ['count' => (int) $scan->attachments_count],
            'urls'        => $scan->urls_json ?? [],
            'extra'       => [
                'dateIso'     => optional($scan->date_iso)->toIso8601String(),
                'spf'         => $scan->spf_json,
                'dmarc'       => $scan->dmarc_json,
                'heuristics'  => $scan->heuristics_json, // expose to view
            ],
        ];

        $scan->load('urls');

        return view('scans.show', ['scan' => $scan, 'results' => $results]);
    }

    /* ------------------------ helpers ------------------------ */

    private function extractDomainFromAddress(?string $from): ?string
    {
        if (!$from || trim($from) === '') return null;

        // Name <email@domain>
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

    /** SPF lookup (existing) */
    private function lookupSpfForDomain(string $domain): array
    {
        $out = ['found' => false, 'records' => [], 'parsed' => [], 'error' => null];

        if (!function_exists('dns_get_record')) {
            $out['error'] = 'dns_get_record() not available on this PHP environment';
            return $out;
        }

        try {
            $txts = @dns_get_record($domain, DNS_TXT);
            if (!$txts || !is_array($txts)) return $out;

            $spfRecords = [];
            foreach ($txts as $row) {
                $txt = $row['txt'] ?? '';
                if (is_array($txt)) $txt = implode('', $txt);
                $txt = trim($txt);
                if (stripos($txt, 'v=spf1') === 0) $spfRecords[] = $txt;
            }
            if (empty($spfRecords)) return $out;

            $out['found']   = true;
            $out['records'] = $spfRecords;
            foreach ($spfRecords as $rec) {
                $out['parsed'][] = $this->parseSpfRecord($rec);
            }
            return $out;
        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();
            return $out;
        }
    }

    private function parseSpfRecord(string $record): array
    {
        $rest = trim(preg_replace('/^v=spf1\s*/i', '', $record));
        $parts = preg_split('/\s+/', $rest);
        $mechanisms = [];
        $all = null; $redirect = null; $exp = null;

        foreach ($parts as $p) {
            if ($p === '') continue;

            if (preg_match('/^([~+\-?])?all$/i', $p, $m)) {
                $all = $m[0]; $mechanisms[] = ['type' => 'all', 'raw' => $m[0]]; continue;
            }
            if (stripos($p, 'redirect=') === 0) { $redirect = substr($p, 9); $mechanisms[] = ['type' => 'redirect', 'value' => $redirect]; continue; }
            if (stripos($p, 'exp=') === 0)      { $exp      = substr($p, 4);  $mechanisms[] = ['type' => 'exp', 'value' => $exp]; continue; }

            if (preg_match('/^(ip4|ip6|include|exists|ptr):(.+)$/i', $p, $m)) { $mechanisms[] = ['type' => strtolower($m[1]), 'value' => $m[2]]; continue; }
            if (in_array(strtolower($p), ['a','mx'], true)) { $mechanisms[] = ['type' => strtolower($p)]; continue; }

            $mechanisms[] = ['type' => 'other', 'raw' => $p];
        }

        return ['record' => $record, 'mechanisms' => $mechanisms, 'all' => $all, 'redirect' => $redirect, 'exp' => $exp];
    }

    /** DMARC lookup (NEW) */
    private function lookupDmarcForDomain(string $domain): array
    {
        $out = ['found' => false, 'domain' => $domain, 'records' => [], 'parsed' => [], 'error' => null];

        if (!function_exists('dns_get_record')) {
            $out['error'] = 'dns_get_record() not available on this PHP environment';
            return $out;
        }

        $name = '_dmarc.' . $domain;

        try {
            $txts = @dns_get_record($name, DNS_TXT);
            if (!$txts || !is_array($txts)) return $out;

            $records = [];
            foreach ($txts as $row) {
                $txt = $row['txt'] ?? '';
                if (is_array($txt)) $txt = implode('', $txt);
                $txt = trim($txt);
                if (stripos($txt, 'v=DMARC1') === 0) $records[] = $txt;
            }

            if (empty($records)) return $out;

            $out['found']   = true;
            $out['records'] = $records;

            foreach ($records as $rec) {
                $out['parsed'][] = $this->parseDmarcRecord($rec);
            }

            return $out;
        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();
            return $out;
        }
    }

    private function parseDmarcRecord(string $record): array
    {
        // Remove v=DMARC1;
        $rest = trim(preg_replace('/^v\s*=\s*DMARC1\s*;?/i', '', $record));

        // Split k=v; pairs
        $tags = [];
        foreach (preg_split('/\s*;\s*/', $rest, -1, PREG_SPLIT_NO_EMPTY) as $kv) {
            if (!str_contains($kv, '=')) continue;
            [$k, $v] = array_map('trim', explode('=', $kv, 2));
            $tags[strtolower($k)] = $v;
        }

        $policy = strtolower($tags['p'] ?? 'none');            // none|quarantine|reject
        $subPol = strtolower($tags['sp'] ?? $policy);
        $rua    = $tags['rua'] ?? null;
        $ruf    = $tags['ruf'] ?? null;
        $pct    = isset($tags['pct']) ? (int) $tags['pct'] : 100;
        $fo     = $tags['fo'] ?? null;
        $adkim  = strtolower($tags['adkim'] ?? 'r');           // r|s
        $aspf   = strtolower($tags['aspf']  ?? 'r');           // r|s

        return compact('record','policy','subPol','rua','ruf','pct','fo','adkim','aspf');
    }

    /* ======================== Heuristics (5.4) ======================== */

    /**
     * Compute simple heuristics and a tiny score.
     * - H-1: display/brand token vs real sender domain core mismatch
     * - H-2: weak auth combo (SPF soft + DMARC none) -> small warning
     */
    private function computeHeuristics(?string $from, ?string $realDomain, array $spf = [], array $dmarc = []): array
    {
        $findings = [];
        $score    = 0;

        // Parse "From:" into display + email + domain
        $parts = $this->parseFromParts($from);
        $display      = $parts['display'] ?? null;
        $displayToken = $this->brandToken($display);        // e.g. "paypal" from "PayPal Support"
        $displayDom   = $parts['display_domain'] ?? null;   // if display contained something like (example.com)
        $realCore     = $this->coreDomain($realDomain);     // e.g. "paypal.com" from "mail.paypal.com"
        $displayCore  = $this->coreDomain($displayDom);

        // H-1: If we have a brand token and a real domain, and the real core doesn't contain the token, flag it.
        if ($displayToken && $realCore && !str_contains($realCore, $displayToken)) {
            $findings[] = [
                'id'       => 'H-1-domain-mismatch',
                'severity' => 'medium',
                'score'    => 15,
                'message'  => 'Display/brand suggests "' . $displayToken . '" but real sender domain is ' . $realCore,
                'evidence' => [
                    'display'       => $display,
                    'displayToken'  => $displayToken,
                    'displayDomain' => $displayDom,
                    'displayCore'   => $displayCore,
                    'realDomain'    => $realDomain,
                    'realCore'      => $realCore,
                ],
            ];
            $score += 15;
        }

        // H-2: weak auth combo — SPF soft (~all/?all/+all) and DMARC not enforcing (p=none / missing)
        $spfAll = $this->spfAllQualifier($spf); // returns '-all'|'~all'|'+all'|'?all'|null
        $p      = strtolower((string) data_get($dmarc, 'parsed.0.policy', '')) ?: (data_get($dmarc, 'found') ? 'none' : 'none');

        if (in_array($spfAll, ['~all', '?all', '+all'], true) && in_array($p, ['none', ''], true)) {
            $findings[] = [
                'id'       => 'H-2-weak-auth',
                'severity' => 'low',
                'score'    => 10,
                'message'  => 'Weak authentication posture (SPF ' . ($spfAll ?: 'n/a') . ', DMARC ' . $p . ').',
            ];
            $score += 10;
        }

        return [
            'score'    => $score,     // 0..100 (small for now)
            'findings' => $findings,  // array of items above
            'meta'     => [
                'display'      => $display,
                'displayToken' => $displayToken,
                'displayDomain'=> $displayDom,
                'realDomain'   => $realDomain,
                'realCore'     => $realCore,
            ],
        ];
    }

    /** Parse From header to display, email, domain, plus any domain hint inside display (e.g., "Brand (brand.com)") */
    private function parseFromParts(?string $from): array
    {
        $out = ['display' => null, 'email' => null, 'domain' => null, 'display_domain' => null];
        if (!$from) return $out;

        // display name
        if (preg_match('/^"?(.*?)"?\s*</', $from, $m)) {
            $out['display'] = trim($m[1], " \t\"'");
        } else {
            // If no <...>, maybe it’s only a name or just email
            $out['display'] = trim(preg_replace('/<.*>/', '', $from)) ?: null;
        }

        // email and domain
        if (preg_match('/<([^>]+)>/', $from, $m)) {
            $out['email'] = strtolower(trim($m[1]));
        } elseif (preg_match('/([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i', $from, $m)) {
            $out['email'] = strtolower(trim($m[1]));
        }

        if ($out['email'] && str_contains($out['email'], '@')) {
            $out['domain'] = strtolower(substr(strrchr($out['email'], '@'), 1));
        }

        // try to pull a domain hint from display e.g. "Foo Support (foo.com)"
        if ($out['display'] && preg_match('/\(([^)]+)\)/', $out['display'], $m)) {
            $hint = strtolower(trim($m[1]));
            if (preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $hint)) {
                $out['display_domain'] = $hint;
            }
        }

        return $out;
    }

    /** Crude brand token from display name: lowercase alphanum of first "wordish" token ≥ 3 chars */
    private function brandToken(?string $display): ?string
    {
        if (!$display) return null;
        $d = strtolower($display);
        // remove punctuation
        $d = preg_replace('/[^a-z0-9 ]+/', ' ', $d);
        $d = preg_replace('/\s+/', ' ', $d);
        foreach (explode(' ', trim($d)) as $tok) {
            if (strlen($tok) >= 3) return $tok;
        }
        return null;
    }

    /** Very light "core" (registrable-ish) domain guess (handles a few common multi-label TLDs) */
    private function coreDomain(?string $host): ?string
    {
        if (!$host) return null;
        $h = strtolower($host);
        $labels = explode('.', $h);
        if (count($labels) < 2) return $h;

        // known multi-label TLDs (small subset)
        $mlt = ['co.uk','ac.uk','gov.uk','co.jp','com.au','com.br','com.ar'];
        $lastTwo = implode('.', array_slice($labels, -2));
        $lastThree = implode('.', array_slice($labels, -3));

        if (in_array($lastThree, $mlt, true)) {
            return implode('.', array_slice($labels, -3));
        }
        if (in_array($lastTwo, $mlt, true)) {
            return implode('.', array_slice($labels, -3)); // e.g., x.co.uk -> take 3 labels
        }
        return $lastTwo;
    }

    /** Extract the SPF "all" qualifier if present across parsed records */
    private function spfAllQualifier(array $spf): ?string
    {
        $parsed = (array) data_get($spf, 'parsed', []);
        foreach ($parsed as $p) {
            $all = data_get($p, 'all');
            if ($all) return strtolower($all);
        }
        return null;
    }
}