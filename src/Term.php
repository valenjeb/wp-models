<?php

declare(strict_types=1);

namespace Devly\WP\Models;

use Devly\Exceptions\ObjectNotFoundException;
use Devly\Utils\SmartObject;
use LogicException;
use RuntimeException;
use WP_Error;
use WP_Term;

use function is_int;
use function sprintf;

/**
 * @property-read int $ID
 * @property-read string $name
 * @property-read string $description
 * @property-read string $slug
 * @property-read int $count
 * @property-read int $parent_id
 * @property-read ?Term $parent
 */
class Term
{
    use SmartObject;

    public static string $taxonomy;

    protected WP_Term $coreObject;

    protected ?Term $termParent = null;

    /**
     * @param int|object|WP_Term $term
     *
     * @throws ObjectNotFoundException
     * @throws LogicException
     */
    public function __construct($term)
    {
        $term = get_term($term);

        if ($term instanceof WP_Error) {
            throw new ObjectNotFoundException($term->get_error_message());
        }

        if ($term === null) {
            throw new RuntimeException();
        }

        if (! isset(static::$taxonomy)) {
            static::$taxonomy = $term->taxonomy;
        }

        if (static::$taxonomy !== $term->taxonomy) {
            throw new LogicException(sprintf(
                'Term "%d" is a "%s" not a "%s". Make sure to use the correct model.',
                $term->term_id,
                static::$taxonomy,
                $term->taxonomy
            ));
        }

        $this->coreObject = $term;
    }

    public function getID(): int
    {
        return $this->getCoreObject()->term_id;
    }

    public function getName(): string
    {
        return $this->getCoreObject()->name;
    }

    public function getDescription(): string
    {
        return $this->getCoreObject()->description;
    }

    public function getSlug(): string
    {
        return $this->getCoreObject()->slug;
    }

    public function getCount(): int
    {
        return $this->getCoreObject()->count;
    }

    public function getParentId(): ?int
    {
        return $this->getCoreObject()->parent ?: null;
    }

    public function hasParent(): bool
    {
        return $this->getParentId() !== null;
    }

    public function getParent(): ?Term
    {
        if (! $this->hasParent()) {
            return null;
        }

        if (! isset($this->termParent)) {
            $this->termParent = new static($this->getParentId()); // @phpstan-ignore-line
        }

        return $this->termParent;
    }

    /**
     * Retrieves metadata for a term.
     *
     * @param string $key The meta key to retrieve.
     *
     * @return mixed
     */
    public function getField(string $key)
    {
        $value = apply_filters(Filter::TERM_PRE_GET_META_FIELD, null, $key, $this);

        if ($value === null) {
            $value = get_term_meta($this->getID(), $key, true);
        }

        return apply_filters(Filter::TERM_GET_META_FIELD . '/' . $key, $value, $this);
    }

    /**
     * Returns data for all meta fields
     *
     * @return array<string, mixed>
     */
    public function getFields(): array
    {
        return get_term_meta($this->getID());
    }

    /**
     * Set metadata for a term.
     *
     * @param string $key      The meta key
     * @param mixed  $value    The meta value. Must be serializable if non-scalar
     * @param mixed  $previous Previous value to check before updating. If specified,
     *                         only update existing metadata entries with this value.
     *                         Otherwise, update all entries. Default empty.
     */
    public function setField(string $key, $value, $previous = ''): bool
    {
        $result = apply_filters(Filter::TERM_PRE_SET_META_FIELD, null, $key, $value, $previous, $this);

        if ($result !== null) {
            return (bool) $result;
        }

        $result = update_term_meta($this->getID(), $key, $value, $previous);

        return $result === true || is_int($result);
    }

    /**
     * Removes metadata matching criteria from a term
     *
     * @param string $key   The meta key
     * @param mixed  $value Metadata value. If provided, rows will only be
     *                      removed that match the value. Must be serializable
     *                      if non-scalar. Default empty.
     */
    public function deleteField(string $key, $value = ''): bool
    {
        $result = apply_filters(Filter::TERM_PRE_DELETE_META_FIELD, null, $key, $value, $this);

        if ($result !== null) {
            return (bool) $result;
        }

        return delete_term_meta($this->getID(), $key, $value);
    }

    public function getCoreObject(): WP_Term
    {
        return $this->coreObject;
    }

    public function refreshCoreObject(): void
    {
        $this->termParent = null;
        $this->coreObject = get_term($this->getID());
    }
}
