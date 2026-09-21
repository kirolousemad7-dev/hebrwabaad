<?php

namespace App\Services\Platform;

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Operations\OperationsAuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformSettingService
{
    public const CACHE_KEY = 'platform.settings.v1';

    /**
     * Defaults match official Hebr & Ab3ad identity — never leave the site blank.
     *
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        'brand' => [
            'name_ar' => 'حبر وأبعاد',
            'name_en' => 'Hebr & Ab3ad',
            'logo_path' => null,
            'logo_secondary_path' => null,
            'mark_path' => null,
            'favicon_path' => null,
            'logo_dark_path' => null,
            'logo_light_path' => null,
            'tagline' => 'نمنح أعمالك أبعادًا للنمو',
        ],
        'business' => [
            'trade_name' => 'منصة حبر وأبعاد لخدمات الأعمال والنمو',
            'short_description' => 'منصة متكاملة تبدأ بتشخيص نشاطك، ثم تخطيط النمو واختيار الخدمات والموردين، وصولًا إلى التنفيذ والقياس والمتابعة.',
            'full_description' => null,
            'commercial_register' => null,
            'tax_number' => null,
            'country' => 'السعودية',
            'city' => 'المدينة المنورة',
            'address' => null,
            'district' => null,
            'postal_code' => null,
            'working_hours' => null,
            'working_days' => null,
            'expose_legal_publicly' => false,
        ],
        'contact' => [
            'phone' => null,
            'whatsapp_country_code' => '966',
            'whatsapp_number' => null,
            'email' => null,
            'support_email' => null,
            'sales_email' => null,
            'address' => null,
            'maps_url' => null,
        ],
        'social' => [
            'items' => [],
        ],
        'website' => [
            'footer_description' => 'منصة سعودية لخدمات تشخيص الأعمال والتسويق والمحتوى والتصوير والمتاجر والطباعة والتغليف وتنظيم المعارض والافتتاحات في جميع مدن المملكة.',
            'copyright_text' => null,
            'show_contact_in_footer' => true,
            'show_social_in_footer' => true,
            'show_quick_links' => true,
            'features' => [
                'show_services' => true,
                'show_packages' => true,
                'show_build_package' => true,
                'show_sectors' => true,
                'show_printing' => true,
                'show_events' => true,
                'show_portfolio' => true,
                'show_suppliers' => true,
                'show_consultant' => true,
            ],
            'navigation' => [
                ['id' => 'consultant', 'label' => 'اكتشف احتياجك', 'path' => '/consultant', 'enabled' => true, 'order' => 10],
                ['id' => 'services', 'label' => 'الخدمات', 'path' => '/services', 'enabled' => true, 'order' => 20],
                ['id' => 'packages', 'label' => 'الباقات', 'path' => '/packages', 'enabled' => true, 'order' => 30],
                ['id' => 'build-package', 'label' => 'صمّم باقتك', 'path' => '/build-package', 'enabled' => true, 'order' => 40],
                ['id' => 'sectors', 'label' => 'القطاعات', 'path' => '/sectors', 'enabled' => true, 'order' => 50],
                ['id' => 'printing', 'label' => 'الطباعة والتغليف', 'path' => '/printing-packaging', 'enabled' => true, 'order' => 60],
                ['id' => 'events', 'label' => 'الفعاليات', 'path' => '/events', 'enabled' => true, 'order' => 70],
                ['id' => 'portfolio', 'label' => 'أعمالنا', 'path' => '/portfolio', 'enabled' => true, 'order' => 80],
                ['id' => 'suppliers', 'label' => 'الموردين', 'path' => '/suppliers', 'enabled' => true, 'order' => 90],
            ],
        ],
        'homepage' => [
            'hero_heading' => 'نمنح أعمالك أبعادًا للنمو',
            'hero_subheading' => 'منصة متكاملة تبدأ بتشخيص نشاطك، ثم تخطيط النمو واختيار الخدمات والموردين، وصولًا إلى التنفيذ والقياس والمتابعة.',
            'hero_primary_cta_label' => 'اكتشف احتياجك',
            'hero_primary_cta_path' => '/consultant',
            'hero_secondary_cta_label' => 'تصفح الخدمات',
            'hero_secondary_cta_path' => '/services',
            'section_titles' => [
                'services' => 'خدماتنا',
                'packages' => 'حلول النمو',
                'build_package' => 'صمّم باقتك',
                'printing' => 'الطباعة والتغليف',
                'events' => 'الفعاليات',
                'portfolio' => 'أعمالنا',
                'suppliers' => 'الموردون',
            ],
            'section_descriptions' => [],
        ],
        'cta' => [
            'items' => [
                ['id' => 'contact', 'label' => 'تواصل معنا', 'path' => '/contact', 'enabled' => true],
                ['id' => 'consultant', 'label' => 'احجز استشارة', 'path' => '/consultant', 'enabled' => true],
                ['id' => 'whatsapp', 'label' => 'واتساب', 'path' => 'whatsapp', 'enabled' => true],
                ['id' => 'start', 'label' => 'ابدأ مشروعك', 'path' => '/register', 'enabled' => true],
            ],
        ],
        'printing' => [
            'pickup_enabled' => true,
            'manual_delivery_enabled' => true,
            'delivery_cities' => [],
            'delivery_fee' => null,
            'free_delivery_threshold' => null,
        ],
        'events' => [
            'public_intro' => null,
        ],
        'customer' => [
            'welcome_text' => 'أهلاً بك في مساحة عملك',
            'support_contact' => null,
            'show_orders' => true,
            'show_quotes' => true,
            'show_payments' => true,
            'show_files' => true,
            'show_approvals' => true,
        ],
        'seo' => [
            'site_title' => 'حبر وأبعاد | خدمات التسويق والطباعة وتطوير الأعمال',
            'default_meta_title' => 'حبر وأبعاد | خدمات التسويق والطباعة وتطوير الأعمال',
            'default_meta_description' => 'منصة سعودية لخدمات تشخيص الأعمال والتسويق والمحتوى والتصوير والمتاجر والطباعة والتغليف وتنظيم المعارض والافتتاحات في جميع مدن المملكة.',
            'default_og_image_path' => null,
            'robots_index' => true,
        ],
    ];

    private const ALLOWED_NAV_IDS = [
        'consultant', 'services', 'packages', 'build-package', 'sectors',
        'printing', 'events', 'portfolio', 'suppliers',
    ];

    private const ALLOWED_SOCIAL = [
        'instagram', 'facebook', 'tiktok', 'x', 'twitter', 'linkedin',
        'youtube', 'snapchat', 'whatsapp', 'behance', 'dribbble', 'pinterest',
    ];

    private const ALLOWED_CTA_PATHS = [
        '/contact', '/consultant', '/register', '/login', '/build-package',
        '/services', '/packages', '/printing-packaging', '/events', 'whatsapp',
    ];

    public function __construct(
        private readonly OperationsAuditLogger $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function (): array {
            $out = self::DEFAULTS;
            $rows = PlatformSetting::query()->pluck('value', 'key')->all();

            foreach ($rows as $key => $value) {
                if (! is_string($key) || ! array_key_exists($key, $out)) {
                    continue;
                }

                $decoded = is_array($value) && array_key_exists('value', $value) ? $value['value'] : $value;
                if (is_array($decoded) && is_array($out[$key])) {
                    $merged = array_replace_recursive($out[$key], $decoded);
                    foreach (['items', 'navigation', 'delivery_cities'] as $listKey) {
                        if (array_key_exists($listKey, $decoded)) {
                            $merged[$listKey] = $decoded[$listKey];
                        }
                    }
                    $out[$key] = $merged;
                } else {
                    $out[$key] = $decoded;
                }
            }

            return $out;
        });
    }

    /**
     * Owner/admin full payload (still no secrets).
     *
     * @return array<string, mixed>
     */
    public function manage(User $actor): array
    {
        $this->assertCanManage($actor);

        return $this->withResolvedMedia($this->all());
    }

    /**
     * Public-safe subset only.
     *
     * @return array<string, mixed>
     */
    public function publicPayload(): array
    {
        $all = $this->withResolvedMedia($this->all());
        $brand = $all['brand'];
        $business = $all['business'];
        $contact = $all['contact'];
        $website = $all['website'];
        $seo = $all['seo'];

        $social = collect($all['social']['items'] ?? [])
            ->filter(fn ($item) => is_array($item)
                && ($item['enabled'] ?? false)
                && filled($item['url'] ?? null))
            ->sortBy(fn ($item) => (int) ($item['order'] ?? 0))
            ->values()
            ->map(fn (array $item) => [
                'platform' => $item['platform'],
                'url' => $item['url'],
                'order' => (int) ($item['order'] ?? 0),
            ])
            ->all();

        $nav = collect($website['navigation'] ?? [])
            ->filter(fn ($item) => is_array($item) && ($item['enabled'] ?? false))
            ->sortBy(fn ($item) => (int) ($item['order'] ?? 0))
            ->values()
            ->map(fn (array $item) => [
                'id' => $item['id'],
                'label' => $item['label'],
                'path' => $item['path'],
                'order' => (int) ($item['order'] ?? 0),
            ])
            ->all();

        $publicBusiness = [
            'trade_name' => $business['trade_name'],
            'short_description' => $business['short_description'],
            'country' => $business['country'],
            'city' => $business['city'],
            'working_hours' => $business['working_hours'],
            'working_days' => $business['working_days'],
        ];

        if (($business['expose_legal_publicly'] ?? false) === true) {
            $publicBusiness['address'] = $business['address'];
            $publicBusiness['district'] = $business['district'];
            $publicBusiness['postal_code'] = $business['postal_code'];
        }

        return [
            'brand' => [
                'name_ar' => $brand['name_ar'],
                'name_en' => $brand['name_en'],
                'tagline' => $brand['tagline'],
                'logo_url' => $brand['logo_url'],
                'logo_secondary_url' => $brand['logo_secondary_url'] ?? null,
                'mark_url' => $brand['mark_url'],
                'favicon_url' => $brand['favicon_url'],
            ],
            'business' => $publicBusiness,
            'contact' => [
                'phone' => $contact['phone'],
                'whatsapp_url' => $this->whatsappUrl($contact),
                'email' => $contact['email'],
                'support_email' => $contact['support_email'],
                'sales_email' => $contact['sales_email'],
                'address' => $contact['address'],
                'maps_url' => $contact['maps_url'],
            ],
            'social' => $social,
            'website' => [
                'footer_description' => $website['footer_description'],
                'copyright_text' => $website['copyright_text']
                    ?? ('© '.now()->year.' '.($brand['name_ar'] ?? 'حبر وأبعاد').'. جميع الحقوق محفوظة.'),
                'show_contact_in_footer' => (bool) $website['show_contact_in_footer'],
                'show_social_in_footer' => (bool) $website['show_social_in_footer'],
                'show_quick_links' => (bool) $website['show_quick_links'],
                'features' => $website['features'],
                'navigation' => $nav,
            ],
            'homepage' => $all['homepage'],
            'cta' => collect($all['cta']['items'] ?? [])
                ->filter(fn ($item) => is_array($item) && ($item['enabled'] ?? false))
                ->values()
                ->all(),
            'printing' => [
                'pickup_enabled' => (bool) ($all['printing']['pickup_enabled'] ?? true),
                'manual_delivery_enabled' => (bool) ($all['printing']['manual_delivery_enabled'] ?? true),
                'delivery_cities' => $all['printing']['delivery_cities'] ?? [],
            ],
            'events' => [
                'public_intro' => $all['events']['public_intro'] ?? null,
            ],
            'customer' => [
                'welcome_text' => $all['customer']['welcome_text'] ?? null,
                'support_contact' => $all['customer']['support_contact'] ?? null,
                'show_orders' => (bool) ($all['customer']['show_orders'] ?? true),
                'show_quotes' => (bool) ($all['customer']['show_quotes'] ?? true),
                'show_payments' => (bool) ($all['customer']['show_payments'] ?? true),
                'show_files' => (bool) ($all['customer']['show_files'] ?? true),
                'show_approvals' => (bool) ($all['customer']['show_approvals'] ?? true),
            ],
            'seo' => [
                'site_title' => $seo['site_title'],
                'default_meta_title' => $seo['default_meta_title'],
                'default_meta_description' => $seo['default_meta_description'],
                'default_og_image_url' => $seo['default_og_image_url'] ?? null,
                'robots_index' => (bool) ($seo['robots_index'] ?? true),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $groups
     * @return array<string, mixed>
     */
    public function update(User $actor, array $groups): array
    {
        $this->assertCanManage($actor);

        foreach ($groups as $group => $payload) {
            if (! is_string($group) || ! array_key_exists($group, self::DEFAULTS) || ! is_array($payload)) {
                continue;
            }

            $validated = match ($group) {
                'brand' => $this->validateBrand($payload),
                'business' => $this->validateBusiness($payload),
                'contact' => $this->validateContact($payload),
                'social' => $this->validateSocial($payload),
                'website' => $this->validateWebsite($payload),
                'homepage' => $this->validateHomepage($payload),
                'cta' => $this->validateCta($payload),
                'printing' => $this->validatePrinting($payload),
                'events' => ['public_intro' => isset($payload['public_intro']) ? $this->plain($payload['public_intro']) : null],
                'customer' => $this->validateCustomer($payload),
                'seo' => $this->validateSeo($payload),
                default => [],
            };

            if ($validated === []) {
                continue;
            }

            $current = $this->all()[$group] ?? self::DEFAULTS[$group];
            $merged = array_replace_recursive(is_array($current) ? $current : [], $validated);
            // List-shaped keys must be replaced wholesale (not merged by index).
            foreach (['items', 'navigation', 'delivery_cities'] as $listKey) {
                if (array_key_exists($listKey, $validated)) {
                    $merged[$listKey] = $validated[$listKey];
                }
            }
            $this->put($group, $merged);
        }

        $this->forgetCache();

        $this->audit->log($actor, 'platform_settings.updated', null, [
            'groups' => array_keys($groups),
        ]);

        return $this->manage($actor);
    }

    public function storeBrandAsset(User $actor, string $slot, UploadedFile $file): array
    {
        $this->assertCanManage($actor);

        $allowed = [
            'logo' => 'logo_path',
            'logo_secondary' => 'logo_secondary_path',
            'mark' => 'mark_path',
            'favicon' => 'favicon_path',
            'logo_dark' => 'logo_dark_path',
            'logo_light' => 'logo_light_path',
            'og_image' => 'og',
        ];

        if (! array_key_exists($slot, $allowed)) {
            throw ValidationException::withMessages([
                'slot' => ['Unsupported brand asset slot.'],
            ]);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        if (! in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg', 'ico'], true)) {
            throw ValidationException::withMessages([
                'file' => ['Unsupported image type.'],
            ]);
        }

        $filename = $slot.'-'.Str::lower(Str::random(8)).'.'.$ext;
        $path = $file->storeAs('platform/brand', $filename, 'public');

        // Never trust a poisoned/partial cache while writing media slots.
        $this->forgetCache();

        if ($slot === 'og_image') {
            $seo = $this->all()['seo'] ?? self::DEFAULTS['seo'];
            $seo['default_og_image_path'] = $path;
            $this->put('seo', $seo);
        } else {
            $brand = $this->all()['brand'] ?? self::DEFAULTS['brand'];
            $brand[$allowed[$slot]] = $path;
            $this->put('brand', $brand);
        }

        $this->forgetCache();
        $this->audit->log($actor, 'platform_settings.brand_asset_updated', null, [
            'slot' => $slot,
            'path' => $path,
        ]);

        return $this->manage($actor);
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  array<string, mixed>  $all
     * @return array<string, mixed>
     */
    private function withResolvedMedia(array $all): array
    {
        $brand = $all['brand'];
        $brand['logo_url'] = $this->publicUrl($brand['logo_path'] ?? null) ?? '/brand/logo.png';
        $brand['logo_secondary_url'] = $this->publicUrl($brand['logo_secondary_path'] ?? null);
        $brand['mark_url'] = $this->publicUrl($brand['mark_path'] ?? null) ?? '/brand/mark.png';
        $brand['favicon_url'] = $this->publicUrl($brand['favicon_path'] ?? null) ?? '/brand/favicon-32.png';
        $brand['logo_dark_url'] = $this->publicUrl($brand['logo_dark_path'] ?? null);
        $brand['logo_light_url'] = $this->publicUrl($brand['logo_light_path'] ?? null);
        $all['brand'] = $brand;

        $seo = $all['seo'];
        $seo['default_og_image_url'] = $this->publicUrl($seo['default_og_image_path'] ?? null) ?? $brand['logo_url'];
        $all['seo'] = $seo;

        return $all;
    }

    private function publicUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    private function whatsappUrl(array $contact): ?string
    {
        $cc = preg_replace('/\D+/', '', (string) ($contact['whatsapp_country_code'] ?? '')) ?: '';
        $num = preg_replace('/\D+/', '', (string) ($contact['whatsapp_number'] ?? '')) ?: '';
        if ($cc === '' || $num === '') {
            return null;
        }

        return 'https://wa.me/'.$cc.ltrim($num, '0');
    }

    private function put(string $key, mixed $value): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => ['value' => $value]],
        );
    }

    private function assertCanManage(User $actor): void
    {
        $role = $actor->role instanceof UserRole ? $actor->role : UserRole::tryFrom((string) $actor->role);
        if ($role === null || ! $role->canManagePlatformSettings()) {
            abort(403, __('messages.unauthorized'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateBrand(array $payload): array
    {
        $out = [];
        foreach (['name_ar', 'name_en', 'tagline'] as $field) {
            if (array_key_exists($field, $payload)) {
                $out[$field] = $this->plain($payload[$field], 255);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateBusiness(array $payload): array
    {
        $out = [];
        foreach ([
            'trade_name', 'short_description', 'full_description', 'commercial_register', 'tax_number',
            'country', 'city', 'address', 'district', 'postal_code', 'working_hours', 'working_days',
        ] as $field) {
            if (array_key_exists($field, $payload)) {
                $out[$field] = $this->plain($payload[$field], $field === 'full_description' ? 5000 : 500);
            }
        }
        if (array_key_exists('expose_legal_publicly', $payload)) {
            $out['expose_legal_publicly'] = (bool) $payload['expose_legal_publicly'];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateContact(array $payload): array
    {
        $out = [];
        foreach (['phone', 'whatsapp_country_code', 'whatsapp_number', 'address'] as $field) {
            if (array_key_exists($field, $payload)) {
                $out[$field] = $this->plain($payload[$field], 80);
            }
        }
        foreach (['email', 'support_email', 'sales_email'] as $field) {
            if (array_key_exists($field, $payload)) {
                $raw = $payload[$field];
                if ($raw === null || $raw === '') {
                    $out[$field] = null;
                } elseif (! filter_var((string) $raw, FILTER_VALIDATE_EMAIL)) {
                    throw ValidationException::withMessages([$field => ['البريد الإلكتروني غير صالح.']]);
                } else {
                    $out[$field] = (string) $raw;
                }
            }
        }
        if (array_key_exists('maps_url', $payload)) {
            $out['maps_url'] = $this->httpsUrl($payload['maps_url'], 'maps_url');
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateSocial(array $payload): array
    {
        $items = $payload['items'] ?? null;
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $platform = strtolower((string) ($item['platform'] ?? ''));
            if (! in_array($platform, self::ALLOWED_SOCIAL, true)) {
                throw ValidationException::withMessages([
                    "social.items.$index.platform" => ['Unsupported social platform.'],
                ]);
            }
            $url = $this->httpsUrl($item['url'] ?? null, "social.items.$index.url");
            $normalized[] = [
                'platform' => $platform === 'twitter' ? 'x' : $platform,
                'url' => $url,
                'enabled' => (bool) ($item['enabled'] ?? false),
                'order' => (int) ($item['order'] ?? (($index + 1) * 10)),
            ];
        }

        return ['items' => $normalized];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateWebsite(array $payload): array
    {
        $out = [];
        foreach (['footer_description', 'copyright_text'] as $field) {
            if (array_key_exists($field, $payload)) {
                $out[$field] = $this->plain($payload[$field], 500);
            }
        }
        foreach (['show_contact_in_footer', 'show_social_in_footer', 'show_quick_links'] as $field) {
            if (array_key_exists($field, $payload)) {
                $out[$field] = (bool) $payload[$field];
            }
        }
        if (isset($payload['features']) && is_array($payload['features'])) {
            $features = [];
            foreach (array_keys(self::DEFAULTS['website']['features']) as $key) {
                if (array_key_exists($key, $payload['features'])) {
                    $features[$key] = (bool) $payload['features'][$key];
                }
            }
            $out['features'] = $features;
        }
        if (isset($payload['navigation']) && is_array($payload['navigation'])) {
            $nav = [];
            foreach ($payload['navigation'] as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $id = (string) ($item['id'] ?? '');
                if (! in_array($id, self::ALLOWED_NAV_IDS, true)) {
                    continue;
                }
                $defaults = collect(self::DEFAULTS['website']['navigation'])->firstWhere('id', $id);
                $nav[] = [
                    'id' => $id,
                    'label' => $this->plain($item['label'] ?? ($defaults['label'] ?? $id), 80) ?? ($defaults['label'] ?? $id),
                    'path' => $defaults['path'] ?? '/',
                    'enabled' => (bool) ($item['enabled'] ?? true),
                    'order' => (int) ($item['order'] ?? ($defaults['order'] ?? 100)),
                ];
            }
            $out['navigation'] = $nav;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateHomepage(array $payload): array
    {
        $out = [];
        foreach ([
            'hero_heading', 'hero_subheading', 'hero_primary_cta_label', 'hero_secondary_cta_label',
        ] as $field) {
            if (array_key_exists($field, $payload)) {
                $out[$field] = $this->plain($payload[$field], 500);
            }
        }
        foreach (['hero_primary_cta_path', 'hero_secondary_cta_path'] as $field) {
            if (array_key_exists($field, $payload)) {
                $path = (string) $payload[$field];
                if (! in_array($path, self::ALLOWED_CTA_PATHS, true) && ! str_starts_with($path, '/')) {
                    throw ValidationException::withMessages([$field => ['مسار زر الدعوة غير صالح.']]);
                }
                if (! in_array($path, self::ALLOWED_CTA_PATHS, true) && ! preg_match('#^/[a-z0-9\\-/]*$#i', $path)) {
                    throw ValidationException::withMessages([$field => ['مسار زر الدعوة غير صالح.']]);
                }
                $out[$field] = $path;
            }
        }
        if (isset($payload['section_titles']) && is_array($payload['section_titles'])) {
            $titles = [];
            foreach ($payload['section_titles'] as $key => $value) {
                if (is_string($key)) {
                    $titles[$key] = $this->plain($value, 120);
                }
            }
            $out['section_titles'] = $titles;
        }
        if (isset($payload['section_descriptions']) && is_array($payload['section_descriptions'])) {
            $descriptions = [];
            foreach ($payload['section_descriptions'] as $key => $value) {
                if (is_string($key)) {
                    $descriptions[$key] = $this->plain($value, 500);
                }
            }
            $out['section_descriptions'] = $descriptions;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateCta(array $payload): array
    {
        $items = $payload['items'] ?? null;
        if (! is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $path = (string) ($item['path'] ?? '');
            if ($path !== 'whatsapp' && ! in_array($path, self::ALLOWED_CTA_PATHS, true)) {
                continue;
            }
            $out[] = [
                'id' => $this->plain($item['id'] ?? Str::slug((string) ($item['label'] ?? 'cta')), 40) ?? 'cta',
                'label' => $this->plain($item['label'] ?? '', 80) ?? '',
                'path' => $path,
                'enabled' => (bool) ($item['enabled'] ?? false),
            ];
        }

        return ['items' => $out];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validatePrinting(array $payload): array
    {
        $out = [];
        foreach (['pickup_enabled', 'manual_delivery_enabled'] as $field) {
            if (array_key_exists($field, $payload)) {
                $out[$field] = (bool) $payload[$field];
            }
        }
        if (isset($payload['delivery_cities']) && is_array($payload['delivery_cities'])) {
            $out['delivery_cities'] = array_values(array_filter(array_map(
                fn ($city) => $this->plain($city, 80),
                $payload['delivery_cities'],
            )));
        }
        foreach (['delivery_fee', 'free_delivery_threshold'] as $field) {
            if (array_key_exists($field, $payload)) {
                $raw = $payload[$field];
                $out[$field] = $raw === null || $raw === '' ? null : max(0, (float) $raw);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateCustomer(array $payload): array
    {
        $out = [];
        if (array_key_exists('welcome_text', $payload)) {
            $out['welcome_text'] = $this->plain($payload['welcome_text'], 255);
        }
        if (array_key_exists('support_contact', $payload)) {
            $out['support_contact'] = $this->plain($payload['support_contact'], 120);
        }
        foreach (['show_orders', 'show_quotes', 'show_payments', 'show_files', 'show_approvals'] as $field) {
            if (array_key_exists($field, $payload)) {
                $out[$field] = (bool) $payload[$field];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateSeo(array $payload): array
    {
        $out = [];
        foreach (['site_title', 'default_meta_title', 'default_meta_description'] as $field) {
            if (array_key_exists($field, $payload)) {
                $out[$field] = $this->plain($payload[$field], $field === 'default_meta_description' ? 320 : 180);
            }
        }
        if (array_key_exists('robots_index', $payload)) {
            $out['robots_index'] = (bool) $payload['robots_index'];
        }

        return $out;
    }

    private function plain(mixed $value, int $max = 255): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim(strip_tags((string) $value));
        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $max);
    }

    private function httpsUrl(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $url = trim((string) $value);
        if (! preg_match('#^https://#i', $url)) {
            throw ValidationException::withMessages([
                $field => ['URL must use https.'],
            ]);
        }
        if (preg_match('#^(javascript|data):#i', $url)) {
            throw ValidationException::withMessages([
                $field => ['Unsafe URL scheme.'],
            ]);
        }

        return mb_substr($url, 0, 500);
    }
}
