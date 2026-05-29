<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\TangibleLMS;

use Tangible\Populater\Registry\AbstractLmsPlugin;
use Tangible\Populater\Registry\LmsContainerEntity;
use Tangible\Populater\Registry\LmsEntitySchema;

class TangibleLmsPlugin extends AbstractLmsPlugin
{
    protected string $slug = 'tangible-lms';
    protected string $name = 'Tangible LMS';
    protected string $pluginFile = 'tangible-lms/tangible-lms.php';
    protected string $seederClass = TangibleLMSSeeder::class;
    protected string $backgroundAction = 'seed_tangible_lms';

    protected function defineEntitySchema(): LmsEntitySchema
    {
        return new LmsEntitySchema(
            postTypes: [
                'courses'      => 'tgl_course',
                'modules'      => 'tgl_module',
                'lessons'      => 'tgl_lesson',
                'quizzes'      => 'tgl_quiz',
                'questions'    => 'tgl_question',
                'certificates' => 'tgl_certificate',
            ],
            titlePrefixes: [
                'courses'      => 'Tangible Course',
                'modules'      => 'Tangible Module',
                'lessons'      => 'Tangible Lesson',
                'quizzes'      => 'Tangible Quiz',
                'questions'    => 'Tangible Question',
                'certificates' => 'Tangible Certificate',
            ],
            userPrefix: 'tangible_lms_user',
            metaMap: [
                'lessons'   => [
                    '_tgl_course_id' => 'courseId',
                    '_tgl_module_id' => 'moduleId',
                ],
                'quizzes'   => ['_tgl_lesson_id' => 'lessonId'],
                'questions' => ['_tgl_quiz_id' => 'quizId'],
            ],
            container: new LmsContainerEntity(
                entity: 'modules',
                cacheMetaKey: '_populater_tgl_module_ids',
                parentMetaKey: '_tgl_course_id',
            ),
        );
    }
}
