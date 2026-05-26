<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LifterLMS;

use Tangible\Populater\LMS\LifterLMS\Steps\LLSeedCertificates;
use Tangible\Populater\LMS\LifterLMS\Steps\LLSeedCourses;
use Tangible\Populater\LMS\LifterLMS\Steps\LLSeedLessons;
use Tangible\Populater\LMS\LifterLMS\Steps\LLSeedQuizzes;
use Tangible\Populater\LMS\LifterLMS\Steps\LLSeedUsers;
use Tangible\Populater\Seeding\SeedingProcess;
use Tangible\Populater\Steps\AbstractSeedingStep;
use Tangible\Populater\Support\Logger;

/**
 * LifterLMS-specific seeding process.
 *
 * Dispatches each queue item to the corresponding LifterLMS step class.
 * Override processItem() in a further subclass if additional LifterLMS-
 * specific behaviour is required (e.g. membership assignments, section grouping).
 */
class LifterLMSSeedingProcess extends SeedingProcess
{
    /** @var array<string, AbstractSeedingStep>|null */
    private ?array $steps = null;

    /** @return array<string, AbstractSeedingStep> */
    protected function buildSteps(): array
    {
        return [
            'course'      => new LLSeedCourses($this->seeder),
            'lesson'      => new LLSeedLessons($this->seeder),
            'quiz'        => new LLSeedQuizzes($this->seeder),
            'user'        => new LLSeedUsers($this->seeder),
            'certificate' => new LLSeedCertificates($this->seeder),
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
            $logger->warning(sprintf('No LifterLMS step registered for type: %s', $item['type']));
            return;
        }

        $step->execute($item['data'], $logger);
    }
}
