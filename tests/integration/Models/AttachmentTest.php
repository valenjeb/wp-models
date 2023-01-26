<?php

declare(strict_types=1);

namespace Devly\WP\Database\Tests\Models;

use Devly\WP\Models\Attachment;
use WP_UnitTestCase;

use function dirname;
use function preg_match;

class AttachmentTest extends WP_UnitTestCase
{
    protected Attachment $post;

    protected function setUp(): void
    {
        $this->post = new Attachment(
            $this->factory()->attachment
                ->create_upload_object(dirname(__DIR__, 2) . '/lib/pickle.png')
        );
    }

    public function testGetUrl(): void
    {
        $this->assertTrue(preg_match('/^http:\/\/(.*)\/pickle(.*)?\.png$/', $this->post->getUrl()) === 1);
    }

    public function testGetPath(): void
    {
        $this->assertTrue(preg_match(
            '/^\/tmp\/wordpress\/wp-content\/uploads\/(.*)\/pickle(.*)?\.png$/',
            $this->post->getPath()
        ) === 1);
    }

    public function testGetMimeType(): void
    {
        $this->assertEquals('image/png', $this->post->mimeType);
    }

    public function testGetBasename(): void
    {
        $this->assertTrue(preg_match('/^pickle(.*)?\.png$/', $this->post->basename) === 1);
    }

    public function testGetFilename(): void
    {
        $this->assertTrue(preg_match('/^pickle(.*)?$/', $this->post->filename) === 1);
    }

    public function testGetExtension(): void
    {
        $this->assertEquals('png', $this->post->extension);
    }

    public function testSetAndGetTitle(): void
    {
        $this->assertTrue(preg_match('/^pickle(.*)?\.png$/', $this->post->title) === 1);

        $this->post->title = 'The image title';

        $this->assertEquals('The image title', $this->post->title);
    }

    public function testSetAndGetDescription(): void
    {
        $this->post->description = 'The image description';

        $this->assertEquals('The image description', $this->post->description);
    }

    public function testGetImageAlt(): void
    {
        $this->assertEquals('', $this->post->altText);

        $newAlt = 'Image alt text';

        $this->post->altText = $newAlt;

        $this->assertEquals($newAlt, $this->post->altText);
    }

    public function testGetCaption(): void
    {
        $this->post->caption = 'The image caption';

        $this->assertEquals('The image caption', $this->post->caption);
    }
}
