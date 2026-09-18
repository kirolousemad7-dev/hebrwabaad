<?php

namespace Database\Seeders;

use App\Models\CrmLeadSource;
use App\Models\CrmLostReason;
use App\Models\CrmPipelineStage;
use App\Models\CrmSetting;
use App\Models\CrmTag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CrmSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['name' => 'Website Contact', 'slug' => 'website-contact'],
            ['name' => 'AI Consultant', 'slug' => 'ai-consultant'],
            ['name' => 'Needs Discovery', 'slug' => 'needs-discovery'],
            ['name' => 'Referral', 'slug' => 'referral'],
            ['name' => 'Cold Call', 'slug' => 'cold-call'],
            ['name' => 'WhatsApp', 'slug' => 'whatsapp'],
            ['name' => 'Social Media', 'slug' => 'social-media'],
            ['name' => 'Event', 'slug' => 'event'],
        ];

        foreach ($sources as $index => $source) {
            CrmLeadSource::query()->updateOrCreate(
                ['slug' => $source['slug']],
                [
                    'name' => $source['name'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }

        $stages = [
            ['name' => 'New Lead', 'slug' => 'new-lead', 'probability' => 5, 'is_won' => false, 'is_lost' => false],
            ['name' => 'Contacted', 'slug' => 'contacted', 'probability' => 15, 'is_won' => false, 'is_lost' => false],
            ['name' => 'Qualified', 'slug' => 'qualified', 'probability' => 30, 'is_won' => false, 'is_lost' => false],
            ['name' => 'Needs Analysis', 'slug' => 'needs-analysis', 'probability' => 40, 'is_won' => false, 'is_lost' => false],
            ['name' => 'Proposal', 'slug' => 'proposal', 'probability' => 55, 'is_won' => false, 'is_lost' => false],
            ['name' => 'Negotiation', 'slug' => 'negotiation', 'probability' => 70, 'is_won' => false, 'is_lost' => false],
            ['name' => 'Closing', 'slug' => 'closing', 'probability' => 85, 'is_won' => false, 'is_lost' => false],
            ['name' => 'Won', 'slug' => 'won', 'probability' => 100, 'is_won' => true, 'is_lost' => false],
            ['name' => 'Lost', 'slug' => 'lost', 'probability' => 0, 'is_won' => false, 'is_lost' => true],
        ];

        foreach ($stages as $index => $stage) {
            CrmPipelineStage::query()->updateOrCreate(
                ['slug' => $stage['slug']],
                [
                    'name' => $stage['name'],
                    'is_won' => $stage['is_won'],
                    'is_lost' => $stage['is_lost'],
                    'probability' => $stage['probability'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }

        $reasons = [
            'Price too high',
            'Chose competitor',
            'No budget',
            'Timing not right',
            'No response',
            'Not a fit',
            'Project cancelled',
        ];

        foreach ($reasons as $index => $name) {
            CrmLostReason::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }

        $tags = [
            ['name' => 'Hot', 'color' => '#DC2626'],
            ['name' => 'Warm', 'color' => '#F59E0B'],
            ['name' => 'Enterprise', 'color' => '#2563EB'],
            ['name' => 'SMB', 'color' => '#059669'],
            ['name' => 'Follow closely', 'color' => '#7C3AED'],
        ];

        foreach ($tags as $tag) {
            CrmTag::query()->updateOrCreate(
                ['slug' => Str::slug($tag['name'])],
                [
                    'name' => $tag['name'],
                    'color' => $tag['color'],
                ],
            );
        }

        $settings = [
            'discount_max_percent' => 10,
            'stale_lead_days' => 7,
            'new_lead_sla_minutes' => 60,
            'assignment_mode' => 'manual',
            'round_robin_cursor' => 0,
        ];

        foreach ($settings as $key => $value) {
            CrmSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => ['value' => $value]],
            );
        }
    }
}
