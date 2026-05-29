<?php

declare(strict_types=1);

namespace Tangible\Populater\Admin;

use Tangible\Populater\Database\DatabaseReset;
use Tangible\Populater\PluginDetector;
use Tangible\Populater\Seeding\SeedingManager;

/**
 * Registers and renders the "Tangible Populator" admin settings page.
 *
 * The page:
 *  - Lists detected LMS plugins and their active state.
 *  - Lets the user configure counts (courses, lessons, quizzes, users).
 *  - Starts / cancels a seeding process via REST API (AJAX).
 *  - Shows a live progress bar and log tail.
 *  - Offers a database reset button (with safety checks).
 */
class SettingsPage
{
    private const PAGE_SLUG   = 'tangible-populater';
    private const MENU_TITLE  = 'Tangible Populator';
    private const PAGE_TITLE  = 'Tangible Populator';
    private const CAPABILITY  = 'manage_options';

    public function __construct(
        private readonly SeedingManager $seedingManager,
        private readonly DatabaseReset  $databaseReset,
        private readonly PluginDetector $pluginDetector,
    ) {}

    public function register(): void
    {
        add_menu_page(
            page_title: self::PAGE_TITLE,
            menu_title: self::MENU_TITLE,
            capability: self::CAPABILITY,
            menu_slug:  self::PAGE_SLUG,
            callback:   [$this, 'render'],
            icon_url:   'dashicons-database-add',
            position:   80,
        );

        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(string $hookSuffix): void
    {
        if ($hookSuffix !== 'toplevel_page_' . self::PAGE_SLUG) {
            return;
        }

        $baseUrl = plugin_dir_url(TANGIBLE_POPULATER_FILE);

        wp_enqueue_style(
            'tangible-populater-admin',
            $baseUrl . 'assets/admin.css',
            [],
            TANGIBLE_POPULATER_VERSION
        );

        wp_enqueue_script(
            'tangible-populater-admin',
            $baseUrl . 'assets/admin.js',
            ['wp-api-fetch'],
            TANGIBLE_POPULATER_VERSION,
            true
        );

        $activeProcess = $this->seedingManager->getActiveProcess();

        wp_localize_script('tangible-populater-admin', 'tangiblePopulater', [
            'restUrl'       => rest_url('tangible-populater/v1'),
            'nonce'         => wp_create_nonce('wp_rest'),
            'plugins'       => $this->seedingManager->getSupportedPlugins(),
            'activeProcess' => $activeProcess?->toArray(),
        ]);
    }

    public function render(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Insufficient permissions.', 'tangible-populater'));
        }

        $activePlugins  = $this->pluginDetector->getActivePlugins();
        $isSafeEnv      = $this->databaseReset->isSafeEnvironment();

        ?>
        <div class="wrap" id="tangible-populater-app">
            <h1><?php echo esc_html(self::PAGE_TITLE); ?></h1>

            <?php if (empty($activePlugins)) : ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('No supported LMS plugins are currently active. Please activate LearnDash LMS, LifterLMS, or Tangible LMS.', 'tangible-populater'); ?></p>
                </div>
            <?php endif; ?>

            <!-- Plugin selector & seeding configuration -->
            <div class="card" style="max-width:700px;">
                <h2><?php esc_html_e('Seed Content', 'tangible-populater'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="tp-plugin"><?php esc_html_e('LMS Plugin', 'tangible-populater'); ?></label></th>
                        <td>
                            <select id="tp-plugin" name="plugin">
                                <?php foreach ($this->seedingManager->getSupportedPlugins() as $p) : ?>
                                    <option value="<?php echo esc_attr($p['slug']); ?>" <?php disabled(!$p['active']); ?>>
                                        <?php echo esc_html($p['name']); ?><?php echo $p['active'] ? '' : ' (' . esc_html__('inactive', 'tangible-populater') . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tp-courses"><?php esc_html_e('Courses', 'tangible-populater'); ?></label></th>
                        <td><input type="number" id="tp-courses" name="courses" value="5" min="0" max="500" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tp-lessons"><?php esc_html_e('Lessons per Course', 'tangible-populater'); ?></label></th>
                        <td><input type="number" id="tp-lessons" name="lessons_per_course" value="5" min="0" max="100" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tp-quizzes"><?php esc_html_e('Quizzes per Lesson', 'tangible-populater'); ?></label></th>
                        <td><input type="number" id="tp-quizzes" name="quizzes_per_lesson" value="1" min="0" max="50" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tp-questions"><?php esc_html_e('Questions per Quiz', 'tangible-populater'); ?></label></th>
                        <td><input type="number" id="tp-questions" name="questions_per_quiz" value="3" min="0" max="100" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tp-users"><?php esc_html_e('Users', 'tangible-populater'); ?></label></th>
                        <td><input type="number" id="tp-users" name="users" value="10" min="0" max="1000" class="small-text"></td>
                    </tr>
                </table>

                <p>
                    <button type="button" id="tp-start-btn" class="button button-primary"><?php esc_html_e('Start Seeding', 'tangible-populater'); ?></button>
                    <button type="button" id="tp-cancel-btn" class="button" style="display:none;"><?php esc_html_e('Cancel', 'tangible-populater'); ?></button>
                </p>

                <!-- Progress bar -->
                <div id="tp-progress-wrap" style="display:none; margin-top:12px;">
                    <progress id="tp-progress-bar" value="0" max="100" style="width:100%;"></progress>
                    <p id="tp-progress-text"></p>
                </div>
            </div>

            <!-- Log viewer -->
            <div class="card" id="tp-log-card" style="max-width:700px; margin-top:16px; display:none;">
                <h2><?php esc_html_e('Logs', 'tangible-populater'); ?></h2>
                <pre id="tp-log-output" style="max-height:300px; overflow-y:auto; background:#f6f7f7; padding:8px; font-size:12px;"></pre>
            </div>

            <!-- Database reset -->
            <?php if ($isSafeEnv) : ?>
            <div class="card" style="max-width:700px; margin-top:16px; border-left:4px solid #d63638;">
                <h2 style="color:#d63638;"><?php esc_html_e('⚠ Reset Database', 'tangible-populater'); ?></h2>
                <p><?php esc_html_e('This removes seeded posts, non-admin users, plugin/LMS data, and non-core options. Administrator accounts, active plugins, and the active theme are preserved.', 'tangible-populater'); ?></p>
                <button type="button" id="tp-reset-btn" class="button" style="background:#d63638; color:#fff; border-color:#d63638;"><?php esc_html_e('Reset Database', 'tangible-populater'); ?></button>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
