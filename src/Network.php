<?php

declare(strict_types=1);

namespace Devly\WP\Models;

use Devly\Exceptions\ObjectNotFoundException;
use Nette\SmartObject;
use RuntimeException;
use WP_Network;
use WP_Site;

use function array_map;
use function sprintf;

/**
 * @property-read int $ID
 * @property-read array $sites
 * @property-read WP_Network $coreObject
 */
class Network
{
    use SmartObject;

    protected WP_Network $coreObject;

    /**
     * @param int|WP_Network|null $network Network to retrieve. Default is the current network.
     *
     * @throws ObjectNotFoundException if the requested network not found.
     */
    public function __construct($network = null)
    {
        if (! is_multisite()) {
            throw new RuntimeException('The current WordPress installation is not multisite.');
        }

        $_network = get_network($network);

        if ($_network === null) {
            throw new ObjectNotFoundException(sprintf('Network "%d" not found.', (int) $network));
        }

        $this->coreObject = $_network;
    }

    public function getID(): int
    {
        return $this->getCoreObject()->id;
    }

    /**
     * Retrieves a list of sites matching requested arguments.
     *
     * @param array<string, mixed> $args Array or string of arguments. See WP_Site_Query::__construct()
     *                                   for information on accepted arguments.
     *
     * @return Site[]|int[]|WP_Site[]
     */
    public function getSites(array $args = [], bool $format = true): array
    {
        $sites = get_sites($args);
        if ($format === false || isset($args['fields']) && $args['fields'] === 'ids') {
            return $sites;
        }

        return array_map(static fn ($site) => new Site($site), $sites);
    }

    /**
     * Retrieve an option value for the current network.
     *
     * @param string $key     Name of the option to retrieve. Expected to not be SQL-escaped.
     * @param mixed  $default Value to return if the option doesn't exist.
     *
     * @return mixed
     */
    public function getOption(string $key, $default = null)
    {
        $value = apply_filters(Filter::NETWORK_PRE_GET_OPTION, null, $key, $default, $this);

        if ($value === null) {
            $value = get_network_option($this->ID, $key, $default);
        }

        return apply_filters(Filter::NETWORK_GET_OPTION . '/' . $key, $value, $this);
    }

    /**
     * Updates the value of an option that was already added for the current network.
     *
     * @param string $key   Name of the option. Expected to not be SQL-escaped.
     * @param mixed  $value Option value. Expected to not be SQL-escaped.
     */
    public function updateOption(string $key, $value): bool
    {
        $result = apply_filters(Filter::NETWORK_PRE_UPDATE_OPTION, true, $key, $value, $this);

        if ($result === false) {
            return false;
        }

        $value = apply_filters(Filter::NETWORK_UPDATE_OPTION . '/' . $key, $value, $this);

        return update_network_option($this->ID, $key, $value);
    }

    /**
     * Adds a new option for the current network.
     *
     * Existing options will not be updated.
     *
     * @param string $key   Name of the option to add. Expected to not be SQL-escaped.
     * @param mixed  $value Option value. Expected to not be SQL-escaped.
     */
    public function addOption(string $key, $value): bool
    {
        $result = apply_filters(Filter::NETWORK_PRE_ADD_OPTION, true, $value, $key);

        if ($result === false) {
            return false;
        }

        $value = apply_filters(Filter::NETWORK_ADD_OPTION . '/' . $key, $value);

        return add_network_option($this->ID, $key, $value);
    }

    /**
     * Adds a new option for the current network.
     *
     * Existing options will not be updated.
     *
     * @param string $key Name of the option to retrieve. Expected to not be SQL-escaped.
     */
    public function deleteOption(string $key): bool
    {
        $result = apply_filters(Filter::NETWORK_PRE_DELETE, true, $key);

        if ($result === false) {
            return false;
        }

        return delete_network_option($this->getID(), $key);
    }

    /** @param mixed $value */
    public function setOption(string $key, $value): bool
    {
        $result = apply_filters(Filter::NETWORK_PRE_SET_OPTION, true, $key, $value, $this);

        if ($result === false) {
            return false;
        }

        if ($this->getOption($key) === null) {
            return $this->addOption($key, $value);
        }

        return $this->updateOption($key, $value);
    }

    public function getCoreObject(): WP_Network
    {
        return $this->coreObject;
    }

    public function refreshCoreObject(): void
    {
        $this->coreObject = get_network($this->getID());
    }
}
