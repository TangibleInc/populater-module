<?php
/**
 * LMS seeding process.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Steps\AbstractSeedingStep;
use Tangible\Populater\Steps\DelegateSeedingStep;
use Tangible\Populater\Support\Logger;

/**
 * LMS seeding process using generic delegate steps.
 */
class LmsSeedingProcess extends AbstractSeeding {

	/**
	 * Cached seeding steps.
	 *
	 * @var array<string, AbstractSeedingStep>|null
	 */
	private ?array $steps = null;

	/**
	 * Creates the process with a custom background action name.
	 *
	 * @param AbstractSeeder         $seeder     Seeder that builds the queue and performs the work.
	 * @param string                 $action     Background process action name, unique per LMS.
	 * @param ProcessRepository|null $repository Repository used to persist process state, or null to create a default one.
	 */
	public function __construct(
		AbstractSeeder $seeder,
		string $action,
		?ProcessRepository $repository = null,
	) {
		$this->action = $action;
		parent::__construct( $seeder, $repository );
	}

	/**
	 * Creates one delegating seeding step per supported queue item type.
	 *
	 * @return array<string, AbstractSeedingStep> Steps keyed by queue item type.
	 */
	protected function buildSteps(): array {
		$label = $this->seeder->getName();

		return array(
			'course'           => new DelegateSeedingStep( $this->repository, $this->seeder, 'course', $label ),
			'lesson'           => new DelegateSeedingStep( $this->repository, $this->seeder, 'lesson', $label ),
			'quiz'             => new DelegateSeedingStep( $this->repository, $this->seeder, 'quiz', $label ),
			'user'             => new DelegateSeedingStep( $this->repository, $this->seeder, 'user', $label ),
			'group'            => new DelegateSeedingStep( $this->repository, $this->seeder, 'group', $label ),
			'group_admin'      => new DelegateSeedingStep( $this->repository, $this->seeder, 'group_admin', $label ),
			'certificate'      => new DelegateSeedingStep( $this->repository, $this->seeder, 'certificate', $label ),
			'course_structure' => new DelegateSeedingStep( $this->repository, $this->seeder, 'course_structure', $label ),
			'student_activity' => new DelegateSeedingStep( $this->repository, $this->seeder, 'student_activity', $label ),
		);
	}

	/**
	 * Runs the step registered for the item type, or logs a warning if there is none.
	 *
	 * @param array{type: string, data: array<string, mixed>} $item      Queue item type and enriched payload.
	 * @param string                                          $processId ID of the seeding process the item belongs to.
	 * @param Logger                                          $logger    Logger that records progress and errors for the process.
	 * @return void
	 */
	protected function processItem( array $item, string $processId, Logger $logger ): void {
		$this->steps ??= $this->buildSteps();

		$step = $this->steps[ $item['type'] ] ?? null;

		if ( null === $step ) {
			$logger->warning( sprintf( 'No step registered for type: %s', $item['type'] ) );
			return;
		}

		$data               = $item['data'];
		$data['process_id'] = $processId;
		$step->execute( $data, $logger );
	}
}
