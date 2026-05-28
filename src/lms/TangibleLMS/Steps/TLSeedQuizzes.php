<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\TangibleLMS\Steps;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Steps\AbstractSeedingStep;
use Tangible\Populater\Support\Logger;

class TLSeedQuizzes extends AbstractSeedingStep
{
    public function __construct(private readonly AbstractSeeder $seeder) {}

    public function getType(): string
    {
        return 'quiz';
    }

    protected function run(array $data, Logger $logger): array
    {
        $lessonId = (int) ($data['lesson_id'] ?? 0);
        $ids      = $this->seeder->seedQuizzes(1, $lessonId, $data);

        foreach ($ids as $id) {
            $logger->info(sprintf('Created Tangible LMS quiz ID: %d (lesson: %d)', $id, $lessonId));
        }
        return $ids;
    }
}
