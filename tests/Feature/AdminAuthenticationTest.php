<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_log_in_and_open_the_dashboard(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $this->post(route('admin.login.store'), [
            'email' => 'admin@example.test',
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs(User::where('email', 'admin@example.test')->first(), 'web');
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_operator_cannot_log_in_to_the_admin_area(): void
    {
        User::factory()->operator()->create([
            'email' => 'operator@example.test',
            'password' => 'password',
        ]);

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), [
                'email' => 'operator@example.test',
                'password' => 'password',
            ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_admin_area_requires_admin_authentication(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->operator()->create(), 'web')
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_login_and_admin_section_pages_render(): void
    {
        $this->get(route('admin.login'))->assertOk();

        $this->actingAs(User::factory()->admin()->create(), 'web');

        foreach ([
            'admin.contests.index',
            'admin.problems.index',
            'admin.teams.index',
            'admin.submissions.index',
            'admin.questions.index',
        ] as $routeName) {
            $this->get(route($routeName))->assertOk();
        }
    }

    public function test_authenticated_admin_is_redirected_away_from_login_and_can_log_out(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'web')
            ->get(route('admin.login'))
            ->assertRedirect(route('admin.dashboard'));

        $this->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('web');
    }
}
