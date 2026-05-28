<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\TangibleLMS;

use Tangible\Populater\Registry\LmsPluginDefinition;
use Tangible\Populater\Seeders\AbstractSeeder;

/**
 * Seeder for the Tangible LMS plugin.
 */
class TangibleLMSSeeder extends AbstractSeeder
{
    private const PT_COURSE      = 'tgl_course';
    private const PT_LESSON      = 'tgl_lesson';
    private const PT_QUIZ        = 'tgl_quiz';
    private const PT_CERTIFICATE = 'tgl_certificate';

    public function __construct(?LmsPluginDefinition $definition = null)
    {
        parent::__construct($definition ?? new LmsPluginDefinition(
            slug: 'tangible-lms',
            name: 'Tangible LMS',
            pluginFile: 'tangible-lms/tangible-lms.php',
            seederClass: self::class,
            processClass: TangibleLMSSeedingProcess::class,
            backgroundAction: 'seed_tangible_lms',
        ));
    }

    protected function getPostType(string $entity): string
    {
        return match ($entity) {
            'courses'      => self::PT_COURSE,
            'lessons'      => self::PT_LESSON,
            'quizzes'      => self::PT_QUIZ,
            'certificates' => self::PT_CERTIFICATE,
            default        => 'post',
        };
    }

    protected function getTitlePrefix(string $entity): string
    {
        return match ($entity) {
            'courses'      => 'Tangible Course',
            'lessons'      => 'Tangible Lesson',
            'quizzes'      => 'Tangible Quiz',
            'certificates' => 'Tangible Certificate',
            default        => parent::getTitlePrefix($entity),
        };
    }

    protected function getMetaFor(string $entity, array $context): array
    {
        return match ($entity) {
            'lessons' => ['_tgl_course_id' => (int) ($context['courseId'] ?? 0)],
            'quizzes' => ['_tgl_lesson_id' => (int) ($context['lessonId'] ?? 0)],
            default   => parent::getMetaFor($entity, $context),
        };
    }
}
