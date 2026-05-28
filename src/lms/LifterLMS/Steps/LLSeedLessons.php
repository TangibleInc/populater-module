<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LifterLMS\Steps;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Steps\AbstractSeedingStep;
use Tangible\Populater\Support\Logger;

class LLSeedLessons extends AbstractSeedingStep
{
    public function __construct(private readonly AbstractSeeder $seeder) {}

    public function getType(): string
    {
        return 'lesson';
    }

    protected function run(array $data, Logger $logger): array
    {
        $courseId = (int) ($data['course_id'] ?? 0);
        $ids      = $this->seeder->seedLessons(1, $courseId, $data);

        foreach ($ids as $id) {
            $logger->info(sprintf('Created LifterLMS lesson ID: %d (course: %d)', $id, $courseId));
        }
        return $ids;
    }
}
