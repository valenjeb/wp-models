<?php

declare(strict_types=1);

namespace Devly\WP\Models\Tests;

use Devly\WP\Models\Page;
use Devly\WP\Models\Post;
use WP_UnitTestCase;

class PageTest extends WP_UnitTestCase
{
    protected Page $post;

    public function testCreatePageObject(): void
    {
        $pageID = $this->factory()->post->create(['post_type' => 'page']);
        $page   = new Page($pageID);
        $this->assertInstanceOf(Post::class, $page);
    }

    public function testGetUsingMagicProperty(): void
    {
        $pageID = $this->factory()->post->create(['post_type' => 'page', 'post_title' => 'Sample Post']);
        $page   = new Page($pageID);
        $this->assertEquals('Sample Post', $page->title);
    }
}
