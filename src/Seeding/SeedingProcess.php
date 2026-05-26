<?php

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Support\Logger;

/**
 * Default concrete implementation of AbstractSeeding.
 *
 * Delegates item processing back to the seeder so each LMS plugin controls
 * how its own content types are created during the background run.
 *
 * LMS-specific subclasses should extend this class and override processItem()
 * to dispatch to their own AbstractSeedingStep implementations.
 */
class SeedingProcess extends AbstractSeeding
{
    public function __construct(AbstractSeeder $seeder)
    {
        parent::__construct($seeder);
    }

    /**
     * Processes a single queued item by calling the appropriate seeder method.
     *
     * @param array{type: string, data: array<string, mixed>} $item
     */
    protected function processItem(array $item, string $processId, Logger $logger): void
    {
        $type = $item['type'];
        $data = $item['data'];

        $logger->info(sprintf('Processing %s (process: %s)', $type, $processId));

        match ($type) {
            'course'      => $this->seeder->seedCourses(1, $data),
            'lesson'      => $this->seeder->seedLessons(1, (int) ($data['course_id'] ?? 0), $data),
            'quiz'        => $this->seeder->seedQuizzes(1, (int) ($data['lesson_id'] ?? 0), $data),
            'user'        => $this->seeder->seedUsers(1, $data),
            'certificate' => $this->seeder->seedCertificates(1, $data),
            default       => $logger->warning(sprintf('Unknown item type: %s', $type)),
        };
    }
}
