<?php

declare(strict_types=1);

namespace Tangible\Populater\Steps;

use Tangible\Populater\Seeding\SeedingIdMap;
use Tangible\Populater\Support\Logger;

/**
 * Base contract for a single content-type seeding step.
 *
 * Concrete implementations handle one item type (course, lesson, quiz, user,
 * certificate, …) and are composed into an LMS-specific seeding process.
 *
 * Subclasses only need to implement getType() and run(). All cross-cutting
 * concerns — start/finish logging, exception catching, and error logging —
 * are handled here in execute().
 */
abstract class AbstractSeedingStep
{
    // -------------------------------------------------------------------------
    // Contract
    // -------------------------------------------------------------------------

    /**
     * The queue item type this step handles (e.g. 'course', 'lesson', 'user').
     */
    abstract public function getType(): string;

    /**
     * Core seeding logic.
     *
     * Implement all content-creation work here. Any uncaught exception will be
     * caught by execute() and recorded as an error log entry.
     *
     * @param array<string, mixed> $data    Item payload from the seed queue.
     * @param Logger               $logger  Process-scoped logger.
     * @return list<int>  Created post/user IDs.
     */
    abstract protected function run(array $data, Logger $logger): array;

    // -------------------------------------------------------------------------
    // Execution shell (logging + error handling)
    // -------------------------------------------------------------------------

    /**
     * Runs the step with automatic logging and error handling.
     *
     * On success the step is silent apart from the start/end log lines.
     * On failure the exception message is recorded at error level and execution
     * continues (the background process can move on to the next item).
     *
     * @param array<string, mixed> $data
     */
    final public function execute(array $data, Logger $logger): void
    {
        $type = $this->getType();

        $logger->info(sprintf('Starting step: %s', $type));

        try {
            $ids = $this->run($data, $logger);

            if (isset($data['process_id']) && is_string($data['process_id'])) {
                SeedingIdMap::record($data['process_id'], $type, $data, $ids);
            }

            $logger->info(sprintf('Completed step: %s', $type));
        } catch (\Throwable $e) {
            $logger->error(sprintf(
                '[%s] %s in %s:%d',
                $type,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
            ));
        }
    }
}
