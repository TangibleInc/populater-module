<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LearnDash\Steps;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Steps\AbstractSeedingStep;
use Tangible\Populater\Support\Logger;

class LDSeedQuizzes extends AbstractSeedingStep
{
    public function __construct(private readonly AbstractSeeder $seeder) {}

    public function getType(): string
    {
        return 'quiz';
    }

    protected function run(array $data, Logger $logger): void
    {
        $lessonId = (int) ($data['lesson_id'] ?? 0);
        $ids      = $this->seeder->seedQuizzes(1, $lessonId, $data);

        foreach ($ids as $id) {
            $logger->info(sprintf('Created LearnDash quiz ID: %d (lesson: %d)', $id, $lessonId));
        }
    }
}
