namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_admin_area()
    {
        $user = User::factory()->create(); // default role is 'user'

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $response->assertStatus(403);
    }
    
    public function test_admin_can_access_admin_area()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
    }
} 