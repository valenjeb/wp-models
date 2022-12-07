<?php

declare(strict_types=1);

namespace Devly\WP\Models\Tests;

use Devly\WP\Models\Attachment;
use Devly\WP\Models\Category;
use Devly\WP\Models\Filter;
use Devly\WP\Models\Post;
use Devly\WP\Models\Tag;
use Devly\WP\Models\User;
use Illuminate\Support\Collection;
use Throwable;
use WP_UnitTestCase;

use function add_filter;
use function preg_match;

class PostTest extends WP_UnitTestCase
{
    protected ?Post $post;
    /**
     * @var int[]
     */
    protected array $posts;

    protected function setUp(): void
    {
        $posts = $this->factory()->post->create_many(4);

        wp_update_post([
            'ID' => $posts[2],
            'post_author' => 1,
            'post_parent' => $posts[1],
        ]);

        $this->posts = $posts;
        $this->post = new Post($posts[2]);
    }

    public function testInsertAndDeletePost(): void
    {
        $post = Post::insert('Dummy post');

        $this->assertInstanceOf(Post::class, $post);
        $this->assertEquals('Dummy post', $post->title);

        $this->assertTrue(Post::delete($post));
    }

    public function testInsertWithPostDataArray(): void
    {
        $post = Post::insert(['post_title' => 'Dummy post']);

        $this->assertEquals('Dummy post', $post->title);

        Post::delete($post);
    }

    public function testInsertThrowsException(): void
    {
        $this->expectException(Throwable::class);

        Post::insert([]);
    }

    public function testDeleteReturnsFalse(): void
    {
        $this->assertFalse(Post::delete(0));
    }

    public function testGetAllPosts(): void
    {
        $posts = Post::all();

        $this->assertInstanceOf(Collection::class, $posts);
        $this->assertInstanceOf(Post::class, $posts[0]);
    }

    public function testFindBySlug(): void
    {
        $post = Post::findBySlug($this->post->slug);

        $this->assertInstanceOf(Post::class, $post);
        $this->assertEquals($post->ID, $this->post->ID);
    }

    public function testGetMagicProperties(): void
    {
        $this->assertIsString($this->post->title);
        $this->assertTrue(preg_match('/^[a-z0-9_.-]*$/', $this->post->slug) === 1);
        $this->assertTrue(preg_match('/^<p>(.*)<\/p>$/', $this->post->content) === 1);
        $this->assertTrue(preg_match('/^<p>(.*)<\/p>$/', $this->post->excerpt) === 1);
        $this->assertEquals('publish', $this->post->status);
    }

    public function testGetPostAuthor(): void
    {
        $author = $this->post->author;
        $this->assertInstanceOf(User::class, $author);
        $this->assertSame($author, $this->post->getAuthor());
    }

    public function testGetParentPost(): void
    {
        $this->assertInstanceOf(Post::class, $this->post->parent);
    }

    public function testSetAndGetCategories(): void
    {
        $this->assertInstanceOf(Collection::class, $this->post->categories);
        $this->assertInstanceOf(Category::class, $this->post->categories[0]);

        $terms = $this->factory()->term->create_many(2, ['taxonomy' => 'category']);
        $this->post->addCategories($terms);

        $this->assertEquals(3, $this->post->categories->count());

        $this->post->removeCategories($terms[0]);

        $this->assertEquals(2, $this->post->categories->count());

        $this->post->setCategories($terms[0]);

        $this->assertEquals(1, $this->post->categories->count());
    }

    public function testGetTagCollection(): void
    {
        $tags = $this->factory()->term->create_many(3);
        $this->post->addTags($tags[0]);
        $this->assertInstanceOf(Collection::class, $this->post->tags);
        $this->assertInstanceOf(Tag::class, $this->post->tags[0]);

        $this->post->addTags($tags[1]);

        $this->assertEquals(2, $this->post->tags->count());
        $this->assertEquals(1, $this->post->removeTags($tags[1])->count());

        $this->post->setTags($tags[0]);
        $this->assertEquals(1, $this->post->tags->count());
    }

    public function testSetGetAndDeleteMetaField(): void
    {
        $this->assertTrue(
            $this->post->setField('test_field', 'foo'),
            'Failed to set meta field.'
        );

        $this->assertEquals(
            'foo',
            $this->post->getField('test_field'),
            'Failed to get meta field value.'
        );

        $this->assertTrue(
            $this->post->deleteField('test_field'),
            'Failed to delete meta field.'
        );

        $this->assertEmpty($this->post->getField('test_field'));
    }

    public function testFilterPreGetField(): void
    {
        add_filter(Filter::POST_PRE_GET_META_FIELD, function ($value, $key, $post) {
            if ($post->ID !== $this->post->ID || $key !== 'foo') {
                return $value;
            }

            return 'filtered';
        }, 0, 3);

        $this->assertEquals('filtered', $this->post->getField('foo'));

        add_filter(Filter::POST_GET_META_FIELD . '/foo', function ($value, $post) {
            if ($post->ID !== $this->post->ID) {
                return $value;
            }

            return $value . ' twice';
        }, 0, 3);

        $this->assertEquals('filtered twice', $this->post->getField('foo'));
    }

    public function testFilterPreSetField(): void
    {
        add_filter(Filter::POST_PRE_SET_META_FIELD, function ($result, $key, $value, $previous, $post) {
            if ($post->ID !== $this->post->ID || $key !== 'bar') {
                return $result;
            }

            return true;
        }, 0, 5);

        $this->post->setField('bar', 'baz');
        $this->assertEmpty($this->post->getField('bar'));
    }

    public function testGetPreviousAndNextPost(): void
    {
        $previous = $this->post->previous_post;

        $this->assertNull($this->post->next_post);
        $this->assertInstanceOf(Post::class, $previous);
        $this->assertSame($previous, $this->post->getPreviousPost());

        $next = $previous->next_post;
        $this->assertInstanceOf(Post::class, $next);
        $this->assertEquals($previous->ID, $next->previous_post->ID);
    }

    public function testGetAndSetPostThumbnail(): void
    {
        $attachment = $this->factory()->attachment->create_and_get();

        $this->assertInstanceOf(Post::class, $this->post->setThumbnail($attachment));
        $this->assertInstanceOf(Attachment::class, $this->post->thumbnail);
        $this->assertTrue($this->post->hasThumbnail());
    }

    public function testGetType(): void
    {
        $this->assertEquals('post', $this->post->type);
        $this->assertTrue($this->post->is('post'));
    }
}
