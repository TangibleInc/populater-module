<?php

declare(strict_types=1);

namespace Tangible\Populater\Steps;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Seeding\ProcessRepository;
use Tangible\Populater\Support\Logger;

/**
 * Generic step that delegates to the LMS seeder for a queue item type.
 */
class DelegateSeedingStep extends AbstractSeedingStep
{
    public function __construct(
        ProcessRepository $repository,
        private readonly AbstractSeeder $seeder,
        private readonly string $type,
        private readonly string $label,
    ) {
        parent::__construct($repository);
    }

    public function getType(): string
    {
        return $this->type;
    }

    protected function run(array $data, Logger $logger): array
    {
        $ids = match ($this->type) {
            'course'           => $this->seeder->seedCourses(1, $data),
            'lesson'           => $this->seeder->seedLessons(1, (int) ($data['course_id'] ?? 0), $data),
            'quiz'             => $this->seeder->seedQuizzes(1, (int) ($data['quiz_parent_id'] ?? 0), $data),
            'user'             => $this->seeder->seedUsers(1, $data),
            'group'            => $this->seeder->seedGroups(1, $data),
            'group_admin'      => $this->seeder->seedGroupAdmins(1, $data),
            'certificate'      => $this->seeder->seedCertificates(1, $data),
            'course_structure' => $this->seeder->seedCourseStructure($data),
            default            => [],
        };

        foreach ($ids as $id) {
            $logger->info(sprintf('Created %s ID: %d', $this->label, $id));
        }

        return $ids;
    }
}
