<?php

declare(strict_types=1);

namespace Devly\WP\Models\Tests;

use Devly\Exceptions\ObjectNotFoundException;
use Devly\WP\Models\Category;
use Illuminate\Support\Collection;
use WP_UnitTestCase;

class TermTest extends WP_UnitTestCase
{
    protected Category $term;

    protected function setUp(): void
    {
        $this->term = new Category(1);
    }

    public function testQueryTerms(): void
    {
        $categories = Category::all();

        $this->assertEquals(1, $categories->count());
        $this->assertInstanceOf(Category::class, $categories[0]);
    }

    public function testGetByName(): void
    {
        $cat = Category::getByName('Uncategorized');

        $this->assertEquals(1, $cat->ID);
    }

    public function testGetByNameShouldTrowExceptionIfTermNameNotFound(): void
    {
        $this->expectException(ObjectNotFoundException::class);

        Category::getByName('fake');
    }

    public function testGetBySlug(): void
    {
        $cat = Category::getBySlug('uncategorized');

        $this->assertEquals(1, $cat->ID);
    }

    public function testGetBySlugShouldTrowExceptionIfTermNameNotFound(): void
    {
        $this->expectException(ObjectNotFoundException::class);

        Category::getBySlug('fake');
    }

    public function testGetById(): void
    {
        $cat = Category::getById(1);

        $this->assertEquals(1, $cat->ID);
    }

    public function testGetByIdShouldTrowExceptionIfTermNotFound(): void
    {
        $this->expectException(ObjectNotFoundException::class);

        Category::getById(100);
    }

    public function testGetManyById(): void
    {
        $ids        = $this->factory()->category->create_many(4);
        $collection = Category::getById($ids);

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals(4, $collection->count());
    }

    public function testTermLink(): void
    {
        $expected = 'http://example.org/?cat=1';
        $this->assertEquals($expected, $this->term->getLink());
        $this->assertEquals($expected, $this->term->link);
        $this->assertEquals($expected, $this->term->url);
    }

    public function testTermTaxonomy(): void
    {
        $this->assertEquals('category', $this->term->getTaxonomy());
    }

    public function testInsertTerm(): void
    {
        $cat = Category::insert('Test category');

        $this->assertInstanceOf(Category::class, $cat);
        $this->assertEquals('Test category', $cat->name);
        $this->assertEquals('test-category', $cat->slug);
    }
}
