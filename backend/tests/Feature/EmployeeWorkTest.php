<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\PortfolioCategory;
use App\Models\PortfolioItem;
use App\Models\User;
use App\Models\WorkSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeWorkTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'موقع تجريبي',
            'description' => 'وصف آمن للنشر',
            'category' => PortfolioCategory::Web->value,
            'tags' => ['react'],
            'tools' => ['laravel'],
            'client_label' => 'عميل تجريبي',
        ], $overrides);
    }

    public function test_employee_can_create_edit_and_submit_work(): void
    {
        $employee = User::factory()->webDeveloper()->create();

        $created = $this->asUser($employee)
            ->postJson('/api/employee/work', $this->payload(['status' => ContentStatus::Published->value]))
            ->assertCreated()
            ->assertJsonPath('data.status', ContentStatus::Draft->value)
            ->json('data');

        $this->asUser($employee)
            ->putJson('/api/employee/work/'.$created['id'], $this->payload(['title' => 'موقع محدّث']))
            ->assertOk()
            ->assertJsonPath('data.title', 'موقع محدّث');

        $this->asUser($employee)
            ->postJson('/api/employee/work/'.$created['id'].'/submit')
            ->assertOk()
            ->assertJsonPath('data.status', ContentStatus::Submitted->value);
    }

    public function test_employee_cannot_publish_or_edit_another_employee_work(): void
    {
        $alice = User::factory()->webDeveloper()->create();
        $bob = User::factory()->graphicDesigner()->create();
        $work = WorkSubmission::factory()->create(['user_id' => $alice->id]);

        $this->asUser($alice)
            ->postJson('/api/admin/work-reviews/'.$work->id.'/approve-publish')
            ->assertForbidden();

        $this->asUser($bob)
            ->putJson('/api/employee/work/'.$work->id, $this->payload())
            ->assertForbidden();

        $this->asUser($bob)
            ->getJson('/api/employee/work/'.$work->id)
            ->assertForbidden();
    }

    public function test_customer_cannot_access_employee_work_or_reviews(): void
    {
        $customer = User::factory()->create();
        $work = WorkSubmission::factory()->create();

        $this->asUser($customer)->getJson('/api/employee/work')->assertForbidden();
        $this->asUser($customer)->postJson('/api/employee/work', $this->payload())->assertForbidden();
        $this->asUser($customer)->getJson('/api/admin/work-reviews')->assertForbidden();
        $this->asUser($customer)->postJson('/api/admin/work-reviews/'.$work->id.'/approve-publish')->assertForbidden();
    }

    public function test_owner_can_request_changes_reject_and_approve_publish(): void
    {
        $employee = User::factory()->webDeveloper()->create();
        $owner = User::factory()->owner()->create();
        $work = WorkSubmission::factory()->submitted()->create(['user_id' => $employee->id, 'title' => 'حملة']);

        $this->asUser($owner)
            ->postJson('/api/admin/work-reviews/'.$work->id.'/request-changes', ['notes' => 'أضف صورة غلاف'])
            ->assertOk()
            ->assertJsonPath('data.status', ContentStatus::ChangesRequested->value)
            ->assertJsonPath('data.review_notes', 'أضف صورة غلاف');

        $this->asUser($employee)
            ->putJson('/api/employee/work/'.$work->id, $this->payload(['title' => 'حملة بعد التعديل']))
            ->assertOk();

        $this->asUser($employee)
            ->postJson('/api/employee/work/'.$work->id.'/resubmit')
            ->assertOk()
            ->assertJsonPath('data.status', ContentStatus::Submitted->value);

        $this->asUser($owner)
            ->postJson('/api/admin/work-reviews/'.$work->id.'/reject', ['notes' => 'غير مناسب'])
            ->assertOk()
            ->assertJsonPath('data.status', ContentStatus::Rejected->value);

        $this->asUser($employee)
            ->putJson('/api/employee/work/'.$work->id, $this->payload(['title' => 'حملة نهائية']))
            ->assertOk();
        $this->asUser($employee)->postJson('/api/employee/work/'.$work->id.'/resubmit')->assertOk();

        $this->asUser($owner)
            ->postJson('/api/admin/work-reviews/'.$work->id.'/approve-publish')
            ->assertOk()
            ->assertJsonPath('data.status', ContentStatus::Published->value);

        $this->assertDatabaseHas('portfolio_items', [
            'work_submission_id' => $work->id,
            'is_published' => true,
            'is_sample' => false,
            'title' => 'حملة نهائية',
        ]);

        $this->getJson('/api/portfolio')
            ->assertOk()
            ->assertJsonFragment(['title' => 'حملة نهائية']);
    }

    public function test_unpublished_work_never_appears_in_public_portfolio(): void
    {
        $employee = User::factory()->webDeveloper()->create();
        WorkSubmission::factory()->create([
            'user_id' => $employee->id,
            'title' => 'مسودة سرية',
            'status' => ContentStatus::Draft,
        ]);
        WorkSubmission::factory()->submitted()->create([
            'user_id' => $employee->id,
            'title' => 'قيد المراجعة',
        ]);

        $this->getJson('/api/portfolio')->assertOk()->assertJsonMissing(['title' => 'مسودة سرية']);
        $this->getJson('/api/portfolio')->assertJsonMissing(['title' => 'قيد المراجعة']);
        $this->assertSame(0, PortfolioItem::query()->where('title', 'مسودة سرية')->count());
    }

    public function test_employee_receives_notification_after_publish(): void
    {
        $employee = User::factory()->webDeveloper()->create();
        $owner = User::factory()->owner()->create();
        $work = WorkSubmission::factory()->submitted()->create(['user_id' => $employee->id]);

        $this->asUser($owner)->postJson('/api/admin/work-reviews/'.$work->id.'/approve-publish')->assertOk();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $employee->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $owner->id,
        ]);
    }
}
