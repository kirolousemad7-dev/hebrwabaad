import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { BrandLogo } from '../../components/brand/BrandLogo'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { usePlatformSettings } from '../../context/PlatformSettingsContext'
import {
  getEventTypesAdmin,
  getPlatformSettings,
  saveEventType,
  updatePlatformSettings,
  uploadPlatformBrandAsset,
} from '../../services/platformSettings'
import type { PlatformSettingsManage } from '../../types/platformSettings'
import { describeApiError } from '../../utils/errors'
import { resolveMediaUrl } from '../../utils/mediaUrl'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

const TABS = [
  { id: 'brand', label: 'الهوية' },
  { id: 'business', label: 'الشركة' },
  { id: 'contact', label: 'التواصل' },
  { id: 'social', label: 'السوشيال' },
  { id: 'website', label: 'الموقع' },
  { id: 'homepage', label: 'الصفحة الرئيسية' },
  { id: 'events', label: 'الفعاليات' },
  { id: 'customer', label: 'العميل' },
  { id: 'seo', label: 'SEO' },
  { id: 'advanced', label: 'متقدم' },
] as const

type TabId = (typeof TABS)[number]['id']

const SOCIAL_PLATFORMS = [
  'instagram',
  'facebook',
  'tiktok',
  'x',
  'linkedin',
  'youtube',
  'snapchat',
  'whatsapp',
  'behance',
  'dribbble',
  'pinterest',
]

const FEATURE_LABELS: Record<string, string> = {
  show_services: 'الخدمات',
  show_packages: 'الباقات',
  show_build_package: 'صمّم باقتك',
  show_sectors: 'القطاعات',
  show_printing: 'الطباعة والتغليف',
  show_events: 'الفعاليات',
  show_portfolio: 'أعمالنا',
  show_suppliers: 'الموردين',
  show_consultant: 'اكتشف احتياجك',
}

