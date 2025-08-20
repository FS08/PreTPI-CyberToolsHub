<?php

namespace App\Services;

class DnsSpf
{
    /**
     * Check SPF for a domain.
     * Returns a compact summary for display/storage.
     */
    public function check(string $domain): array
    {
        $result = [
            'domain'       => strtolower($domain),
            'exists'       => false,
            'record'       => null,
            'policy'       => 'none',   // none | pass | fail | softfail | neutral | unknown
            'has_include'  => false,
            'has_ip4'      => false,
            'has_ip6'      => false,
            'has_a'        => false,
            'has_mx'       => false,
            'has_ptr'      => false,
            'mechanisms'   => [],
            'warnings'     => [],
        ];

        if ($domain === '') {
            return $result;
        }

        // Fetch TXT records
        $txts = @dns_get_record($domain, DNS_TXT) ?: [];

        // Pick the first SPF record (starts with v=spf1)
        $spf = null;
        foreach ($txts as $rec) {
            $txt = $rec['txt'] ?? '';
            if (stripos($txt, 'v=spf1') === 0) {
                $spf = $txt;
                break;
            }
        }

        if (!$spf) {
            // no SPF
            return $result;
        }

        $result['exists'] = true;
        $result['record'] = $spf;

        // Tokenize by spaces (very simple tokenizer)
        $tokens = preg_split('/\s+/', trim($spf)) ?: [];

        // Parse mechanisms
        foreach ($tokens as $tok) {
            if ($tok === '' || stripos($tok, 'v=spf1') === 0) continue;

            $result['mechanisms'][] = $tok;

            // flags
            if (str_starts_with($tok, 'include:')) $result['has_include'] = true;
            if (str_starts_with($tok, 'ip4:'))     $result['has_ip4']     = true;
            if (str_starts_with($tok, 'ip6:'))     $result['has_ip6']     = true;
            if (preg_match('~^[+\-~?]?a(?::|/|$)~i', $tok))   $result['has_a']  = true;
            if (preg_match('~^[+\-~?]?mx(?::|/|$)~i', $tok))  $result['has_mx'] = true;
            if (stripos($tok, 'ptr') === 0 || preg_match('~^[+\-~?]?ptr~i', $tok)) $result['has_ptr'] = true;
        }

        // Determine policy from the *all mechanism (if present)
        // e.g.  -all  => fail,  ~all => softfail,  ?all => neutral, +all => pass
        $policy = 'unknown';
        foreach (array_reverse($tokens) as $tok) {
            if (preg_match('~^([+\-~?])?all$~i', $tok, $m)) {
                $q = $m[1] ?? '+';
                $policy = match ($q) {
                    '-' => 'fail',
                    '~' => 'softfail',
                    '?' => 'neutral',
                    '+', '' => 'pass',
                    default => 'unknown',
                };
                break;
            }
        }
        $result['policy'] = $policy;

        if ($result['has_ptr']) {
            $result['warnings'][] = 'Uses PTR (discouraged).';
        }
        if ($policy === 'pass' && !$result['has_ip4'] && !$result['has_ip6'] && !$result['has_include'] && !$result['has_a'] && !$result['has_mx']) {
            $result['warnings'][] = 'Policy "pass" but no explicit mechanisms.';
        }

        return $result;
    }
}