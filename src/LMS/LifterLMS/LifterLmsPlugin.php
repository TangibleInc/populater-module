<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LifterLMS;

use Tangible\Populater\Registry\AbstractLmsPlugin;
use Tangible\Populater\Registry\LmsContainerEntity;
use Tangible\Populater\Registry\LmsEntitySchema;

class LifterLmsPlugin extends AbstractLmsPlugin
{
    protected string $slug = 'lifterlms';
    protected string $name = 'LifterLMS';
    protected string $pluginFile = 'lifterlms/lifterlms.php';
    protected string $seederClass = LifterLMSSeeder::class;
    protected string $backgroundAction = 'seed_lifterlms';

    protected function defineEntitySchema(): LmsEntitySchema
    {
        return new LmsEntitySchema(
            postTypes: [
                'courses'      => 'course',
                'lessons'      => 'lesson',
                'sections'     => 'section',
                'quizzes'      => 'llms_quiz',
                'questions'    => 'llms_question',
                'certificates' => 'llms_certificate',
                'groups'       => 'llms_group',
            ],
            titlePrefixes: [
                'courses'      => 'LifterLMS Course',
                'lessons'      => 'LifterLMS Lesson',
                'sections'     => 'LifterLMS Section',
                'quizzes'      => 'LifterLMS Quiz',
                'questions'    => 'LifterLMS Question',
                'certificates' => 'LifterLMS Certificate',
                'groups'       => 'LifterLMS Group',
            ],
            userPrefix: 'lifterlms_user',
            metaMap: [
                'lessons'   => ['_llms_parent_course' => 'courseId'],
                'quizzes'   => ['_llms_lesson_id' => 'lessonId'],
                'questions' => ['_llms_parent_id' => 'quizId'],
            ],
            container: new LmsContainerEntity(
                entity: 'sections',
                cacheMetaKey: '_populater_llms_section_ids',
                parentMetaKey: '_llms_parent_course',
                lessonParentMetaKey: '_llms_parent_section',
            ),
            quizParentEntity: 'sections',
        );
    }
}
