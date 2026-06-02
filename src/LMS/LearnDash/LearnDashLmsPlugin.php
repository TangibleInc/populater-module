<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LearnDash;

use Tangible\Populater\Registry\AbstractLmsPlugin;
use Tangible\Populater\Registry\LmsEntitySchema;

class LearnDashLmsPlugin extends AbstractLmsPlugin
{
    protected string $slug = 'learndash';
    protected string $name = 'LearnDash LMS';
    protected string $pluginFile = 'sfwd-lms/sfwd_lms.php';
    protected string $seederClass = LearnDashSeeder::class;
    protected string $backgroundAction = 'seed_learndash';

    protected function defineEntitySchema(): LmsEntitySchema
    {
        return new LmsEntitySchema(
            postTypes: [
                'courses'      => 'sfwd-courses',
                'lessons'      => 'sfwd-lessons',
                'topics'       => 'sfwd-topic',
                'quizzes'      => 'sfwd-quiz',
                'questions'    => 'sfwd-question',
                'certificates' => 'sfwd-certificates',
                'groups'       => 'groups',
            ],
            titlePrefixes: [
                'courses'      => 'LearnDash Course',
                'lessons'      => 'LearnDash Lesson',
                'topics'       => 'LearnDash Topic',
                'quizzes'      => 'LearnDash Quiz',
                'questions'    => 'LearnDash Question',
                'certificates' => 'LearnDash Certificate',
                'groups'       => 'LearnDash Group',
            ],
            userPrefix: 'learndash_user',
            metaMap: [
                'lessons'   => ['course_id' => 'courseId'],
                'topics'    => ['course_id' => 'courseId', 'lesson_id' => 'lessonId'],
                'quizzes'   => ['lesson_id' => 'lessonId', 'course_id' => 'courseId', 'topic_id' => 'topicId'],
                'questions' => ['quiz_id' => 'quizId'],
            ],
            quizParentEntity: 'topics',
        );
    }
}
