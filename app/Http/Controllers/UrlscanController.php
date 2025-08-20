<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UrlscanClient;

class UrlscanController extends Controller
{
    public function __construct(private UrlscanClient $client) {}

    /** GET /dev/urlscan */
    public function index()
    {
        return view('dev.urlscan', [
            'searchResults'  => null,
            'submitResponse' => null,
            'q'              => '',
            'error'          => null,
        ]);
    }

    /** GET /dev/urlscan/search?q=... */
    public function search(Request $request)
    {
        $request->validate(['q' => ['required','string','min:2']]);

        $json  = null;
        $error = null;

        try {
            $json = $this->client->search($request->string('q'), size: 10);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('dev.urlscan', [
            'searchResults'  => $json,
            'submitResponse' => null,
            'q'              => (string) $request->string('q'),
            'error'          => $error,
        ]);
    }

    /** POST /dev/urlscan/submit */
    public function submit(Request $request)
    {
        $request->validate([
            'url'    => ['required','url'],
            'public' => ['nullable','boolean'],
        ]);

        $json  = null;
        $error = null;

        try {
            $json = $this->client->submit(
                url: $request->string('url'),
                public: $request->has('public') ? 'on' : 'off'
            );
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('dev.urlscan', [
            'searchResults'  => null,
            'submitResponse' => $json,
            'q'              => '',
            'error'          => $error,
        ]);
    }
}