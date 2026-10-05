<?php

namespace FLAIRUK\Square\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'square:install')]
class InstallCommand extends Command
{
    protected $signature = 'square:install';

    protected $description = 'Publish the Square config and add its environment variables to .env';

    /** @var list<string> */
    protected array $variables = [
        'SQUARE_ACCESS_TOKEN',
        'SQUARE_ENVIRONMENT',
        'SQUARE_LOCATION_ID',
        'SQUARE_APPLICATION_ID',
        'SQUARE_WEBHOOK_SIGNATURE_KEY',
        'SQUARE_WEBHOOK_URL',
    ];

    public function handle(Filesystem $files): int
    {
        $this->call('vendor:publish', ['--tag' => 'square-config']);

        foreach ([$this->laravel->environmentFilePath(), base_path('.env.example')] as $path) {
            if (! $files->exists($path)) {
                continue;
            }

            $contents = $files->get($path);
            $missing = array_filter($this->variables, fn (string $key) => ! preg_match("/^{$key}=/m", $contents));

            if ($missing) {
                $files->append($path, PHP_EOL.implode(PHP_EOL, array_map(fn ($key) => "{$key}=", $missing)).PHP_EOL);
                $this->components->info('Added '.implode(', ', $missing).' to '.basename($path).'.');
            }
        }

        $this->components->info('Fill in your Square credentials, then run `php artisan square:status` to check the connection.');

        return self::SUCCESS;
    }
}
