<?php

namespace Tests\Feature\Ai;

use Tests\TestCase;

final class AiScienceWorkerBootstrapTest extends TestCase
{
    public function test_production_does_not_expose_development_bootstrap(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->get('/ai/science/client-worker.js')->assertNotFound();
    }

    public function test_bootstrap_ignores_caller_selected_module_urls(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        if (! is_file(public_path('hot'))) {
            $this->markTestSkipped('Requires an active Vite development server.');
        }
        config(['security.headers.csp.dev_hosts' => [rtrim(trim(file_get_contents(public_path('hot'))), '/')]]);
        $this->get('/ai/science/client-worker.js?url=https://evil.example/program.js')
            ->assertOk()->assertHeader('Content-Type', 'text/javascript; charset=UTF-8')
            ->assertSee('/resources/js/ai/science/client/worker.js', false)
            ->assertDontSee('evil.example', false)->assertSee('pending', false);
    }

    public function test_unapproved_development_origin_is_rejected(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        if (! is_file(public_path('hot'))) {
            $this->markTestSkipped('Requires a Vite hot file.');
        }
        config(['security.headers.csp.dev_hosts' => []]);
        $this->get('/ai/science/client-worker.js')->assertStatus(503);
    }
}
