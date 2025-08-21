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

        // 4) Basic headers (robust: keep display name + email)
        $fromH = $message->getHeader('from');
        $fromName = $fromEmail = null;
        if ($fromH && method_exists($fromH, 'getAddresses')) {
            $addrs = $fromH->getAddresses();            // array of AddressPart
            if (!empty($addrs)) {
                $fromEmail = trim((string) $addrs[0]->getEmail());
                $fromName  = trim((string) $addrs[0]->getName());
            }
        }
        $from = $fromEmail ?: trim((string) $message->getHeaderValue('from'));
        if ($fromName !== '' && $fromEmail !== '') {
            $from = $fromName . ' <' . $fromEmail . '>';
        }

        $to       = trim((string) $message->getHeaderValue('to'));
        $subject  = trim((string) $message->getHeaderValue('subject'));
        $dateRaw  = trim((string) $message->getHeaderValue('date'));

        $replyH = $message->getHeader('reply-to');
        $replyName = $replyEmail = null;
        if ($replyH && method_exists($replyH, 'getAddresses')) {
            $raddrs = $replyH->getAddresses();
            if (!empty($raddrs)) {
                $replyEmail = trim((string) $raddrs[0]->getEmail());
                $replyName  = trim((string) $raddrs[0]->getName());
            }
        }
        $replyTo = $replyEmail ?: trim((string) $message->getHeaderValue('reply-to'));
        if ($replyName !== '' && $replyEmail !== '') {
            $replyTo = $replyName . ' <' . $replyEmail . '>';
        }
        $replyDomain = $this->extractDomainFromAddress($replyTo);

        // Normalize date
        $dateIso = null;
        try { if ($dateRaw !== '') $dateIso = Carbon::parse($dateRaw)->toIso8601String(); } catch (\Throwable) { $dateIso = null; }

        // 5) Bodies (lengths only)
        $textBody = $message->getTextContent() ?? '';
        $htmlBody = $message->getHtmlContent() ?? '';

        // 6) Attachments (count)
        $attachCount = count(iterator_to_array($message->getAllAttachmentParts()));

        // 7) Sender domain
        $fromDomain = $this->extractDomainFromAddress($from);

        // 8) URL extraction
        $combined = $textBody . "\n" . strip_tags($htmlBody);
        $urls     = $this->extractUrls($combined);

        // 9) Extra metadata
        $messageId   = $message->getHeaderValue('message-id') ?? null;
        $contentType = $message->getHeaderValue('content-type') ?? null;
        $received    = [];
        foreach ($message->getAllHeadersByName('received') as $h) $received[] = (string) $h->getValue();

        // 10) SPF + DMARC lookup
        $spf   = $fromDomain ? $this->lookupSpfForDomain($fromDomain)   : ['found' => false, 'records' => [], 'note' => 'No sender domain'];
        $dmarc = $fromDomain ? $this->lookupDmarcForDomain($fromDomain) : ['found' => false, 'records' => [], 'note' => 'No sender domain'];

        // 11) Heuristics
        $heuristics = $this->evaluateHeuristics([
            'from'        => $from,
            'fromDomain'  => $fromDomain,
            'replyTo'     => $replyTo,
            'replyDomain' => $replyDomain,
            'subject'     => $subject,
            'textBody'    => $textBody,
            'urls'        => $urls,
            'spf'         => $spf,
            'dmarc'       => $dmarc,
        ]);

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
                'replyTo'     => $replyTo,
                'replyDomain' => $replyDomain,
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
            'heuristics_json'   => $heuristics,
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
                    $submitted[] = ['url'=>$u,'uuid'=>$resp['uuid'] ?? null,'result'=>$resp['result'] ?? null,'status'=>'submitted'];
                } catch (\Throwable $e) {
                    $row->update(['status'=>'error','error_message'=>$e->getMessage()]);
                    $submitted[] = ['url'=>$u,'status'=>'error','error'=>$e->getMessage()];
                }

                if ($sleepMs > 0) usleep($sleepMs * 1000);
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
                'heuristics'  => $scan->heuristics_json,
            ],
        ];

        $scan->load('urls');

        return view('scans.show', ['scan' => $scan, 'results' => $results]);
    }

    /* ------------------------ helpers ------------------------ */

    private function extractDomainFromAddress(?string $from): ?string
    {
        if (!$from || trim($from) === '') return null;

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

    /** SPF lookup */
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
            foreach ($spfRecords as $rec) $out['parsed'][] = $this->parseSpfRecord($rec);
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

            if (preg_match('/^([~+\-?])?all$/i', $p, $m)) { $all = $m[0]; $mechanisms[] = ['type'=>'all','raw'=>$m[0]]; continue; }
            if (stripos($p, 'redirect=') === 0) { $redirect = substr($p, 9); $mechanisms[] = ['type'=>'redirect','value'=>$redirect]; continue; }
            if (stripos($p, 'exp=') === 0)      { $exp      = substr($p, 4); $mechanisms[] = ['type'=>'exp','value'=>$exp]; continue; }

            if (preg_match('/^(ip4|ip6|include|exists|ptr):(.+)$/i', $p, $m)) { $mechanisms[] = ['type'=>strtolower($m[1]), 'value'=>$m[2]]; continue; }
            if (in_array(strtolower($p), ['a','mx'], true)) { $mechanisms[] = ['type'=>strtolower($p)]; continue; }

            $mechanisms[] = ['type'=>'other','raw'=>$p];
        }

        return ['record'=>$record,'mechanisms'=>$mechanisms,'all'=>$all,'redirect'=>$redirect,'exp'=>$exp];
    }

    /** DMARC lookup */
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
            foreach ($records as $rec) $out['parsed'][] = $this->parseDmarcRecord($rec);

            return $out;
        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();
            return $out;
        }
    }

    private function parseDmarcRecord(string $record): array
    {
        $rest = trim(preg_replace('/^v\s*=\s*DMARC1\s*;?/i', '', $record));

        $tags = [];
        foreach (preg_split('/\s*;\s*/', $rest, -1, PREG_SPLIT_NO_EMPTY) as $kv) {
            if (!str_contains($kv, '=')) continue;
            [$k, $v] = array_map('trim', explode('=', $kv, 2));
            $tags[strtolower($k)] = $v;
        }

        $policy = strtolower($tags['p'] ?? 'none');
        $subPol = strtolower($tags['sp'] ?? $policy);
        $rua    = $tags['rua'] ?? null;
        $ruf    = $tags['ruf'] ?? null;
        $pct    = isset($tags['pct']) ? (int) $tags['pct'] : 100;
        $fo     = $tags['fo'] ?? null;
        $adkim  = strtolower($tags['adkim'] ?? 'r');
        $aspf   = strtolower($tags['aspf']  ?? 'r');

        return compact('record','policy','subPol','rua','ruf','pct','fo','adkim','aspf');
    }

    /* ======================== Heuristics (H‑1 … H‑8) + 6.2 improvements ======================== */

    private function evaluateHeuristics(array $c): array
    {
        // normalize
        $c['subject']  = $this->normText((string)($c['subject']  ?? ''));
        $c['textBody'] = $this->normText((string)($c['textBody'] ?? ''));

        $enabled = (array) (config('heuristics.enabled', [
            'H1'=>true,'H2'=>true,'H3'=>true,'H4'=>true,'H5'=>true,'H6'=>true,'H7'=>true,'H8'=>true,
        ]));

        $findings = [];
        $score = 0;
        $add = function (?array $f, string $code) use (&$findings, &$score, $enabled) {
            if (!$f) return;
            if (isset($enabled[$code]) && $enabled[$code] === false) return;
            $f['score'] = max(0, min(30, (int)($f['score'] ?? 0)));
            if (!empty($f['evidence'])) $f['evidence'] = $this->trimEvidence($f['evidence']);
            $findings[] = $f;
            $score += $f['score'];
        };

        $add($this->h1DisplayVsRealDomain($c), 'H1');
        $add($this->h2UrgencyKeywords($c),     'H2');
        $add($this->h3ReplyToMismatch($c),     'H3');
        $add($this->h4LinksOffBrand($c),       'H4');
        $add($this->h5FreemailClaimsBrand($c), 'H5');
        $add($this->h6WeakAuthCombo($c),       'H6');
        $add($this->h7SuspiciousTld($c),       'H7');
        $add($this->h8PunycodeHomoglyph($c),   'H8');

        $score = max(0, min(100, $score));
        $risk  = $score >= 50 ? 'high' : ($score >= 20 ? 'medium' : 'low');

        return ['score' => $score, 'risk' => $risk, 'findings' => array_values(array_filter($findings))];
    }

    /** H‑1: Brand token in display vs official sender domains */
    private function h1DisplayVsRealDomain(array $c): ?array
    {
        $from       = (string)($c['from'] ?? '');
        $realDomain = (string)($c['fromDomain'] ?? '');
        if ($realDomain === '' || $from === '') return null;

        // Extract display name (works with quoted and unquoted)
        $display = null;
        if (preg_match('/^"([^"]+)"\s*</', $from, $m)) {
            $display = $m[1];
        } elseif (preg_match('/^([^<]+)</', $from, $m)) {
            $display = trim($m[1]);
        } else {
            // Fallback: try before the email
            $display = trim($from);
        }

        $brand = $this->brandToken($display);              // e.g. "paypal"
        if (!$brand) return null;

        $realCore  = $this->coreDomain($realDomain);       // e.g. paypal-login.com
        $realBase  = $this->secondLevelLabel($realDomain); // e.g. "paypal-login"
        $exactLike = ($realBase === $brand);               // perfect match to label
        $contains  = str_contains($realBase, $brand);      // label contains brand with extra bits

        // If the domain label equals the brand exactly (e.g., paypal.com), don't flag
        if ($exactLike) return null;

        // Look‑alike: brand is embedded but with extra prefix/suffix/hyphen etc.
        if ($contains) {
            return [
                'id'       => 'H-1-lookalike-domain',
                'severity' => 'medium',
                'score'    => 18,
                'message'  => "Sender domain looks like the brand '{$brand}' (domain: {$realCore}).",
                'evidence' => [
                    'from'       => $from,
                    'display'    => $display,
                    'brand'      => $brand,
                    'realDomain' => $realDomain,
                    'realCore'   => $realCore,
                    'realBase'   => $realBase,
                ],
            ];
        }

        // Pure mismatch: display hints a brand not reflected in the domain
        return [
            'id'       => 'H-1-domain-mismatch',
            'severity' => 'medium',
            'score'    => 15,
            'message'  => "Display/brand suggests '{$brand}' but real sender domain is {$realCore}.",
            'evidence' => [
                'from'       => $from,
                'display'    => $display,
                'brand'      => $brand,
                'realDomain' => $realDomain,
                'realCore'   => $realCore,
                'realBase'   => $realBase,
            ],
        ];
    }

    private function h2UrgencyKeywords(array $c): ?array
    {
        $subject = strtolower((string)($c['subject'] ?? ''));
        $text    = strtolower((string)($c['textBody'] ?? ''));

        $strong = ['urgent','immediately','suspend','locked','breach','compromised','verify now','overdue','final notice'];
        $weak   = ['verify','confirm','update account','password','invoice','payment','limited time','click','security alert'];

        $hits = [];
        foreach ($strong as $w) if (str_contains($subject, $w) || str_contains($text, $w)) $hits[] = $w;
        foreach ($weak   as $w) if (str_contains($subject, $w) || str_contains($text, $w)) $hits[] = $w;

        $hits = array_values(array_unique($hits));
        if (!$hits) return null;

        $both  = $this->hitsInBoth($strong, $subject, $text) || $this->hitsInBoth($weak, $subject, $text);
        $sev   = $both || count($hits) >= 3 ? 'medium' : 'low';
        $score = $both || count($hits) >= 3 ? 12 : 6;

        return ['id'=>'H-2-urgency','severity'=>$sev,'score'=>$score,'message'=>'Urgency/pressure keywords present','evidence'=>['hits'=>$hits]];
    }

    private function h3ReplyToMismatch(array $c): ?array
    {
        $fromCore  = $this->coreDomain((string)($c['fromDomain']  ?? ''));
        $replyCore = $this->coreDomain((string)($c['replyDomain'] ?? ''));
        if ($fromCore && $replyCore && $fromCore !== $replyCore) {
            return ['id'=>'H-3-replyto-mismatch','severity'=>'medium','score'=>15,
                'message'=>"Reply-To domain ($replyCore) differs from From domain ($fromCore)",
                'evidence'=>compact('fromCore','replyCore')];
        }
        return null;
    }

    private function h4LinksOffBrand(array $c): ?array
    {
        $fromCore = $this->coreDomain((string)($c['fromDomain'] ?? ''));
        $urls     = (array)($c['urls'] ?? []);
        if (!$fromCore || !$urls) return null;

        $allow = ['bit.ly','tinyurl.com','linktr.ee','lnkd.in','goo.gl'];
        $off = [];
        foreach ($urls as $u) {
            $host = parse_url($u, PHP_URL_HOST);
            $core = $this->coreDomain($host);
            if ($core && $core !== $fromCore && !in_array($core, $allow, true)) $off[] = ['url'=>$u,'core'=>$core];
        }
        if ($off && count($off) === count($urls)) {
            return ['id'=>'H-4-links-off-brand','severity'=>'medium','score'=>15,
                'message'=>'All links point to domains different from the sender’s core domain',
                'evidence'=>['fromCore'=>$fromCore,'off'=>$off]];
        }
        return null;
    }

    private function h5FreemailClaimsBrand(array $c): ?array
    {
        $fromDomain = strtolower((string)($c['fromDomain'] ?? ''));
        $freemail = ['gmail.com','outlook.com','hotmail.com','live.com','yahoo.com','icloud.com','proton.me','protonmail.com','gmx.com','aol.com'];
        if (!in_array($fromDomain, $freemail, true)) return null;

        $from = strtolower((string)($c['from'] ?? ''));
        $displayToken = null;
        if (preg_match('/"([^"]+)"/', $from, $m))      $displayToken = strtolower($m[1]);
        elseif (preg_match('/^([^<]+)</', $from, $m))  $displayToken = strtolower(trim($m[1]));

        if (!$this->looksBrandish($displayToken)) return null;

        return ['id'=>'H-5-freemail-claims-brand','severity'=>'medium','score'=>15,
            'message'=>"Freemail sender ($fromDomain) presenting brand-like display name",
            'evidence'=>['from'=>$from,'fromDomain'=>$fromDomain,'displayToken'=>$displayToken]];
    }

    private function h6WeakAuthCombo(array $c): ?array
    {
        $spf   = (array)($c['spf']   ?? []);
        $dmarc = (array)($c['dmarc'] ?? []);

        $spfWeak = empty($spf['found']);
        if (!$spfWeak && !empty($spf['parsed'])) {
            $qual = null;
            foreach ($spf['parsed'] as $p) { $qual = $qual ?? ($p['all'] ?? null); }
            if (in_array($qual, ['~all','?all','+all'], true)) $spfWeak = true;
        }
        $dmarcWeak = empty($dmarc['found']) || strtolower((string)($dmarc['parsed'][0]['policy'] ?? 'none')) === 'none';

        if ($spfWeak && $dmarcWeak) {
            return ['id'=>'H-6-weak-auth','severity'=>'low','score'=>10,
                'message'=>'Weak authentication: SPF weak/missing and DMARC none/not found',
                'evidence'=>['spf'=>$spf,'dmarc'=>$dmarc]];
        }
        return null;
    }

    private function h7SuspiciousTld(array $c): ?array
    {
        $risk = ['ru','cn','top','icu','zip','cam','tokyo','country','support','xyz','click','live'];
        $fromTld = $this->tld((string)($c['fromDomain'] ?? ''));
        $urls    = (array)($c['urls'] ?? []);
        $bad = 0;

        if ($fromTld && in_array($fromTld, $risk, true)) $bad += 5;
        foreach ($urls as $u) {
            $host = parse_url($u, PHP_URL_HOST);
            $tld  = $this->tld((string)$host);
            if ($tld && in_array($tld, $risk, true)) { $bad += 5; break; }
        }

        if ($bad > 0) {
            return ['id'=>'H-7-suspicious-tld','severity'=>'low','score'=>min(10,$bad),
                'message'=>'Suspicious top-level domain on sender or links',
                'evidence'=>['fromTld'=>$fromTld]];
        }
        return null;
    }

    private function h8PunycodeHomoglyph(array $c): ?array
    {
        $domains = [];
        if (!empty($c['fromDomain']))   $domains[] = $c['fromDomain'];
        if (!empty($c['replyDomain']))  $domains[] = $c['replyDomain'];
        foreach ((array)($c['urls'] ?? []) as $u) {
            $host = parse_url($u, PHP_URL_HOST);
            if ($host) $domains[] = $host;
        }

        $puny = [];
        foreach ($domains as $d) if (str_contains($d, 'xn--') || preg_match('/[^\x20-\x7E]/', $d)) $puny[] = $d;
        if ($puny) {
            return ['id'=>'H-8-punycode','severity'=>'medium','score'=>15,
                'message'=>'Punycode/homoglyph domains detected',
                'evidence'=>['domains'=>$puny]];
        }
        return null;
    }

    /* --- tiny helpers for heuristics --- */

    private function normText(string $s): string
    {
        $s = preg_replace('/[^\P{C}\n\r\t]+/u', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return trim($s);
    }

    private function hitsInBoth(array $dict, string $subject, string $text): bool
    {
        $sHit = false; $tHit = false;
        foreach ($dict as $w) {
            if (!$sHit && str_contains($subject, $w)) $sHit = true;
            if (!$tHit && str_contains($text,    $w)) $tHit = true;
            if ($sHit && $tHit) return true;
        }
        return false;
    }

    private function coreDomain(?string $domain): ?string
    {
        if (!$domain) return null;
        $parts = explode('.', strtolower($domain));
        return count($parts) >= 2 ? implode('.', array_slice($parts, -2)) : $domain;
    }

    private function tld(string $domain): ?string
    {
        if ($domain === '') return null;
        $parts = explode('.', strtolower($domain));
        return end($parts) ?: null;
    }

    private function looksBrandish(?string $token): bool
    {
        if (!$token) return false;
        $brands = ['paypal','apple','microsoft','amazon','google','bank','netflix','facebook','instagram','dhl','ups','stripe','billing','support','service','security'];
        $t = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $token));
        foreach (preg_split('/\s+/', $t) as $w) if ($w !== '' && in_array($w, $brands, true)) return true;
        return false;
    }

    /** Return the first known brand keyword present in the token/domain */
    private function brandKeyword(?string $token): ?string
    {
        if (!$token) return null;
        $known = ['paypal','apple','microsoft','amazon','google','netflix','facebook','instagram','dhl','ups','stripe','bank'];
        $t = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $token));
        foreach (preg_split('/\s+/', trim($t)) as $w) {
            if ($w !== '' && in_array($w, $known, true)) return $w;
        }
        // also detect plain domains like paypal.com -> paypal
        if (preg_match('/^([a-z0-9-]+)\.(?:com|net|org|co|io|me)$/i', trim($token), $m)) {
            $w = strtolower($m[1]);
            if (in_array($w, $known, true)) return $w;
        }
        return null;
    }

    /** Pull a brand token from a display name */
    private function brandToken(?string $display): ?string
    {
        if (!$display) return null;
        $d = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $display));
        $d = trim(preg_replace('/\s+/', ' ', $d));
        if ($d === '') return null;

        // known brand-ish words (extend as needed)
        $brands = [
            'paypal','apple','microsoft','amazon','google','bank','netflix','facebook','instagram',
            'dhl','ups','stripe','billing','support','service','security'
        ];

        foreach (explode(' ', $d) as $w) {
            if (strlen($w) >= 3 && in_array($w, $brands, true)) return $w;
        }
        return null;
    }

    /** Second-level label (before the TLD), e.g. paypal-login from paypal-login.com */
    private function secondLevelLabel(string $domain): string
    {
        $host = strtolower($domain);
        $labels = explode('.', $host);
        if (count($labels) < 2) return $host;
        // handle a few common multi-label TLDs
        $mlt = ['co.uk','ac.uk','gov.uk','co.jp','com.au','com.br','com.ar'];
        $lastTwo   = implode('.', array_slice($labels, -2));
        $lastThree = implode('.', array_slice($labels, -3));
        if (in_array($lastThree, $mlt, true)) {
            return $labels[count($labels)-4] ?? $labels[count($labels)-3];
        }
        if (in_array($lastTwo, $mlt, true)) {
            return $labels[count($labels)-3] ?? $labels[count($labels)-2];
        }
        // normal case: take the label before TLD
        return $labels[count($labels)-2];
    }

    /** Official core domains per brand (minimal allowlist; expand as needed) */
    private function officialDomainsFor(string $brand): array
    {
        $map = [
            'paypal'     => ['paypal.com','paypalobjects.com','paypalinc.com'],
            'apple'      => ['apple.com','icloud.com'],
            'microsoft'  => ['microsoft.com','live.com','outlook.com'],
            'amazon'     => ['amazon.com','amazon.co.uk'],
            'google'     => ['google.com','gmail.com'],
            'netflix'    => ['netflix.com'],
            'facebook'   => ['facebook.com','fb.com'],
            'instagram'  => ['instagram.com'],
            'dhl'        => ['dhl.com','dhl.de'],
            'ups'        => ['ups.com'],
            'stripe'     => ['stripe.com'],
            'bank'       => [], // generic – no allowlist
        ];
        return $map[$brand] ?? [];
    }

    private function trimEvidence(mixed $evidence, int $maxLen = 200, int $maxItems = 5): mixed
    {
        if (is_string($evidence)) {
            return mb_strlen($evidence) > $maxLen ? mb_substr($evidence, 0, $maxLen) . '…' : $evidence;
        }
        if (is_array($evidence)) {
            $trimmed = []; $count = 0;
            foreach ($evidence as $k => $v) {
                if ($count >= $maxItems) break;
                $trimmed[$k] = $this->trimEvidence($v, $maxLen, $maxItems);
                $count++;
            }
            return $trimmed;
        }
        if (is_object($evidence)) {
            $arr = (array) $evidence;
            return $this->trimEvidence($arr, $maxLen, $maxItems);
        }
        return $evidence;
    }
}