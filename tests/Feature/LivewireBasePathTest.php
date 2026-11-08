<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Livewire\Mechanisms\FrontendAssets\FrontendAssets;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
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

    private function fromTrustedGateway(string $prefix): static
    {
        return $this
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('X-Forwarded-Prefix', $prefix);
    }
}
