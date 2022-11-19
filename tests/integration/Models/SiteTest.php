<?php

declare(strict_types=1);

namespace Devly\WP\Models\Tests;

use Devly\WP\Models\Filter;
use Devly\WP\Models\Site;
use Devly\WP\Models\Theme;
use LogicException;
use WP_UnitTestCase;

use function add_filter;

class SiteTest extends WP_UnitTestCase
{
    protected Site $theme;

    protected function setUp(): void
    {
        $this->site = new Site();
    }

    public function testGetBlogInfo(): void
    {
        $this->assertEquals('Test Blog', $this->site->info('name'));
    }

    public function testGetNetworkThrowLogicError(): void
    {
        $this->expectException(LogicException::class);
        $this->site->getNetwork();
    }

    public function testGetBodyCssClass(): void
    {
        $this->assertEquals('foo bar', $this->site->getBodyClass('foo', 'bar'));
    }

    public function testGetTheme(): void
    {
        $theme = $this->site->getTheme();
        $this->assertInstanceOf(Theme::class, $theme);
    }

    public function testSetOption(): void
    {
        $this->site->setOption('foo', 'bar');

        $this->assertEquals('bar', $this->site->getOption('foo'));
        $this->assertTrue($this->site->deleteOption('foo'));
        $this->assertEmpty($this->site->getOption('foo'));
    }

    public function testFilterGetOption(): void
    {
        $this->site->setOption('foo', 'baz');

        add_filter(Filter::SITE_PRE_GET_OPTION, '__return_true', 0, 4);

        $this->assertTrue($this->site->getOption('foo'));

        remove_filter(Filter::SITE_PRE_GET_OPTION, '__return_true', 0);

        $this->site->deleteOption('foo');
    }

    public function testFilterSetOption(): void
    {
        add_filter(Filter::SITE_PRE_SET_OPTION, '__return_true', 0, 4);

        $this->site->setOption('foo', 'bar');

        $this->assertEmpty($this->site->getOption('foo'));

        remove_filter(Filter::SITE_PRE_SET_OPTION, '__return_true', 0);
    }

    public function testFilterDeleteOption(): void
    {
        add_filter(Filter::SITE_PRE_DELETE_OPTION, '__return_true', 0, 3);

        $this->site->setOption('foo', 'bar');
        $this->site->deleteOption('foo');

        remove_filter(Filter::SITE_PRE_SET_OPTION, '__return_true', 0);

        $this->assertEquals('bar', $this->site->getOption('foo'));
    }
}
