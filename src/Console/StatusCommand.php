<?php

namespace FLAIRUK\Square\Console;

use FLAIRUK\Square\Exceptions\SquareException as PackageException;
use FLAIRUK\Square\Square;
use Illuminate\Console\Command;
use Square\Exceptions\SquareException;
use Square\Types\Location;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'square:status')]
class StatusCommand extends Command
{
    protected $signature = 'square:status';

    protected $description = 'Check the Square access token and list the locations it can access';

    public function handle(Square $square): int
    {
        try {
            $environment = $square->environment();
            $locations = $square->client()->locations->list()->getLocations() ?? [];
        } catch (SquareException|PackageException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Connected to Square ({$environment->value}).");

        $default = $square->locationId();

        $this->table(['Location ID', 'Name', 'Status', 'Currency', 'Default'], array_map(
            fn (Location $location) => [
                $location->getId() ?? '',
                $location->getName() ?? '',
                $location->getStatus() ?? '',
                $location->getCurrency() ?? '',
                $default !== null && $location->getId() === $default ? 'Yes' : '',
            ],
            $locations,
        ));

        if ($default !== null && ! in_array($default, array_map(fn (Location $location) => $location->getId(), $locations), true)) {
            $this->components->warn("SQUARE_LOCATION_ID ({$default}) is not one of these locations.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
