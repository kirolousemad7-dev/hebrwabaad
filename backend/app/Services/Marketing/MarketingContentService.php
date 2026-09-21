<?php

namespace App\Services\Marketing;

use App\Models\MarketingContent;
use App\Models\MarketingSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketingContentService
{
    /**
     * @return list<MarketingSection>
     */
    public function listSectionsForOwner(): array
    {
        return MarketingSection::query()
            ->with(['contents.media'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * @return list<MarketingSection>
     */
    public function listEnabledForPublic(): array
    {
        return MarketingSection::query()
            ->enabled()
            ->with(['contents' => function ($query): void {
                $query->enabled()->with('media')->orderBy('sort_order')->orderBy('id');
            }])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function findByKey(string $key): MarketingSection
    {
        return MarketingSection::query()
            ->where('key', $key)
            ->with(['contents.media'])
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateSection(MarketingSection $section, array $attributes): MarketingSection
    {
        $section->fill(collect($attributes)->only([
            'admin_title',
            'type',
            'is_enabled',
            'sort_order',
            'config',
        ])->all());
        $section->save();

        return $section->fresh(['contents.media']) ?? $section->load(['contents.media']);
    }

    /**
     * Upsert content rows for a section. Does not create orphan sections.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function syncContents(MarketingSection $section, array $items): MarketingSection
    {
        DB::transaction(function () use ($section, $items): void {
            foreach ($items as $index => $item) {
                $key = (string) ($item['content_key'] ?? '');
                if ($key === '') {
                    throw ValidationException::withMessages([
                        "contents.{$index}.content_key" => ['content_key is required.'],
                    ]);
                }

                /** @var MarketingContent $content */
                $content = MarketingContent::query()->firstOrNew([
                    'marketing_section_id' => $section->id,
                    'content_key' => $key,
                ]);

                if (array_key_exists('value_text', $item)) {
                    $content->value_text = $item['value_text'];
                }
                if (array_key_exists('value_html', $item)) {
                    $content->value_html = $item['value_html'];
                }
                if (array_key_exists('media_id', $item)) {
                    $mediaId = $item['media_id'];
                    $content->media_id = $mediaId === null || $mediaId === '' ? null : (int) $mediaId;
                }
                if (array_key_exists('sort_order', $item)) {
                    $content->sort_order = (int) $item['sort_order'];
                } elseif (! $content->exists) {
                    $content->sort_order = $index;
                }
                if (array_key_exists('is_enabled', $item)) {
                    $content->is_enabled = (bool) $item['is_enabled'];
                }
                if (array_key_exists('metadata', $item) && (is_array($item['metadata']) || $item['metadata'] === null)) {
                    $content->metadata = $item['metadata'];
                }

                $content->save();
            }
        });

        return $section->fresh(['contents.media']) ?? $section->load(['contents.media']);
    }

    /**
     * Safe media replacement: update the content FK to a new media id.
     * The previous media row is left intact (orphan candidate).
     */
    public function assignMedia(MarketingContent $content, ?int $mediaId): MarketingContent
    {
        $content->media_id = $mediaId;
        $content->save();

        return $content->fresh(['media', 'section']) ?? $content->load(['media', 'section']);
    }
}
