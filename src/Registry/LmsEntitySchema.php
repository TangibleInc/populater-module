<?php
/**
 * Post types, title prefixes, relationship meta, and optional container entities for an LMS integration.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Registry;

/**
 * Post types, title prefixes, relationship meta, and optional container entities
 * for an LMS integration.
 */
final class LmsEntitySchema {

	/**
	 * Constructor.
	 *
	 * @param array<string, string>                $postTypes        Entity key => WordPress post type slug.
	 * @param array<string, string>                $titlePrefixes    Entity key => generated content title prefix.
	 * @param string                               $userPrefix       Username prefix.
	 * @param array<string, array<string, string>> $metaMap          Entity key => [meta key => context key].
	 * @param LmsContainerEntity|null              $container        Optional container entity.
	 * @param string|null                          $quizParentEntity Entity key quizzes attach to: topics, sections, modules, etc.
	 */
	public function __construct(
		public readonly array $postTypes,
		public readonly array $titlePrefixes = array(),
		public readonly string $userPrefix = '',
		public readonly array $metaMap = array(),
		public readonly ?LmsContainerEntity $container = null,
		public readonly ?string $quizParentEntity = null,
	) {}

	/**
	 * Get the post type for an entity key.
	 *
	 * @param string $entity Entity key.
	 * @return string
	 */
	public function getPostType( string $entity ): string {
		return $this->postTypes[ $entity ] ?? 'post';
	}

	/**
	 * Get the title prefix for an entity key.
	 *
	 * @param string $entity     Entity key.
	 * @param string $pluginName Plugin display name.
	 * @return string
	 */
	public function getTitlePrefix( string $entity, string $pluginName ): string {
		if ( isset( $this->titlePrefixes[ $entity ] ) ) {
			return $this->titlePrefixes[ $entity ];
		}

		return $pluginName . ' ' . ucfirst( rtrim( $entity, 's' ) );
	}

	/**
	 * Resolve meta values for an entity from a context.
	 *
	 * @param string               $entity  Entity key.
	 * @param array<string, mixed> $context Context values.
	 * @return array<string, int>
	 */
	public function resolveMeta( string $entity, array $context ): array {
		$map  = $this->metaMap[ $entity ] ?? array();
		$meta = array();

		foreach ( $map as $metaKey => $contextKey ) {
			$meta[ $metaKey ] = (int) ( $context[ $contextKey ] ?? 0 );
		}

		return $meta;
	}

	/**
	 * Get the post types and username prefix recorded in a seeding snapshot.
	 *
	 * @return array{courses: string, lessons: string, quizzes: string, questions: string, user_prefix: string, topics?: string, sections?: string, modules?: string}
	 */
	public function postTypesForSnapshot(): array {
		$snapshot = array(
			'courses'     => $this->getPostType( 'courses' ),
			'lessons'     => $this->getPostType( 'lessons' ),
			'quizzes'     => $this->getPostType( 'quizzes' ),
			'questions'   => $this->getPostType( 'questions' ),
			'user_prefix' => $this->userPrefix,
		);

		foreach ( array( 'topics', 'sections', 'modules' ) as $optional ) {
			if ( isset( $this->postTypes[ $optional ] ) ) {
				$snapshot[ $optional ] = $this->postTypes[ $optional ];
			}
		}

		return $snapshot;
	}
}
