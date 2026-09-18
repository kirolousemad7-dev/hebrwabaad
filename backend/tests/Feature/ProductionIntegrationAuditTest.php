<?php

namespace Tests\Feature;

use App\Enums\CommercialQuotationStatus;
use App\Enums\ContentStatus;
use App\Enums\SupplierQuoteStatus;
use App\Enums\SupplierVisibility;
use App\Enums\UserRole;
use App\Models\CommercialQuotation;
use App\Models\ContentMedia;
use App\Models\QuotationSupplierQuote;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\SupplierPortfolioItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProductionIntegrationAuditTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_scheduler_registers_audit_critical_commands(): void
    {
        $events = collect(app('Illuminate\Console\Scheduling\Schedule')->events())
            ->map(fn ($event) => $event->command ?? $event->description ?? '')
            ->implode("\n");

        $this->assertStringContainsString('blog:publish-scheduled', $events);
        $this->assertStringContainsString('otp:prune-expired', $events);
        $this->assertStringContainsString('quotations:expire-commercial', $events);
        $this->assertStringContainsString('quotations:expire-supplier-quotes', $events);
        $this->assertStringContainsString('printing:expire-quotations', $events);
    }

    public function test_content_media_rejects_internal_portfolio_parent_for_guests(): void
    {
        $supplier = Supplier::factory()->create();

        $item = SupplierPortfolioItem::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => ContentStatus::Published,
            'visibility' => SupplierVisibility::Internal,
            'is_active' => true,
        ]);

        $media = ContentMedia::factory()->create([
            'attachable_type' => 'supplier_portfolio_item',
            'attachable_id' => $item->id,
        ]);

        $this->get('/api/content-media/'.$media->id)->assertForbidden();
    }

    public function test_expire_commercial_quotations_command_marks_overdue_sent(): void
    {
        $quotation = CommercialQuotation::factory()->sent()->create([
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        Artisan::call('quotations:expire-commercial');

        $this->assertSame(
            CommercialQuotationStatus::Expired,
            $quotation->fresh()->status,
        );
    }

    public function test_expire_supplier_quotes_command_marks_overdue_open_quotes(): void
    {
        $quote = QuotationSupplierQuote::factory()->create([
            'status' => SupplierQuoteStatus::Requested,
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        Artisan::call('quotations:expire-supplier-quotes');

        $this->assertSame(
            SupplierQuoteStatus::Expired,
            $quote->fresh()->status,
        );
    }

    public function test_supplier_documents_force_local_disk_and_reject_client_disk(): void
    {
        $owner = User::factory()->owner()->create();
        $supplier = Supplier::factory()->create();

        $created = $this->asUser($owner)->postJson('/api/admin/suppliers/'.$supplier->id.'/documents', [
            'title' => 'عقد توريد',
            'category' => 'contracts',
            'path' => 'suppliers/documents/contract.pdf',
            'original_name' => 'contract.pdf',
            'disk' => 'public',
        ]);

        $created->assertUnprocessable();

        $ok = $this->asUser($owner)->postJson('/api/admin/suppliers/'.$supplier->id.'/documents', [
            'title' => 'عقد توريد',
            'category' => 'contracts',
            'path' => 'suppliers/documents/contract.pdf',
            'original_name' => 'contract.pdf',
        ])->assertCreated();

        $this->assertSame('local', $ok->json('data.disk'));
        $this->assertSame('local', SupplierDocument::query()->findOrFail($ok->json('data.id'))->disk);
        $this->assertSame(UserRole::Owner, $owner->role);
    }
}
