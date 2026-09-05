<?php

namespace Tests\Unit;

use App\Enums\PrintingMethod;
use App\Enums\PrintingPricingType;
use App\Enums\PrintingShape;
use App\Models\PrintingRequest;
use App\Services\PrintingPricingService;
use PHPUnit\Framework\TestCase;

class PrintingPricingServiceTest extends TestCase
{
    public function test_standard_job_receives_an_estimate(): void
    {
        $request = new PrintingRequest([
            'product_slug' => 'standard-business-cards',
            'quantity' => 100,
            'printing_method' => PrintingMethod::Digital,
            'shape' => PrintingShape::Rectangle,
            'finishing' => ['NONE'],
        ]);

        $suggestion = (new PrintingPricingService)->suggest($request);

        $this->assertSame(PrintingPricingType::Estimated, $suggestion['pricing_type']);
        $this->assertSame('85.00', $suggestion['estimated_price']);
    }

    public function test_custom_shape_requires_a_quote(): void
    {
        $request = new PrintingRequest([
            'product_slug' => 'standard-business-cards',
            'quantity' => 50,
            'printing_method' => PrintingMethod::Digital,
            'shape' => PrintingShape::Custom,
            'finishing' => ['NONE'],
        ]);

        $suggestion = (new PrintingPricingService)->suggest($request);

        $this->assertSame(PrintingPricingType::QuoteRequired, $suggestion['pricing_type']);
        $this->assertNull($suggestion['estimated_price']);
    }
}
