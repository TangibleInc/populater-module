<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LearnDash;

use Tangible\Populater\LMS\LearnDash\Steps\LDSeedCertificates;
use Tangible\Populater\LMS\LearnDash\Steps\LDSeedCourses;
use Tangible\Populater\LMS\LearnDash\Steps\LDSeedLessons;
use Tangible\Populater\LMS\LearnDash\Steps\LDSeedQuizzes;
use Tangible\Populater\LMS\LearnDash\Steps\LDSeedUsers;
use Tangible\Populater\Seeding\AbstractSeeding;
use Tangible\Populater\Steps\AbstractSeedingStep;
use Tangible\Populater\Support\Logger;

/**
 * LearnDash-specific seeding process.
 *
 * Dispatches each queue item to the corresponding LearnDash step class.
 * Override processItem() in a further subclass if additional LearnDash-
 * specific behaviour is required (e.g. group enrolment, certificate triggers).
 */
class LearnDashSeedingProcess extends AbstractSeeding
{
    protected $action = 'seed_learndash';

    /** @var array<string, AbstractSeedingStep>|null */
    private ?array $steps = null;

    /** @return array<string, AbstractSeedingStep> */
    protected function buildSteps(): array
    {
        return [
            'course'      => new LDSeedCourses($this->seeder),
            'lesson'      => new LDSeedLessons($this->seeder),
            'quiz'        => new LDSeedQuizzes($this->seeder),
            'user'        => new LDSeedUsers($this->seeder),
            'certificate' => new LDSeedCertificates($this->seeder),
        ];
    }

    /**
     * @param array{type: string, data: array<string, mixed>} $item
     */
    protected function processItem(array $item, string $processId, Logger $logger): void
    {
        $this->steps ??= $this->buildSteps();

        $step = $this->steps[$item['type']] ?? null;

        if ($step === null) {
            $logger->warning(sprintf('No LearnDash step registered for type: %s', $item['type']));
            return;
        }

        $data = $item['data'];
        $data['process_id'] = $processId;
        $step->execute($data, $logger);
    }
}
