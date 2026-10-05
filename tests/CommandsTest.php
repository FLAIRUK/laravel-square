<?php

namespace FLAIRUK\Square\Tests;

use FLAIRUK\Square\ClientFactory;
use FLAIRUK\Square\Square;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class CommandsTest extends TestCase
{
    #[Test]
    public function install_publishes_config_and_adds_missing_env_keys_once(): void
    {
        $env = $this->app->environmentFilePath();
        $example = base_path('.env.example');
        $originals = [];

        foreach ([$env, $example] as $path) {
            $originals[$path] = File::exists($path) ? File::get($path) : null;
        }

        File::put($env, "APP_NAME=Test\nSQUARE_ACCESS_TOKEN=existing\n");
        File::put($example, "APP_NAME=Laravel\n");

        try {
            $this->artisan('square:install')->assertSuccessful();
            $this->artisan('square:install')->assertSuccessful();

            $contents = File::get($env);
            foreach (['SQUARE_ACCESS_TOKEN', 'SQUARE_ENVIRONMENT', 'SQUARE_LOCATION_ID', 'SQUARE_APPLICATION_ID', 'SQUARE_WEBHOOK_SIGNATURE_KEY', 'SQUARE_WEBHOOK_URL'] as $key) {
                $this->assertSame(1, substr_count($contents, "{$key}="), $key);
                $this->assertStringContainsString("{$key}=", File::get($example));
            }
            $this->assertStringContainsString('SQUARE_ACCESS_TOKEN=existing', $contents);
            $this->assertStringContainsString("\nSQUARE_LOCATION_ID=\n", $contents);
            $this->assertFileExists(config_path('square.php'));
        } finally {
            foreach ($originals as $path => $original) {
                $original === null ? File::delete($path) : File::put($path, $original);
            }
            File::delete(config_path('square.php'));
        }
    }

    #[Test]
    public function status_lists_locations(): void
    {
        $this->fakeSquare(['*/v2/locations' => Http::response(['locations' => [
            $this->location(),
            $this->location('L456', 'Market Stall'),
        ]])]);

        $this->artisan('square:status')
            ->expectsOutputToContain('Connected to Square (sandbox).')
            ->expectsTable(['Location ID', 'Name', 'Status', 'Currency', 'Default'], [
                ['L123', 'High Street', 'ACTIVE', 'GBP', 'Yes'],
                ['L456', 'Market Stall', 'ACTIVE', 'GBP', ''],
            ])
            ->assertSuccessful();
    }

    #[Test]
    public function status_reports_api_failures(): void
    {
        $this->fakeSquare(['*' => Http::response(['errors' => [['category' => 'AUTHENTICATION_ERROR', 'code' => 'UNAUTHORIZED']]], 401)]);

        $this->artisan('square:status')
            ->expectsOutputToContain('Status code: 401')
            ->assertFailed();
    }

    #[Test]
    public function status_reports_a_missing_token(): void
    {
        config(['square.access_token' => '']);
        $this->app->forgetInstance(Square::class);
        $this->app->forgetInstance(ClientFactory::class);
        $this->fakeSquare();

        $this->artisan('square:status')
            ->expectsOutputToContain('SQUARE_ACCESS_TOKEN')
            ->assertFailed();

        Http::assertNothingSent();
    }

    #[Test]
    public function status_fails_when_the_default_location_is_not_accessible(): void
    {
        $this->fakeSquare(['*/v2/locations' => Http::response(['locations' => [$this->location('L999', 'Elsewhere')]])]);

        $this->artisan('square:status')
            ->expectsOutputToContain('SQUARE_LOCATION_ID (L123) is not one of these locations.')
            ->assertFailed();
    }
}
