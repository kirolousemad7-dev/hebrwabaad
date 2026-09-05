<?php

namespace Tests\Unit;

use App\Services\Consultant\ConversationExtractor;
use PHPUnit\Framework\TestCase;

class ConversationExtractorTest extends TestCase
{
    public function test_extracts_chicken_restaurant_profile_from_one_message(): void
    {
        $extracted = (new ConversationExtractor)->extract(
            'عندي مطعم فراخ فرعين في سموحة وبنبيع delivery كويس بس عايز نزود الطلبات'
        );

        $this->assertSame('restaurants-food', $extracted['business_category']);
        $this->assertSame('chicken-restaurant', $extracted['business_subtype']);
        $this->assertSame('2', $extracted['branches']);
        $this->assertSame('سموحة', $extracted['location']);
        $this->assertContains('increase_sales', $extracted['goals']);
        $this->assertSame(['delivery'], $extracted['answers']['dine_channels']);
    }

    public function test_extracts_unsure_intent(): void
    {
        $extracted = (new ConversationExtractor)->extract('مش عارف محتاج إيه بالظبط');

        $this->assertSame('unsure', $extracted['help_mode']);
        $this->assertTrue($extracted['unsure_needs']);
    }
}
