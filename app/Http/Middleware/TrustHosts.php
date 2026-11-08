<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrustHosts extends Middleware
{
    public function handle(Request $request, $next): Response
    {
        if (! $this->shouldSpecifyTrustedHosts()) {
            return $next($request);
        }

        // Hindari host request sebelumnya tertinggal pada worker yang persisten.
        Request::setTrustedHosts([]);

        $hosts = $this->hosts();

        // Gateway tepercaya menjadi batas keamanan untuk hostname tunnel dinamis.
        // Request langsung tetap dibatasi oleh APP_URL dan subdomainnya.
        if ($request->isFromTrustedProxy()) {
            $hosts[] = '^'.preg_quote($request->getHost(), '/').'$';
        }

        Request::setTrustedHosts(array_filter($hosts));

        return $next($request);
    }

    /**
     * Get the host patterns that should be trusted.
     *
     * @return array<int, string|null>
     */
    public function hosts(): array
    {
        return [
            $this->allSubdomainsOfApplicationUrl(),
        ];
    }
}
