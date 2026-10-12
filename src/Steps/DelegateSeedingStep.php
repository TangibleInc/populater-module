<?php
/**
 * Generic step that delegates to the LMS seeder.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Steps;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Seeding\ProcessRepository;
use Tangible\Populater\Support\Logger;

/**
 * Generic step that delegates to the LMS seeder for a queue item type.
 */
class DelegateSeedingStep extends AbstractSeedingStep {

	/**
	 * Constructor.
	 *
	 * @param ProcessRepository $repository Process repository.
	 * @param AbstractSeeder    $seeder     LMS seeder to delegate to.
	 * @param string            $type       Queue item type handled.
	 * @param string            $label      Human-readable entity label for logs.
	 */
	public function __construct(
		ProcessRepository $repository,
		private readonly AbstractSeeder $seeder,
		private readonly string $type,
		private readonly string $label,
	) {
		parent::__construct( $repository );
	}

	/**
	 * The queue item type this step handles.
	 *
	 * @return string
	 */
	public function getType(): string {
		return $this->type;
	}

	/**
	 * Delegate seeding to the LMS seeder.
	 *
	 * @param array<string, mixed> $data   Item payload from the seed queue.
	 * @param Logger               $logger Process-scoped logger.
	 * @return list<int> Created post/user IDs.
	 */
	protected function run( array $data, Logger $logger ): array {
		$ids = match ( $this->type ) {
			'course'           => $this->seeder->seedCourses( 1, $data ),
			'lesson'           => $this->seeder->seedLessons( 1, (int) ( $data['course_id'] ?? 0 ), $data ),
			'quiz'             => $this->seeder->seedQuizzes( 1, (int) ( $data['quiz_parent_id'] ?? 0 ), $data ),
			'user'             => $this->seeder->seedUsers( 1, $data ),
			'group'            => $this->seeder->seedGroups( 1, $data ),
			'group_admin'      => $this->seeder->seedGroupAdmins( 1, $data ),
			'certificate'      => $this->seeder->seedCertificates( 1, $data ),
			'course_structure' => $this->seeder->seedCourseStructure( $data ),
			'student_activity' => $this->seeder->seedStudentActivity( 1, $data ),
			default            => array(),
		};

		foreach ( $ids as $id ) {
			$verb = 'student_activity' === $this->type ? 'Completed courses for' : 'Created';
			$logger->info( sprintf( '%s %s ID: %d', $verb, $this->label, $id ) );
		}

		return $ids;
	}
}
