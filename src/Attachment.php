<?php

declare(strict_types=1);

namespace Devly\WP\Models;

use Devly\Exceptions\ObjectNotFoundException;
use Nette\SmartObject;
use RuntimeException;
use WP_Error;
use WP_Post;

use function explode;
use function pathinfo;
use function sprintf;

use const PATHINFO_BASENAME;
use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;

/**
 * @property-read int $ID
 * @property-read string $url
 * @property string $altText
 * @property string $title
 * @property string $description
 * @property string $caption
 * @property-read string $basename
 * @property-read string $filename
 * @property-read string $extension
 * @property-read string $mimeType
 * @property-read WP_Post $coreObject
 */
class Attachment
{
    use SmartObject;

    protected WP_Post $coreObject;
    protected string $url;
    protected string $path;
    protected string $basename;
    protected string $filename;
    protected string $extension;

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
     * @throws RuntimeException
     */
    public function update(array $data): bool
    {
        $data = wp_parse_args($data, [
            'ID'           => $this->getID(),
        ]);

        $result = wp_update_post($data, true);

        if ($result instanceof WP_Error) {
            throw new RuntimeException($result->get_error_message());
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
     *
     * @throws RuntimeException if the current attachment object is not an image.
     */
    public function getAltText(): string
    {
        $type = explode('/', $this->getMimeType())[0];

        if ($type !== 'image') {
            throw new RuntimeException(sprintf('Attachment "%s" is not an image.', $this->getID()));
        }

        return $this->getField('_wp_attachment_image_alt') ?: '';
    }

    /**
     * Sets the attachment description alt.
     *
     * @throws RuntimeException if the current attachment object is not an image.
     */
    public function setAltText(string $text): self
    {
        $type = explode('/', $this->getMimeType())[0];

        if ($type !== 'image') {
            throw new RuntimeException(sprintf('Attachment "%s" is not an image.', $this->getID()));
        }

        $this->setField('_wp_attachment_image_alt', $text);

        return $this;
    }

    /**
     * Retrieves the attachment file url.
     */
    public function getUrl(): string
    {
        if (! isset($this->url)) {
            $this->url = wp_get_attachment_url($this->getID()) ?: '';
        }

        return $this->url;
    }

    /**
     * Retrieves the attachment file path.
     */
    public function getPath(): string
    {
        if (! isset($this->path)) {
            $this->path = get_attached_file($this->getID()) ?: '';
        }

        return $this->path;
    }

    /**
     * Get the attachment file name.
     */
    public function getFilename(): string
    {
        if (! isset($this->filename)) {
            $this->filename = pathinfo($this->getPath(), PATHINFO_FILENAME);
        }

        return $this->filename;
    }

    /**
     * Get the attachment base name.
     */
    public function getBasename(): string
    {
        if (! isset($this->basename)) {
            $this->basename = pathinfo($this->getPath(), PATHINFO_BASENAME);
        }

        return $this->basename;
    }

    /**
     * Get the attachment extension.
     */
    public function getExtension(): string
    {
        if (! isset($this->extension)) {
            $this->extension = pathinfo($this->getPath(), PATHINFO_EXTENSION);
        }

        return $this->extension;
    }

    public function getMimeType(): string
    {
        return get_post_mime_type($this->getCoreObject()) ?: '';
    }

    /**
     * Get an HTML output representing the attachment.
     *
     * @param array<string, mixed> $attr
     */
    public function html(array $attr = []): string
    {
        $type = explode('/', $this->getMimeType())[0];
        if ($type === 'video') {
            return $this->video($attr);
        }

        if ($type === 'audio') {
            return $this->audio($attr);
        }

        if ($type === 'image') {
            $size = 'full';
            if (isset($attr['size'])) {
                $size = $attr['size'];
                unset($attr['size']);
            }

            return $this->image($size, $attr);
        }

        return '';
    }

    /**
     * Returns an HTML video player representing a video attachment.
     *
     * This implements the functionality of the Video Shortcode
     * for displaying WordPress mp4s in a post.
     *
     * @param array<string, mixed> $attr
     *
     * @throws RuntimeException
     */
    public function video(array $attr = []): string
    {
        $type = explode('/', $this->getMimeType())[0];

        if ($type !== 'video') {
            throw new RuntimeException(sprintf('Attachment [%s] is not a video file.', $this->getUrl()));
        }

        $attr['src'] = $this->getUrl();

        return wp_video_shortcode($attr);
    }

    /**
     * Returns an HTML audio player representing an audio attachment.
     *
     * This implements the functionality of the Audio Shortcode
     * for displaying WordPress mp3s in a post.
     *
     * @param array<string, mixed> $attr
     *
     * @throws RuntimeException
     */
    public function audio(array $attr = []): string
    {
        $type = explode('/', $this->getMimeType())[0];

        if ($type !== 'audio') {
            throw new RuntimeException(sprintf('Attachment [%s] is not a audio file.', $this->getUrl()));
        }

        $attr['src'] = $this->getUrl();

        return wp_audio_shortcode($attr);
    }

    /**
     * Returns an HTML img element representing an image attachment.
     *
     * @param array<string, mixed> $attr
     *
     * @throws RuntimeException
     */
    public function image(string $size = 'full', array $attr = []): string
    {
        $type = explode('/', $this->getMimeType())[0];

        if ($type !== 'image') {
            throw new RuntimeException(sprintf('Attachment [%s] is not an image file.', $this->getUrl()));
        }

        return wp_get_attachment_image($this->getID(), $size, false, $attr);
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
            'alt' => $this->getAltText(),
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
