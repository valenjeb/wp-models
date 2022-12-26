<?php

declare(strict_types=1);

namespace Devly\WP\Models;

use Devly\Exceptions\ObjectNotFoundException;
use Devly\Utils\SmartObject;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;
use WP_Error;
use WP_User;

use function collect;
use function sprintf;

/**
 * @property-read int $ID
 * @property-read string $display_name
 * @property-read string $username The user's login username.
 * @property-read string $nicename The URL-friendly username
 * @property-read string $email The user's email address
 * @property-read string $first_name
 * @property-read string $last_name
 * @property-read string $password
 * @property-read string $bio
 * @property-read string $website
 * @property-read string $registered_at
 * @property-read string $archive_url The URL to the author page
 * @property-read int $post_count The number of posts user has written.
 * @property-read string[] $roles The roles the user is part of
 */
class User
{
    use SmartObject;

    protected WP_User $coreObject;

    /**
     * @param int|WP_User $id
     *
     * @throws ObjectNotFoundException
     */
    public function __construct($id)
    {
        if (empty($id)) {
            throw new InvalidArgumentException(sprintf(
                'The %s() parameter $user must be a user ID or a WP_Ussr instance.',
                __METHOD__
            ));
        }

        $user = $id instanceof WP_User ? $id : get_user_by('id', $id);

        if ($user === false) {
            throw new ObjectNotFoundException(sprintf('User id "%s" does not exist', $id));
        }

        $this->coreObject = $user;
    }

    public function getID(): int
    {
        return $this->getCoreObject()->ID;
    }

    /** Retrieves the user's display name. */
    public function getDisplayName(): string
    {
        return $this->getCoreObject()->display_name;
    }

    /** Retrieves the user's login username. */
    public function getUsername(): string
    {
        return $this->getCoreObject()->user_login;
    }

    /** Retrieves the URL-friendly username */
    public function getNicename(): string
    {
        return $this->getCoreObject()->user_nicename;
    }

    /** Retrieves the user's email address */
    public function getEmail(): string
    {
        return $this->getCoreObject()->user_email;
    }

    /** Retrieves the user's first name. */
    public function getFirstName(): string
    {
        return $this->getCoreObject()->first_name;
    }

    /** Retrieves the user's last name. */
    public function getLastName(): string
    {
        return $this->getCoreObject()->last_name;
    }

    /** Retrieves the user's password. */
    public function getPassword(): string
    {
        return $this->getCoreObject()->user_pass;
    }

    /** Retrieves the user's description. */
    public function getBio(): string
    {
        return $this->getCoreObject()->user_description;
    }

    public function getWebsite(): string
    {
        return $this->getCoreObject()->user_url;
    }

    public function getDateRegistered(): string
    {
        return $this->getCoreObject()->user_registered;
    }

    /** Retrieves the URL to the author page. */
    public function getArchiveUrl(): string
    {
        return get_author_posts_url($this->getID());
    }

    /**
     * Return number of posts user has written.
     *
     * @param string|string[] $typeType
     */
    public function getPostCount($typeType = 'post', bool $publicOnly = true): int
    {
        return (int) count_user_posts($this->getID(), $typeType, $publicOnly);
    }

    /**
     * Retrieve user meta field.
     *
     * @param string $name    The meta key to retrieve.
     * @param mixed  $default Default value to return.
     *
     * @return mixed
     */
    public function getField(string $name, $default = null)
    {
        $value = apply_filters(Filter::USER_PRE_GET_META_FIELD, null, $name, $this);

        if ($value === null) {
            $value = get_user_meta($this->getID(), $name, true);
        }

        if (empty($value)) {
            $value = $default;
        }

        return apply_filters(Filter::USER_GET_META_FIELD . '/' . $name, $value, $this);
    }

    /**
     * Retrieves all user meta field.
     *
     * @return array<string, mixed>
     */
    public function getFields(): array
    {
        return get_user_meta($this->getID());
    }

    /**
     * @param string $key      The field key name
     * @param mixed  $value    The field value. Must be serializable if non-scalar.
     * @param mixed  $previous Previous value to check before updating. If specified,
     *                         only update existing metadata entries with this value.
     *                         Otherwise, update all entries. Default empty.
     */
    public function setField(string $key, $value, $previous = ''): bool
    {
        $result = apply_filters(Filter::USER_PRE_SET_META_FIELD, null, $key, $value, $previous, $this);

        if ($result !== null) {
            return (bool) $result;
        }

        $result = update_user_meta($this->getID(), $key, $value, $previous);

        return $result !== false;
    }

    /**
     * Removes user metadata matching criteria.
     *
     * @param string $key   The field key name
     * @param mixed  $value If provided, rows will only be removed that match the value.
     *                      Must be serializable if non-scalar. Default empty.
     */
    public function deleteField(string $key, $value = ''): bool
    {
        $result = apply_filters(Filter::USER_PRE_DELETE_META_FIELD, null, $key, $value, $this);

        if ($result !== null) {
            return (bool) $result;
        }

        return delete_user_meta($this->getID(), $key, $value);
    }

