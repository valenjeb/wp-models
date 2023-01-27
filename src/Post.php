<?php

/** phpcs:disable Generic.Files.LineLength.TooLong */

declare(strict_types=1);

namespace Devly\WP\Models;

use Devly\Exceptions\ObjectNotFoundException;
use Devly\Utils\Helpers;
use Devly\WP\Query\PostQuery;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use LogicException;
use Nette\SmartObject;
use RuntimeException;
use Throwable;
use WP_Comment;
use WP_Error;
use WP_Post;
use WP_Term;

use function apply_filters;
use function array_filter;
use function array_map;
use function array_merge;
use function collect;
use function func_get_args;
use function get_edit_post_link;
use function get_permalink;
use function get_post;
use function get_post_class;
use function get_post_status;
use function get_post_thumbnail_id;
use function get_the_title;
use function gettype;
use function implode;
use function is_array;
use function is_int;
use function is_object;
use function sprintf;
use function wp_insert_post;

use const DATE_W3C;

/**
 * @property-read int $ID
 * @property-read string $rawTitle The raw post title as stored in the database.
 * @property-read string $title The post title
 * @property-read string $type The post type
 * @property-read string $slug The URL-safe slug, this corresponds to the poorly-named "post_name" in the WP database, ex: "hello-world"
 * @property-read string $status The post status
 * @property-read string $rawDate  DateTime string (0000-00-00 00:00:00)
 * @property-read string $rawDateModified DateTime string (0000-00-00 00:00:00)
 * @property-read string $date The formatted post create date
 * @property-read string $dateModified The formatted post modified date
 * @property-read string $dateW3c The post create date (0000-00-00T00:00:00+00:00)
 * @property-read string $dateModifiedW3c The post modified date (0000-00-00T00:00:00+00:00)
 * @property-read int $createTimestamp The post create timestamp
 * @property-read int $modifiedTimestamp The post modified timestamp
 * @property-read string $time The formatted post create time
 * @property-read string $timeModified The formatted post modified time
 * @property-read string $rawContent The raw post content as stored in the database.
 * @property-read string $content The post content.
 * @property-read string $rawExcerpt The raw post excerpt as stored in the database.
 * @property-read string $excerpt The post excerpt.
 * @property-read ?int $thumbnailID The post thumbnail ID
 * @property-read ?Attachment $thumbnail
 * @property-read string $cssClass
 * @property-read ?int $parentID
 * @property-read ?self $parent
 * @property-read User $author
 * @property-read int $authorID
 * @property-read string $editLink
 * @property-read string $permalink
 * @property-read string $url
 * @property-read ?Post $previousPost
 * @property-read ?Post $nextPost
 * @property-read int $commentCount
 * @property-read string $commentStatus
 * @property-read bool $commentsOpen
 * @property-read string $format
 * @property-read Collection<Category> $categories
 * @property-read Collection<Tag> $tags
 * @property-read string $tagList
 * @property-read string $categoryList
 * @property-read string $password
 * @property-read bool $passwordRequired
 */
class Post
{
    use SmartObject;

    protected WP_Post $coreObject;
    public static string $postType = 'post';
    protected Attachment $postThumbnail;
    protected ?self $postParent;
    protected User $author;
    /** @var Post|false */
    protected $previousPost;
    /** @var Post|false */
    protected $nextPost;

    /**
     * @param int|WP_Post $post Post ID or post object. `0` values return
     *                          the current global post inside the loop.
     *
     * @throws ObjectNotFoundException
     */
    public function __construct($post = 0)
    {
        $_post = get_post($post);

        if ($_post === null) {
            if ($post === 0) {
                throw new LogicException(sprintf(
                    '"%s" must be initialized with a valid post ID or'
                            . ' an instance of WP_Post when called outside of the loop.',
                    static::class
                ));
            }

            if (! is_int($post)) {
                throw new InvalidArgumentException(sprintf(
                    'The "%s" constructor expects a post ID or an instance of WP_Post. Provided %s.',
                    static::class,
                    gettype($post)
                ));
            }

            throw new ObjectNotFoundException(sprintf(
                'Post ID "%s" of type "%s" not found.',
                $post,
                static::$postType,
            ));
        }

        if (static::$postType !== 'post' && static::$postType !== $_post->post_type) {
            throw new LogicException(sprintf(
                'Post ID "%d" is a "%s" post type, not a "%s" post type. Make sure to use the correct model.',
                $post,
                $_post->post_type,
                static::$postType,
            ));
        }

        $this->coreObject = $_post;
    }

