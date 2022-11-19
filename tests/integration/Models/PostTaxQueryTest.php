<?php

declare(strict_types=1);

namespace Devly\WP\Models\Tests;

use Devly\WP\Query\PostQuery;
use Devly\WP\Query\TaxQuery;
use WP_UnitTestCase;

class PostTaxQueryTest extends WP_UnitTestCase
{
    protected PostQuery $builder;

    protected function setUp(): void
    {
        $this->builder = new PostQuery();
    }

    protected function tearDown(): void
    {
        unset($this->builder);
    }

    public function testSimpleTaxQuery(): void
    {
        $this->builder->whereTax('people', 'slug', 'bob');

        $this->assertEquals([
            'tax_query' => [
                [
                    'taxonomy' => 'people',
                    'field' => 'slug',
                    'terms' => 'bob',
                    'include_children' => true,
                    'operator' => 'IN',
                ],
            ],
        ], $this->builder->getQueryArgs());
    }

    public function testTaxQueryWithAndRelation(): void
    {
        $this->builder->whereTax('movie_genre', 'slug', ['action', 'comedy']);
        $this->builder->andWhereTax('actor', 'term_id', [103, 115, 206], '!in');

        $this->assertEquals([
            'tax_query' => [
                'relation' => 'AND',
                [
                    'taxonomy' => 'movie_genre',
                    'field'    => 'slug',
                    'terms'    => ['action', 'comedy'],
                    'include_children' => true,
                    'operator' => 'IN',
                ],
                [
                    'taxonomy' => 'actor',
                    'field' => 'term_id',
                    'terms' => [103, 115, 206],
                    'include_children' => true,
                    'operator' => 'NOT IN',
                ],
            ],
        ], $this->builder->getQueryArgs());
    }

    public function testTaxQueryWithOrRelation(): void
    {
        $this->builder->whereTax('category', 'slug', ['quotes']);
        $this->builder->orWhereTax('post_format', 'slug', ['post-format-quote']);

        $this->assertEquals([
            'tax_query' => [
                'relation' => 'OR',
                [
                    'taxonomy' => 'category',
                    'field' => 'slug',
                    'terms' => ['quotes'],
                    'include_children' => true,
                    'operator' => 'IN',
                ],
                [
                    'taxonomy' => 'post_format',
                    'field' => 'slug',
                    'terms' => ['post-format-quote'],
                    'include_children' => true,
                    'operator' => 'IN',
                ],
            ],
        ], $this->builder->getQueryArgs());
    }

    public function testNestedTaxQuery(): void
    {
        $this->builder->wherePostType('post');
        $this->builder->whereTax('category', 'slug', ['quotes']);
        $this->builder->orWhereTax(static function (TaxQuery $query): void {
            $query->where('post_format', 'slug', ['post-format-quote']);
            $query->andWhere('category', 'slug', ['wisdom'], '!exists');
        });

        $this->assertEquals([
            'post_type' => 'post',
            'tax_query' => [
                'relation' => 'OR',
                [
                    'taxonomy' => 'category',
                    'field' => 'slug',
                    'terms' => ['quotes'],
                    'include_children' => true,
                    'operator' => 'IN',
                ],
                [
                    'relation' => 'AND',
                    [
                        'taxonomy' => 'post_format',
                        'field'    => 'slug',
                        'terms'    => ['post-format-quote'],
                        'include_children' => true,
                        'operator' => 'IN',
                    ],
                    [
                        'taxonomy' => 'category',
                        'field'    => 'slug',
                        'terms'    => ['wisdom'],
                        'include_children' => true,
                        'operator' => 'NOT EXISTS',
                    ],
                ],
            ],
        ], $this->builder->getQueryArgs());
    }
}
