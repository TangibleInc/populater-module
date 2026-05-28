<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\TangibleLMS;

use Tangible\Populater\LMS\TangibleLMS\Steps\TLSeedCertificates;
use Tangible\Populater\LMS\TangibleLMS\Steps\TLSeedCourses;
use Tangible\Populater\LMS\TangibleLMS\Steps\TLSeedLessons;
use Tangible\Populater\LMS\TangibleLMS\Steps\TLSeedQuizzes;
use Tangible\Populater\LMS\TangibleLMS\Steps\TLSeedUsers;
use Tangible\Populater\Seeding\AbstractSeeding;
use Tangible\Populater\Steps\AbstractSeedingStep;
use Tangible\Populater\Support\Logger;

/**
 * Tangible LMS-specific seeding process.
 *
 * Dispatches each queue item to the corresponding Tangible LMS step class.
 * Override processItem() in a further subclass if additional Tangible LMS-
 * specific behaviour is required once the plugin's public API is stable.
 */
class TangibleLMSSeedingProcess extends AbstractSeeding
{
    protected $action = 'seed_tangible_lms';

    /** @var array<string, AbstractSeedingStep>|null */
    private ?array $steps = null;

    /** @return array<string, AbstractSeedingStep> */
    protected function buildSteps(): array
    {
        return [
            'course'      => new TLSeedCourses($this->seeder),
            'lesson'      => new TLSeedLessons($this->seeder),
            'quiz'        => new TLSeedQuizzes($this->seeder),
            'user'        => new TLSeedUsers($this->seeder),
            'certificate' => new TLSeedCertificates($this->seeder),
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
            $logger->warning(sprintf('No Tangible LMS step registered for type: %s', $item['type']));
            return;
        }

        $data = $item['data'];
        $data['process_id'] = $processId;
        $step->execute($data, $logger);
    }
}