    /**
     * Retrieve user option that can be either per Site or per Network.
     *
     * @return false|mixed
     */
    public function getOption(string $name)
    {
        $value = apply_filters(Filter::USER_PRE_GET_OPTION, null, $name, $this);

        if ($value === null) {
            $value = get_user_option($name, $this->getID());
        }

        return apply_filters(Filter::USER_GET_OPTION . '/' . $name, $value, $this);
    }

    /**
     * Set user option with global blog capability
     *
     * @param mixed $value
     */
    public function setOption(string $name, $value): bool
    {
        $result = apply_filters(Filter::USER_PRE_SET_OPTION, null, $name, $value, $this);

        if ($result !== null) {
            return (bool) $result;
        }

        $result = update_user_option($this->getID(), $name, $value);

        return $result !== false;
    }

    /**
     * Deletes user option with global blog capability.
     *
     * @param string $key    User option name.
     * @param bool   $global Whether option name is global or blog specific. Default false (blog specific).
     */
    public function deleteOption(string $key, bool $global = false): bool
    {
        $result = apply_filters(Filter::USER_PRE_DELETE_OPTION, null, $key, $global, $this);

        if ($result !== null) {
            return (bool) $result;
        }

        return delete_user_option($this->getID(), $key, $global);
    }

    /**
     * Returns whether the user has the specified capability.
     *
     * @param string $capability The capability name
     * @param mixed  ...$args    Optional further parameters, typically starting with an
     *                           object ID.
     */
    public function can(string $capability, ...$args): bool
    {
        return @user_can($this->getCoreObject(), $capability, ...$args);
    }

    /**
     * Retrieves the roles the user is part of
     *
     * @return string[]
     */
    public function getRoles(): array
    {
        return $this->getCoreObject()->roles;
    }

    /**
     * Sets the role of the user
     *
     * This will remove the previous roles of the user and
     * assign the user the new one. You can set the role to
     * an empty string, and it will remove all the roles
     * from the user.
     */
    public function setRole(string $role): void
    {
        $this->getCoreObject()->set_role($role);
    }

    /**
     * Add role to user.
     */
    public function addRole(string $role): void
    {
        $this->getCoreObject()->add_role($role);
    }

    /** Remove role from user */
    public function removeRole(string $role): void
    {
        $this->getCoreObject()->remove_role($role);
    }

    /**
     * Inserts a user into the database.
     *
     * @param array<string, mixed>|object|WP_User $options An array, object, or WP_User
     *                                                     object of user data arguments.
     *
     * @throws RuntimeException
     */
    public static function insert($options): self
    {
        $user = @wp_insert_user($options);

        if ($user instanceof WP_Error) {
            throw new RuntimeException($user->get_error_message());
        }

        return new self($user);
    }

    /**
     * Provides a simpler way of inserting a user into the database
     *
     * For more complex user creation use User::insert() to specify more information.
     *
     * @throws RuntimeException
     */
    public static function create(string $username, string $password, string $email = ''): self
    {
        $user = @wp_create_user($username, $password, $email);

        if ($user instanceof WP_Error) {
            throw new RuntimeException($user->get_error_message());
        }

        return new self($user);
    }

    public static function delete(int $id, ?int $reassign = null): bool
    {
        return wp_delete_user($id, $reassign);
    }

    /**
     * Retrieves all users
     *
     * @param bool $format If true returns array of User objects. Default false to return
     *                     array of all WP_User objects.
     *
     * @return Collection<WP_User|self>
     */
    public static function all(bool $format = true): Collection
    {
        $users = get_users();

        $collection = collect($users);

        if (! $format) {
            return $collection;
        }

        $className = static::class;

        return $collection->map(static fn (WP_User $user) => new $className($user));
    }

    /**
     * Retrieve user by email address
     *
     * @return self|WP_User
     *
     * @throws ObjectNotFoundException
     */
    public static function findByEmail(string $email, bool $format = true)
    {
        $user = get_user_by('email', $email);

        if ($user === false) {
            throw new ObjectNotFoundException(sprintf('No user found with the mail "%s"', $email));
        }

        if ($format) {
            return new self($user);
        }

        return $user;
    }

    /**
     * Retrieve the current user object.
     *
     * @return self|WP_User
     *
     * @throws RuntimeException if no user is logged in.
     */
    public static function getCurrent(bool $format = true)
    {
        $id = get_current_user_id();
        if ($id === 0) {
            throw new RuntimeException('No user is logged in');
        }

        if ($format) {
            return new self($id);
        }

        return wp_get_current_user();
    }

    /** Check whether user exists by a user ID */
    public static function exists(int $id): bool
    {
        try {
            new self($id);
        } catch (ObjectNotFoundException $e) {
            return false;
        }

        return true;
    }

    public function getCoreObject(): WP_User
    {
        return $this->coreObject;
    }
}
