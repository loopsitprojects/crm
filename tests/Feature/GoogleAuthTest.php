<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('maintenance_mode', 0);
    }

    protected function tearDown(): void
    {
        Setting::set('maintenance_mode', 0);
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper to mock Socialite Google driver.
     */
    protected function mockSocialiteUser(string $id, string $email, string $name = 'Test User', ?string $avatar = null)
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn($id);
        $socialiteUser->shouldReceive('getEmail')->andReturn($email);
        $socialiteUser->shouldReceive('getName')->andReturn($name);
        $socialiteUser->shouldReceive('getAvatar')->andReturn($avatar);

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        return $socialiteUser;
    }

    public function test_login_page_renders_google_sign_in_button(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Sign in with Google');
        $response->assertSee(route('auth.google'));
    }

    public function test_redirect_to_google_fails_when_unconfigured(): void
    {
        Config::set('services.google.client_id', null);
        Config::set('services.google.client_secret', null);
        Setting::set('google_client_id', null);
        Setting::set('google_client_secret', null);
        putenv('GOOGLE_CLIENT_ID=');
        putenv('GOOGLE_CLIENT_SECRET=');
        unset($_ENV['GOOGLE_CLIENT_ID'], $_ENV['GOOGLE_CLIENT_SECRET'], $_SERVER['GOOGLE_CLIENT_ID'], $_SERVER['GOOGLE_CLIENT_SECRET']);

        $response = $this->get(route('auth.google'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email']);
    }

    public function test_redirect_to_google_redirects_when_configured(): void
    {
        Config::set('services.google.client_id', 'test-client-id');
        Config::set('services.google.client_secret', 'test-client-secret');

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route('auth.google'));

        $response->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    public function test_google_callback_logs_in_existing_user_and_saves_google_id_and_avatar(): void
    {
        Config::set('services.google.client_id', 'test-client-id');
        Config::set('services.google.client_secret', 'test-client-secret');

        $user = User::factory()->create([
            'email' => 'member@loopsintegrated.com',
            'name' => 'Existing Member',
            'google_id' => null,
            'avatar' => null,
            'role' => 'Staff',
        ]);

        $this->mockSocialiteUser(
            id: 'google-uid-12345',
            email: 'member@loopsintegrated.com',
            name: 'Existing Member Google',
            avatar: 'https://lh3.googleusercontent.com/avatar-test.jpg'
        );

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertEquals('google-uid-12345', $user->google_id);
        $this->assertEquals('https://lh3.googleusercontent.com/avatar-test.jpg', $user->avatar);

        // Verify activity was logged
        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'description' => 'Logged in via Google',
        ]);
    }

    public function test_google_callback_logs_in_user_already_linked_by_google_id(): void
    {
        Config::set('services.google.client_id', 'test-client-id');
        Config::set('services.google.client_secret', 'test-client-secret');

        $user = User::factory()->create([
            'email' => 'original@loopsintegrated.com',
            'google_id' => 'google-uid-already-linked',
            'role' => 'Manager',
        ]);

        $this->mockSocialiteUser(
            id: 'google-uid-already-linked',
            email: 'different-alias@loopsintegrated.com'
        );

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_callback_rejects_unregistered_email(): void
    {
        Config::set('services.google.client_id', 'test-client-id');
        Config::set('services.google.client_secret', 'test-client-secret');

        $this->mockSocialiteUser(
            id: 'google-uid-stranger',
            email: 'stranger@example.com'
        );

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_google_callback_handles_user_cancelling_consent(): void
    {
        $response = $this->get(route('auth.google.callback', ['error' => 'access_denied']));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_google_callback_blocks_standard_user_in_maintenance_mode(): void
    {
        Config::set('services.google.client_id', 'test-client-id');
        Config::set('services.google.client_secret', 'test-client-secret');
        Setting::set('maintenance_mode', 1);

        $user = User::factory()->create([
            'email' => 'staff@loopsintegrated.com',
            'role' => 'Staff',
        ]);

        $this->mockSocialiteUser(
            id: 'google-uid-staff',
            email: 'staff@loopsintegrated.com'
        );

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('maintenance'));
        $this->assertGuest();

        Setting::set('maintenance_mode', 0);
    }

    public function test_google_callback_allows_admin_in_maintenance_mode(): void
    {
        Config::set('services.google.client_id', 'test-client-id');
        Config::set('services.google.client_secret', 'test-client-secret');
        Setting::set('maintenance_mode', 1);

        $admin = User::factory()->create([
            'email' => 'admin@loopsintegrated.com',
            'role' => 'Finance Admin',
        ]);

        $this->mockSocialiteUser(
            id: 'google-uid-admin',
            email: 'admin@loopsintegrated.com'
        );

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);

        Setting::set('maintenance_mode', 0);
    }

    public function test_admin_can_update_google_auth_settings(): void
    {
        $admin = User::factory()->create([
            'role' => 'IT Admin',
        ]);

        $response = $this->actingAs($admin)->post(route('settings.google-auth.update'), [
            'google_client_id' => '123456-abcdef.apps.googleusercontent.com',
            'google_client_secret' => 'GOCSPX-secret123456',
        ]);

        $response->assertRedirect(route('settings.index', ['section' => 'google-auth']));
        $response->assertSessionHas('success');

        $this->assertEquals('123456-abcdef.apps.googleusercontent.com', Setting::get('google_client_id'));
        $this->assertEquals('GOCSPX-secret123456', Setting::get('google_client_secret'));
    }

    public function test_non_admin_cannot_update_google_auth_settings(): void
    {
        $hod = User::factory()->create([
            'role' => 'HOD',
        ]);

        $response = $this->actingAs($hod)->post(route('settings.google-auth.update'), [
            'google_client_id' => 'malicious-client-id',
            'google_client_secret' => 'malicious-secret',
        ]);

        $response->assertStatus(403);
    }
}
