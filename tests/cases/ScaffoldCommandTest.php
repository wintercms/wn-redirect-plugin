<?php

declare(strict_types=1);

namespace Winter\Redirect\Tests\Cases;

use Artisan;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Winter\Redirect\Console\ScaffoldCommand;
use Winter\Redirect\Models\Redirect;
use Winter\Redirect\Tests\RedirectPluginTestCase;

/**
 * Guards the safety behaviour of the demo-data scaffolder.
 *
 * The full seed (redirects + categories + hit statistics) is verified end-to-end
 * against a real install; it is not asserted here because this plugin's
 * Vdlp-heritage migrations do not fully provision their tables under the isolated
 * plugin:refresh test harness (the base create-tables migration is skipped/partly
 * rolled back), which is orthogonal to the command itself.
 */
class ScaffoldCommandTest extends RedirectPluginTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->app->register(\Winter\Redirect\ServiceProvider::class);

        // Plugin console commands are registered via ConsoleApplication::starting, which has
        // already fired by the time the test harness boots the plugin — so the command isn't
        // resolvable through Artisan here. Register it directly with the kernel for the test.
        $this->app->make(ConsoleKernel::class)->registerCommand(new ScaffoldCommand());
    }

    public function testCommandIsRegistered()
    {
        $this->assertArrayHasKey('scaffold:winter.redirect', Artisan::all());
    }

    public function testRefusesToRunInProduction()
    {
        $this->app['env'] = 'production';

        $exitCode = Artisan::call('scaffold:winter.redirect');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('production', Artisan::output());
        $this->assertSame(
            0,
            Redirect::where('description', 'like', ScaffoldCommand::MARKER . '%')->count(),
            'Nothing should be created in production.'
        );

        $this->app['env'] = 'testing';
    }
}