export function OwnerPlatformSettingsPage() {
  const { refresh: refreshPublic } = usePlatformSettings()
  const [tab, setTab] = useState<TabId>('brand')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [dirty, setDirty] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [form, setForm] = useState<PlatformSettingsManage | null>(null)
  const [eventTypes, setEventTypes] = useState<Array<Record<string, unknown>>>([])
  const [eventDraft, setEventDraft] = useState({ name_ar: '', description: '' })

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const [settingsRes, eventsRes] = await Promise.all([
        getPlatformSettings(),
        getEventTypesAdmin().catch(() => ({ data: [] as Array<Record<string, unknown>> })),
      ])
      setForm(settingsRes.data)
      setEventTypes(eventsRes.data ?? [])
      setDirty(false)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل إعدادات المنصة.'))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    function onBeforeUnload(event: BeforeUnloadEvent) {
      if (!dirty) return
      event.preventDefault()
      event.returnValue = ''
    }
    window.addEventListener('beforeunload', onBeforeUnload)
    return () => window.removeEventListener('beforeunload', onBeforeUnload)
  }, [dirty])

  const brandPreview = useMemo(() => {
    if (!form) return null
    const brand = form.brand as Record<string, string | null>
    return {
      name: (brand.name_ar as string) || 'حبر وأبعاد',
      logo: resolveMediaUrl((brand.logo_url as string) || '/brand/logo.png'),
    }
  }, [form])

  function patchGroup<K extends keyof PlatformSettingsManage>(
    group: K,
    patch: Partial<PlatformSettingsManage[K]> | Record<string, unknown>,
  ) {
    setForm((prev) => {
      if (!prev) return prev
      return {
        ...prev,
        [group]: { ...(prev[group] as object), ...patch },
      }
    })
    setDirty(true)
  }

  async function handleSave() {
    if (!form) return
    setSaving(true)
    setError(null)
    setNotice(null)
    try {
      const response = await updatePlatformSettings(form)
      setForm(response.data)
      setDirty(false)
      setNotice('تم حفظ الإعدادات بنجاح')
      await refreshPublic()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ الإعدادات.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleUpload(slot: string, file: File | null) {
    if (!file) return
    setSaving(true)
    setError(null)
    try {
      const response = await uploadPlatformBrandAsset(slot, file)
      setForm(response.data)
      setNotice('تم رفع الملف بنجاح')
      await refreshPublic()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر رفع الملف.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleAddEventType() {
    if (!eventDraft.name_ar.trim()) {
      setError('اسم نوع الفعالية مطلوب.')
      return
    }
    try {
      await saveEventType({
        name_ar: eventDraft.name_ar,
        description: eventDraft.description || null,
        is_active: true,
        is_public: true,
      })
      setEventDraft({ name_ar: '', description: '' })
      const eventsRes = await getEventTypesAdmin()
      setEventTypes(eventsRes.data ?? [])
      setNotice('تمت إضافة نوع الفعالية.')
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إضافة نوع الفعالية.'))
    }
  }

  if (loading) {
    return <DashboardPanelSkeleton label="جاري تحميل إعدادات المنصة…" />
  }

  if (error && !form) {
    return <DashboardErrorState message={error} onRetry={() => void load()} />
  }

  if (!form) {
    return <DashboardEmptyState title="لا توجد إعدادات" description="تعذر تحميل إعدادات المنصة." />
  }

  const brand = form.brand as Record<string, string | null>
  const business = form.business as Record<string, string | boolean | null>
  const contact = form.contact as Record<string, string | null>
  const website = form.website as {
    footer_description?: string | null
    copyright_text?: string | null
    show_contact_in_footer?: boolean
    show_social_in_footer?: boolean
    show_quick_links?: boolean
    features?: Record<string, boolean>
    navigation?: Array<{ id: string; label: string; path: string; enabled: boolean; order: number }>
  }
  const homepage = form.homepage as Record<string, unknown>
  const customer = form.customer as Record<string, string | boolean | null>
  const seo = form.seo as Record<string, string | boolean | null>
  const printing = form.printing as Record<string, unknown>
  const socialItems = form.social?.items ?? []

  return (
    <div className="space-y-6" dir="rtl">
      <DashboardSection
        title="إعدادات المنصة"
        description="إدارة الهوية والمحتوى الظاهر للعملاء دون تعديل الشيفرة."
        action={
          <div className="flex flex-wrap gap-2">
            <Link
              to="/owner/printing-catalog"
              className="rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50"
            >
              كتالوج الطباعة
            </Link>
            <button
              type="button"
              disabled={saving || !dirty}
              onClick={() => void handleSave()}
              className="rounded-lg bg-brand-ink-900 px-4 py-2 text-sm text-white disabled:opacity-50"
            >
              {saving ? 'جاري الحفظ…' : 'حفظ التغييرات'}
            </button>
          </div>
        }
      >
        {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}
        {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

        <div className="mb-4 flex flex-wrap gap-2 border-b border-slate-200 pb-3">
          {TABS.map((item) => (
            <button
              key={item.id}
              type="button"
              onClick={() => setTab(item.id)}
              className={`rounded-full px-3 py-1.5 text-sm ${
                tab === item.id
                  ? 'bg-brand-ink-900 text-white'
                  : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
              }`}
            >
              {item.label}
            </button>
          ))}
        </div>

        {tab === 'brand' ? (
          <div className="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
            <div className="space-y-3">
              <label className="block text-sm">
                اسم الشركة بالعربي
                <input
                  className={`${fieldClass} mt-1`}
                  value={(brand.name_ar as string) || ''}
                  onChange={(e) => patchGroup('brand', { name_ar: e.target.value })}
                />
              </label>
              <label className="block text-sm">
                اسم الشركة بالإنجليزي
                <input
                  className={`${fieldClass} mt-1`}
                  value={(brand.name_en as string) || ''}
                  onChange={(e) => patchGroup('brand', { name_en: e.target.value })}
                />
              </label>
              <label className="block text-sm">
                الشعار التعريفي
                <input
                  className={`${fieldClass} mt-1`}
                  value={(brand.tagline as string) || ''}
                  onChange={(e) => patchGroup('brand', { tagline: e.target.value })}
                />
              </label>
              {(
                [
                  ['logo', 'الشعار الأساسي'],
                  ['logo_secondary', 'الشعار الثانوي'],
                  ['mark', 'أيقونة العلامة'],
                  ['favicon', 'Favicon'],
                  ['logo_dark', 'شعار الوضع الداكن'],
                  ['logo_light', 'شعار الوضع الفاتح'],
                ] as const
              ).map(([slot, label]) => (
                <label key={slot} className="block text-sm">
                  {label}
                  <input
                    type="file"
                    accept="image/png,image/jpeg,image/webp,image/svg+xml,image/x-icon"
                    className="mt-1 block w-full text-sm"
                    onChange={(e) => void handleUpload(slot, e.target.files?.[0] ?? null)}
                  />
                </label>
              ))}
            </div>
            <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
              <p className="mb-3 text-sm font-medium text-slate-700">معاينة سريعة</p>
              <BrandLogo size="sidebar" to={null} />
              <p className="mt-3 text-lg font-semibold">{brandPreview?.name}</p>
              {brandPreview?.logo ? (
                <img src={brandPreview.logo} alt="" className="mt-3 max-h-20 object-contain" />
              ) : null}
            </div>
          </div>
        ) : null}

        {tab === 'business' ? (
          <div className="grid gap-3 sm:grid-cols-2">
            {(
              [
                ['trade_name', 'اسم النشاط'],
                ['short_description', 'الوصف المختصر'],
                ['full_description', 'الوصف الكامل'],
                ['commercial_register', 'السجل التجاري'],
                ['tax_number', 'الرقم الضريبي'],
                ['country', 'الدولة'],
                ['city', 'المدينة'],
                ['address', 'العنوان'],
                ['district', 'المنطقة'],
                ['postal_code', 'الرمز البريدي'],
                ['working_hours', 'ساعات العمل'],
                ['working_days', 'أيام العمل'],
              ] as const
            ).map(([key, label]) => (
              <label key={key} className="block text-sm sm:col-span-1">
                {label}
                <input
                  className={`${fieldClass} mt-1`}
                  value={String(business[key] ?? '')}
                  onChange={(e) => patchGroup('business', { [key]: e.target.value })}
                />
              </label>
            ))}
            <label className="flex items-center gap-2 text-sm sm:col-span-2">
              <input
                type="checkbox"
                checked={Boolean(business.expose_legal_publicly)}
                onChange={(e) => patchGroup('business', { expose_legal_publicly: e.target.checked })}
              />
              إظهار العنوان/البيانات القانونية للعامة
            </label>
          </div>
        ) : null}

        {tab === 'contact' ? (
          <div className="grid gap-3 sm:grid-cols-2">
            {(
              [
                ['phone', 'رقم الهاتف'],
                ['whatsapp_country_code', 'رمز الدولة لواتساب'],
                ['whatsapp_number', 'رقم واتساب'],
                ['email', 'البريد الإلكتروني'],
                ['support_email', 'بريد الدعم'],
                ['sales_email', 'بريد المبيعات'],
                ['address', 'العنوان'],
                ['maps_url', 'رابط خرائط Google'],
              ] as const
            ).map(([key, label]) => (
              <label key={key} className="block text-sm">
                {label}
                <input
                  className={`${fieldClass} mt-1`}
                  value={String(contact[key] ?? '')}
                  onChange={(e) => patchGroup('contact', { [key]: e.target.value })}
                />
              </label>
            ))}
          </div>
        ) : null}

        {tab === 'social' ? (
          <div className="space-y-4">
            {SOCIAL_PLATFORMS.map((platform, index) => {
              const existing = socialItems.find((item) => item.platform === platform) ?? {
                platform,
                url: '',
                enabled: false,
                order: (index + 1) * 10,
              }
              return (
                <div key={platform} className="grid gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-[8rem_1fr_auto]">
                  <p className="text-sm font-medium capitalize">{platform}</p>
                  <input
                    className={fieldClass}
                    placeholder="https://"
                    value={existing.url || ''}
                    onChange={(e) => {
                      const next = [...socialItems.filter((item) => item.platform !== platform), {
                        ...existing,
                        url: e.target.value,
                      }]
                      patchGroup('social', { items: next })
                    }}
                  />
                  <label className="flex items-center gap-2 text-sm">
                    <input
                      type="checkbox"
                      checked={Boolean(existing.enabled)}
                      onChange={(e) => {
                        const next = [
                          ...socialItems.filter((item) => item.platform !== platform),
                          { ...existing, enabled: e.target.checked },
                        ]
                        patchGroup('social', { items: next })
                      }}
                    />
                    مفعّل
                  </label>
                </div>
              )
            })}
          </div>
        ) : null}

        {tab === 'website' ? (
          <div className="space-y-6">
            <label className="block text-sm">
              وصف التذييل
              <textarea
                className={`${fieldClass} mt-1`}
                rows={3}
                value={website.footer_description || ''}
                onChange={(e) => patchGroup('website', { footer_description: e.target.value })}
              />
            </label>
            <label className="block text-sm">
              نص حقوق النشر
              <input
                className={`${fieldClass} mt-1`}
                value={website.copyright_text || ''}
                onChange={(e) => patchGroup('website', { copyright_text: e.target.value })}
              />
            </label>
            <div className="flex flex-wrap gap-4 text-sm">
              {(
                [
                  ['show_contact_in_footer', 'إظهار التواصل في التذييل'],
                  ['show_social_in_footer', 'إظهار السوشيال في التذييل'],
                  ['show_quick_links', 'إظهار الروابط السريعة'],
                ] as const
              ).map(([key, label]) => (
                <label key={key} className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    checked={Boolean(website[key])}
                    onChange={(e) => patchGroup('website', { [key]: e.target.checked })}
                  />
                  {label}
                </label>
              ))}
            </div>
            <div>
              <p className="mb-2 text-sm font-semibold">ظهور الأقسام العامة</p>
              <div className="grid gap-2 sm:grid-cols-2">
                {Object.entries(FEATURE_LABELS).map(([key, label]) => (
                  <label key={key} className="flex items-center gap-2 text-sm">
                    <input
                      type="checkbox"
                      checked={website.features?.[key] !== false}
                      onChange={(e) =>
                        patchGroup('website', {
                          features: { ...(website.features ?? {}), [key]: e.target.checked },
                        })
                      }
                    />
                    {label}
                  </label>
                ))}
              </div>
            </div>
            <div>
              <p className="mb-2 text-sm font-semibold">ترتيب قائمة التنقل</p>
              <div className="space-y-2">
                {(website.navigation ?? []).map((item) => (
                  <div key={item.id} className="grid gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-[1fr_6rem_auto]">
                    <span className="text-sm">{item.label}</span>
                    <input
                      type="number"
                      className={fieldClass}
                      value={item.order}
                      onChange={(e) => {
                        const navigation = (website.navigation ?? []).map((row) =>
                          row.id === item.id ? { ...row, order: Number(e.target.value) } : row,
                        )
                        patchGroup('website', { navigation })
                      }}
                    />
                    <label className="flex items-center gap-2 text-sm">
                      <input
                        type="checkbox"
                        checked={item.enabled}
                        onChange={(e) => {
                          const navigation = (website.navigation ?? []).map((row) =>
                            row.id === item.id ? { ...row, enabled: e.target.checked } : row,
                          )
                          patchGroup('website', { navigation })
                        }}
                      />
                      ظاهر
                    </label>
                  </div>
                ))}
              </div>
            </div>
          </div>
        ) : null}

        {tab === 'homepage' ? (
          <div className="grid gap-3">
            {(
              [
                ['hero_heading', 'عنوان البطل'],
                ['hero_subheading', 'الوصف'],
                ['hero_primary_cta_label', 'زر أساسي'],
                ['hero_primary_cta_path', 'مسار الزر الأساسي'],
                ['hero_secondary_cta_label', 'زر ثانوي'],
                ['hero_secondary_cta_path', 'مسار الزر الثانوي'],
              ] as const
            ).map(([key, label]) => (
              <label key={key} className="block text-sm">
                {label}
                <input
                  className={`${fieldClass} mt-1`}
                  value={String(homepage[key] ?? '')}
                  onChange={(e) => patchGroup('homepage', { [key]: e.target.value })}
                />
              </label>
            ))}
            <p className="text-sm font-semibold">عناوين الأقسام</p>
            {Object.entries((homepage.section_titles as Record<string, string>) || {}).map(
              ([key, value]) => (
                <label key={key} className="block text-sm">
                  {key}
                  <input
                    className={`${fieldClass} mt-1`}
                    value={value || ''}
                    onChange={(e) =>
                      patchGroup('homepage', {
                        section_titles: {
                          ...((homepage.section_titles as Record<string, string>) || {}),
                          [key]: e.target.value,
                        },
                      })
                    }
                  />
                </label>
              ),
            )}
          </div>
        ) : null}

        {tab === 'events' ? (
          <div className="space-y-4">
            <label className="block text-sm">
              مقدمة صفحة الفعاليات
              <textarea
                className={`${fieldClass} mt-1`}
                rows={3}
                value={String((form.events as { public_intro?: string }).public_intro ?? '')}
                onChange={(e) => patchGroup('events', { public_intro: e.target.value })}
              />
            </label>
            <div className="rounded-xl border border-slate-200 p-4">
              <p className="mb-3 text-sm font-semibold">أنواع الفعاليات</p>
              <ul className="mb-4 space-y-2 text-sm">
                {eventTypes.map((item) => (
                  <li key={String(item.id)} className="flex items-center justify-between gap-2">
                    <span>{String(item.name_ar)}</span>
                    <button
                      type="button"
                      className="text-xs text-slate-600 underline"
                      onClick={() =>
                        void saveEventType(
                          { is_active: !item.is_active },
                          Number(item.id),
                        ).then(async () => {
                          const eventsRes = await getEventTypesAdmin()
                          setEventTypes(eventsRes.data ?? [])
                        })
                      }
                    >
                      {item.is_active ? 'تعطيل' : 'تفعيل'}
                    </button>
                  </li>
                ))}
              </ul>
              <div className="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                <input
                  className={fieldClass}
                  placeholder="اسم النوع"
                  value={eventDraft.name_ar}
                  onChange={(e) => setEventDraft((prev) => ({ ...prev, name_ar: e.target.value }))}
                />
                <input
                  className={fieldClass}
                  placeholder="وصف مختصر"
                  value={eventDraft.description}
                  onChange={(e) => setEventDraft((prev) => ({ ...prev, description: e.target.value }))}
                />
                <button
                  type="button"
                  onClick={() => void handleAddEventType()}
                  className="rounded-lg bg-brand-ink-900 px-3 py-2 text-sm text-white"
                >
                  إضافة
                </button>
              </div>
            </div>
          </div>
        ) : null}

        {tab === 'customer' ? (
          <div className="grid gap-3">
            <label className="block text-sm">
              نص الترحيب
              <input
                className={`${fieldClass} mt-1`}
                value={String(customer.welcome_text ?? '')}
                onChange={(e) => patchGroup('customer', { welcome_text: e.target.value })}
              />
            </label>
            <label className="block text-sm">
              تواصل الدعم
              <input
                className={`${fieldClass} mt-1`}
                value={String(customer.support_contact ?? '')}
                onChange={(e) => patchGroup('customer', { support_contact: e.target.value })}
              />
            </label>
            <div className="flex flex-wrap gap-4 text-sm">
              {(
                [
                  ['show_orders', 'الطلبات'],
                  ['show_quotes', 'عروض الأسعار'],
                  ['show_payments', 'المدفوعات'],
                  ['show_files', 'الملفات'],
                  ['show_approvals', 'الموافقات'],
                ] as const
              ).map(([key, label]) => (
                <label key={key} className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    checked={customer[key] !== false}
                    onChange={(e) => patchGroup('customer', { [key]: e.target.checked })}
                  />
                  {label}
                </label>
              ))}
            </div>
          </div>
        ) : null}

        {tab === 'seo' ? (
          <div className="grid gap-3">
            {(
              [
                ['site_title', 'عنوان الموقع'],
                ['default_meta_title', 'Meta Title الافتراضي'],
                ['default_meta_description', 'Meta Description الافتراضي'],
              ] as const
            ).map(([key, label]) => (
              <label key={key} className="block text-sm">
                {label}
                <input
                  className={`${fieldClass} mt-1`}
                  value={String(seo[key] ?? '')}
                  onChange={(e) => patchGroup('seo', { [key]: e.target.value })}
                />
              </label>
            ))}
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={seo.robots_index !== false}
                onChange={(e) => patchGroup('seo', { robots_index: e.target.checked })}
              />
              السماح بالفهرسة (robots index)
            </label>
            <label className="block text-sm">
              صورة المشاركة الافتراضية (Open Graph)
              <input
                type="file"
                accept="image/*"
                className="mt-1 block w-full text-sm"
                onChange={(e) => void handleUpload('og_image', e.target.files?.[0] ?? null)}
              />
            </label>
          </div>
        ) : null}

        {tab === 'advanced' ? (
          <div className="space-y-4">
            <p className="text-sm text-slate-600">
              إعدادات توصيل الطباعة العامة (قواعد عمل فقط — بدون تكامل شحن).
            </p>
            <div className="flex flex-wrap gap-4 text-sm">
              <label className="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={Boolean(printing.pickup_enabled)}
                  onChange={(e) => patchGroup('printing', { pickup_enabled: e.target.checked })}
                />
                استلام من الفرع
              </label>
              <label className="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={Boolean(printing.manual_delivery_enabled)}
                  onChange={(e) =>
                    patchGroup('printing', { manual_delivery_enabled: e.target.checked })
                  }
                />
                توصيل يدوي
              </label>
            </div>
            <label className="block text-sm">
              مدن التوصيل (مفصولة بفاصلة)
              <input
                className={`${fieldClass} mt-1`}
                value={((printing.delivery_cities as string[]) || []).join(', ')}
                onChange={(e) =>
                  patchGroup('printing', {
                    delivery_cities: e.target.value
                      .split(',')
                      .map((city) => city.trim())
                      .filter(Boolean),
                  })
                }
              />
            </label>
            <label className="block text-sm">
              رسوم التوصيل الثابتة (اختياري)
              <input
                type="number"
                className={`${fieldClass} mt-1`}
                value={printing.delivery_fee == null ? '' : String(printing.delivery_fee)}
                onChange={(e) =>
                  patchGroup('printing', {
                    delivery_fee: e.target.value === '' ? null : Number(e.target.value),
                  })
                }
              />
            </label>
            <label className="block text-sm">
              حد التوصيل المجاني (اختياري)
              <input
                type="number"
                className={`${fieldClass} mt-1`}
                value={
                  printing.free_delivery_threshold == null
                    ? ''
                    : String(printing.free_delivery_threshold)
                }
                onChange={(e) =>
                  patchGroup('printing', {
                    free_delivery_threshold: e.target.value === '' ? null : Number(e.target.value),
                  })
                }
              />
            </label>
          </div>
        ) : null}
      </DashboardSection>
    </div>
  )
}
