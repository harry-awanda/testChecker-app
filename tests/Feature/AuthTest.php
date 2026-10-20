<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class AuthTest extends TestCase {
    use RefreshDatabase;
    public function test_public_registration_is_not_available(): void { $this->get('/register')->assertNotFound(); }
    public function test_login_page_is_private_and_in_indonesian(): void {
        $this->withoutVite()->get('/login')->assertOk()->assertSee('Kata sandi')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }
    public function test_disabled_teacher_cannot_sign_in(): void {
        $user = User::factory()->create(['is_active' => false]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
    public function test_credentials_are_encrypted_and_hidden(): void {
        $user = User::factory()->create(['gemini_api_key' => 'test-only-placeholder']);
        $this->assertNotEquals('test-only-placeholder', $user->getRawOriginal('gemini_api_key'));
        $this->assertArrayNotHasKey('gemini_api_key', $user->toArray());
    }
}
