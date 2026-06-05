<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LifterLMS;

use Tangible\Populater\LMS\LmsSettingsTab;
use Tangible\Populater\Seeding\SeedConfig;
use Tangible\Populater\Support\LmsGroupsCapability;

/**
 * LifterLMS integration for Tangible Populater.
 */
class LifterLMSIntegration extends LmsSettingsTab
{
    public function getSlug(): string
    {
        return 'lifterlms';
    }

    public function getLabel(): string
    {
        return __('LifterLMS', 'tangible-populater');
    }

    public function getDescription(): string
    {
        return __('Course → sections → lessons → quiz. One quiz per section.', 'tangible-populater');
    }

    public function getDefaultConfig(): array
    {
        return [
            'courses'           => SeedConfig::DEFAULT_COURSES,
            'sectionsPerCourse' => SeedConfig::DEFAULT_SECTIONS_PER_COURSE,
            'lessonsPerSection' => SeedConfig::DEFAULT_LESSONS_PER_SECTION,
            'quizzesPerSection' => SeedConfig::DEFAULT_QUIZZES_PER_SECTION,
            'questionsPerQuiz'  => SeedConfig::DEFAULT_QUESTIONS_PER_QUIZ,
            'users'             => SeedConfig::DEFAULT_USERS,
            'groups'            => SeedConfig::DEFAULT_GROUPS,
        ];
    }

    public function supportsGroups(): bool
    {
        return LmsGroupsCapability::supports('lifterlms');
    }

    public function renderFields(): void
    {
        ?>
        <tr class="tp-field-row" data-tp-tab="lifterlms">
            <th scope="row"><label for="tp-llms-sections"><?php esc_html_e('Sections per Course', 'tangible-populater'); ?></label></th>
            <td><input type="number" id="tp-llms-sections" data-tp-field="sections_per_course" value="<?php echo esc_attr((string) SeedConfig::DEFAULT_SECTIONS_PER_COURSE); ?>" min="0" max="50" class="small-text"></td>
        </tr>
        <tr class="tp-field-row" data-tp-tab="lifterlms">
            <th scope="row"><label for="tp-llms-lessons"><?php esc_html_e('Lessons per Section', 'tangible-populater'); ?></label></th>
            <td><input type="number" id="tp-llms-lessons" data-tp-field="lessons_per_section" value="<?php echo esc_attr((string) SeedConfig::DEFAULT_LESSONS_PER_SECTION); ?>" min="0" max="100" class="small-text"></td>
        </tr>
        <tr class="tp-field-row" data-tp-tab="lifterlms">
            <th scope="row"><label for="tp-llms-quizzes"><?php esc_html_e('Quizzes per Section', 'tangible-populater'); ?></label></th>
            <td><input type="number" id="tp-llms-quizzes" data-tp-field="quizzes_per_section" value="<?php echo esc_attr((string) SeedConfig::DEFAULT_QUIZZES_PER_SECTION); ?>" min="0" max="50" class="small-text"></td>
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
        return 'lifterlms';
    }
}
