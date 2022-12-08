<?php

declare(strict_types=1);

namespace Devly\WP\Models;

use Devly\Exceptions\ObjectNotFoundException;
use Devly\Utils\SmartObject;
use Devly\WP\Query\TermQuery;
use Illuminate\Support\Collection;
use LogicException;
use RuntimeException;
use WP_Error;
use WP_Term;

use function is_int;
use function sprintf;
use function str_replace;
use function ucfirst;

/**
 * @property-read int $ID The term ID
 * @property-read string $name The term name
 * @property-read string $description The term description
 * @property-read string $slug The term slug
 * @property-read string $link The term URL
 * @property-read string $url The term URL
 * @property-read int $count The term associated objects count
 * @property-read int $parent_id The term parent ID
 * @property-read ?Term $parent The term parent object
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

    public function getTaxonomy(): string
    {
        return static::$taxonomy;
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

    /** @throws RuntimeException */
    public function getLink(): string
    {
        $link = get_term_link($this->getCoreObject());

        if ($link instanceof WP_Error) {
            throw new RuntimeException($link->get_error_message());
        }

        return $link;
    }

    /** @throws RuntimeException */
    public function getUrl(): string
    {
        return $this->getLink();
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

    public static function insert(string $name): self
    {
        switch (static::$taxonomy) {
            case Category::$taxonomy:
                $result = wp_create_category($name);
                break;
            case Tag::$taxonomy:
                $result = wp_create_tag($name);
                break;
            default:
                $result = wp_create_term($name, static::$taxonomy);
        }

        if ($result instanceof WP_Error) {
            throw new RuntimeException($result->get_error_message());
        }

        return static::getByName($name);
    }

    public static function all(bool $format = true): Collection
    {
        return self::query()
            ->hideEmpty(false)
            ->get($format ? static::class : null);
    }

    /** @param int|int[] $id */
    public static function getById($id, bool $format = true): Term
    {
        $results = self::query()
            ->whereIdIn($id)
            ->limit(1)
            ->hideEmpty(false)
            ->get($format ? static::class : null);

        if ($results->isEmpty()) {
            throw new ObjectNotFoundException(sprintf(
                '%s ID: [%d] not found in database.',
                ucfirst(str_replace(['_', '-'], ' ', static::$taxonomy)),
                $id
            ));
        }

        return $results[0];
    }

    /** @throws ObjectNotFoundException */
    public static function getByName(string $name, bool $format = true): Term
    {
        $results = self::query()
            ->whereName($name)
            ->limit(1)
            ->hideEmpty(false)
            ->get($format ? static::class : null);

        if ($results->isEmpty()) {
            throw new ObjectNotFoundException(sprintf(
                '%s name: "%s" not found in database.',
                ucfirst(str_replace(['_', '-'], '', static::$taxonomy)),
                $name
            ));
        }

        return $results[0];
    }

    /** @throws ObjectNotFoundException */
    public static function getBySlug(string $slug, bool $format = true): Term
    {
        $results = self::query()
            ->whereSlug($slug)
            ->hideEmpty(false)
            ->limit(1)
            ->get($format ? static::class : null);

        if ($results->isEmpty()) {
            throw new ObjectNotFoundException(sprintf(
                '%s slug: "%s" not found in database.',
                ucfirst(str_replace(['_', '-'], '', static::$taxonomy)),
                $slug
            ));
        }

        return $results[0];
    }

    /** @param array<string, mixed> $query */
    public static function query(array $query = []): TermQuery
    {
        return TermQuery::create($query)->whereTaxonomy(static::$taxonomy);
    }
}
