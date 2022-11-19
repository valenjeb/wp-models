<?php

declare(strict_types=1);

namespace Devly\WP\Database\Tests\Integration\Multisite;

use Devly\WP\Models\Network;
use Devly\WP\Models\Site;
use WP_UnitTestCase;

class MultisiteTest extends WP_UnitTestCase
{
    protected Site $theme;
    protected Site $site2;

    protected function setUp(): void
    {
        $sites = get_sites(['fields' => 'ids']);

        $sites       = $this->factory()->blog->create_many(2);
        $this->site1 = new Site();
        $this->site2 = new Site($sites[0]);
    }

    public function testGetNetwork(): void
    {
        $this->assertInstanceOf(Network::class, $this->site2->getNetwork());
    }

    public function testGetSiteID(): void
    {
        $this->assertNotEquals($this->site2->ID, $this->site1->ID);
    }

    public function testSetSiteOption(): void
    {
        $this->site1->setOption('foo', 'bar');
        $this->site2->setOption('foo', 'baz');

        $this->assertEquals('bar', $this->site1->getOption('foo'));
        $this->assertNotEquals($this->site1->getOption('foo'), $this->site2->getOption('foo'));
    }

    public function testGetSiteUrl(): void
    {
        $this->assertNotEquals($this->site1->url, $this->site2->url);
    }
}
