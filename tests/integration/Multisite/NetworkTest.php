<?php

declare(strict_types=1);

namespace Devly\WP\Database\Tests\Integration\Multisite;

use Devly\WP\Models\Network;
use Devly\WP\Models\Site;
use WP_Site;
use WP_UnitTestCase;

class NetworkTest extends WP_UnitTestCase
{
    protected Network $network;

    protected function setUp(): void
    {
        $this->network = new Network();
    }

    public function testGetSites(): void
    {
        $sites = $this->network->getSites();

        $this->assertIsArray($sites);
        $this->assertInstanceOf(Site::class, $sites[0]);

        $sites = $this->network->getSites(['fields' => 'ids']);
        $this->assertIsInt($sites[0]);

        $sites = $this->network->getSites([], false);
        $this->assertInstanceOf(WP_Site::class, $sites[0]);
    }

    public function testSetGetAndDeleteOption(): void
    {
        $this->network->setOption('foo', 'bar');
        $this->assertEquals('bar', $this->network->getOption('foo'));
        $this->network->deleteOption('foo');
        $this->assertEmpty($this->network->getOption('foo'));
    }
}
