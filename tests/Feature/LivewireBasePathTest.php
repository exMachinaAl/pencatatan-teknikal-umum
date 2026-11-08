<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustHosts as TrustHostsMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Livewire\Mechanisms\FrontendAssets\FrontendAssets;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Tests\TestCase;

class LivewireBasePathTest extends TestCase
{
    use RefreshDatabase;

    public function test_gateway_prefix_dipakai_oleh_script_dan_endpoint_update_livewire(): void
    {
        $response = $this->fromTrustedGateway('/pencatatan')->get('/admin/login');

        $response
            ->assertOk()
            ->assertSee('src="/pencatatan/livewire/livewire.js?id=', false)
            ->assertSee('data-update-uri="/pencatatan/livewire/update"', false)
            ->assertSee('wire:submit="authenticate"', false);

        $this->assertDoesNotMatchRegularExpression(
            '/<form[^>]+action=["\'][^"\']*admin\/login/i',
            $response->getContent(),
        );
    }

    public function test_subdomain_tanpa_prefix_tetap_memakai_endpoint_root(): void
    {
        $response = $this
            ->withServerVariables([
                'HTTP_HOST' => 'tu.sekolah.test',
                'REMOTE_ADDR' => '127.0.0.1',
            ])
            ->get('/admin/login');

        $response
            ->assertOk()
            ->assertSee('src="/livewire/livewire.js?id=', false)
            ->assertSee('data-update-uri="/livewire/update"', false)
            ->assertDontSee('/pencatatan/livewire/', false);
    }

