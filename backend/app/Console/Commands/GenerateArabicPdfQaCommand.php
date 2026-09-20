<?php

namespace App\Console\Commands;

use App\Enums\CommercialQuotationStatus;
use App\Enums\UserRole;
use App\Models\CommercialQuotation;
use App\Models\CommercialQuotationItem;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Services\Quotes\CommercialQuotationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('pdf:arabic-qa {--force : Allow outside local/testing}')]
#[Description('Generate a sample Arabic commercial quotation PDF for visual QA (local/testing only)')]
class GenerateArabicPdfQaCommand extends Command
{
    public function handle(CommercialQuotationService $quotations): int
    {
        if (! app()->environment(['local', 'testing']) && ! $this->option('force')) {
            $this->error('Refusing to create QA data outside local/testing. Pass --force only if you accept creating sample rows.');

            return self::FAILURE;
        }

        $owner = User::query()->where('role', UserRole::Owner)->first()
            ?? User::factory()->owner()->create(['email' => 'pdf-qa-owner@example.com']);

        $customer = User::factory()->create([
            'role' => UserRole::Customer,
            'name' => 'شركة حبر وأبعاد',
            'email' => 'pdf-qa-customer@example.com',
        ]);

        $quoteRequest = QuoteRequest::factory()->create([
            'customer_id' => $customer->id,
            'title' => 'عرض سعر أعمال التشطيبات والتجديد',
        ]);

        $quotation = CommercialQuotation::factory()->create([
            'quote_request_id' => $quoteRequest->id,
            'customer_id' => $customer->id,
            'created_by' => $owner->id,
            'reference' => 'CQ-TEST-001',
            'status' => CommercialQuotationStatus::Sent,
            'currency' => 'EGP',
            'subtotal' => '25000.00',
            'discount_amount' => '1500.00',
            'tax_amount' => '0.00',
            'shipping_amount' => '0.00',
            'rental_amount' => '0.00',
            'total' => '23500.00',
            'notes' => "Hebr & Ab3ad\nInterior Design & Finishing\nالعنوان: المدينة المنورة، المملكة العربية السعودية",
            'terms' => 'صالح لمدة 14 يومًا. الأسعار بالجنيه المصري EGP.',
            'execution_duration' => '45 يوم عمل',
            'sent_at' => now(),
            'valid_until' => now()->addDays(14)->toDateString(),
        ]);

        $lines = [
            ['تشطيب داخلي كامل', '10', '2500.00', '25000.00'],
            ['أعمال الكهرباء', '1', '0.00', '0.00'],
            ['أعمال السباكة', '1', '0.00', '0.00'],
            ['توريد وتركيب البورسلين', '1', '0.00', '0.00'],
            ['دهانات وديكورات', '1', '0.00', '0.00'],
            ['إدارة ومتابعة المشروع', '1', '0.00', '0.00'],
        ];

        foreach ($lines as [$description, $qty, $unit, $subtotal]) {
            CommercialQuotationItem::query()->create([
                'commercial_quotation_id' => $quotation->id,
                'description' => $description,
                'quantity' => $qty,
                'unit_price' => $unit,
                'subtotal' => $subtotal,
            ]);
        }

        $fresh = $quotation->fresh(['items', 'customer', 'quoteRequest']);
        assert($fresh instanceof CommercialQuotation);

        $binary = $quotations->pdfBinary($fresh);
        $relative = 'pdf-qa/arabic-quotation-'.$fresh->id.'.pdf';
        Storage::disk('local')->put($relative, $binary);

        $this->info('Arabic PDF QA sample created.');
        $this->line('quotation_id='.$fresh->id);
        $this->line('reference='.$fresh->reference);
        $this->line('staff_pdf_route=/api/operations/commercial-quotations/'.$fresh->id.'/pdf');
        $this->line('pdf_path='.Storage::disk('local')->path($relative));
        $this->line('status='.(string) $fresh->status->value);

        return self::SUCCESS;
    }
}
