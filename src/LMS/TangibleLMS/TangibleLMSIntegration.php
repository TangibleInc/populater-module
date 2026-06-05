<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\TangibleLMS;

use Tangible\Populater\LMS\LmsSettingsTab;
use Tangible\Populater\Seeding\SeedConfig;

/**
 * Tangible LMS integration for Tangible Populater.
 */
class TangibleLMSIntegration extends LmsSettingsTab
{
    public function getSlug(): string
    {
        return 'tangible-lms';
    }

    public function getLabel(): string
    {
        return __('Tangible LMS', 'tangible-populater');
    }

    public function getDescription(): string
    {
        return __('Course → modules → lessons → quiz. One quiz per module.', 'tangible-populater');
    }

    public function getDefaultConfig(): array
    {
        return [
            'courses'          => SeedConfig::DEFAULT_COURSES,
            'modulesPerCourse' => SeedConfig::DEFAULT_MODULES_PER_COURSE,
            'lessonsPerCourse' => SeedConfig::DEFAULT_LESSONS_PER_COURSE,
            'quizzesPerModule' => SeedConfig::DEFAULT_QUIZZES_PER_SECTION,
            'questionsPerQuiz' => SeedConfig::DEFAULT_QUESTIONS_PER_QUIZ,
            'users'            => SeedConfig::DEFAULT_USERS,
            'groups'           => SeedConfig::DEFAULT_GROUPS,
        ];
    }

    public function supportsGroups(): bool
    {
        return false;
    }

    public function renderFields(): void
    {
        ?>
        <tr class="tp-field-row" data-tp-tab="tangible-lms">
            <th scope="row"><label for="tp-tgl-modules"><?php esc_html_e('Modules per Course', 'tangible-populater'); ?></label></th>
            <td><input type="number" id="tp-tgl-modules" data-tp-field="modules_per_course" value="<?php echo esc_attr((string) SeedConfig::DEFAULT_MODULES_PER_COURSE); ?>" min="0" max="50" class="small-text"></td>
        </tr>
        <tr class="tp-field-row" data-tp-tab="tangible-lms">
            <th scope="row"><label for="tp-tgl-lessons"><?php esc_html_e('Lessons per Course', 'tangible-populater'); ?></label></th>
            <td><input type="number" id="tp-tgl-lessons" data-tp-field="lessons_per_course" value="<?php echo esc_attr((string) SeedConfig::DEFAULT_LESSONS_PER_COURSE); ?>" min="0" max="100" class="small-text"></td>
        </tr>
        <tr class="tp-field-row" data-tp-tab="tangible-lms">
            <th scope="row"><label for="tp-tgl-quizzes"><?php esc_html_e('Quizzes per Module', 'tangible-populater'); ?></label></th>
            <td><input type="number" id="tp-tgl-quizzes" data-tp-field="quizzes_per_section" value="<?php echo esc_attr((string) SeedConfig::DEFAULT_QUIZZES_PER_SECTION); ?>" min="0" max="50" class="small-text"></td>
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
        return 'tangible-lms';
    }
}

