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
     */
    public function submit(string $url, $visibility = null, ?string $customAgent = null): array
    {
        // Normalize visibility
        if (is_bool($visibility)) {
            $visibility = $visibility ? 'public' : 'unlisted';
        } elseif (!is_string($visibility) || $visibility === '') {
            $visibility = $this->visibilityDefault;
        }

        $payload = [
            'url'        => $url,
            'visibility' => $visibility, // urlscan expects 'visibility'
        ];

        if ($customAgent) {
            $payload['customagent'] = $customAgent; // optional
        }

        return $this->http()
            ->post("{$this->base}/v1/scan", $payload)
            ->throw()
            ->json();
    }

    /** Poll until result is ready (or timeout) */
    public function waitForResult(string $uuid, int $maxSeconds = 15, int $intervalSeconds = 2): ?array
    {
        $deadline = time() + $maxSeconds;

        while (time() < $deadline) {
            try {
                $resp = $this->result($uuid);

                // A finished scan will have a task + data section
                if (!empty($resp['task']) && !empty($resp['data'])) {
                    return $resp;
                }
            } catch (\Illuminate\Http\Client\RequestException $e) {
                if ($e->response?->status() !== 404) {
                    throw $e; // rethrow other errors
                }
                // 404 = still pending
            }

            sleep($intervalSeconds);
        }

        return null; // timed out
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