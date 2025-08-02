<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tool;
use App\Models\ApiService;
use App\Models\ToolOrder;
use App\Models\ViewOrder;
use App\Models\License;
use App\Models\Transaction;
use App\Services\LicenseService;
use App\Services\JustAnotherPanelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class NewFeaturesTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);
    }

    /** @test */
    public function user_can_view_tool_store()
    {
        // Create test tools
        Tool::factory()->create([
            'name' => 'Test Tool',
            'slug' => 'test-tool',
            'price' => 100000,
            'is_active' => true
        ]);

        $response = $this->actingAs($this->user)
                        ->get(route('tools.index'));

        $response->assertStatus(200);
        $response->assertSee('Cửa hàng Tool');
        $response->assertSee('Test Tool');
    }

    /** @test */
    public function user_can_view_tool_detail()
    {
        $tool = Tool::factory()->create([
            'name' => 'Test Tool',
            'slug' => 'test-tool',
            'price' => 100000,
            'is_active' => true
        ]);

        $response = $this->actingAs($this->user)
                        ->get(route('tools.show', $tool->slug));

        $response->assertStatus(200);
        $response->assertSee($tool->name);
    }

    /** @test */
    public function user_can_purchase_tool()
    {
        $tool = Tool::factory()->create([
            'name' => 'Test Tool',
            'slug' => 'test-tool',
            'price' => 100000,
            'is_active' => true
        ]);

        // Simulate tool purchase
        $toolOrder = ToolOrder::create([
            'user_id' => $this->user->id,
            'tool_id' => $tool->id,
            'amount' => $tool->price,
            'status' => 'PENDING'
        ]);

        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'tool_order_id' => $toolOrder->id,
            'payment_code' => 'TOOL' . str_pad($toolOrder->id, 6, '0', STR_PAD_LEFT),
            'amount' => $tool->price,
            'currency' => 'VND',
            'payment_gateway' => 'VIETQR_VCB',
            'status' => 'PENDING',
            'description' => "Mua tool: {$tool->name}"
        ]);

        $this->assertDatabaseHas('tool_orders', [
            'id' => $toolOrder->id,
            'status' => 'PENDING'
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'PENDING'
        ]);
    }

    /** @test */
    public function license_is_created_when_tool_order_is_completed()
    {
        $tool = Tool::factory()->create([
            'name' => 'Test Tool',
            'price' => 100000,
            'is_active' => true
        ]);

        $toolOrder = ToolOrder::create([
            'user_id' => $this->user->id,
            'tool_id' => $tool->id,
            'amount' => $tool->price,
            'status' => 'PENDING'
        ]);

        // Complete the order
        $licenseService = new LicenseService();
        $license = $licenseService->createLicenseForOrder($toolOrder);

        $this->assertNotNull($license);
        $this->assertEquals($this->user->id, $license->user_id);
        $this->assertEquals($tool->id, $license->tool_id);
        $this->assertNotNull($license->license_key);
        $this->assertTrue($license->is_active);

        // Check that tool order is updated with license key
        $toolOrder->refresh();
        $this->assertEquals($license->license_key, $toolOrder->license_key);
    }

    /** @test */
    public function user_can_view_license_manager()
    {
        // Create a license for the user
        $tool = Tool::factory()->create();
        $license = License::factory()->create([
            'user_id' => $this->user->id,
            'tool_id' => $tool->id,
            'license_key' => 'TEST-1234-5678-9012',
            'is_active' => true
        ]);

        $response = $this->actingAs($this->user)
                        ->get(route('licenses.index'));

        $response->assertStatus(200);
        $response->assertSee('Quản lý License');
        $response->assertSee($license->license_key);
    }

    /** @test */
    public function user_can_view_view_services()
    {
        // Create test API service
        ApiService::factory()->create([
            'service_id' => 1001,
            'name' => 'YouTube Views',
            'category' => 'YouTube',
            'rate' => 0.50,
            'is_active' => true
        ]);

        $response = $this->actingAs($this->user)
                        ->get(route('view-services.index'));

        $response->assertStatus(200);
        $response->assertSee('Mua View & Tương tác');
        $response->assertSee('YouTube Views');
    }

    /** @test */
    public function user_can_create_view_order()
    {
        $apiService = ApiService::factory()->create([
            'service_id' => 1001,
            'name' => 'YouTube Views',
            'category' => 'YouTube',
            'rate' => 0.50,
            'min_quantity' => 100,
            'max_quantity' => 10000,
            'is_active' => true
        ]);

        $viewOrder = ViewOrder::create([
            'user_id' => $this->user->id,
            'api_service_id' => $apiService->id,
            'link' => 'https://youtube.com/watch?v=test',
            'quantity' => 1000,
            'total_amount' => 0.50 * 1000,
            'status' => 'PENDING'
        ]);

        $this->assertDatabaseHas('view_orders', [
            'id' => $viewOrder->id,
            'user_id' => $this->user->id,
            'status' => 'PENDING'
        ]);
    }

    /** @test */
    public function license_api_verify_works()
    {
        $tool = Tool::factory()->create();
        $license = License::factory()->create([
            'user_id' => $this->user->id,
            'tool_id' => $tool->id,
            'license_key' => 'TEST-1234-5678-9012',
            'is_active' => true
        ]);

        $response = $this->postJson('/api/license/verify', [
            'license_key' => $license->license_key,
            'device_id' => 'test-device-123',
            'device_name' => 'Test Device',
            'device_info' => ['os' => 'Windows 10']
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'License verified successfully'
        ]);

        // Check that license is activated
        $license->refresh();
        $this->assertEquals('test-device-123', $license->device_id);
        $this->assertEquals('Test Device', $license->device_name);
        $this->assertNotNull($license->activated_at);
    }

    /** @test */
    public function license_api_check_status_works()
    {
        $tool = Tool::factory()->create();
        $license = License::factory()->create([
            'user_id' => $this->user->id,
            'tool_id' => $tool->id,
            'license_key' => 'TEST-1234-5678-9012',
            'device_id' => 'test-device-123',
            'is_active' => true,
            'activated_at' => now()
        ]);

        $response = $this->postJson('/api/license/check-status', [
            'license_key' => $license->license_key,
            'device_id' => 'test-device-123'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'License is active'
        ]);
    }

    /** @test */
    public function license_service_generates_unique_keys()
    {
        $licenseService = new LicenseService();
        
        $key1 = $licenseService->generateLicenseKey();
        $key2 = $licenseService->generateLicenseKey();
        
        $this->assertNotEquals($key1, $key2);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $key1);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $key2);
    }
}
