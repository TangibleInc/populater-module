<?php
/**
 * Intermediate container entity definition.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Registry;

/**
 * Intermediate entity between course and lesson (section, module, etc.).
 */
final class LmsContainerEntity {

	/**
	 * Constructor.
	 *
	 * @param string $entity              Entity key.
	 * @param string $cacheMetaKey        Meta key used for caching.
	 * @param string $parentMetaKey       Meta key referencing the parent.
	 * @param string $lessonParentMetaKey Meta key referencing the lesson parent.
	 */
	public function __construct(
		public readonly string $entity,
		public readonly string $cacheMetaKey,
		public readonly string $parentMetaKey,
		public readonly string $lessonParentMetaKey = '',
	) {}
}
