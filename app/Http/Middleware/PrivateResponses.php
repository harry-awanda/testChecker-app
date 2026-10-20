<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class PrivateResponses {
    public function handle(Request $request, Closure $next) {
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=()');
        return $response;
    }
}
