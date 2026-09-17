<?php

namespace Tests\Feature;

use App\Enums\GoogleCalendarSyncStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Jobs\SyncTaskToGoogleCalendar;
use App\Models\GoogleCalendarConnection;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskReminder;
use App\Models\User;
use App\Notifications\TaskReminderNotification;
use App\Services\GoogleCalendar\GoogleCalendarOAuthService;
use App\Services\GoogleCalendar\GoogleCalendarTaskSyncService;
use App\Support\GoogleCalendarSyncContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GoogleCalendarIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect_uri' => 'http://localhost/api/google-calendar/callback',
            'app.frontend_url' => 'http://localhost:5173',
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
        $project = Project::factory()->create(['account_manager_id' => $manager->id]);
        $task = Task::factory()->create([
            'title' => 'تصوير فيديو',
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
            'project_id' => $project->id,
            'priority' => TaskPriority::High,
            'status' => TaskStatus::Todo,
            'deadline' => now()->addDays(2)->toDateString(),
            'start_at' => now()->addDay()->setTime(10, 0),
            'due_at' => now()->addDay()->setTime(11, 0),
            'timezone' => 'Africa/Cairo',
        ]);

        return compact('manager', 'developer', 'project', 'task');
    }

    private function connectUser(User $user): GoogleCalendarConnection
    {
        $connection = new GoogleCalendarConnection([
            'user_id' => $user->id,
            'google_account_id' => 'gid-1',
            'google_email' => 'owner@gmail.com',
            'calendar_id' => 'primary',
            'meet_enabled' => true,
            'sync_enabled' => true,
            'connected_at' => now(),
            'token_expires_at' => now()->addHour(),
            'scopes' => 'https://www.googleapis.com/auth/calendar.events',
        ]);
        $connection->setAccessToken('access-token-1');
        $connection->setRefreshToken('refresh-token-1');
        $connection->save();

        return $connection;
    }

    public function test_oauth_connect_returns_authorize_url_without_exposing_secrets(): void
    {
        $manager = User::factory()->accountManager()->create();

        $payload = $this->asUser($manager)
            ->postJson('/api/google-calendar/connect')
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey('authorize_url', $payload);
        $this->assertStringContainsString('client_id=test-client-id', $payload['authorize_url']);
        $this->assertStringNotContainsString('test-client-secret', json_encode($payload));
        $this->assertArrayHasKey('state', $payload);
    }

    public function test_oauth_callback_stores_encrypted_tokens(): void
    {
        $manager = User::factory()->accountManager()->create();
        $state = 'state-abc';
        Cache::put('google_oauth_state:'.$state, ['user_id' => $manager->id], now()->addMinutes(10));

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new-access',
                'refresh_token' => 'new-refresh',
                'expires_in' => 3600,
                'scope' => 'https://www.googleapis.com/auth/calendar.events',
            ], 200),
            'www.googleapis.com/oauth2/v2/userinfo' => Http::response([
                'id' => 'google-123',
                'email' => 'sync@gmail.com',
            ], 200),
        ]);

        $this->get('/api/google-calendar/callback?code=auth-code&state='.$state)
            ->assertRedirect();

        $connection = GoogleCalendarConnection::query()->where('user_id', $manager->id)->firstOrFail();
        $this->assertSame('sync@gmail.com', $connection->google_email);
        $this->assertNotSame('new-access', $connection->access_token_encrypted);
        $this->assertSame('new-access', Crypt::decryptString($connection->access_token_encrypted));
        $this->assertSame('new-refresh', Crypt::decryptString($connection->refresh_token_encrypted));

        $status = $this->asUser($manager)->getJson('/api/google-calendar/status')->assertOk()->json('data');
        $this->assertTrue($status['connected']);
        $this->assertArrayNotHasKey('access_token', $status);
        $this->assertArrayNotHasKey('refresh_token', $status);
        $this->assertArrayNotHasKey('access_token_encrypted', $status);
    }

    public function test_token_refresh_updates_access_token(): void
    {
        $manager = User::factory()->accountManager()->create();
        $connection = $this->connectUser($manager);
        $connection->update(['token_expires_at' => now()->subMinute()]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'refreshed-access',
                'expires_in' => 3600,
            ], 200),
        ]);

        $token = app(GoogleCalendarOAuthService::class)->ensureAccessToken($connection->fresh());
        $this->assertSame('refreshed-access', $token);
        $this->assertSame('refreshed-access', $connection->fresh()->accessToken());
    }

    public function test_create_update_and_cancel_google_events(): void
    {
        ['manager' => $manager, 'task' => $task] = $this->seededTask();
        $this->connectUser($manager);

        Http::fake([
            'www.googleapis.com/calendar/v3/calendars/*' => Http::sequence()
                ->push([
                    'id' => 'evt-1',
                    'htmlLink' => 'https://calendar.google.com/event?eid=evt-1',
                    'etag' => 'etag-1',
                ], 200)
                ->push([
                    'id' => 'evt-1',
                    'htmlLink' => 'https://calendar.google.com/event?eid=evt-1',
                    'etag' => 'etag-2',
                ], 200)
                ->push([], 204),
        ]);

        $enabled = $this->asUser($manager)
            ->postJson('/api/workspace/tasks/'.$task->id.'/google-calendar/enable', [
                'reminders' => ['MINUTES_15', 'HOUR_1'],
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame(GoogleCalendarSyncStatus::Synced->value, $enabled['google_sync_status']);
        $this->assertSame('evt-1', $enabled['google_event_id']);
        $this->assertSame('https://calendar.google.com/event?eid=evt-1', $enabled['google_html_link']);

        $task->refresh();
        $this->assertTrue($task->google_sync_enabled);
        $this->assertGreaterThan(0, TaskReminder::query()->where('task_id', $task->id)->count());

        $this->asUser($manager)
            ->putJson('/api/workspace/account-manager/tasks/'.$task->id, [
                'title' => 'تصوير فيديو محدث',
                'description' => 'وصف',
                'project_id' => $task->project_id,
                'assigned_to' => $task->assigned_to,
                'priority' => TaskPriority::High->value,
                'status' => TaskStatus::InProgress->value,
                'deadline' => $task->deadline?->toDateString(),
                'start_at' => $task->start_at?->toIso8601String(),
                'due_at' => $task->due_at?->toIso8601String(),
            ])
            ->assertOk();

        $task->refresh();
        $this->assertSame('تصوير فيديو محدث', $task->title);
        $this->assertTrue($task->google_sync_enabled);

        $disabled = $this->asUser($manager)
            ->postJson('/api/workspace/tasks/'.$task->id.'/google-calendar/disable', [
                'delete_remote' => true,
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame(GoogleCalendarSyncStatus::Cancelled->value, $disabled['google_sync_status']);
        $this->assertNull($disabled['google_event_id']);
        $this->assertFalse($disabled['google_sync_enabled']);
    }

    public function test_duplicate_sync_job_is_skipped_for_stale_version(): void
    {
        ['manager' => $manager, 'task' => $task] = $this->seededTask();
        $this->connectUser($manager);
        $task->update([
            'google_sync_enabled' => true,
            'google_sync_version' => 5,
            'google_sync_status' => GoogleCalendarSyncStatus::Pending,
        ]);

        Http::fake();

        $job = new SyncTaskToGoogleCalendar($task->id, 4);
        $job->handle(app(GoogleCalendarTaskSyncService::class));

        Http::assertNothingSent();
    }

    public function test_sync_context_prevents_recursive_loops(): void
    {
        $this->assertFalse(GoogleCalendarSyncContext::isSyncing());
        GoogleCalendarSyncContext::run(function (): void {
            $this->assertTrue(GoogleCalendarSyncContext::isSyncing());
        });
        $this->assertFalse(GoogleCalendarSyncContext::isSyncing());
    }

    public function test_task_reminders_dispatch_in_app_notification(): void
    {
        Notification::fake();
        ['manager' => $manager, 'developer' => $developer, 'task' => $task] = $this->seededTask();

        TaskReminder::factory()->create([
            'task_id' => $task->id,
            'offset' => 'MINUTES_15',
            'remind_at' => now()->subMinute(),
            'sent_at' => null,
        ]);

        $this->artisan('tasks:dispatch-reminders')->assertSuccessful();

        Notification::assertSentTo($developer, TaskReminderNotification::class);
        $this->assertNotNull(TaskReminder::query()->where('task_id', $task->id)->value('sent_at'));
    }

    public function test_permission_isolation_on_google_sync_endpoints(): void
    {
        ['manager' => $manager, 'task' => $task] = $this->seededTask();
        $stranger = User::factory()->accountManager()->create();
        $this->connectUser($manager);

        $this->asUser($stranger)
            ->postJson('/api/workspace/tasks/'.$task->id.'/google-calendar/enable')
            ->assertForbidden();

        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        $this->asUser($customer)
            ->getJson('/api/google-calendar/status')
            ->assertOk(); // status is available, but tokens never exposed
        $status = $this->asUser($customer)->getJson('/api/google-calendar/status')->json('data');
        $this->assertFalse($status['connected']);
    }

    public function test_queue_sync_dispatches_unique_job(): void
    {
        Queue::fake();
        ['manager' => $manager, 'task' => $task] = $this->seededTask();
        $this->connectUser($manager);
        $task->update(['google_sync_enabled' => true, 'google_sync_version' => 1]);

        app(GoogleCalendarTaskSyncService::class)->queueSync($task->fresh());

        Queue::assertPushed(SyncTaskToGoogleCalendar::class, function (SyncTaskToGoogleCalendar $job) use ($task): bool {
            return $job->taskId === $task->id && $job->expectedVersion === 2;
        });
    }

    public function test_disconnect_removes_connection_and_revokes_token(): void
    {
        $manager = User::factory()->accountManager()->create();
        $this->connectUser($manager);

        Http::fake([
            'oauth2.googleapis.com/revoke' => Http::response([], 200),
        ]);

        $this->asUser($manager)->deleteJson('/api/google-calendar/disconnect')->assertOk();
        $this->assertNull(GoogleCalendarConnection::query()->where('user_id', $manager->id)->first());
    }
}