    public function test_https_tunnel_tidak_memerlukan_hostname_yang_dihardcode(): void
    {
        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'X-Forwarded-Host' => 'acak-tunnel.example',
                'X-Forwarded-Prefix' => '/pencatatan',
                'X-Forwarded-Proto' => 'https',
            ])
            ->get('/admin/login');

        $response
            ->assertOk()
            ->assertSee('https://acak-tunnel.example/pencatatan/build/assets/', false)
            ->assertSee('src="/pencatatan/livewire/livewire.js?id=', false)
            ->assertSee('data-update-uri="/pencatatan/livewire/update"', false)
            ->assertDontSee('ngrok', false)
            ->assertDontSee('cloudflare', false);
    }

    public function test_login_livewire_berhasil_melalui_gateway_dengan_prefix(): void
    {
        $user = User::create([
            'name' => 'Operator Gateway',
            'username' => 'operator.gateway',
            'password' => 'password',
            'role' => User::ROLE_PETUGAS_TU,
            'is_active' => true,
        ]);

        $page = $this->fromTrustedGateway('/pencatatan')->get('/admin/login');

        preg_match('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);
        $this->assertArrayHasKey(1, $matches);

        $response = $this
            ->fromTrustedGateway('/pencatatan')
            ->withHeader('X-Livewire', 'true')
            // Simulasikan URI upstream setelah Nginx menghapus /pencatatan.
            ->postJson('http://localhost/livewire/update', [
                'components' => [[
                    'snapshot' => html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5),
                    'updates' => [
                        'data.login' => 'operator.gateway',
                        'data.password' => 'password',
                    ],
                    'calls' => [[
                        'path' => '',
                        'method' => 'authenticate',
                        'params' => [],
                    ]],
                ]],
            ]);

        $response->assertOk();
        $this->assertStringEndsWith(
            '/pencatatan/admin',
            $response->json('components.0.effects.redirect'),
        );
        $this->assertAuthenticatedAs($user);
    }

    public function test_route_internal_livewire_tetap_tanpa_prefix(): void
    {
        $routes = collect(RouteFacade::getRoutes()->getRoutes());

        $scriptRoute = $routes->first(
            fn (Route $route): bool => $route->getActionName() === FrontendAssets::class.'@returnJavaScriptAsFile',
        );
        $updateRoute = $routes->first(
            fn (Route $route): bool => $route->getActionName() === HandleRequests::class.'@handleUpdate',
        );

        $this->assertNotNull($scriptRoute);
        $this->assertNotNull($updateRoute);
        $this->assertContains('GET', $scriptRoute->methods());
        $this->assertSame('livewire/livewire.js', $scriptRoute->uri());
        $this->assertContains('POST', $updateRoute->methods());
        $this->assertSame('livewire/update', $updateRoute->uri());
        $this->assertFalse($routes->contains(
            fn (Route $route): bool => str_starts_with($route->uri(), 'pencatatan/livewire/'),
        ));

        $this->get('/livewire/livewire.js')->assertOk();
        $this->postJson('/livewire/update', [])->assertNotFound();
    }

    public function test_prefix_dari_proxy_tidak_tepercaya_diabaikan(): void
    {
        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->withHeader('X-Forwarded-Prefix', '/pencatatan')
            ->get('/admin/login');

        $response
            ->assertOk()
            ->assertSee('src="/livewire/livewire.js?id=', false)
            ->assertSee('data-update-uri="/livewire/update"', false)
            ->assertDontSee('/pencatatan/livewire/', false);
    }

    public function test_workflow_lengkap_subdomain_tanpa_prefix(): void
    {
        $this->assertCompleteBrowserWorkflow(
            publicHost: 'tatausaha.smkn1cerme.test',
            prefix: '',
            https: false,
        );
    }

    public function test_workflow_lengkap_base_path_http(): void
    {
        $this->assertCompleteBrowserWorkflow(
            publicHost: 'smkn1cerme.test',
            prefix: '/pencatatan',
            https: false,
        );
    }

    public function test_workflow_lengkap_tunnel_https_dengan_prefix(): void
    {
        $this->assertCompleteBrowserWorkflow(
            publicHost: 'tunnel-acak.example',
            prefix: '/pencatatan',
            https: true,
        );
    }

    public function test_hostname_dinamis_hanya_diterima_dari_proxy_tepercaya(): void
    {
        $middleware = new class(app()) extends TrustHostsMiddleware
        {
            protected function shouldSpecifyTrustedHosts(): bool
            {
                return true;
            }
        };

        Request::setTrustedProxies(['127.0.0.1'], Request::HEADER_X_FORWARDED_HOST);

        try {
            $trustedRequest = Request::create(
                'http://127.0.0.1/admin/login',
                'GET',
                server: [
                    'REMOTE_ADDR' => '127.0.0.1',
                    'HTTP_X_FORWARDED_HOST' => 'tunnel-dinamis.example',
                ],
            );
            $trustedResponse = $middleware->handle(
                $trustedRequest,
                fn (Request $request) => response($request->getHost()),
            );

            $this->assertSame('tunnel-dinamis.example', $trustedResponse->getContent());

            $untrustedRequest = Request::create(
                'http://host-penyerang.example/admin/login',
                'GET',
                server: ['REMOTE_ADDR' => '192.0.2.10'],
            );

            $this->expectException(SuspiciousOperationException::class);
            $middleware->handle(
                $untrustedRequest,
                fn (Request $request) => response($request->getHost()),
            );
        } finally {
            Request::setTrustedHosts([]);
            Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        }
    }

    private function assertCompleteBrowserWorkflow(string $publicHost, string $prefix, bool $https): void
    {
        $scheme = $https ? 'https' : 'http';
        $publicBase = $scheme.'://'.$publicHost.$prefix;

        // Buktikan origin browser tidak dikunci oleh canonical fallback.
        app('url')->useOrigin('http://canonical.invalid');
        app('url')->useAssetOrigin('http://canonical.invalid');

        $user = User::create([
            'name' => 'Operator Dual Mode',
            'username' => 'operator.dual',
            'password' => 'password',
            'role' => User::ROLE_PETUGAS_TU,
            'is_active' => true,
        ]);

        $page = $this->forPublicMode($publicHost, $prefix, $https)
            ->get('http://localhost/admin/login');

        $page
            ->assertOk()
            ->assertSee('href="'.$publicBase.'/css/filament/', false)
            ->assertSee('href="'.$publicBase.'/build/assets/', false)
            ->assertSee('src="'.$publicBase.'/js/filament/', false)
            ->assertSee('src="'.$prefix.'/livewire/livewire.js?id=', false)
            ->assertSee('data-update-uri="'.$prefix.'/livewire/update"', false)
            ->assertDontSee('canonical.invalid', false);

        $snapshot = $this->extractAttribute($page->getContent(), 'wire:snapshot');
        $csrf = $this->extractAttribute($page->getContent(), 'data-csrf');
        $this->assertSame(session()->token(), $csrf);
        $sessionBeforeLogin = session()->getId();

        $this->forPublicMode($publicHost, $prefix, $https)
            ->get('http://localhost/livewire/livewire.js')
            ->assertOk()
            ->assertHeaderContains('content-type', 'application/javascript');

        $login = $this
            ->forPublicMode($publicHost, $prefix, $https)
            ->withHeaders([
                'X-CSRF-TOKEN' => $csrf,
                'X-Livewire' => 'true',
            ])
            ->postJson('http://localhost/livewire/update', [
                'components' => [[
                    'snapshot' => $snapshot,
                    'updates' => [
                        'data.login' => 'operator.dual',
                        'data.password' => 'password',
                    ],
                    'calls' => [[
                        'path' => '',
                        'method' => 'authenticate',
                        'params' => [],
                    ]],
                ]],
            ]);

        $login->assertOk()->assertCookie(config('session.cookie'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionBeforeLogin, session()->getId());
        $this->assertSame($publicBase.'/admin', $login->json('components.0.effects.redirect'));

        $dashboard = $this->forPublicMode($publicHost, $prefix, $https)
            ->get('http://localhost/admin');
        $dashboard
            ->assertOk()
            ->assertSee('href="'.$publicBase.'/admin', false)
            ->assertDontSee('canonical.invalid', false);

        $this->forPublicMode($publicHost, $prefix, $https)
            ->get('http://localhost/')
            ->assertRedirect($publicBase.'/admin');

        $sessionBeforeLogout = session()->getId();
        $csrfBeforeLogout = session()->token();
        $logout = $this
            ->forPublicMode($publicHost, $prefix, $https)
            ->withHeader('X-CSRF-TOKEN', $csrfBeforeLogout)
            ->post('http://localhost/admin/logout', ['_token' => $csrfBeforeLogout]);

        $logout->assertRedirect($publicBase.'/admin/login');
        $this->assertGuest();
        $this->assertNotSame($sessionBeforeLogout, session()->getId());
        $this->assertNotSame($csrfBeforeLogout, session()->token());
    }

    private function extractAttribute(string $html, string $attribute): string
    {
        preg_match('/'.preg_quote($attribute, '/').'="([^"]+)"/', $html, $matches);
        $this->assertArrayHasKey(1, $matches, "Atribut {$attribute} tidak ditemukan.");

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
    }

    private function forPublicMode(string $host, string $prefix, bool $https): static
    {
        $headers = [
            'X-Forwarded-Host' => $host,
            'X-Forwarded-Proto' => $https ? 'https' : 'http',
        ];

        if ($prefix !== '') {
            $headers['X-Forwarded-Prefix'] = $prefix;
        }

        return $this
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders($headers);
    }

    private function fromTrustedGateway(string $prefix): static
    {
        return $this
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('X-Forwarded-Prefix', $prefix);
    }
}
