<?php

declare(strict_types=1);

namespace Devly\WP\Models\Tests;

use Devly\WP\Models\Comment;
use Illuminate\Support\Collection;
use WP_UnitTestCase;

class CommentTest extends WP_UnitTestCase
{
    protected Comment $comment;

    protected function setUp(): void
    {
        $post = $this->factory()->post->create_and_get();
        $this->factory()->comment->create_post_comments($post->ID, 2);

        $comments = get_comments(['post_id' => $post->ID]);

        wp_update_comment(['comment_ID' => $comments[0]->comment_ID, 'comment_parent' => $comments[1]->comment_ID]);

        $this->comment = new Comment($comments[1]);
    }

    public function testGetChildComments(): void
    {
        $children = $this->comment->getChildren();
        $this->assertInstanceOf(Collection::class, $children);
        $this->assertInstanceOf(Comment::class, $children[0]);
    }

    public function testGetParent(): void
    {
        $children = $this->comment->getChildren()[0];
        $this->assertInstanceOf(Comment::class, $children->parent);
    }

    public function testIsApproved(): void
    {
        $this->assertTrue($this->comment->isApproved());
    }

    public function testSetStatus(): void
    {
        $this->comment->setStatus('hold');

        $this->assertFalse($this->comment->isApproved());
    }
}
