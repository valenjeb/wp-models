<?php

declare(strict_types=1);

namespace Devly\WP\Models;

use Devly\Exceptions\ObjectNotFoundException;
use InvalidArgumentException;
use Nette\SmartObject;
use RuntimeException;
use WP_Theme;

use function is_array;
use function is_string;
use function sprintf;

/**
 * @property-read string $name The theme name
 * @property-read string $description The theme description
 * @property-read string $textDomain The theme text domain
 * @property-read string $version
 * @property-read string $slug
 * @property-read string $url
 * @property-read string $path
 * @property-read ?string $screenshot The main screenshot file for the theme.
 * @property-read ?string $template The directory name of the theme's "stylesheet" files, inside the theme root.
 * @property-read self $parent Reference to the parent theme.
 * @property-read bool $active Whether the theme is active
 */
class Theme
{
    use SmartObject;

    protected WP_Theme $coreObject;
    protected ?Theme $parentTheme = null;

    /**
     * @param WP_Theme|string|null $theme
     *
     * @throws ObjectNotFoundException
     */
    public function __construct($theme = null)
    {
        $_theme = wp_get_theme($theme);

        if (! $_theme->exists()) {
            throw new ObjectNotFoundException(sprintf('Theme "%s" does not exist', $theme));
        }

        $this->coreObject = $_theme;
    }

    /**
     * Gets a theme header, formatted and translated for display
     *
     * @param string $header    Theme header. Name, Description, Author, Version,
     *                          ThemeURI, AuthorURI, Status, Tags.
     * @param bool   $markup    Whether to mark up the header. Defaults to true.
     * @param bool   $translate Whether to translate the header. Defaults to true.
     *
     * @return array<string, mixed>|false|string Processed header. An array for Tags if `$markup`
     *                                           is false, string otherwise. False on failure.
     */
    public function display(string $header, bool $markup = true, bool $translate = true)
    {
        return $this->getCoreObject()->display($header, $markup, $translate);
    }

    /**
     * Gets a raw, un-formatted theme header
     *
     * The header is sanitized, but is not translated, and is not marked up for
     * display. To get a theme header for display, use the display() method.
     *
     * @param string $header Theme header. Name, Description, Author, Version,
     *                       ThemeURI, AuthorURI, Status, Tags.
     *
     * @return array<string, mixed>|false|string String or array (for Tags header)
     *                                           on success, false on failure.
     */
    public function get(string $header)
    {
        return $this->getCoreObject()->get($header);
    }

    /**
     * Retrieves the theme name
     */
    public function getName(bool $formatted = false): string
    {
        if (! $formatted) {
            return $this->get('Name');
        }

        return $this->display('Name');
    }

    /**
     * Retrieves the theme description
     */
    public function getDescription(bool $formatted = false): string
    {
        if (! $formatted) {
            return $this->get('Description');
        }

        return $this->display('Description');
    }

    /**
     * Retrieves the theme version
     */
    public function getVersion(): string
    {
        return $this->get('Version');
    }

    /**
     * Retrieves the theme text domain
     */
    public function getTextDomain(): string
    {
        return $this->get('TextDomain');
    }

    /**
     * Returns the directory name of the theme's "stylesheet" files, inside the theme root.
     *
     * In the case of a child theme, this is directory name of the child
     * theme. Otherwise, is the same as getTemplate()
     */
    public function getSlug(): string
    {
        return $this->getCoreObject()->get_stylesheet();
    }

    /**
     * Returns the URL to the directory of a theme's “stylesheet” files.
     *
     * In the case of a child theme, this is the URL to the directory
     * of the child theme's files.
     */
    public function getUrl(): string
    {
        return $this->getCoreObject()->get_stylesheet_directory_uri();
    }

    /**
     * Returns the absolute path to the directory of the theme's “stylesheet” files.
     *
     * In the case of a child theme, this is the absolute path
     * to the directory of the child theme's files.
     */
    public function getPath(): string
    {
        return $this->getCoreObject()->get_stylesheet_directory();
    }

    /**
     * Returns the directory name of the theme's "template" files, inside the theme root.
     *
     * In the case of a child theme, this is the directory name of the parent theme.
     */
    public function getTemplate(): string
    {
        return $this->getCoreObject()->get_template();
    }

