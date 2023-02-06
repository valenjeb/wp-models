<?php

declare(strict_types=1);

namespace Devly\WP\Models;

use Devly\Exceptions\ObjectNotFoundException;
use Devly\WP\Query\TermQuery;
use Illuminate\Support\Collection;
use LogicException;
use Nette\SmartObject;
use RuntimeException;
use WP_Error;
use WP_Term;

use function is_bool;
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
 * @property-read array $fields List of all meta fields
 * @property-read int $parentID The term parent ID
 * @property-read ?Term $parent The term parent object
 */
abstract class Term
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
        $term = get_term($term, self::getTaxonomy());

        if ($term instanceof WP_Error) {
            throw new RuntimeException($term->get_error_message());
        }

        if ($term === null) {
            throw new ObjectNotFoundException(sprintf(
                '%s id: %d not found in database.',
                self::getTaxonomy(),
                $term
            ));
        }

        if (self::getTaxonomy() !== $term->taxonomy) {
            throw new LogicException(sprintf(
                'Term "%d" is a "%s" not a "%s". Make sure to use the correct model.',
                $term->term_id,
                self::getTaxonomy(),
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

    public static function getTaxonomy(): string
    {
        if (isset(static::$taxonomy)) {
            return static::$taxonomy;
        }

        if (static::class === self::class) {
            throw new LogicException(sprintf('The base "%s" object should not be called directly.', self::class));
        }

        throw new LogicException(sprintf('The required %s::$taxonomy parameter is not defined.', self::class));
    }

    public static function delete(int $id): bool
    {
        $result = wp_delete_term($id, self::getTaxonomy());

        if (is_bool($result)) {
            return $result;
        }

        if ($result instanceof WP_Error) {
            $err = $result->get_error_message();
        } else {
            $err = 'The default WordPress category can not be deleted.';
        }

        throw new RuntimeException($err);
    }

    public static function insert(string $name): self
    {
        switch (self::getTaxonomy()) {
            case Category::getTaxonomy():
                $result = wp_create_category($name);
                break;
            case Tag::getTaxonomy():
                $result = wp_create_tag($name);
                break;
            default:
                $result = wp_create_term($name, self::getTaxonomy());
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

    /**
     * @param int|int[] $id
     *
     * @return Collection<static>|static
     *
     * @throws ObjectNotFoundException
     */
    public static function getById($id)
    {
        if (is_int($id)) {
            return new static($id);
        }

        return self::query()
            ->whereIdIn($id)
            ->hideEmpty(false)
            ->get(static::class);
    }

    /** @throws ObjectNotFoundException */
    public static function getByName(string $name): Term
    {
        $term = get_term_by('name', $name, self::getTaxonomy());

        if ($term === false) {
            throw new ObjectNotFoundException(sprintf(
                '%s name: "%s" not found in database.',
                ucfirst(str_replace(['_', '-'], '', self::getTaxonomy())),
                $name
            ));
        }

        if ($term instanceof WP_Error) {
            throw new RuntimeException($term->get_error_message());
        }

        return new static($term);
    }

    /** @throws ObjectNotFoundException */
    public static function getBySlug(string $slug): Term
    {
        $term = get_term_by('slug', $slug, self::getTaxonomy());

        if ($term === false) {
            throw new ObjectNotFoundException(sprintf(
                '%s slug: "%s" not found in database.',
                ucfirst(str_replace(['_', '-'], '', self::getTaxonomy())),
                $slug
            ));
        }

        if ($term instanceof WP_Error) {
            throw new RuntimeException($term->get_error_message());
        }

        return new static($term);
    }

    /** @param array<string, mixed> $query */
    public static function query(array $query = []): TermQuery
    {
        $query = TermQuery::create($query)->whereTaxonomy(self::getTaxonomy());
        $query->setReturnType(static::class);

        return $query;
    }
}