    /**
     * Retrieves the post type
     */
    public function getType(): string
    {
        return self::$postType;
    }

    /**
     * Determines whether the post type equals the specified type.
     */
    public function is(string $type): bool
    {
        return $this->getType() === $type;
    }

    public function getID(): int
    {
        return $this->getCoreObject()->ID;
    }

    /**
     * Retrieves post title.
     */
    public function getTitle(): string
    {
        return get_the_title($this->getCoreObject());
    }

    public function getRawTitle(): string
    {
        return $this->getCoreObject()->post_title;
    }

    public function getStatus(): string
    {
        return get_post_status($this->getCoreObject());
    }

    public function getSlug(): string
    {
        return $this->getCoreObject()->post_name;
    }

    public function getContent(?string $moreLinkText = null, bool $stripTeaser = false): string
    {
        try {
            return Helpers::capture(function () use ($moreLinkText, $stripTeaser): void {
                $post            = $GLOBALS['post'] ?? null;
                $GLOBALS['post'] = $this->getCoreObject();

                the_content($moreLinkText, $stripTeaser);

                if ($post) {
                    $GLOBALS['post'] = $post;
                } else {
                    unset($GLOBALS['post']);
                }
            });
        } catch (Throwable $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getRawContent(): string
    {
        return $this->getCoreObject()->post_content;
    }

    public function getRawExcerpt(): string
    {
        return $this->getCoreObject()->post_excerpt;
    }

    public function getExcerpt(): string
    {
        try {
            return Helpers::capture(function (): void {
                $post            = $GLOBALS['post'] ?? null;
                $GLOBALS['post'] = $this->getCoreObject();

                the_excerpt();

                if ($post) {
                    $GLOBALS['post'] = $post;
                } else {
                    unset($GLOBALS['post']);
                }
            });
        } catch (Throwable $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getThumbnailID(): ?int
    {
        return get_post_thumbnail_id($this->getCoreObject()) ?: null;
    }

    public function hasThumbnail(): bool
    {
        return $this->getThumbnailID() !== null;
    }

    public function getThumbnail(): ?Attachment
    {
        if (! $this->hasThumbnail()) {
            return null;
        }

        if (! isset($this->postThumbnail)) {
            $this->postThumbnail = new Attachment($this->getThumbnailID());
        }

        return $this->postThumbnail;
    }

    /**
     * Set the post thumbnail
     *
     * @param int|WP_Post|Attachment $attachment
     */
    public function setThumbnail($attachment): self
    {
        $id = $attachment instanceof Attachment || $attachment instanceof WP_Post ? $attachment->ID : $attachment;

        if (! is_int($id)) {
            throw new InvalidArgumentException(sprintf(
                "Method '%s::%s()' expects an integer, an Attachment or WP_Post instance.",
                static::class,
                __METHOD__
            ));
        }

        $this->setField('_thumbnail_id', $id);

        return $this;
    }

    /**
     * Retrieves the full permalink for the current post or post ID.
     */
    public function getPermalink(): string
    {
        return get_permalink($this->getCoreObject());
    }

    public function getUrl(): string
    {
        return $this->getPermalink();
    }

    /**
     * Retrieves the edit post link for post.
     */
    public function getEditLink(): ?string
    {
        return get_edit_post_link($this->getCoreObject());
    }

    /** @param string|string[] $class */
    public function getCssClass($class = ''): string
    {
        return implode(' ', get_post_class($class, $this->getCoreObject()));
    }

    public function getParentID(): ?int
    {
        return $this->getCoreObject()->post_parent ?: null;
    }

    public function getParent(): ?self
    {
        if (! $this->hasParent()) {
            return null;
        }

        if (! isset($this->postParent)) {
            $className = static::class;

            $this->postParent = new $className($this->getParentID());
        }

        return $this->postParent;
    }

    public function hasParent(): bool
    {
        return $this->getParentID() !== null;
    }

    public function isChild(): bool
    {
        return $this->hasParent();
    }

    /**
     * Retrieve formatted time string or Unix timestamp on which the post was written.
     *
     * @param string $format Format to use for retrieving the time the post was written.
     *                       Accepts 'G', 'U', or PHP date format. Defaults to the
     *                       'time_format' option.
     *
     * @return string|int
     */
    public function getTime(string $format = '')
    {
        return get_the_time($format, $this->getCoreObject());
    }

    /**
     * Retrieve formatted modified time string or Unix timestamp on which the post was written.
     *
     * @param string $format Format to use for retrieving the time the post was written.
     *                       Accepts 'G', 'U', or PHP date format. Defaults to the
     *                       'time_format' option.
     *
     * @return string|int
     */
    public function getTimeModified(string $format = '')
    {
        return get_the_modified_time($format, $this->getCoreObject());
    }

    /**
     * Retrieve formatted date on which the post was written
     *
     * @param string $format PHP date format. Defaults to the 'date_format' option.
     *
     * @return string|int
     */
    public function getDate(string $format = '')
    {
        return get_the_date($format, $this->getCoreObject());
    }

    public function getRawDate(): string
    {
        return $this->getCoreObject()->post_date;
    }

    /**
     * Returns the post create date in "Y-m-d\TH:i:sP" format
     */
    public function getDateW3c(): string
    {
        return $this->getDate(DATE_W3C);
    }

    /**
     * Returns the post modified date in "Y-m-d\TH:i:sP" format
     */
    public function getDateModifiedW3c(): string
    {
        return $this->getDate(DATE_W3C);
    }

    public function getCreateTimestamp(): int
    {
        return $this->getDate('U');
    }

    public function getModifiedTimestamp(): int
    {
        return $this->getDateModified('U');
    }

    /**
     * Retrieve formatted date on which the post was last modified.
     *
     * @param string $format PHP date format. Defaults to the 'date_format' option.
     *
     * @return string|int
     */
    public function getDateModified(string $format = '')
    {
        return get_the_modified_date($format, $this->getCoreObject());
    }

    /**
     * Get the post modification date in DateTime string (0000-00-00 00:00:00) format
     */
    public function getRawDateModified(): string
    {
        return $this->getCoreObject()->post_modified;
    }

    public function getAuthor(): User
    {
        if (! isset($this->author)) {
            $this->author = new User($this->getAuthorID());
        }

        return $this->author;
    }

    public function getAuthorID(): int
    {
        return (int) $this->getCoreObject()->post_author;
    }

    /**
     * Retrieves the previous post that is adjacent to the current post
     *
     * @param bool   $sameTerm Whether post should be in a same taxonomy term. Default false.
     * @param int[]  $excluded Array of excluded term IDs. Default empty.
     * @param string $taxonomy Taxonomy, if $in_same_term is true. Default 'category'
     *
     * @return Post|null Post object if successful. Null if nothing found.
     */
    public function getPreviousPost(bool $sameTerm = false, array $excluded = [], string $taxonomy = 'category'): ?Post
    {
        // @phpstan-ignore-next-line
        if (! isset($this->previousPost)) {
            $_post           = $GLOBALS['post'] ?? null;
            $GLOBALS['post'] = $this->getCoreObject();
            $previous        = get_previous_post($sameTerm, $excluded, $taxonomy);
            if ($_post) {
                $GLOBALS['post'] = $_post;
            }

            $this->previousPost = ! empty($previous) ? new Post($previous) : false;
        }

        return $this->previousPost === false ? null : $this->previousPost;
    }

    /**
     * Retrieves the next post that is adjacent to the current post
     *
     * @param bool   $sameTerm Whether post should be in a same taxonomy term. Default false.
     * @param int[]  $excluded Array of excluded term IDs. Default empty.
     * @param string $taxonomy Taxonomy, if $in_same_term is true. Default 'category'
     *
     * @return Post|null Post object if successful. Null if nothing found.
     */
    public function getNextPost(bool $sameTerm = false, array $excluded = [], string $taxonomy = 'category'): ?Post
    {
        // @phpstan-ignore-next-line
        if (! isset($this->nextPost)) {
            $_post           = $GLOBALS['post'] ?? null;
            $GLOBALS['post'] = $this->getCoreObject();
            $next            = get_next_post($sameTerm, $excluded, $taxonomy);
            if ($_post) {
                $GLOBALS['post'] = $_post;
            }

            $this->nextPost = ! empty($next) ? new Post($next) : false;
        }

        return $this->nextPost === false ? null : $this->nextPost;
    }

    /**
     * Checks whether the post has comments.
     */
    public function hasComments(): bool
    {
        return $this->getCoreObject()->comment_count > 0;
    }

    /**
     * Checks whether comments are allowed.
     */
    public function isCommentsOpen(): bool
    {
        return $this->getCoreObject()->comment_status === 'open';
    }

    /**
     * Determines whether comments are allowed.
     */
    public function getCommentStatus(): string
    {
        return $this->getCoreObject()->comment_status;
    }

    /**
     * @param string $key     The meta key to retrieve. By default, returns
     *                        data for all keys. Default empty.
     * @param mixed  $default Default value to return if no key is found.
     *
     * @return mixed
     */
    public function getField(string $key = '', $default = null)
    {
        $value = apply_filters(Filter::POST_PRE_GET_META_FIELD, null, $key, $this);

        if ($value === null) {
            $value = get_post_meta($this->getID(), $key, true);
        }

        if (empty($value)) {
            $value = $default;
        }

        return apply_filters(Filter::POST_GET_META_FIELD . '/' . $key, $value, $this);
    }

    /**
     * @param string $key      The meta key.
     * @param mixed  $value    The field value. Must be serializable if non-scalar.
     * @param mixed  $previous Previous value to check before updating. If specified,
     *                         only update existing metadata entries with this value.
     *                         Otherwise, update all entries. Default empty.
     *
     * @return bool true on successful update, false on failure or if the value passed
     *              to the function is the same as the one that is already in the database.
     */
    public function setField(string $key, $value, $previous = ''): bool
    {
        $result = apply_filters(Filter::POST_PRE_SET_META_FIELD, null, $key, $value, $previous, $this);

        if ($result !== null) {
            return (bool) $result;
        }

        $result = update_post_meta($this->getID(), $key, $value, $previous);

        return $result !== false;
    }

    /**
     * Deletes a post meta field for the given post ID.
     *
     * @param mixed $value
     */
    public function deleteField(string $key, $value = ''): bool
    {
        $result = apply_filters(Filter::POST_PRE_DELETE_META_FIELD, null, $key, $this);

        if ($result !== null) {
            return $result;
        }

        return delete_post_meta($this->getID(), $key, $value);
    }

    /**
     * Retrieve the format slug for a post.
     */
    public function getFormat(): string
    {
        return get_post_format($this->getCoreObject());
    }

    /**
     * Retrieves the terms for the post
     *
     * @param array<string, mixed> $args
     *
     * @throws RuntimeException
     */
    public function getTerms(string $taxonomy = 'post_tag', array $args = [], string $return = Term::class): Collection
    {
        $terms = wp_get_post_terms($this->ID, $taxonomy, $args);

        if ($terms instanceof WP_Error) {
            throw new RuntimeException($terms->get_error_message());
        }

        return collect($terms)
            ->map(static fn (WP_Term $term) => $return === WP_Term::class ? $term : new $return($term));
    }

    /**
     * Set the terms for the post.
     *
     * @param int[] $terms
     *
     * @throws RuntimeException
     */
    public function setTerms(array $terms, string $taxonomy = 'post_tag'): void
    {
        $res = wp_set_post_terms($this->getID(), $terms, $taxonomy);

        if ($res instanceof WP_Error) {
            throw new RuntimeException($res->get_error_message());
        }
    }

    /**
     * Add terms for the post.
     *
     * @param int[] $terms
     *
     * @throws RuntimeException
     */
    public function addTerms(array $terms, string $taxonomy = 'post_tag'): void
    {
        $res = wp_set_post_terms($this->getID(), $terms, $taxonomy, true);

        if ($res instanceof WP_Error) {
            throw new RuntimeException($res->get_error_message());
        }
    }

    /**
     * Removes terms from the post
     *
     * @param int[] $terms A term ID or a list of term IDs to remove
     *
     * @throws RuntimeException
     */
    public function removeTerms(array $terms, string $taxonomy = 'post_tag'): void
    {
        $res = wp_get_post_terms($this->getID(), $taxonomy);

        if ($res instanceof WP_Error) {
            throw new RuntimeException($res->get_error_message());
        }

        foreach ($terms as $term) {
            $res = array_filter($res, static function ($item) use ($term) {
                return $item->term_id !== $term;
            });
        }

        $res = array_map(static fn ($item) => $item->term_id, $res);

        $this->setTerms($res, $taxonomy);
    }

    /**
     * Retrieves the categories for the post
     *
     * @param array<string, mixed> $args
     *
     * @throws RuntimeException
     */
    public function getCategories(array $args = []): Collection
    {
        return $this->getTerms('category', $args, Category::class);
    }

    /**
     * Set the categories for the post
     *
     * @param int|int[] $terms A category ID or a list of category IDs to set
     *
     * @throws RuntimeException
     */
    public function setCategories($terms): Collection
    {
        $terms = is_array($terms) ? $terms : func_get_args();

        $this->setTerms($terms, 'category');

        return $this->getCategories();
    }

    /**
     * Remove categories from the post
     *
     * @param int|int[] $terms A category ID or a list of category IDs to remove
     *
     * @throws RuntimeException
     */
    public function removeCategories($terms): Collection
    {
        $terms = is_array($terms) ? $terms : func_get_args();

        $this->removeTerms($terms, 'category');

        return $this->getCategories();
    }

    /**
     * Add categories for the post
     *
     * @param int|int[] $terms A category ID or a list of category IDs to add
     *
     * @throws RuntimeException
     */
    public function addCategories($terms): Collection
    {
        $terms = is_array($terms) ? $terms : func_get_args();

        $this->addTerms($terms, 'category');

        return $this->getCategories();
    }

    /**
     * Retrieves the tags for the post
     *
     * @param array<string, mixed> $args
     *
     * @throws RuntimeException
     */
    public function getTags(array $args = []): Collection
    {
        return $this->getTerms('post_tag', $args, Tag::class);
    }

    /**
     * Set the tags for the post
     *
     * @param int|int[] $terms A tag ID or a list of tag IDs to set
     *
     * @throws RuntimeException
     */
    public function setTags($terms): Collection
    {
        $terms = is_array($terms) ? $terms : func_get_args();

        $this->setTerms($terms);

        return $this->getTags();
    }

    /**
     * Remove tags from the post
     *
     * @param int|int[] $terms A tag ID or a list of tag IDs to remove
     *
     * @throws RuntimeException
     */
    public function removeTags($terms): Collection
    {
        $terms = is_array($terms) ? $terms : func_get_args();

        $this->removeTerms($terms);

        return $this->getTags();
    }

    /**
     * Add tags for the post
     *
     * @param int|int[] $terms A tag ID or a list of tag IDs to add
     *
     * @throws RuntimeException
     */
    public function addTags($terms): Collection
    {
        $terms = is_array($terms) ? $terms : func_get_args();

        $this->addTerms($terms);

        return $this->getTags();
    }

    /**
     * Retrieves category list for a post in either HTML list or custom format.
     */
    public function getCategoryList(string $separator = ', ', string $parents = ''): string
    {
        return get_the_category_list($separator, $parents, $this->getID());
    }

    /**
     * Retrieves category list for a post in either HTML list or custom format.
     */
    public function getTagList(string $separator = ', '): string
    {
        return (string) get_the_tag_list('', $separator, '', $this->getID());
    }

    /**
     * Retrieves the post's password in plain text.
     */
    public function getPassword(): string
    {
        return $this->getCoreObject()->post_password;
    }

    /**
     * Determine Whether post has.
     */
    public function hasPassword(): bool
    {
        return ! empty($this->getPassword());
    }

    /**
     * Determine Whether post requires password and correct password has been provided.
     */
    public function isPasswordRequired(): bool
    {
        return post_password_required($this->getCoreObject());
    }

    /**
     * Update the post with new post data
     *
     * @param array<string, mixed> $options
     *
     * @throws RuntimeException on failure.
     */
    public function update(array $options): bool
    {
        $id = wp_update_post(['ID' => $this->getID()] + $options, true);

        if ($id instanceof WP_Error) {
            throw new RuntimeException($id->get_error_message());
        }

        $this->refreshCoreObject();

        return true;
    }

    /**
     * Retrieves all posts
     *
     * @param bool $format Whether to return list of WP_Post objects
     *                     or Devly\Database\Models\Post objects.
     *
     * @return Collection<WP_Post|self> Post collection
     */
    public static function all(bool $format = true): Collection
    {
        return PostQuery::create()
            ->wherePostType(static::$postType)
            ->all($format ? static::class : null);
    }

    /**
     * Find a post by its slug
     *
     * @throws ObjectNotFoundException if No post found with associated slug.
     */
    public static function findBySlug(string $slug, bool $format = true): self
    {
        $posts = PostQuery::create()
            ->wherePostType(static::$postType)
            ->wherePostSlug($slug)
            ->limit(1)
            ->get($format ? static::class : null);

        if ($posts->isEmpty()) {
            throw new ObjectNotFoundException(sprintf(
                'No "%s" found with associated slug: "%s".',
                static::$postType,
                $slug
            ));
        }

        return $posts[0];
    }

    public static function query(): PostQuery
    {
        $query = PostQuery::create(['post_type' => static::$postType]);
        $query->setReturnType(static::class);

        return $query;
    }

    public static function where(): PostQuery
    {
        return self::query();
    }

    /**
     * Trash or delete a post or page
     *
     * @param int|Post|WP_Post $post  The post to delete
     * @param bool             $force Whether to bypass Trash and force
     *                                deletion. Default false.
     */
    public static function delete($post, bool $force = false): bool
    {
        $id = is_object($post) ? $post->getID() : (int) $post;

        $result = wp_delete_post($id, $force);

        return $result !== null && $result !== false;
    }

    /**
     * Insert or update a post.
     *
     * @param string|array<string, mixed> $titleOrPostData
     */
    public static function insert(
        $titleOrPostData,
        string $content = '',
        string $excerpt = '',
        string $status = 'draft'
    ): self {
        if (is_array($titleOrPostData)) {
            $postarr = wp_parse_args($titleOrPostData, ['post_type' => static::$postType]);
        } else {
            $postarr = [
                'post_type'    => static::$postType,
                'post_title'   => $titleOrPostData,
                'post_content' => $content,
                'post_excerpt' => $excerpt,
                'post_status'  => $status,
            ];
        }

        $id = wp_insert_post($postarr, true);

        if ($id instanceof WP_Error) {
            throw new RuntimeException($id->get_error_message());
        }

        return new self($id);
    }

    public function getCommentCount(): int
    {
        return (int) $this->getCoreObject()->comment_count;
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return Collection|int|int[]|WP_Comment[]
     */
    public function getComments(array $args = [], bool $format = true)
    {
        $default = [
            'post_id' => $this->getID(),
            'hierarchical' => 'threaded',
        ];

        $comments = get_comments(array_merge($args, $default));

        if (! $format || isset($args['fields']) && $args['fields'] === 'ids') {
            return $comments;
        }

        return collect($comments)->map(static fn ($comment) => new Comment($comment));
    }

    public function __toString(): string
    {
        return $this->getPermalink();
    }

    public function getCoreObject(): WP_Post
    {
        return $this->coreObject;
    }

    public function refreshCoreObject(): void
    {
        $this->coreObject = get_post($this->getID());
    }
}
