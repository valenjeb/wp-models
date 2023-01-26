<?php

declare(strict_types=1);

namespace Devly\WP\Models;

use Devly\Exceptions\ObjectNotFoundException;
use Illuminate\Support\Collection;
use Nette\SmartObject;
use RuntimeException;
use WP_Comment;
use WP_Error;

use function array_values;
use function collect;

/**
 * @property-read int $ID
 * @property-read string $date
 * @property-read string $dateGmt
 * @property-read string $rawDate
 * @property-read string $time
 * @property-read string $content
 * @property-read int $karma
 * @property-read bool $approved
 * @property-read int $parentID
 * @property-read ?Comment $parent
 * @property-read int $postID
 * @property-read int $authorID
 * @property-read User $author
 * @property-read string $authorName
 * @property-read string $authorIP
 * @property-read string $authorUrl
 * @property-read string $authorEmail
 * @property-read Collection<Comment> $children
 * @property-read WP_Comment $coreObject
 */
class Comment
{
    use SmartObject;

    protected WP_Comment $coreObject;
    protected self $parentObject;
    protected Collection $childObjects;
    protected User $userObject;

    /**
     * @param WP_Comment|int $comment comment ID to retrieve or WP_Comment object
     *
     * @throws ObjectNotFoundException
     */
    public function __construct($comment)
    {
        $comment = get_comment($comment);

        if ($comment === null) {
            throw new ObjectNotFoundException('Comment not found');
        }

        $this->coreObject = $comment;
    }

    /**
     * Retrieves the comment ID
     */
    public function getID(): int
    {
        return (int) $this->getCoreObject()->comment_ID;
    }

    /**
     * Retrieves the raw comment create date as saved in the database.
     *
     * @return string Comment date in YYYY-MM-DD HH:MM:SS format
     */
    public function getRawDate(): string
    {
        return $this->getCoreObject()->comment_date;
    }

    /**
     * Retrieves formatted comment create date.
     */
    public function getDate(string $format = ''): string
    {
        return get_comment_date($format, $this->getID());
    }

    /**
     * Retrieves the comment create GMT date as saved in the database.
     *
     * @return string Comment date in YYYY-MM-DD HH:MM:SS format
     */
    public function getDateGmt(): string
    {
        return $this->getCoreObject()->comment_date_gmt;
    }

    /**
     * Retrieves formatted comment time of the current comment.
     */
    public function getTime(string $format = '', bool $gmt = false, bool $translate = true): string
    {
        $rawDate = $gmt ? $this->getDateGmt() : $this->getRawDate();

        $_format = ! empty($format) ? $format : get_option('time_format');

        $date = mysql2date($_format, $rawDate, $translate);

        return apply_filters('get_comment_time', $date, $format);
    }

    public function getContent(): string
    {
        return $this->getCoreObject()->comment_content;
    }

    public function getKarma(): int
    {
        return (int) $this->getCoreObject()->comment_karma;
    }

    public function isApproved(): bool
    {
        return $this->getStatus() === 'approved';
    }

    public function isTrashed(): bool
    {
        return $this->getStatus() === 'trash';
    }

    public function isSpam(): bool
    {
        return $this->getStatus() === 'spam';
    }

    public function getStatus(): string
    {
        return wp_get_comment_status($this->getCoreObject());
    }

    /**
     * Retrieves the comment status.
     *
     * @throws RuntimeException
     */
    public function setStatus(string $status): self
    {
        $res = wp_set_comment_status($this->getCoreObject(), $status, true);

        if ($res instanceof WP_Error) {
            throw new RuntimeException($res->get_error_message());
        }

        $this->refreshCoreObject();

        return $this;
    }

    /**
     * Retrieves the parent comment ID.
     */
    public function getParentID(): int
    {
        return (int) $this->getCoreObject()->comment_parent;
    }

    /**
     * Determine whether the comment is a child.
     */
    public function hasParent(): bool
    {
        return $this->getParentID() !== 0;
    }

    /**
     * Get parent comment.
     */
    public function getParent(): ?Comment
    {
        if (! $this->hasParent()) {
            return null;
        }

        return new self($this->getParentID());
    }

    /**
     * retrieves the ID of the post the comment is associated with.
     */
    public function getPostID(): int
    {
        return (int) $this->getCoreObject()->comment_post_ID;
    }

    public function getAuthorID(): int
    {
        return (int) $this->getCoreObject()->user_id;
    }

    /**
     * Get the comment author
     *
     * @throws RuntimeException if the comment author is anonymous.
     */
    public function getAuthor(): User
    {
        if ($this->isAnonymous()) {
            throw new RuntimeException('Can not create User object for anonymous user');
        }

        return new User($this->getAuthorID());
    }

    /**
     * Retrieves the comment author name
     */
    public function getAuthorName(): string
    {
        return $this->getCoreObject()->comment_author;
    }

    /**
     * Retrieves the comment author email address.
     */
    public function getAuthorEmail(): string
    {
        return $this->getCoreObject()->comment_author_email;
    }

    /**
     * Retrieves the comment author URL.
     */
    public function getAuthorUrl(): string
    {
        return $this->getCoreObject()->comment_author_url;
    }

    /**
     * Retrieves the comment author IP address.
     *
     * @return string IP address (IPv4 format)
     */
    public function getAuthorIP(): string
    {
        return $this->getCoreObject()->comment_author_IP;
    }

    /**
     * Determines whether the comment author is anonymous.
     */
    public function isAnonymous(): bool
    {
        return $this->getAuthorID() !== 0;
    }

    /**
     * Get collection of the children of a comment
     *
     * @param array<string, mixed> $args
     */
    public function getChildren(array $args = [], bool $format = true): Collection
    {
        $comments = $this->getCoreObject()->get_children($args);

        $comments = collect(array_values($comments));

        if (! $format) {
            return $comments;
        }

        return $comments->map(static fn ($comment) => new self($comment));
    }

    public function getCoreObject(): WP_Comment
    {
        return $this->coreObject;
    }

    public function refreshCoreObject(): void
    {
        $this->coreObject = get_comment($this->getID());
    }

    public function cleanCache(): void
    {
        clean_comment_cache($this->getID());
    }
}
