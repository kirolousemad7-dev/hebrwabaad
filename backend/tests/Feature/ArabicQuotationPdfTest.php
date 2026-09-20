<?php

namespace Tests\Feature;

use App\Enums\CommercialQuotationStatus;
use App\Enums\PrintingPaymentPolicy;
use App\Enums\QuoteRequestStatus;
use App\Enums\UserRole;
use App\Models\CommercialQuotation;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Services\Pdf\ArabicHtmlShaper;
use App\Services\Pdf\PdfFactory;
use App\Services\Quotes\CommercialQuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ArabicQuotationPdfTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_arabic_shaper_converts_presentation_glyphs_and_keeps_western_digits(): void
    {
        $shaper = app(ArabicHtmlShaper::class);

        $pureArabic = 'تشطيب داخلي كامل';
        $shapedArabic = $shaper->shape($pureArabic);
        $this->assertNotSame($pureArabic, $shapedArabic);
        $this->assertStringNotContainsString('تشطيب', $shapedArabic);

        // Digits adjacent to Arabic reverse unless isolated in an LTR island (as templates do).
        $html = '<p>الإجمالي: <span dir="ltr">25,000.00 EGP</span></p>';
        $shapedHtml = $shaper->shape($html);
        $this->assertStringContainsString('25,000.00 EGP', $shapedHtml);
        $this->assertStringContainsString('dir="ltr"', $shapedHtml);
    }

    public function test_shaper_leaves_english_only_html_unchanged(): void
    {
        $html = '<html><body><p>Printing Quotation PQ-1</p></body></html>';
        $this->assertSame($html, app(ArabicHtmlShaper::class)->shape($html));
    }

    public function test_pdf_factory_renders_arabic_quotation_without_exception(): void
    {
        $html = '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
            .'<style>body{font-family:DejaVu Sans,sans-serif;direction:rtl}</style></head><body>'
            .'<h1>حبر وأبعاد</h1><p>عرض سعر أعمال التشطيبات والتجديد</p>'
            .'<p>Quotation #TEST-001 — Hebr &amp; Ab3ad</p>'
            .'<table><tr><td>تشطيب داخلي كامل</td><td dir="ltr">2,500.00</td></tr></table>'
            .'<p>الإجمالي النهائي: <span dir="ltr">23,500.00 EGP</span></p>'
            .'</body></html>';

        $binary = app(PdfFactory::class)->loadHtml($html)->output();

        $this->assertNotSame('', $binary);
        $this->assertStringStartsWith('%PDF', $binary);
        $this->assertGreaterThan(1000, strlen($binary));
    }

    public function test_commercial_quotation_pdf_endpoint_returns_pdf_binary(): void
    {
        Notification::fake();

        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create([
            'role' => UserRole::Customer,
            'name' => 'شركة حبر وأبعاد',
        ]);
        $request = QuoteRequest::factory()->create([
            'customer_id' => $customer->id,
            'status' => QuoteRequestStatus::UnderReview,
            'title' => 'عرض سعر أعمال التشطيبات والتجديد',
        ]);

        $qid = (int) $this->asUser($owner)
            ->postJson('/api/operations/quote-requests/'.$request->id.'/quotations')
            ->assertCreated()
            ->json('data.id');

        $this->asUser($owner)->patchJson('/api/operations/commercial-quotations/'.$qid, [
            'payment_policy' => PrintingPaymentPolicy::Full->value,
            'valid_until' => now()->addDays(7)->toDateString(),
            'currency' => 'EGP',
            'discount_amount' => '1500.00',
            'notes' => "Hebr & Ab3ad\nInterior Design & Finishing",
            'items' => [
                ['description' => 'تشطيب داخلي كامل', 'quantity' => 10, 'unit_price' => '2500.00', 'category' => 'OTHER'],
                ['description' => 'أعمال الكهرباء', 'quantity' => 1, 'unit_price' => '0.00', 'category' => 'OTHER'],
                ['description' => 'أعمال السباكة', 'quantity' => 1, 'unit_price' => '0.00', 'category' => 'OTHER'],
            ],
        ])->assertOk();

        $this->asUser($owner)
            ->postJson('/api/operations/commercial-quotations/'.$qid.'/send')
            ->assertOk();

        $response = $this->asUser($owner)
            ->get('/api/operations/commercial-quotations/'.$qid.'/pdf')
            ->assertOk();

        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent() ?: '');

        $html = $this->asUser($owner)
            ->get('/api/operations/commercial-quotations/'.$qid.'/pdf?format=html')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('حبر وأبعاد', $html);
        $this->assertStringContainsString('تشطيب داخلي كامل', $html);
        $this->assertStringContainsString('Hebr &amp; Ab3ad', $html);
        $this->assertStringContainsString('DejaVu Sans', $html);
    }

    public function test_existing_html_fallback_still_contains_logical_arabic(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $request = QuoteRequest::factory()->create([
            'customer_id' => $customer->id,
            'status' => QuoteRequestStatus::UnderReview,
        ]);

        $qid = (int) $this->asUser($owner)
            ->postJson('/api/operations/quote-requests/'.$request->id.'/quotations')
            ->assertCreated()
            ->json('data.id');

        $this->asUser($owner)->patchJson('/api/operations/commercial-quotations/'.$qid, [
            'payment_policy' => PrintingPaymentPolicy::None->value,
            'items' => [
                ['description' => 'دهانات وديكورات', 'quantity' => 1, 'unit_price' => '100.00', 'category' => 'OTHER'],
            ],
        ])->assertOk();

        $quotation = CommercialQuotation::query()->findOrFail($qid);
        $this->assertSame(CommercialQuotationStatus::Draft, $quotation->status);

        $binary = app(CommercialQuotationService::class)->pdfBinary($quotation->fresh(['items', 'customer', 'quoteRequest']));
        $this->assertStringStartsWith('%PDF', $binary);
    }
}
