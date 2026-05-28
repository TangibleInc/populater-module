<?php

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

use Tangible\Populater\Steps\AbstractSeedingStep;
use Tangible\Populater\Steps\DelegateSeedingStep;
use Tangible\Populater\Support\Logger;

/**
 * LMS seeding process using generic delegate steps.
 */
abstract class LmsSeedingProcess extends AbstractSeeding
{
    /** @var array<string, AbstractSeedingStep>|null */
    private ?array $steps = null;

    /** @return array<string, AbstractSeedingStep> */
    protected function buildSteps(): array
    {
        $label = $this->seeder->getName();

        return [
            'course'      => new DelegateSeedingStep($this->seeder, 'course', $label),
            'lesson'      => new DelegateSeedingStep($this->seeder, 'lesson', $label),
            'quiz'        => new DelegateSeedingStep($this->seeder, 'quiz', $label),
            'user'        => new DelegateSeedingStep($this->seeder, 'user', $label),
            'certificate' => new DelegateSeedingStep($this->seeder, 'certificate', $label),
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
            $logger->warning(sprintf('No step registered for type: %s', $item['type']));
            return;
        }

        $data               = $item['data'];
        $data['process_id'] = $processId;
        $step->execute($data, $logger);
    }
}
