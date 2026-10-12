<?php
/**
 * Seeding manager.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

use Tangible\Populater\Registry\LmsPluginRegistry;
use Tangible\Populater\Seeders\AbstractSeeder;

/**
 * Orchestrates seeding across multiple supported LMS plugins.
 */
class SeedingManager {

	/**
	 * Registered seeders keyed by slug.
	 *
	 * @var array<string, AbstractSeeder>
	 */
	private array $seeders;

	/**
	 * Registered job runners keyed by slug.
	 *
	 * @var array<string, SeedingJobRunner>
	 */
	private array $runners;

	/**
	 * Repository that persists seeding process state.
	 *
	 * @var ProcessRepository
	 */
	private readonly ProcessRepository $repository;

	/**
	 * Creates the manager and instantiates a seeder for every registered LMS plugin.
	 *
	 * @param LmsPluginRegistry      $registry   Registry of supported LMS plugins.
	 * @param ProcessRepository|null $repository Repository used to persist process state, or null to create a default one.
	 */
	public function __construct(
		private readonly LmsPluginRegistry $registry = new LmsPluginRegistry(),
		?ProcessRepository $repository = null,
	) {
		$this->repository = $repository ?? new ProcessRepository();
		$this->seeders    = array();

		foreach ( $this->registry->all() as $slug => $plugin ) {
			$this->seeders[ $slug ] = $plugin->createSeeder();
		}
	}

	/**
	 * Constructs LMS seeding processes so WP_Background_Process ajax/cron hooks
	 * are registered for the lifetime of the request.
	 *
	 * @return void
	 */
	public function registerBackgroundProcesses(): void {
		$this->runners = array();

		foreach ( $this->registry->all() as $slug => $plugin ) {
			$seeder                 = $this->seeders[ $slug ];
			$process                = $plugin->createProcess( $seeder, $this->repository );
			$this->runners[ $slug ] = new WpBackgroundSeedingRunner( $process );
		}
	}

	/**
	 * Validates the configuration and starts seeding with the runner of the selected plugin.
	 *
	 * @param array<string, mixed> $config Raw seeding configuration including the plugin slug.
	 * @return string ID of the newly started process.
	 */
	public function start( array $config ): string {
		$seedConfig = SeedConfig::fromArray( $config );
		$seeder     = $this->resolveSeeder( $seedConfig->plugin );

		return $this->getRunner( $seeder->getSlug() )->start( $seedConfig->toArray() );
	}

	/**
	 * Cancels a seeding process using the runner of the plugin that started it.
	 *
	 * @param string $processId ID of the seeding process.
	 * @return bool True if the process was cancelled, false if its plugin is unknown or it could not be cancelled.
	 */
	public function cancel( string $processId ): bool {
		$slug = $this->resolvePluginSlugForProcess( $processId );

		if ( null === $slug ) {
			return false;
		}

		return $this->getRunner( $slug )->cancel( $processId );
	}

	/**
	 * Gets the status of a seeding process.
	 *
	 * @param string $processId ID of the seeding process.
	 * @return SeedingStatus Status value object for the process.
	 */
	public function getStatus( string $processId ): SeedingStatus {
		$data = $this->repository->getStatus( $processId );

		return SeedingStatus::fromArray( $processId, $data );
	}

	/**
	 * Gets the status of the currently active process, clearing the active marker if it can no longer be cancelled.
	 *
	 * @return SeedingStatus|null Status of the active process, or null if none is active.
	 */
	public function getActiveProcess(): ?SeedingStatus {
		$processId = $this->repository->findActiveProcessId();

		if ( null === $processId ) {
			return null;
		}

		$status = $this->getStatus( $processId );

		if ( ! $status->canBeCancelled() ) {
			$this->repository->clearActiveProcess();

			return null;
		}

		return $status;
	}

	/**
	 * Gets the log entries of a seeding process.
	 *
	 * @param string $processId ID of the seeding process.
	 * @return list<array{level: string, message: string, timestamp: int}> Log entries, oldest first.
	 */
	public function getLogs( string $processId ): array {
		return $this->repository->getLogs( $processId );
	}

	/**
	 * Lists every supported LMS plugin with its name and activation state.
	 *
	 * @return list<array{slug: string, name: string, active: bool}> Supported plugins.
	 */
	public function getSupportedPlugins(): array {
		return array_values(
			array_map(
				fn( AbstractSeeder $seeder ) => array(
					'slug'   => $seeder->getSlug(),
					'name'   => $seeder->getName(),
					'active' => $seeder->isActive(),
				),
				$this->seeders
			)
		);
	}

	/**
	 * Finds the seeder for a plugin slug and ensures its plugin is active.
	 *
	 * @param string $slug Plugin slug.
	 * @return AbstractSeeder Seeder for the plugin.
	 * @throws \InvalidArgumentException If the slug is not supported.
	 * @throws \RuntimeException If the plugin is not active.
	 */
	private function resolveSeeder( string $slug ): AbstractSeeder {
		if ( ! isset( $this->seeders[ $slug ] ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Unsupported plugin "%s". Available: %s', esc_html( $slug ), esc_html( implode( ', ', array_keys( $this->seeders ) ) ) )
			);
		}

		$seeder = $this->seeders[ $slug ];

		if ( ! $seeder->isActive() ) {
			throw new \RuntimeException(
				sprintf( 'Plugin "%s" is not active. Activate it before seeding.', esc_html( $seeder->getName() ) )
			);
		}

		return $seeder;
	}

	/**
	 * Gets the job runner for a plugin slug, registering runners on first use.
	 *
	 * @param string $slug Plugin slug.
	 * @return SeedingJobRunner Runner for the plugin.
	 * @throws \InvalidArgumentException If no runner exists for the slug.
	 */
	private function getRunner( string $slug ): SeedingJobRunner {
		$this->ensureRunnersRegistered();

		if ( ! isset( $this->runners[ $slug ] ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'No seeding process for plugin "%s".', esc_html( $slug ) )
			);
		}

		return $this->runners[ $slug ];
	}

	/**
	 * Registers the background processes if that has not happened yet.
	 *
	 * @return void
	 */
	private function ensureRunnersRegistered(): void {
		if ( ! isset( $this->runners ) ) {
			$this->registerBackgroundProcesses();
		}
	}

	/**
	 * Looks up the plugin slug stored in a process status and checks that it is registered.
	 *
	 * @param string $processId ID of the seeding process.
	 * @return string|null Registered plugin slug, or null if it is missing or unknown.
	 */
	private function resolvePluginSlugForProcess( string $processId ): ?string {
		$data = $this->repository->getStatus( $processId );
		$slug = $data['plugin'] ?? null;

		return is_string( $slug ) && $this->registry->has( $slug ) ? $slug : null;
	}
}
