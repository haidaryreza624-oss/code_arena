<?php

namespace Tests\Feature;

use App\Models\Contest;
use App\Models\ContestParticipation;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminTeamManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_create_list_filter_show_update_and_delete_team(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post(route('admin.teams.store'), ['name' => 'Alpha Team'])
            ->assertRedirect();

        $team = Team::where('name', 'Alpha Team')->firstOrFail();

        $this->get(route('admin.teams.index'))->assertOk()->assertSee('Alpha Team');
        $this->get(route('admin.teams.show', $team))->assertOk()->assertSee('Alpha Team');

        $this->put(route('admin.teams.update', $team), ['name' => 'Alpha Updated'])
            ->assertRedirect(route('admin.teams.show', $team));

        $this->delete(route('admin.teams.destroy', $team))
            ->assertRedirect(route('admin.teams.index'));
        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
    }

    public function test_admin_can_add_edit_and_delete_team_members(): void
    {
        $team = Team::create(['name' => 'Beta Team']);
        $this->actingAs($this->admin, 'web');

        $this->post(route('admin.teams.members.store', $team), [
            'name' => 'Alice',
            'email' => 'alice@example.test',
            'ordinal' => 0,
        ])->assertRedirect(route('admin.teams.show', $team));

        $member = TeamMember::where('email', 'alice@example.test')->firstOrFail();

        $this->put(route('admin.teams.members.update', [$team, $member]), [
            'name' => 'Alice Smith',
            'email' => 'alice.smith@example.test',
            'ordinal' => 2,
        ])->assertRedirect(route('admin.teams.show', $team));

        $this->assertDatabaseHas('team_members', [
            'id' => $member->id,
            'name' => 'Alice Smith',
            'ordinal' => 2,
        ]);

        $this->delete(route('admin.teams.members.destroy', [$team, $member]))
            ->assertRedirect(route('admin.teams.show', $team));
        $this->assertDatabaseMissing('team_members', ['id' => $member->id]);
    }

    public function test_duplicate_member_email_returns_an_inline_validation_error(): void
    {
        $team = Team::create(['name' => 'Duplicate Team']);
        TeamMember::create([
            'team_id' => $team->id,
            'name' => 'Existing Member',
            'email' => 'duplicate@example.test',
            'ordinal' => 0,
        ]);

        $this->actingAs($this->admin, 'web')
            ->from(route('admin.teams.show', $team))
            ->post(route('admin.teams.members.store', $team), [
                'name' => 'Another Member',
                'email' => 'duplicate@example.test',
                'ordinal' => 1,
            ])
            ->assertRedirect(route('admin.teams.show', $team))
            ->assertSessionHasErrorsIn('addMember', ['email']);

        $this->get(route('admin.teams.show', $team))
            ->assertOk()
            ->assertSee('The email has already been taken')
            ->assertSee('duplicate@example.test');
    }

    public function test_active_contest_team_cannot_be_deleted_individually_or_in_bulk(): void
    {
        $team = Team::create(['name' => 'Locked Team']);
        $contest = Contest::factory()->running()->create([
            'created_by' => $this->admin->id,
        ]);
        ContestParticipation::factory()->create([
            'contest_id' => $contest->id,
            'team_id' => $team->id,
        ]);

        $this->actingAs($this->admin, 'web');
        $this->delete(route('admin.teams.destroy', $team))->assertStatus(423);
        $this->post(route('admin.teams.bulk-delete'), ['team_ids' => [$team->id]])
            ->assertRedirect();
        $this->assertDatabaseHas('teams', ['id' => $team->id]);
    }

    public function test_team_members_are_locked_during_an_active_contest(): void
    {
        $team = Team::create(['name' => 'Member Locked Team']);
        $member = TeamMember::create([
            'team_id' => $team->id,
            'name' => 'Existing Member',
            'email' => 'existing@example.test',
            'ordinal' => 0,
        ]);
        $contest = Contest::factory()->running()->create([
            'created_by' => $this->admin->id,
        ]);
        ContestParticipation::factory()->create([
            'contest_id' => $contest->id,
            'team_id' => $team->id,
        ]);

        $this->actingAs($this->admin, 'web');

        $this->get(route('admin.teams.show', $team))
            ->assertOk()
            ->assertSee('Members are locked while this team is participating')
            ->assertDontSee('data-bs-target="#add-member"')
            ->assertDontSee('data-bs-target="#edit-member-'.$member->id.'"')
            ->assertDontSee('name="ordinal"', false)
            ->assertDontSee('name="email" type="email"', false);

        $this->post(route('admin.teams.members.store', $team), [
            'name' => 'New Member',
            'email' => 'new@example.test',
            'ordinal' => 1,
        ])->assertStatus(423);

        $this->put(route('admin.teams.members.update', [$team, $member]), [
            'name' => 'Changed Member',
            'email' => 'changed@example.test',
            'ordinal' => 0,
        ])->assertStatus(423);

        $this->delete(route('admin.teams.members.destroy', [$team, $member]))
            ->assertStatus(423);

        $this->assertDatabaseHas('team_members', [
            'id' => $member->id,
            'name' => 'Existing Member',
            'email' => 'existing@example.test',
        ]);
    }

    public function test_team_in_an_ended_contest_can_be_changed_and_deleted(): void
    {
        $team = Team::create(['name' => 'Ended Contest Team']);
        $member = TeamMember::create([
            'team_id' => $team->id,
            'name' => 'Original Member',
            'email' => 'ended.member@example.test',
            'ordinal' => 0,
        ]);
        $contest = Contest::factory()->ended()->create([
            'created_by' => $this->admin->id,
        ]);
        ContestParticipation::factory()->create([
            'contest_id' => $contest->id,
            'team_id' => $team->id,
        ]);

        $this->actingAs($this->admin, 'web')
            ->put(route('admin.teams.members.update', [$team, $member]), [
                'name' => 'Updated Member',
                'email' => 'updated.member@example.test',
                'ordinal' => 0,
            ])->assertRedirect(route('admin.teams.show', $team));

        $this->delete(route('admin.teams.destroy', $team))
            ->assertRedirect(route('admin.teams.index'));

        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
        $this->assertDatabaseMissing('team_members', ['id' => $member->id]);
        $this->assertDatabaseMissing('contest_participations', ['team_id' => $team->id]);
    }

    public function test_csv_import_does_not_add_members_to_teams_in_active_contests(): void
    {
        $team = Team::create(['name' => 'CSV Locked Team']);
        $contest = Contest::factory()->running()->create([
            'created_by' => $this->admin->id,
        ]);
        ContestParticipation::factory()->create([
            'contest_id' => $contest->id,
            'team_id' => $team->id,
        ]);

        $csv = "team_name,member_name,member_email\n";
        $csv .= "CSV Locked Team,Blocked Member,blocked@example.test\n";

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.teams.import'), [
                'csv_file' => UploadedFile::fake()->createWithContent('teams.csv', $csv),
            ])
            ->assertRedirect(route('admin.teams.index'))
            ->assertSessionHas('success', fn ($message) => str_contains($message, '1 member row(s) skipped'));

        $this->assertDatabaseMissing('team_members', ['email' => 'blocked@example.test']);
    }

    public function test_bulk_delete_removes_unlocked_team_and_skips_locked_team(): void
    {
        $unlocked = Team::create(['name' => 'Unlocked Team']);
        $locked = Team::create(['name' => 'Locked Team']);
        $contest = Contest::factory()->approved()->create([
            'created_by' => $this->admin->id,
        ]);
        ContestParticipation::factory()->create([
            'contest_id' => $contest->id,
            'team_id' => $locked->id,
        ]);

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.teams.bulk-delete'), [
                'team_ids' => [$unlocked->id, $locked->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('teams', ['id' => $unlocked->id]);
        $this->assertDatabaseHas('teams', ['id' => $locked->id]);
    }

    public function test_csv_import_creates_team_and_member_rows(): void
    {
        $csv = "team_name,member_name,member_email\n";
        $csv .= "Imported Team,Imported Member,imported.member@example.test\n";

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.teams.import'), [
                'csv_file' => UploadedFile::fake()->createWithContent('teams.csv', $csv),
            ])
            ->assertRedirect(route('admin.teams.index'));

        $team = Team::where('name', 'Imported Team')->firstOrFail();
        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'name' => 'Imported Member',
            'email' => 'imported.member@example.test',
        ]);
    }

    public function test_team_list_can_be_filtered_by_contest(): void
    {
        $contest = Contest::factory()->draft()->create([
            'created_by' => $this->admin->id,
        ]);
        $memberTeam = Team::create(['name' => 'Participating Team']);
        ContestParticipation::factory()->create([
            'contest_id' => $contest->id,
            'team_id' => $memberTeam->id,
        ]);
        Team::create(['name' => 'Other Team']);

        $this->actingAs($this->admin, 'web')
            ->get(route('admin.teams.index', ['contest_id' => $contest->id]))
            ->assertOk()
            ->assertSee('Participating Team')
            ->assertDontSee('Other Team');
    }
}
