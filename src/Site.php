<?php

declare(strict_types=1);

namespace Devly\WP\Models;

use Devly\Exceptions\ObjectNotFoundException;
use Devly\Utils\SmartObject;
use LogicException;
use WP_Site;

use function func_get_args;
use function implode;
use function is_array;
use function sprintf;

/**
 * @property-read string $ID
 * @property-read string $name The site title (set in Settings > General)
 * @property-read string $description The site tagline (set in Settings > General)
 * @property-read string $wpurl The WordPress address (URL) (set in Settings > General)
 * @property-read string $url The Site address (URL) (set in Settings > General)
 * @property-read string $admin_url
 * @property-read string $admin_email Admin email (set in Settings > General)
 * @property-read string $charset The "Encoding for pages and feeds"  (set in Settings > Reading)
 * @property-read string $version The current WordPress version
 * @property-read string $text_direction The text direction determined by the site's language.
 * @property-read string $language Language code for the current site
 * @property-read bool   $multisite Whether multisite is enabled.
 * @property-read string $body_class A list of the class names for the body element
 * @property-read string $network_id
 * @property-read Network $network
 */
class Site
{
    use SmartObject;

    protected ?WP_Site $coreObject = null;

    /**
     * @param int|WP_Site|null $site
     *
     * @throws LogicException
     * @throws ObjectNotFoundException
     */
    public function __construct($site = null)
    {
        if (! $this->isMultisite()) {
            if (! empty($site)) {
                $this->forceMultisite();
            }

            return;
        }

        if ($site instanceof WP_Site) {
            $site = $site->id;
        }

        $site = empty($site) ? get_current_blog_id() : $site;

        $_site = WP_Site::get_instance($site);

        if (! $_site) {
            throw new ObjectNotFoundException(sprintf('Site "%s" not found.', $site));
        }

        $this->coreObject = $_site;
    }

    /**
     * Get blog ID
     *
     * @throws LogicException if the current WordPress installation is not multisite.
     */
    public function getID(): int
    {
        $this->forceMultisite();

        return (int) $this->getCoreObject()->blog_id;
    }

    /**
     * The ID of the site's parent network
     *
     * @throws LogicException if the current WordPress installation is not multisite.
     */
    public function getNetworkId(): int
    {
        $this->forceMultisite();

        return (int) $this->getCoreObject()->site_id;
    }

    /**
     * Get the network object of the current site
     *
     * @throws LogicException if the current WordPress installation is not multisite.
     */
    public function getNetwork(): Network
    {
        $this->forceMultisite();

        $id = get_network($this->getNetworkId());

        return new Network($id);
    }

    /**
     * Retrieves information about the current site.
     */
    public function info(string $show = '', string $filter = 'raw'): ?string
    {
        if (! $this->isMultisite() || get_current_blog_id() === $this->getID()) {
            return get_bloginfo($show, $filter) ?: null;
        }

        switch_to_blog($this->getID());

        $return = get_bloginfo($show, $filter) ?: null;

        restore_current_blog();

        return $return;
    }

    public function getName(): string
    {
        return (string) $this->info('name');
    }

    public function getDescription(): string
    {
        return (string) $this->info('description');
    }

    public function getWpurl(): string
    {
        try {
            return get_site_url($this->getID());
        } catch (LogicException $e) {
            return get_site_url();
        }
    }

    public function getUrl(): string
    {
        if ($this->isMultisite()) {
            return $this->getCoreObject()->home;
        }

        return home_url();
    }

    public function getAdminUrl(): string
    {
        try {
            return get_admin_url($this->getID());
        } catch (LogicException $e) {
            return admin_url();
        }
    }

    public function getAdminEmail(): string
    {
        return (string) $this->info('admin_email');
    }

    public function getCharset(): string
    {
        return (string) $this->info('charset');
    }

    public function getVersion(): string
    {
        return (string) $this->info('version');
    }

    public function getTextDirection(): string
    {
        return is_rtl() ? 'rtl' : 'ltr';
    }

    public function getLanguage(): string
    {
        return (string) $this->info('language');
    }

    public function isMultisite(): bool
    {
        return is_multisite();
    }

    /**
     * Retrieves a list of the class names for the body element.
     *
     * @param string|string[] $class
     */
    public function getBodyClass($class = []): string
    {
        $class = is_array($class) ? $class : func_get_args();

        $classes = get_body_class($class);

        return implode(' ', $classes);
    }

    public function getTheme(): Theme
    {
        if ($this->isMultisite()) {
            if (get_current_blog_id() === $this->getID()) {
                return new Theme();
            }

            switch_to_blog($this->getID());
            $return = new Theme();
            restore_current_blog();

            return $return;
        }

        return new Theme();
    }

    /**
     * Adds a new option
     *
     * @param mixed $value
     */
    public function addOption(string $key, $value): bool
    {
        try {
            $this->forceMultisite();

            return add_blog_option($this->getID(), $key, $value);
        } catch (LogicException $e) {
        }

        return add_option($key, $value);
    }

    /**
     * Updates the value of an option that was already added
     *
     * @param mixed $value
     */
    public function updateOption(string $key, $value): bool
    {
        try {
            $this->forceMultisite();

            return update_blog_option($this->getID(), $key, $value);
        } catch (LogicException $e) {
            return update_option($key, $value);
        }
    }

    /**
     * Add or update option if exists.
     *
     * @param mixed $value
     */
    public function setOption(string $key, $value): bool
    {
        $result = apply_filters(Filter::SITE_PRE_SET_OPTION, null, $key, $value, $this);

        if ($result !== null) {
            return (bool) $result;
        }

        if ($this->getOption($key) === null) {
            return $this->addOption($key, $value);
        }

        return $this->updateOption($key, $value);
    }

    /**
     * @param mixed $default
     *
     * @return mixed
     */
    public function getOption(string $key, $default = null)
    {
        $value = apply_filters(Filter::SITE_PRE_GET_OPTION, null, $key, $default, $this);

        if ($value === null) {
            try {
                $this->forceMultisite();

                $value = get_blog_option($this->getID(), $key, $default);
            } catch (LogicException $e) {
                $value = get_option($key, $default);
            }
        }

        return apply_filters(Filter::SITE_GET_OPTION . '/' . $key, $value, $this);
    }

    public function deleteOption(string $key): bool
    {
        $result = apply_filters(Filter::SITE_PRE_DELETE_OPTION, null, $key, $this);

        if ($result !== null) {
            return $result;
        }

        try {
            $this->forceMultisite();

            $result = delete_blog_option($this->getID(), $key);
        } catch (LogicException $e) {
            $result = delete_option($key);
        }

        return $result;
    }

    /**
     * Ensure WordPress configured as multisite
     *
     * @throws LogicException if the current WordPress installation is not multisite.
     */
    protected function forceMultisite(): void
    {
        if (! isset($this->coreObject)) {
            throw new LogicException('The current WordPress installation is not multisite.');
        }
    }

    public function getCoreObject(): ?WP_Site
    {
        return $this->coreObject;
    }
}
