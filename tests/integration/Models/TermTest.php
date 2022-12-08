<?php

declare(strict_types=1);

namespace Devly\WP\Models\Tests;

use Devly\WP\Models\Category;
use WP_UnitTestCase;

class TermTest extends WP_UnitTestCase
{
    public function testQueryTerms(): void
    {
        $categories = Category::all();

        $this->assertEquals(1, $categories->count());
        $this->assertInstanceOf(Category::class, $categories[0]);
    }

    public function testGetByName(): void
    {
        $cat = Category::getByName('Uncategorized');

        $this->assertEquals('Uncategorized', $cat->name);
    }

    public function testGetBySlug(): void
    {
        $cat = Category::getBySlug('uncategorized');

        $this->assertEquals('Uncategorized', $cat->name);
    }

    public function testGetById(): void
    {
        $cat = Category::getById(1);

        $this->assertEquals('Uncategorized', $cat->name);
    }

    public function testTermLink(): void
    {
        $cat = Category::getById(1);

        $expected = 'http://example.org/?cat=1';
        $this->assertEquals($expected, $cat->getLink());
        $this->assertEquals($expected, $cat->link);
        $this->assertEquals($expected, $cat->url);
    }

    public function testTermTaxonomy(): void
    {
        $cat = Category::getById(1);

        $this->assertEquals('category', $cat->getTaxonomy());
    }

    public function testInsertTerm(): void
    {
        $cat = Category::insert('Test category');

        $this->assertInstanceOf(Category::class, $cat);
        $this->assertEquals('Test category', $cat->name);
        $this->assertEquals('test-category', $cat->slug);
    }
}
