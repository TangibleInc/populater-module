<?php

declare(strict_types=1);

namespace Tangible\Populater\Registry;

/**
 * Post types, title prefixes, relationship meta, and optional container entities
 * for an LMS integration.
 */
final readonly class LmsEntitySchema
{
    /**
     * @param array<string, string> $postTypes Entity key => WordPress post type slug
     * @param array<string, string> $titlePrefixes Entity key => generated content title prefix
     * @param array<string, array<string, string>> $metaMap Entity key => [meta key => context key]
     */
    public function __construct(
        public array $postTypes,
        public array $titlePrefixes = [],
        public string $userPrefix = '',
        public array $metaMap = [],
        public ?LmsContainerEntity $container = null,
    ) {}

    public function getPostType(string $entity): string
    {
        return $this->postTypes[$entity] ?? 'post';
    }

    public function getTitlePrefix(string $entity, string $pluginName): string
    {
        if (isset($this->titlePrefixes[$entity])) {
            return $this->titlePrefixes[$entity];
        }

        return $pluginName . ' ' . ucfirst(rtrim($entity, 's'));
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, int>
     */
    public function resolveMeta(string $entity, array $context): array
    {
        $map  = $this->metaMap[$entity] ?? [];
        $meta = [];

        foreach ($map as $metaKey => $contextKey) {
            $meta[$metaKey] = (int) ($context[$contextKey] ?? 0);
        }

        return $meta;
    }

    /**
     * @return array{courses: string, lessons: string, quizzes: string, questions: string, user_prefix: string, topics?: string, sections?: string, modules?: string}
     */
    public function postTypesForSnapshot(): array
    {
        $snapshot = [
            'courses'     => $this->getPostType('courses'),
            'lessons'     => $this->getPostType('lessons'),
            'quizzes'     => $this->getPostType('quizzes'),
            'questions'   => $this->getPostType('questions'),
            'user_prefix' => $this->userPrefix,
        ];

        foreach (['topics', 'sections', 'modules'] as $optional) {
            if (isset($this->postTypes[$optional])) {
                $snapshot[$optional] = $this->postTypes[$optional];
            }
        }

        return $snapshot;
    }
}

/**
 * Intermediate entity between course and lesson (section, module, etc.).
 */
final readonly class LmsContainerEntity
{
    public function __construct(
        public string $entity,
        public string $cacheMetaKey,
        public string $parentMetaKey,
        public string $lessonParentMetaKey = '',
    ) {}
}
