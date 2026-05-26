<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Brain\Monkey;

// Define WordPress constants used in tests
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}
if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}
if (!defined('TANGIBLE_POPULATER_VERSION')) {
    define('TANGIBLE_POPULATER_VERSION', '1.0.0');
}
if (!defined('TANGIBLE_POPULATER_FILE')) {
    define('TANGIBLE_POPULATER_FILE', dirname(__DIR__) . '/tangible-populater.php');
}
// Mark this as a development environment so DatabaseReset::isSafeEnvironment()
// returns true without needing to mock that method in every test.
if (!defined('WP_ENVIRONMENT_TYPE')) {
    define('WP_ENVIRONMENT_TYPE', 'development');
}

// ---------------------------------------------------------------------------
// WP_CLI stub
// The real WP_CLI class is only available in a CLI context. All methods are
// intentionally no-ops so CLI command code runs without throwing "Class not
// found". Tests needing to assert CLI output should use the stub directly.
// ---------------------------------------------------------------------------
if (!class_exists('WP_CLI')) {
    class WP_CLI
    {
        public static function confirm(string $message, array $assocArgs = []): void {}
        public static function success(string $message): void {}
        public static function error(string $message, bool $exit = true): void {}
        public static function warning(string $message): void {}
        public static function line(string $message = ''): void {}
        public static function log(string $message): void {}
    }
}

if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server
    {
        public const READABLE = 'GET';
        public const CREATABLE = 'POST';
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        public function __construct(
            private array $params = [],
            private array $urlParams = []
        ) {}

        public function get_param(string $key): mixed
        {
            return $this->params[$key] ?? $this->urlParams[$key] ?? null;
        }

        public function get_params(): array
        {
            return array_merge($this->params, $this->urlParams);
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response implements \ArrayAccess
    {
        public function __construct(private mixed $data) {}

        public function get_data(): mixed
        {
            return $this->data;
        }

        public function offsetExists(mixed $offset): bool
        {
            return is_array($this->data) && array_key_exists((string) $offset, $this->data);
        }

        public function offsetGet(mixed $offset): mixed
        {
            return is_array($this->data) ? ($this->data[(string) $offset] ?? null) : null;
        }

        public function offsetSet(mixed $offset, mixed $value): void
        {
            if (!is_array($this->data)) {
                $this->data = [];
            }
            if ($offset === null) {
                $this->data[] = $value;
                return;
            }
            $this->data[(string) $offset] = $value;
        }

        public function offsetUnset(mixed $offset): void
        {
            if (is_array($this->data)) {
                unset($this->data[(string) $offset]);
            }
        }
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(
            public string $code = '',
            public string $message = '',
            public array $data = []
        ) {}
    }
}

// Common WordPress test base with Brain\Monkey setup
abstract class WPTestCase extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        // Stub common WP i18n and escaping functions used across tests
        Monkey\Functions\stubs([
            '__',
            '_e',
            'esc_html',
            'esc_html__',
            'esc_attr',
            'esc_attr__',
            'esc_url',
            'wp_unslash',
            'sanitize_text_field',
            'absint',
        ]);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
