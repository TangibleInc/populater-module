<?php
/**
 * Plugin Name:  Tangible Populator
 * Description:  Populate a WP site with courses, lessons, quizzes and users for LearnDash LMS, LifterLMS and Tangible LMS. Includes a database reset tool for development.
 * Version:      1.3.1
 * Requires PHP: 8.1
 * Author:       Tangible
 * License:      GPL-2.0-or-later
 * Text Domain:  tangible-populater
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('TANGIBLE_POPULATER_VERSION', '1.2.5');
define('TANGIBLE_POPULATER_FILE', __FILE__);

// Composer autoloader — covers src/ and any vendor dependencies.
$autoloader = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloader)) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p>'
            . esc_html__('Tangible Populator: please run `composer install` to install dependencies.', 'tangible-populater')
            . '</p></div>';
    });
    return;
}
require_once $autoloader;

// Boot the plugin after WordPress is loaded.
add_action('plugins_loaded', static function (): void {
    \Tangible\Populater\Plugin::getInstance()->init();
});
