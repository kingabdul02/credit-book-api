<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetETagHeader
{
    /**
     * Handle an incoming request and attach ETag / respond with 304 if unchanged.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only compute ETags for safe read methods (GET / HEAD) on successful 200 responses
        if (! $request->isMethodSafe() || $response->getStatusCode() !== 200) {
            return $response;
        }

        $content = $response->getContent();
        if ($content === false || $content === '') {
            return $response;
        }

        $etag = '"' . md5($content) . '"';
        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', 'no-cache, private, must-revalidate');

        $ifNoneMatch = $request->header('If-None-Match');
        if ($ifNoneMatch) {
            $clientEtags = array_map('trim', explode(',', $ifNoneMatch));
            if (in_array($etag, $clientEtags, true) || in_array('*', $clientEtags, true)) {
                return response('', 304, [
                    'ETag' => $etag,
                    'Cache-Control' => 'no-cache, private, must-revalidate',
                ]);
            }
        }

        return $response;
    }
}
