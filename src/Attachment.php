<?php

declare(strict_types=1);

namespace Devly\WP\Models;

use Devly\Exceptions\ObjectNotFoundException;
use Devly\Utils\SmartObject;
use Exception;
use RuntimeException;
use WP_Error;
use WP_Post;

use function pathinfo;
use function sprintf;

use const PATHINFO_BASENAME;
use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;

/**
 * @property-read int $ID
 * @property-read string $url
 * @property string $image_alt
 * @property string $title
 * @property string $description
 * @property string $caption
 * @property-read string $basename
 * @property-read string $filename
 * @property-read string $extension
 * @property-read string $mime_type
 */
class Attachment
{
    use SmartObject;

    protected WP_Post $coreObject;
    protected string $fileUrl;
    protected string $filePath;
    protected string $fileBasename;
    protected string $attachmentFilename;
    protected string $fileExtension;

    public function __construct(int $id)
    {
        $post = get_post($id);

        if ($post === null) {
            throw new ObjectNotFoundException('The requested thumbnail ID not found in database.');
        }

        if ($post->post_type !== 'attachment') {
            throw new RuntimeException(sprintf(
                'Post ID: "%s" is a "%s", not an attachment.',
                $id,
                $post->post_type
            ));
        }

        $this->coreObject = $post;
    }

    public function getID(): int
    {
        return $this->getCoreObject()->ID;
    }

    /**
     * Retrieves the attachment title.
     */
    public function getTitle(): string
    {
        return get_the_title($this->getCoreObject());
    }

    public function setTitle(string $title): self
    {
        $this->update(['post_title' => $title]);

        return $this;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws Exception
     */
    public function update(array $data): bool
    {
        $data = wp_parse_args($data, [
            'ID'           => $this->getID(),
        ]);

        $result = wp_update_post($data, true);

        if ($result instanceof WP_Error) {
            throw new Exception($result->get_error_message());
        }

        $this->refreshCoreObject();

        return true;
    }

    /**
     * Retrieves the raw attachment description as stored in the database.
     */
    public function getDescription(): string
    {
        return $this->getCoreObject()->post_content;
    }

    public function setDescription(string $description): self
    {
        $this->update(['post_content' => $description]);

        return $this;
    }

    /**
     * Retrieves the raw attachment caption as stored in the database.
     */
    public function getCaption(): string
    {
        return wp_get_attachment_caption($this->getID());
    }

    public function setCaption(string $caption): self
    {
        $this->update(['post_excerpt' => $caption]);

        return $this;
    }

    /**
     * Retrieves the attachment description alt.
     */
    public function getImageAlt(): string
    {
        return $this->getField('_wp_attachment_image_alt') ?: '';
    }

    public function setImageAlt(string $text): self
    {
        $this->setField('_wp_attachment_image_alt', $text);

        return $this;
    }

    /**
     * Retrieves the attachment file url.
     */
    public function getUrl(): string
    {
        if (! isset($this->fileUrl)) {
            $this->fileUrl = wp_get_attachment_url($this->getID()) ?: '';
        }

        return $this->fileUrl;
    }

    /**
     * Retrieves the attachment file path.
     */
    public function getPath(): string
    {
        if (! isset($this->filePath)) {
            $this->filePath = get_attached_file($this->getID()) ?: '';
        }

        return $this->filePath;
    }

    /**
     * Get the attachment file name.
     */
    public function getFilename(): string
    {
        if (! isset($this->attachmentFilename)) {
            $this->attachmentFilename = pathinfo($this->getPath(), PATHINFO_FILENAME);
        }

        return $this->attachmentFilename;
    }

    /**
     * Get the attachment base name.
     */
    public function getBasename(): string
    {
        if (! isset($this->fileBasename)) {
            $this->fileBasename = pathinfo($this->getPath(), PATHINFO_BASENAME);
        }

        return $this->fileBasename;
    }

    /**
     * Get the attachment extension.
     */
    public function getExtension(): string
    {
        if (! isset($this->fileExtension)) {
            $this->fileExtension = pathinfo($this->getPath(), PATHINFO_EXTENSION);
        }

        return $this->fileExtension;
    }

    public function getMimeType(): string
    {
        return get_post_mime_type($this->getCoreObject()) ?: '';
    }

    /**
     * @param string $key The meta key to retrieve. By default, returns
     *                    data for all keys. Default empty.
     *
     * @return mixed
     */
    public function getField(string $key = '')
    {
        $value = apply_filters(Filter::ATTACHMENT_PRE_GET_META_FIELD, null, $key, $this);

        if ($value === null) {
            $value = get_post_meta($this->ID, $key, true);
        }

        return apply_filters(Filter::ATTACHMENT_GET_META_FIELD . '/' . $key, $value, $this);
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
        $result = apply_filters(Filter::ATTACHMENT_PRE_SET_META_FIELD, null, $key, $value, $previous);

        if ($result !== null) {
            return $result;
        }

        $result = update_post_meta($this->ID, $key, $value, $previous);

        return $result !== false;
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'ID' => $this->getID(),
            'title' => $this->getTitle(),
            'alt' => $this->getImageAlt(),
            'description' => $this->getDescription(),
            'caption' => $this->getCaption(),
            'path' => $this->getPath(),
            'url' => $this->getUrl(),
            'filename' => $this->getFilename(),
            'basename' => $this->getBasename(),
            'mime_type' => $this->getMimeType(),
            'extension' => $this->getExtension(),
        ];
    }

    public function __toString(): string
    {
        return $this->getUrl();
    }

    public function getCoreObject(): WP_Post
    {
        return $this->coreObject;
    }

    public function refreshCoreObject(): void
    {
        $this->coreObject = get_post($this->getID());
    }

    public function cleanCache(): void
    {
        clean_post_cache($this->coreObject);
    }
}
