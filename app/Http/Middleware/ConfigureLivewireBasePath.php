<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Livewire\Livewire;
use Livewire\Mechanisms\FrontendAssets\FrontendAssets;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Symfony\Component\HttpFoundation\Response;

class ConfigureLivewireBasePath
{
    public function __construct(
        private readonly Router $router,
        private readonly UrlGenerator $url,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // APP_URL tetap menjadi fallback untuk CLI. URL browser selalu mengikuti
        // host, skema, dan prefix request yang telah diproses TrustProxies/TrustHosts.
        $this->url->useOrigin($request->root());
        $this->url->useAssetOrigin($request->root());

        $scriptRoute = $this->findRoute(FrontendAssets::class.'@returnJavaScriptAsFile');
        $updateRoute = $this->findRoute(HandleRequests::class.'@handleUpdate');

        if ($scriptRoute !== null) {
            $publicScriptRoute = $this->forPublicBasePath($scriptRoute, $request->getBaseUrl());

            Livewire::setScriptRoute(fn (array $handle): Route => $publicScriptRoute);
        }

        if ($updateRoute !== null) {
            $publicUpdateRoute = $this->forPublicBasePath($updateRoute, $request->getBaseUrl());

            Livewire::setUpdateRoute(fn (array $handle): Route => $publicUpdateRoute);
        }

        return $next($request);
    }

    private function findRoute(string $action): ?Route
    {
        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            if ($route->getActionName() === $action) {
                return $route;
            }
        }

        return null;
    }

    private function forPublicBasePath(Route $route, string $basePath): Route
    {
        // Hanya clone ini yang dipakai Livewire untuk membentuk URL publik.
        // Route asli tetap tanpa prefix agar cocok dengan URI yang sudah ditulis ulang gateway.
        $publicRoute = clone $route;
        $publicRoute->setUri(trim($basePath, '/').'/'.$route->uri());

        return $publicRoute;
    }
}
