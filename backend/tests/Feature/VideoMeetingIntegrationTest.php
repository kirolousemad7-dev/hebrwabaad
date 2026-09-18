<?php

namespace Tests\Feature;

use App\Enums\MeetingProvider;
use App\Enums\MeetingStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\GoogleCalendarConnection;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VideoMeetingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'google-client',
            'services.google.client_secret' => 'google-secret',
            'services.google.redirect_uri' => 'http://localhost/api/google-calendar/callback',
            'services.zoom.client_id' => 'zoom-client',
            'services.zoom.client_secret' => 'zoom-secret',
            'services.zoom.account_id' => 'zoom-account',
            'services.zoom.redirect_uri' => 'http://localhost/api/zoom/callback',
        ]);
    }

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    /**
     * @return array{manager: User, developer: User, project: Project, task: Task}
     */
    private function seededTask(): array
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create(['email' => 'dev@hebr.test']);
        $project = Project::factory()->create([
            'account_manager_id' => $manager->id,
            'customer_id' => User::factory()->create(['role' => UserRole::Customer->value])->id,
        ]);
        $task = Task::factory()->create([
            'title' => 'جلسة مراجعة',
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
            'project_id' => $project->id,
            'priority' => TaskPriority::Medium,
            'status' => TaskStatus::Todo,
        ]);

        return compact('manager', 'developer', 'project', 'task');
    }

    private function connectGoogle(User $user): void
    {
        $connection = new GoogleCalendarConnection([
            'user_id' => $user->id,
            'google_email' => 'host@gmail.com',
            'calendar_id' => 'primary',
            'sync_enabled' => true,
            'meet_enabled' => true,
            'connected_at' => now(),
            'token_expires_at' => now()->addHour(),
        ]);
        $connection->setAccessToken('g-access');
        $connection->setRefreshToken('g-refresh');
        $connection->save();
    }

    public function test_providers_endpoint_reports_configuration_without_secrets(): void
    {
        $manager = User::factory()->accountManager()->create();

        $data = $this->asUser($manager)
            ->getJson('/api/meetings/providers')
            ->assertOk()
            ->json('data.providers');

        $encoded = json_encode($data);
        $this->assertStringNotContainsString('zoom-secret', (string) $encoded);
        $this->assertStringNotContainsString('google-secret', (string) $encoded);

        $byProvider = collect($data)->keyBy('provider');
        $this->assertTrue($byProvider['ZOOM']['configured']);
        $this->assertTrue($byProvider['GOOGLE_MEET']['configured']);
        $this->assertTrue($byProvider['NONE']['configured']);
    }

    public function test_create_zoom_meeting_with_mocked_api(): void
    {
        ['manager' => $manager, 'developer' => $developer, 'task' => $task] = $this->seededTask();

        Http::fake([
            'zoom.us/oauth/token' => Http::response([
                'access_token' => 'zoom-token',
                'expires_in' => 3600,
            ], 200),
            'api.zoom.us/v2/users/me/meetings' => Http::response([
                'id' => 987654321,
                'join_url' => 'https://zoom.us/j/987654321',
                'start_url' => 'https://zoom.us/s/987654321',
                'password' => 'abc',
            ], 201),
        ]);

        $payload = $this->asUser($manager)
            ->postJson('/api/meetings', [
                'provider' => MeetingProvider::Zoom->value,
                'title' => 'اجتماع Zoom',
                'start_at' => now()->addDay()->setTime(11, 0)->toIso8601String(),
                'end_at' => now()->addDay()->setTime(12, 0)->toIso8601String(),
                'timezone' => 'Africa/Cairo',
                'task_id' => $task->id,
                'participant_ids' => [$developer->id],
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame(MeetingProvider::Zoom->value, $payload['provider']);
        $this->assertSame('https://zoom.us/j/987654321', $payload['join_url']);
        $this->assertSame('https://zoom.us/s/987654321', $payload['host_url']);
        $this->assertSame('987654321', $payload['meeting_id']);
        $this->assertDatabaseHas('meetings', [
            'task_id' => $task->id,
            'provider' => MeetingProvider::Zoom->value,
            'meeting_id' => '987654321',
        ]);
    }

    public function test_create_google_meet_stores_conference_url(): void
    {
        ['manager' => $manager, 'task' => $task] = $this->seededTask();
        $this->connectGoogle($manager);

        Http::fake([
            'www.googleapis.com/calendar/v3/calendars/*' => Http::response([
                'id' => 'evt-meet-1',
                'hangoutLink' => 'https://meet.google.com/abc-defg-hij',
                'htmlLink' => 'https://calendar.google.com/event?eid=evt-meet-1',
                'conferenceData' => [
                    'conferenceId' => 'abc-defg-hij',
                    'entryPoints' => [
                        ['entryPointType' => 'video', 'uri' => 'https://meet.google.com/abc-defg-hij'],
                    ],
                ],
            ], 200),
        ]);

        $payload = $this->asUser($manager)
            ->postJson('/api/meetings', [
                'provider' => MeetingProvider::GoogleMeet->value,
                'title' => 'اجتماع Meet',
                'start_at' => now()->addDay()->setTime(14, 0)->toIso8601String(),
                'end_at' => now()->addDay()->setTime(15, 0)->toIso8601String(),
                'task_id' => $task->id,
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame('https://meet.google.com/abc-defg-hij', $payload['join_url']);
        $this->assertSame('abc-defg-hij', $payload['meeting_id']);
        $this->assertSame('evt-meet-1', $payload['external_event_id']);
        $this->assertSame('https://calendar.google.com/event?eid=evt-meet-1', $payload['calendar_event_url']);
    }

    public function test_update_and_cancel_zoom_meeting(): void
    {
        ['manager' => $manager, 'task' => $task] = $this->seededTask();
        Cache::put('zoom_s2s_access_token', 'zoom-token', now()->addHour());

        Http::fake([
            'api.zoom.us/v2/meetings/*' => Http::sequence()
                ->push([], 204)
                ->push([
                    'id' => 111,
                    'join_url' => 'https://zoom.us/j/111',
                    'start_url' => 'https://zoom.us/s/111',
                ], 200)
                ->push([], 204),
        ]);

        $meeting = Meeting::factory()->zoom()->create([
            'created_by' => $manager->id,
            'task_id' => $task->id,
            'meeting_id' => '111',
            'join_url' => 'https://zoom.us/j/111',
            'host_url' => 'https://zoom.us/s/111',
        ]);

        $this->asUser($manager)
            ->patchJson('/api/meetings/'.$meeting->id, [
                'title' => 'عنوان محدث',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'عنوان محدث');

        $this->asUser($manager)
            ->deleteJson('/api/meetings/'.$meeting->id)
            ->assertOk()
            ->assertJsonPath('data.status', MeetingStatus::Cancelled->value);
    }

    public function test_customer_cannot_see_join_url_unless_explicitly_included(): void
    {
        ['manager' => $manager, 'project' => $project, 'task' => $task] = $this->seededTask();
        $customer = User::query()->findOrFail($project->customer_id);

        $meeting = Meeting::factory()->zoom()->create([
            'created_by' => $manager->id,
            'task_id' => $task->id,
            'project_id' => $project->id,
            'customer_id' => $customer->id,
            'include_customer' => false,
            'join_url' => 'https://zoom.us/j/secret',
            'host_url' => 'https://zoom.us/s/secret',
        ]);

        $this->asUser($customer)
            ->getJson('/api/meetings/'.$meeting->id)
            ->assertNotFound();

        $meeting->update(['include_customer' => true]);

        $visible = $this->asUser($customer)
            ->getJson('/api/meetings/'.$meeting->id)
            ->assertOk()
            ->json('data');

        $this->assertSame('https://zoom.us/j/secret', $visible['join_url']);
        $this->assertNull($visible['host_url']);
    }

    public function test_supplier_only_sees_assigned_meetings(): void
    {
        ['manager' => $manager, 'task' => $task] = $this->seededTask();
        $supplierUser = User::factory()->supplier()->create();
        $supplier = Supplier::factory()->create(['user_id' => $supplierUser->id]);
        $otherSupplierUser = User::factory()->supplier()->create();
        Supplier::factory()->create(['user_id' => $otherSupplierUser->id]);

        $task->update(['supplier_id' => $supplier->id]);

        $meeting = Meeting::factory()->zoom()->create([
            'created_by' => $manager->id,
            'task_id' => $task->id,
            'supplier_id' => $supplier->id,
            'join_url' => 'https://zoom.us/j/supplier-meet',
        ]);

        $this->asUser($supplierUser)
            ->getJson('/api/meetings/'.$meeting->id)
            ->assertOk()
            ->assertJsonPath('data.join_url', 'https://zoom.us/j/supplier-meet');

        $this->asUser($otherSupplierUser)
            ->getJson('/api/meetings/'.$meeting->id)
            ->assertNotFound();
    }

    public function test_unauthorized_user_cannot_create_meeting(): void
    {
        ['task' => $task] = $this->seededTask();
        $customer = User::factory()->create(['role' => UserRole::Customer->value]);

        $this->asUser($customer)
            ->postJson('/api/meetings', [
                'provider' => MeetingProvider::None->value,
                'title' => 'محاولة',
                'start_at' => now()->addHour()->toIso8601String(),
                'task_id' => $task->id,
            ])
            ->assertForbidden();
    }

    public function test_none_provider_creates_local_meeting_without_http(): void
    {
        Http::fake();
        ['manager' => $manager, 'task' => $task] = $this->seededTask();

        $payload = $this->asUser($manager)
            ->postJson('/api/meetings', [
                'provider' => MeetingProvider::None->value,
                'title' => 'اجتماع داخلي بدون رابط',
                'start_at' => now()->addDay()->toIso8601String(),
                'task_id' => $task->id,
            ])
            ->assertCreated()
            ->json('data');

        $this->assertNull($payload['join_url']);
        $this->assertSame(MeetingProvider::None->value, $payload['provider']);
        Http::assertNothingSent();
    }
}
