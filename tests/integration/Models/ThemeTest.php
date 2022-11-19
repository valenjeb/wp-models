<?php

declare(strict_types=1);

namespace Devly\WP\Models\Tests;

use Devly\Exceptions\ObjectNotFoundException;
use Devly\WP\Models\Filter;
use Devly\WP\Models\Theme;
use WP_UnitTestCase;

use function add_filter;
use function preg_match;

class ThemeTest extends WP_UnitTestCase
{
    protected Theme $theme;

    protected function setUp(): void
    {
        $this->theme = new Theme();
    }

    public function testNewInstanceThrowsInvalidArgumentException(): void
    {
        $this->expectException(ObjectNotFoundException::class);

        new Theme('fake');
    }

    public function testGetThemeDisplayHeader(): void
    {
        $this->assertTrue(preg_match('/^\d\.\d(\.\d)?$/', $this->theme->display('Version')) === 1);
    }

    public function testGetThemeHeader(): void
    {
        $this->assertTrue(preg_match('/^\d\.\d(\.\d)?$/', $this->theme->get('Version')) === 1);
    }

    public function testIsActive(): void
    {
        $this->assertTrue($this->theme->active);

        $twentytwenty = new Theme('twentytwentythree');

        $this->assertFalse($twentytwenty->active);
    }

    public function testSetAndGetThemeMods(): void
    {
        $this->assertIsArray($this->theme->getOptions());
        $this->assertTrue($this->theme->setOption('foo', 'bar'));
        $this->assertEquals('bar', $this->theme->getOption('foo'));
        $this->theme->deleteOption('foo');
    }

    public function testFilterGetThemeMod(): void
    {
        add_filter(Filter::THEME_PRE_GET_OPTION, '__return_true', 0);

        $this->assertTrue($this->theme->getOption('foo'));

        remove_filter(Filter::THEME_PRE_GET_OPTION, '__return_true', 0);
    }

    public function testFilterPreSetThemeMod(): void
    {
        add_filter(Filter::THEME_PRE_SET_OPTION, '__return_true', 0);

        $this->theme->setOption('bar', 'foo');

        remove_filter(Filter::THEME_PRE_SET_OPTION, '__return_true', 0);

        $this->assertEmpty($this->theme->getOption('foo'));
    }

    public function testRemoveThemeMods(): void
    {
        $this->theme->setOption('debug', true);

        $this->theme->deleteOptions();

        $this->assertEmpty($this->theme->getOptions());
    }

    public function testImportThemeMods(): void
    {
        $twentytwenty = new Theme('twentytwentythree');
        $twentytwenty->setOption('twentytwenty_foo', 'bar');

        $this->theme->importOptions($twentytwenty);

        $this->assertEquals($twentytwenty->getOption('twentytwenty_foo'), $this->theme->getOption('twentytwenty_foo'));
    }
}
