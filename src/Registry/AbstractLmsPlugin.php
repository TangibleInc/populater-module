<?php

declare(strict_types=1);

namespace Tangible\Populater\Registry;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Seeding\AbstractSeeding;

/**
 * Base LMS registration: subclasses set properties and hook into
 * {@see AbstractLmsPlugin::FILTER} on construction.
 */
abstract class AbstractLmsPlugin
{
    public const FILTER = 'tangible-populator-lms-plugins';

    /** @var array<string, AbstractLmsPlugin> */
    private static array $registered = [];

    protected string $slug;
    protected string $name;
    protected string $pluginFile;
    /** @var class-string<AbstractSeeder> */
    protected string $seederClass;
    /** @var class-string<AbstractSeeding> */
    protected string $processClass;
    protected string $backgroundAction;

    public function __construct()
    {
        $this->register([]);
        add_filter(self::FILTER, [$this, 'register'], 10, 1);
    }

    /**
     * @param array<string, AbstractLmsPlugin> $plugins
     * @return array<string, AbstractLmsPlugin>
     */
    public function register(array $plugins): array
    {
        $plugins[$this->getSlug()] = $this;
        self::$registered[$this->getSlug()] = $this;

        return $plugins;
    }

    /**
     * Plugins registered via {@see register()} (used when filter callbacks are not executed, e.g. unit tests).
     *
     * @return array<string, AbstractLmsPlugin>
     */
    public static function getRegistered(): array
    {
        return self::$registered;
    }

    public static function clearRegistered(): void
    {
        self::$registered = [];
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPluginFile(): string
    {
        return $this->pluginFile;
    }

    public function getBackgroundAction(): string
    {
        return $this->backgroundAction;
    }

    public function isActive(): bool
    {
        return is_plugin_active($this->pluginFile);
    }

    public function createSeeder(): AbstractSeeder
    {
        $class = $this->seederClass;

        return new $class($this);
    }

    public function createProcess(AbstractSeeder $seeder): AbstractSeeding
    {
        $class = $this->processClass;

        return new $class($seeder);
    }
}
