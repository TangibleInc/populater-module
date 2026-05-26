<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LearnDash\Steps;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Steps\AbstractSeedingStep;
use Tangible\Populater\Support\Logger;

class LDSeedCourses extends AbstractSeedingStep
{
    public function __construct(private readonly AbstractSeeder $seeder) {}

    public function getType(): string
    {
        return 'course';
    }

    protected function run(array $data, Logger $logger): void
    {
        $ids = $this->seeder->seedCourses(1, $data);

        foreach ($ids as $id) {
            $logger->info(sprintf('Created LearnDash course ID: %d', $id));
        }
    }
}
