<?php

namespace Tests\Feature\Services;

use App\Models\VpsServer;
use App\Services\VpsAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VpsAllocationServiceTest extends TestCase
{
    use RefreshDatabase;

    private VpsAllocationService $allocationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->allocationService = new VpsAllocationService();
    }

    /** @test */
    public function it_selects_the_vps_with_the_lowest_cpu_load_from_eligible_servers()
    {
        // Arrange: Create servers with different loads
        $overloadedVps = VpsServer::factory()->create(['status' => 'ACTIVE']);
        $overloadedVps->stats()->create($this->getStats(cpuLoad: 90.0)); // Overloaded CPU

        $highRamVps = VpsServer::factory()->create(['status' => 'ACTIVE']);
        $highRamVps->stats()->create($this->getStats(ramUsed: 900, ramTotal: 1000)); // Overloaded RAM

        $bestVps = VpsServer::factory()->create(['status' => 'ACTIVE']);
        $bestVps->stats()->create($this->getStats(cpuLoad: 20.0)); // Low load

        $goodVps = VpsServer::factory()->create(['status' => 'ACTIVE']);
        $goodVps->stats()->create($this->getStats(cpuLoad: 50.0)); // Higher load than bestVps

        // Act: Find the optimal VPS
        $chosenVps = $this->allocationService->findOptimalVps();

        // Assert: It should choose the one with the lowest CPU load
        $this->assertNotNull($chosenVps);
        $this->assertEquals($bestVps->id, $chosenVps->id);
    }

    /** @test */
    public function it_returns_null_when_all_servers_are_overloaded()
    {
        // Arrange: Create only overloaded servers
        $vps1 = VpsServer::factory()->create(['status' => 'ACTIVE']);
        $vps1->stats()->create($this->getStats(cpuLoad: 95.0));

        $vps2 = VpsServer::factory()->create(['status' => 'ACTIVE']);
        $vps2->stats()->create($this->getStats(ramUsed: 980, ramTotal: 1000));

        // Act: Find the optimal VPS
        $chosenVps = $this->allocationService->findOptimalVps();

        // Assert: It should return null
        $this->assertNull($chosenVps);
    }

    /** @test */
    public function it_considers_a_server_with_no_stats_as_eligible()
    {
        // Arrange: Create a server with no stats history
        $newVps = VpsServer::factory()->create(['status' => 'ACTIVE']);

        // Act: Find the optimal VPS
        $chosenVps = $this->allocationService->findOptimalVps();

        // Assert: The new server should be chosen as it's the only one
        $this->assertNotNull($chosenVps);
        $this->assertEquals($newVps->id, $chosenVps->id);
    }

    private function getStats(float $cpuLoad = 10.0, int $ramUsed = 200, int $ramTotal = 2048, int $diskUsed = 5, int $diskTotal = 25): array
    {
        return [
            'cpu_load' => $cpuLoad,
            'ram_used_mb' => $ramUsed,
            'ram_total_mb' => $ramTotal,
            'disk_used_gb' => $diskUsed,
            'disk_total_gb' => $diskTotal,
        ];
    }
}
