<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ScanController extends Controller
{
    /**
     * Handle .eml upload (validation only, no parsing).
     */
    public function store(Request $request)
    {
        // Server-side validation: require .eml file, correct MIME, max 15MB
        $request->validate(
            [
                'eml' => ['required', 'file', 'mimetypes:message/rfc822', 'max:15360'],
            ],
            // Optional: clearer custom messages
            [
                'eml.required'   => 'Please select a file.',
                'eml.file'       => 'The upload must be a file.',
                'eml.mimetypes'  => 'Only .eml files are allowed (MIME message/rfc822).',
                'eml.max'        => 'The email must not exceed 15MB.',
            ]
        );

        // No parsing yet — just acknowledge success
        return back()->with('ok', 'File received successfully.');
    }
}