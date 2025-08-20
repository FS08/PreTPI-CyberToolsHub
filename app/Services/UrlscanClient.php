<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class UrlscanClient
{
    private string $base;
    private ?string $key;
    private string $visibilityDefault;

    public function __construct()
    {
        $this->base              = rtrim(config('urlscan.base', 'https://urlscan.io/api'), '/');
        $this->key               = config('urlscan.key');
        $this->visibilityDefault = (string) config('urlscan.visibility', 'unlisted'); // public|unlisted|private
    }

    private function http(): PendingRequest
    {
        $req = Http::acceptJson()
            ->timeout(20)
            ->retry(2, 300);

        $headers = ['Content-Type' => 'application/json'];
        if (!empty($this->key)) {
            $headers['API-Key'] = $this->key;
        }

        return $req->withHeaders($headers);
    }

    /** Quick search by URL/domain/IP. */
    public function search(string $q, int $size = 10): array
    {
        return $this->http()
            ->get("{$this->base}/v1/search", ['q' => $q, 'size' => $size])
            ->throw()
            ->json();
    }

    /**
     * Submit a URL for scanning.
     *
     * $visibility can be:
     *   - string: 'public' | 'unlisted' | 'private'
     *   - bool:   true => 'public', false => 'unlisted' (back-compat with old dev form)
     *   - null:   falls back to config('urlscan.visibility')
     *
     * $wait controls whether we poll until finished (default: false).
     */
    public function submit(string $url, $visibility = null, ?string $customAgent = null, bool $wait = false): array
    {
        // Normalize visibility
        if (is_bool($visibility)) {
            $visibility = $visibility ? 'public' : 'unlisted';
        } elseif (!is_string($visibility) || $visibility === '') {
            $visibility = $this->visibilityDefault;
        }

        $payload = [
            'url'        => $url,
            'visibility' => $visibility,
        ];

        if ($customAgent) {
            $payload['customagent'] = $customAgent;
        }

        $resp = $this->http()
            ->post("{$this->base}/v1/scan", $payload)
            ->throw()
            ->json();

        // Default: return immediately (fast, avoids timeouts)
        if ($wait === false || !config('urlscan.wait_result', false)) {
            return $resp; // contains 'uuid' and 'result' URL
        }

        // Optional: block until result ready
        return $this->waitForResult($resp['uuid'] ?? '');
    }

    /** Poll until result is ready (or timeout). */
    private function waitForResult(string $uuid): ?array
    {
        if (empty($uuid)) {
            return null;
        }

        $interval = (int) config('urlscan.poll_interval_seconds', 2);
        $timeout  = (int) config('urlscan.poll_timeout_seconds', 20);
        $deadline = time() + $timeout;

        while (time() < $deadline) {
            try {
                $resp = $this->result($uuid);
                if (!empty($resp['task']) && !empty($resp['data'])) {
                    return $resp;
                }
            } catch (\Illuminate\Http\Client\RequestException $e) {
                if ($e->response?->status() !== 404) {
                    throw $e; // rethrow non-404 errors
                }
                // 404 = still pending
            }

            sleep(max(1, $interval));
        }

        return null; // timed out waiting
    }

    /** Fetch result by result ID/UUID. */
    public function result(string $resultId): array
    {
        return $this->http()
            ->get("{$this->base}/v1/result/{$resultId}")
            ->throw()
            ->json();
    }
}