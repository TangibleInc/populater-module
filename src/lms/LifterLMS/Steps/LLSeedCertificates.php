<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LifterLMS\Steps;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Steps\AbstractSeedingStep;
use Tangible\Populater\Support\Logger;

class LLSeedCertificates extends AbstractSeedingStep
{
    public function __construct(private readonly AbstractSeeder $seeder) {}

    public function getType(): string
    {
        return 'certificate';
    }

    protected function run(array $data, Logger $logger): void
    {
        $ids = $this->seeder->seedCertificates(1, $data);

        foreach ($ids as $id) {
            $logger->info(sprintf('Created LifterLMS certificate ID: %d', $id));
        }
    }
}
