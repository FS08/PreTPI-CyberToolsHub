<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class UrlscanClient
{
    private string $base;
    private ?string $key;
    private bool $publicDefault;

    public function __construct()
    {
        $this->base          = rtrim(config('urlscan.base', 'https://urlscan.io/api'), '/');
        $this->key           = config('urlscan.key');
        $this->publicDefault = (bool) config('urlscan.public', true);
    }

    private function http(): PendingRequest
    {
        $req = Http::acceptJson()
            ->timeout(15)
            ->retry(2, 250);

        if (!empty($this->key)) {
            $req = $req->withHeaders([
                'API-Key'      => $this->key,
                'Content-Type' => 'application/json',
            ]);
        } else {
            $req = $req->withHeaders(['Content-Type' => 'application/json']);
        }

        return $req;
    }

    /** Quick search by URL/domain/ip */
    public function search(string $q, int $size = 10): array
    {
        return $this->http()
            ->get("{$this->base}/v1/search", ['q' => $q, 'size' => $size])
            ->throw()
            ->json();
    }

    /** Submit a URL for scanning */
    public function submit(string $url, ?string $public = 'on', ?string $customAgent = null)
    {
        $payload = [
            'url'    => $url,
            // urlscan expects "public" as string: "on", "off", or "unlisted"
            'public' => $public,
        ];

        if ($customAgent) {
            $payload['customagent'] = $customAgent; // optional
        }

        return $this->http()
            ->post("{$this->base}/v1/scan", $payload)
            ->throw()
            ->json();
    }

    /** Fetch result by result ID/UUID */
    public function result(string $resultId): array
    {
        return $this->http()
            ->get("{$this->base}/v1/result/{$resultId}")
            ->throw()
            ->json();
    }
}