    /**
     * Returns the main screenshot file for the theme.
     *
     * @param bool $absolute Whether to return 'relative' or an absolute
     *                       URI. Defaults to absolute URI.
     *
     * @return string|null Screenshot file. Null if the theme does
     *                     not have a screenshot.
     */
    public function getScreenshot(bool $absolute = true): ?string
    {
        return $this->getCoreObject()->get_screenshot($absolute ? 'uri' : 'relative') ?: null;
    }

    public function isActive(): bool
    {
        return get_stylesheet() === $this->getSlug();
    }

    /** Checks whether the theme is a child theme */
    public function hasParent(): bool
    {
        return $this->getCoreObject()->parent() !== false;
    }

    /**
     * Returns reference to the parent theme.
     *
     * @throws RuntimeException if the theme is not a child theme.
     */
    public function getParent(): self
    {
        if (! $this->hasParent()) {
            throw new RuntimeException(sprintf('Theme "%s" is not a child theme.', $this->getName()));
        }

        if (! isset($this->parentTheme)) {
            $this->parentTheme = new self($this->getCoreObject()->parent());
        }

        return $this->parentTheme;
    }

    /**
     * Updates theme modification value.
     *
     * @param string $name  Theme modification name.
     * @param mixed  $value Theme modification value.
     */
    public function setOption(string $name, $value): bool
    {
        $mods     = $this->getOptions();
        $oldValue = $mods[$name] ?? false;

        $result = apply_filters(Filter::THEME_PRE_SET_OPTION, null, $name, $value, $oldValue, $this);

        if ($result !== null) {
            return (bool) $result;
        }

        $value = apply_filters('pre_set_theme_mod_' . $name, $value, $oldValue);

        $mods[$name] = $value;

        return update_option('theme_mods_' . $this->getSlug(), $mods);
    }

    /** Removes theme modification filed */
    public function deleteOption(string $name): bool
    {
        $result = apply_filters(Filter::THEME_PRE_DELETE_OPTION, null, $name, $this);

        if ($result !== null) {
            return (bool) $result;
        }

        $mods = $this->getOptions();

        if (! isset($mods[$name])) {
            return false;
        }

        unset($mods[$name]);

        if (empty($mods)) {
            return $this->deleteOptions();
        }

        return update_option('theme_mods_' . $this->getSlug(), $mods);
    }

    /**
     * Retrieves theme modification value.
     *
     * @param string $name    Theme modification name.
     * @param mixed  $default Theme modification default value. Default false.
     *
     * @return mixed
     */
    public function getOption(string $name, $default = false)
    {
        $value = apply_filters(Filter::THEME_PRE_GET_OPTION, null, $name, $default, $this);

        if ($value === null) {
            $mods = $this->getOptions();

            $value = $mods[$name] ?? $default;
        }

        $value = apply_filters(Filter::THEME_GET_OPTION . '/' . $name, $value, $this);

        return apply_filters('theme_mod_' . $name, $value);
    }

    /**
     * Retrieves all theme modifications.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        $mods = get_option('theme_mods_' . $this->getSlug());

        if (! is_array($mods)) {
            return [];
        }

        return $mods;
    }

    /**
     * Remove all theme modifications.
     */
    public function deleteOptions(): bool
    {
        return delete_option('theme_mods_' . $this->getSlug());
    }

    /**
     * @param string|self $theme Theme name or an instance of Theme object
     *
     * @throws ObjectNotFoundException if no theme is found with the given name.
     */
    public function importOptions($theme): bool
    {
        if (is_string($theme)) {
            $theme = new self($theme);
        }

        if (! $theme instanceof Theme) {
            throw new InvalidArgumentException(
                'The #1 argument "$theme" must be a theme name or an instance of ' . self::class
            );
        }

        $options     = $theme->getOptions();
        $selfOptions = $this->getOptions();

        return update_option('theme_mods_' . $this->getSlug(), wp_parse_args($options, $selfOptions));
    }

    public function getCoreObject(): WP_Theme
    {
        return $this->coreObject;
    }
}
