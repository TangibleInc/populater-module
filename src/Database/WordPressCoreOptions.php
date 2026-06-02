<?php

declare(strict_types=1);

namespace Tangible\Populater\Database;

/**
 * Identifies WordPress core options that should survive a content reset.
 *
 * Plugin, LMS, transient, and seeding options are removed; core site config,
 * active plugins, and theme settings are kept.
 */
final class WordPressCoreOptions
{
    /** @var list<string> */
    private const CORE_OPTIONS = [
        'siteurl',
        'home',
        'blogname',
        'blogdescription',
        'users_can_register',
        'admin_email',
        'start_of_week',
        'use_balanceTags',
        'use_smilies',
        'require_name_email',
        'comments_notify',
        'posts_per_rss',
        'rss_use_excerpt',
        'mailserver_url',
        'mailserver_login',
        'mailserver_pass',
        'mailserver_port',
        'default_category',
        'default_comment_status',
        'default_ping_status',
        'default_pingback_flag',
        'posts_per_page',
        'date_format',
        'time_format',
        'links_updated_date_format',
        'comment_moderation',
        'moderation_notify',
        'permalink_structure',
        'rewrite_rules',
        'hack_file',
        'blog_charset',
        'moderation_keys',
        'active_plugins',
        'category_base',
        'ping_sites',
        'comment_max_links',
        'gmt_offset',
        'default_email_category',
        'recently_edited',
        'template',
        'stylesheet',
        'template_root',
        'stylesheet_root',
        'current_theme',
        'comment_registration',
        'html_type',
        'use_trackback',
        'default_role',
        'db_version',
        'uploads_use_yearmonth_folders',
        'upload_path',
        'blog_public',
        'default_link_category',
        'show_on_front',
        'tag_base',
        'show_avatars',
        'avatar_rating',
        'upload_url_path',
        'thumbnail_size_w',
        'thumbnail_size_h',
        'thumbnail_crop',
        'medium_size_w',
        'medium_size_h',
        'avatar_default',
        'large_size_w',
        'large_size_h',
        'image_default_link_type',
        'image_default_size',
        'image_default_align',
        'close_comments_for_old_posts',
        'close_comments_days_old',
        'thread_comments',
        'thread_comments_depth',
        'page_comments',
        'comments_per_page',
        'default_comments_page',
        'comment_order',
        'sticky_posts',
        'widget_categories',
        'widget_text',
        'widget_rss',
        'uninstall_plugins',
        'timezone_string',
        'page_for_posts',
        'page_on_front',
        'default_post_format',
        'link_manager_enabled',
        'finished_splitting_shared_terms',
        'site_icon',
        'medium_large_size_w',
        'medium_large_size_h',
        'wp_page_for_privacy_policy',
        'show_comments_cookies_opt_in',
        'admin_email_lifespan',
        'disallowed_keys',
        'comment_previously_approved',
        'auto_plugin_theme_update_emails',
        'auto_update_core_dev',
        'auto_update_core_minor',
        'auto_update_core_major',
        'wp_force_deactivated_plugins',
        'sidebars_widgets',
        'cron',
        'can_compress_scripts',
        'WPLANG',
        'locale',
        'fresh_site',
        'user_count',
        'initial_db_version',
        'db_upgraded',
        'recovery_keys',
        'nav_menu_options',
        'widget_block',
        'https_detection_errors',
        'https_migration_required',
        'auto_core_update_notified',
        'category_children',
        'recently_activated',
        'deactivated_plugins',
        'finished_updating_comment_type',
        'wp_calendar_block_has_published_posts',
        'wp_attachment_pages_enabled',
    ];

    public static function shouldPreserve(string $optionName): bool
    {
        if (self::shouldAlwaysDelete($optionName)) {
            return false;
        }

        if (in_array($optionName, self::CORE_OPTIONS, true)) {
            return true;
        }

        if (str_starts_with($optionName, 'theme_mods_')) {
            return true;
        }

        if (str_starts_with($optionName, 'widget_')) {
            return true;
        }

        /**
         * @param bool   $preserve    Whether the option should be kept.
         * @param string $optionName  The wp_options option_name value.
         */
        return (bool) apply_filters('tangible_populater_preserve_option', false, $optionName);
    }

    private static function shouldAlwaysDelete(string $optionName): bool
    {
        if (str_starts_with($optionName, '_transient_')) {
            return true;
        }

        if (str_starts_with($optionName, '_site_transient_')) {
            return true;
        }

        if (str_starts_with($optionName, 'tangible_populater_')) {
            return true;
        }

        return str_contains($optionName, '_batch_');
    }
}
