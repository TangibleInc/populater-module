<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LearnDash;

use Tangible\Populater\LMS\LmsSettingsTab;
use Tangible\Populater\Seeding\SeedConfig;

/**
 * LearnDash integration for Tangible Populater.
 */
class LearnDashIntegration extends LmsSettingsTab
{
    public function getSlug(): string
    {
        return 'learndash';
    }

    public function getLabel(): string
    {
        return __('LearnDash', 'tangible-populater');
    }

    public function getDescription(): string
    {
        return __('Course → lessons → topics → quiz. One quiz per lesson (attached to a topic).', 'tangible-populater');
    }

    public function getDefaultConfig(): array
    {
        return [
            'courses'           => SeedConfig::DEFAULT_COURSES,
            'lessonsPerCourse'  => SeedConfig::DEFAULT_LESSONS_PER_COURSE,
            'topicsPerLesson'   => SeedConfig::DEFAULT_TOPICS_PER_LESSON,
            'quizzesPerLesson'  => SeedConfig::DEFAULT_QUIZZES_PER_LESSON,
            'questionsPerQuiz'  => SeedConfig::DEFAULT_QUESTIONS_PER_QUIZ,
            'users'             => SeedConfig::DEFAULT_USERS,
            'groups'            => SeedConfig::DEFAULT_GROUPS,
        ];
    }

    public function supportsGroups(): bool
    {
        return true;
    }

    public function renderFields(): void
    {
        ?>
        <tr class="tp-field-row" data-tp-tab="learndash">
            <th scope="row"><label for="tp-ld-lessons"><?php esc_html_e('Lessons per Course', 'tangible-populater'); ?></label></th>
            <td><input type="number" id="tp-ld-lessons" data-tp-field="lessons_per_course" value="<?php echo esc_attr((string) SeedConfig::DEFAULT_LESSONS_PER_COURSE); ?>" min="0" max="100" class="small-text"></td>
        </tr>
        <tr class="tp-field-row" data-tp-tab="learndash">
            <th scope="row"><label for="tp-ld-topics"><?php esc_html_e('Topics per Lesson', 'tangible-populater'); ?></label></th>
            <td><input type="number" id="tp-ld-topics" data-tp-field="topics_per_lesson" value="<?php echo esc_attr((string) SeedConfig::DEFAULT_TOPICS_PER_LESSON); ?>" min="0" max="50" class="small-text"></td>
        </tr>
        <tr class="tp-field-row" data-tp-tab="learndash">
            <th scope="row"><label for="tp-ld-quizzes"><?php esc_html_e('Quizzes per Lesson', 'tangible-populater'); ?></label></th>
            <td><input type="number" id="tp-ld-quizzes" data-tp-field="quizzes_per_lesson" value="<?php echo esc_attr((string) SeedConfig::DEFAULT_QUIZZES_PER_LESSON); ?>" min="0" max="50" class="small-text"></td>
        </tr>
        <?php
    }

    public static function register(): void
    {
        add_filter('tangible_populater_lms_tabs', function (array $tabs) {
            $tabs[self::getSlugStatic()] = new self();
            return $tabs;
        });
    }

    private static function getSlugStatic(): string
    {
        return 'learndash';
    }
}
