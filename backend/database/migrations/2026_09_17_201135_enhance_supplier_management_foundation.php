<?php

use App\Enums\SupplierOnboardingStatus;
use App\Enums\SupplierStatus;
use App\Enums\SupplierVerificationStatus;
use App\Enums\SupplierVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('user_id')->constrained('crm_companies')->nullOnDelete();
            $table->string('supplier_code', 32)->nullable()->unique()->after('company_id');
            $table->string('legal_name')->nullable()->after('name');
            $table->string('display_name')->nullable()->after('legal_name');
            $table->string('whatsapp', 40)->nullable()->after('phone');
            $table->string('country', 80)->nullable()->after('location');
            $table->string('city', 120)->nullable()->after('country');
            $table->string('status', 32)->default(SupplierStatus::Pending->value)->index()->after('is_featured');
            $table->string('verification_status', 32)->default(SupplierVerificationStatus::Unverified->value)->index()->after('status');
            $table->string('onboarding_status', 32)->default(SupplierOnboardingStatus::Draft->value)->index()->after('verification_status');
            $table->decimal('rating', 3, 2)->nullable()->after('onboarding_status');
            $table->text('notes')->nullable()->after('review_notes');
            $table->text('internal_notes')->nullable()->after('notes');
            $table->boolean('show_public_contact')->default(false)->after('is_published');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });

        $this->backfillSupplierLifecycle();

        Schema::table('supplier_products', function (Blueprint $table): void {
            $table->string('sku', 64)->nullable()->after('slug');
            $table->string('unit', 40)->nullable()->after('currency');
            $table->unsignedInteger('minimum_quantity')->nullable()->after('unit');
            $table->string('lead_time', 120)->nullable()->after('availability');
            $table->text('internal_notes')->nullable()->after('review_notes');
            $table->string('visibility', 32)->default(SupplierVisibility::Internal->value)->index()->after('status');
        });

        Schema::table('supplier_portfolio_items', function (Blueprint $table): void {
            $table->json('videos')->nullable()->after('gallery');
            $table->date('completion_date')->nullable()->after('external_url');
            $table->string('visibility', 32)->default(SupplierVisibility::Internal->value)->index()->after('status');
        });

        // Preserve existing public catalog: already-published rows stay publicly visible.
        DB::table('supplier_products')
            ->where('status', 'PUBLISHED')
            ->update(['visibility' => SupplierVisibility::Public->value]);

        DB::table('supplier_portfolio_items')
            ->where('status', 'PUBLISHED')
            ->update(['visibility' => SupplierVisibility::Public->value]);
    }

    public function down(): void
    {
        Schema::table('supplier_portfolio_items', function (Blueprint $table): void {
            $table->dropColumn(['videos', 'completion_date', 'visibility']);
        });

        Schema::table('supplier_products', function (Blueprint $table): void {
            $table->dropColumn(['sku', 'unit', 'minimum_quantity', 'lead_time', 'internal_notes', 'visibility']);
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn([
                'supplier_code',
                'legal_name',
                'display_name',
                'whatsapp',
                'country',
                'city',
                'status',
                'verification_status',
                'onboarding_status',
                'rating',
                'notes',
                'internal_notes',
                'show_public_contact',
            ]);
        });
    }

    private function backfillSupplierLifecycle(): void
    {
        $rows = DB::table('suppliers')->select(['id', 'name', 'is_active', 'is_published', 'profile_status'])->get();

        foreach ($rows as $row) {
            $status = SupplierStatus::Pending->value;
            if (! $row->is_active) {
                $status = SupplierStatus::Suspended->value;
            } elseif ($row->is_published) {
                $status = SupplierStatus::Active->value;
            }

            $onboarding = $row->is_published
                ? SupplierOnboardingStatus::Completed->value
                : SupplierOnboardingStatus::Draft->value;

            DB::table('suppliers')->where('id', $row->id)->update([
                'supplier_code' => 'SUP-'.str_pad((string) $row->id, 5, '0', STR_PAD_LEFT),
                'display_name' => $row->name,
                'legal_name' => $row->name,
                'status' => $status,
                'verification_status' => SupplierVerificationStatus::Unverified->value,
                'onboarding_status' => $onboarding,
                'show_public_contact' => false,
            ]);
        }
    }
};
