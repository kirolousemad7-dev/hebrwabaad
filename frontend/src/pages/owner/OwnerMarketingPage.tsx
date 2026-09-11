import { FormEvent, useState } from 'react'
import { Link } from 'react-router-dom'
import { DashboardErrorState, DashboardPanelSkeleton } from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useAsyncData } from '../../hooks/useAsyncData'
import {
  createAdminPortfolioItem,
  createAdminTestimonial,
  deleteAdminPortfolioItem,
  deleteAdminTestimonial,
  getAdminPortfolio,
  getAdminTestimonials,
  PORTFOLIO_CATEGORIES,
  PORTFOLIO_MEDIA_TYPES,
  updateAdminPortfolioItem,
  updateAdminTestimonial,
  type PortfolioCategory,
  type PortfolioItem,
  type PortfolioMediaItem,
  type PortfolioMediaType,
  type Testimonial,
} from '../../services/marketing'
import { describeApiError } from '../../utils/errors'

const CATEGORY_LABELS: Record<PortfolioCategory, string> = {
  web: 'مواقع',
  branding: 'هوية',
  social: 'سوشيال',
  video: 'فيديو',
  marketing: 'تسويق',
  events: 'فعاليات',
  printing: 'طباعة',
}

const MEDIA_TYPE_LABELS: Record<PortfolioMediaType, string> = {
  IMAGE: 'صورة',
  UPLOADED_VIDEO: 'فيديو مرفوع',
  EXTERNAL_VIDEO: 'فيديو YouTube/Vimeo',
  WEBSITE_LINK: 'رابط موقع',
  EXTERNAL_PROJECT_LINK: 'رابط مشروع خارجي',
}

type PortfolioForm = {
  id: number | null
  title: string
  slug: string
  category: PortfolioCategory
  short_description: string
  description: string
  challenge: string
  solution: string
  execution: string
  deliverables: string
  results: string
  tags: string
  image_url: string
  project_url: string
  video_url: string
  primary_media_type: PortfolioMediaType | ''
  service_ids: string
  sector_ids: string
  package_id: string
  media: PortfolioMediaItem[]
  is_sample: boolean
  is_published: boolean
  is_featured: boolean
  sort_order: string
}

type TestimonialForm = {
  id: number | null
  author_name: string
  author_role: string
  quote: string
  is_published: boolean
  sort_order: string
}

const emptyMedia = (): PortfolioMediaItem => ({
  type: 'IMAGE',
  title: '',
  url: '',
  thumbnail_url: '',
  caption: '',
  display_order: 0,
  is_featured: false,
  is_public: true,
})

const emptyPortfolio: PortfolioForm = {
  id: null,
  title: '',
  slug: '',
  category: 'branding',
  short_description: '',
  description: '',
  challenge: '',
  solution: '',
  execution: '',
  deliverables: '',
  results: '',
  tags: '',
  image_url: '/brand/logo.png',
  project_url: '',
  video_url: '',
  primary_media_type: '',
  service_ids: '',
  sector_ids: '',
  package_id: '',
  media: [],
  is_sample: false,
  is_published: true,
  is_featured: false,
  sort_order: '0',
}

const emptyTestimonial: TestimonialForm = {
  id: null,
  author_name: '',
  author_role: '',
  quote: '',
  is_published: false,
  sort_order: '0',
}

function itemToForm(item: PortfolioItem): PortfolioForm {
  return {
    id: item.id,
    title: item.title,
    slug: item.slug ?? '',
    category: item.category,
    short_description: item.short_description ?? '',
    description: item.description ?? '',
    challenge: item.challenge ?? '',
    solution: item.solution ?? '',
    execution: item.execution ?? '',
    deliverables: (item.deliverables ?? []).join('\n'),
    results: item.results ?? '',
    tags: (item.tags ?? []).join('، '),
    image_url: item.image_url,
    project_url: item.project_url ?? '',
    video_url: item.video_url ?? '',
    primary_media_type: item.primary_media_type ?? '',
    service_ids: (item.service_ids ?? []).join(','),
    sector_ids: (item.sector_ids ?? []).join(','),
    package_id: item.package_id ? String(item.package_id) : '',
    media: (item.media ?? []).map((row) => ({ ...row })),
    is_sample: item.is_sample,
    is_published: Boolean(item.is_published),
    is_featured: Boolean(item.is_featured),
    sort_order: String(item.sort_order),
  }
}

function parseIdList(value: string): number[] {
  return value
    .split(/[,،\s]+/)
    .map((part) => Number(part.trim()))
    .filter((id) => Number.isFinite(id) && id > 0)
}

export function OwnerMarketingPage() {
  const portfolio = useAsyncData(getAdminPortfolio)
  const testimonials = useAsyncData(getAdminTestimonials)
  const [tab, setTab] = useState<'portfolio' | 'testimonials'>('portfolio')
  const [portfolioForm, setPortfolioForm] = useState<PortfolioForm | null>(null)
  const [testimonialForm, setTestimonialForm] = useState<TestimonialForm | null>(null)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)

  const portfolioItems = portfolio.state.status === 'ready' ? portfolio.state.data : []
  const testimonialItems = testimonials.state.status === 'ready' ? testimonials.state.data : []

  async function savePortfolio(event: FormEvent) {
    event.preventDefault()
    if (!portfolioForm) {
      return
    }

    setSaving(true)
    setError(null)
    setNotice(null)

    const payload = {
      title: portfolioForm.title,
      slug: portfolioForm.slug || undefined,
      category: portfolioForm.category,
      short_description: portfolioForm.short_description || null,
      description: portfolioForm.description || null,
      challenge: portfolioForm.challenge || null,
      solution: portfolioForm.solution || null,
      execution: portfolioForm.execution || null,
      deliverables: portfolioForm.deliverables
        .split('\n')
        .map((row) => row.trim())
        .filter(Boolean),
      results: portfolioForm.results || null,
      tags: portfolioForm.tags
        .split(/[,،]/)
        .map((tag) => tag.trim())
        .filter(Boolean),
      image_url: portfolioForm.image_url,
      project_url: portfolioForm.project_url || null,
      video_url: portfolioForm.video_url || null,
      primary_media_type: portfolioForm.primary_media_type || null,
      service_ids: parseIdList(portfolioForm.service_ids),
      sector_ids: parseIdList(portfolioForm.sector_ids),
      package_id: portfolioForm.package_id ? Number(portfolioForm.package_id) : null,
      media: portfolioForm.media.map((row, index) => ({
        id: row.id,
        type: row.type,
        title: row.title || null,
        caption: row.caption || null,
        url: row.url || null,
        thumbnail_url: row.thumbnail_url || null,
        display_order: row.display_order ?? index,
        is_featured: Boolean(row.is_featured),
        is_public: row.is_public !== false,
      })),
      is_sample: portfolioForm.is_sample,
      is_published: portfolioForm.is_published,
      is_featured: portfolioForm.is_featured,
      sort_order: Number(portfolioForm.sort_order || 0),
    }

    try {
      if (portfolioForm.id) {
        await updateAdminPortfolioItem(portfolioForm.id, payload)
      } else {
        await createAdminPortfolioItem(payload)
      }
      setNotice('تم حفظ عنصر المعرض.')
      setPortfolioForm(null)
      await portfolio.reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ عنصر المعرض.'))
    } finally {
      setSaving(false)
    }
  }

  async function saveTestimonial(event: FormEvent) {
    event.preventDefault()
    if (!testimonialForm) {
      return
    }

    setSaving(true)
    setError(null)
    setNotice(null)

    const payload = {
      author_name: testimonialForm.author_name,
      author_role: testimonialForm.author_role || null,
      quote: testimonialForm.quote,
      is_published: testimonialForm.is_published,
      sort_order: Number(testimonialForm.sort_order || 0),
    }

    try {
      if (testimonialForm.id) {
        await updateAdminTestimonial(testimonialForm.id, payload)
      } else {
        await createAdminTestimonial(payload)
      }
      setNotice('تم حفظ الرأي.')
      setTestimonialForm(null)
      await testimonials.reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ الرأي.'))
    } finally {
      setSaving(false)
    }
  }

  return (
    <section className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold">المحتوى التسويقي</h1>
        <p className="text-sm text-slate-600">
          أضف أعمالاً حقيقية كدراسات حالة تفاعلية: صور، فيديو، روابط مشروع، وخدمات مرتبطة.
        </p>
      </header>

      <div className="flex gap-2">
        <button
          type="button"
          onClick={() => setTab('portfolio')}
          className={`rounded-xl px-4 py-2 text-sm ${tab === 'portfolio' ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white'}`}
        >
          المعرض
        </button>
        <button
          type="button"
          onClick={() => setTab('testimonials')}
          className={`rounded-xl px-4 py-2 text-sm ${tab === 'testimonials' ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white'}`}
        >
          الآراء
        </button>
      </div>

      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      {tab === 'portfolio' ? (
        <div className="space-y-4">
          {portfolio.state.status === 'loading' ? <DashboardPanelSkeleton label="جاري تحميل المعرض..." /> : null}
          {portfolio.state.status === 'error' ? (
            <DashboardErrorState message={portfolio.state.message} onRetry={() => void portfolio.reload()} />
          ) : null}
          <button
            type="button"
            onClick={() => {
              setPortfolioForm({ ...emptyPortfolio })
              setError(null)
              setNotice(null)
            }}
            className="min-h-11 rounded-xl bg-brand-primary px-4 text-sm font-medium text-white hover:bg-brand-primary-hover"
          >
            إضافة عمل
          </button>
          <ul className="space-y-2">
            {portfolioItems.map((item) => (
              <li
                key={item.id}
                className="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between"
              >
                <div>
                  <p className="font-medium">{item.title}</p>
                  <p className="text-xs text-slate-500">
                    {CATEGORY_LABELS[item.category]} · {item.slug || 'بدون slug'} ·{' '}
                    {item.is_published ? 'منشور' : 'مسودة'}
                    {item.is_featured ? ' · مميز' : ''}
                    {item.is_sample ? ' · نموذج عرض' : ''}
                  </p>
                </div>
                <div className="flex flex-wrap gap-2">
                  {item.slug && item.is_published ? (
                    <Link
                      to={`/portfolio/${item.slug}`}
                      className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm"
                      target="_blank"
                      rel="noreferrer"
                    >
                      معاينة
                    </Link>
                  ) : null}
                  <button type="button" onClick={() => setPortfolioForm(itemToForm(item))} className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                    تعديل
                  </button>
                  <button
                    type="button"
                    onClick={() =>
                      void deleteAdminPortfolioItem(item.id)
                        .then(() => portfolio.reload())
                        .catch((caught) => setError(describeApiError(caught, 'تعذر الحذف.')))
                    }
                    className="rounded-lg border border-red-200 px-3 py-1.5 text-sm text-red-700"
                  >
                    حذف
                  </button>
                </div>
              </li>
            ))}
          </ul>

          {portfolioForm ? (
            <form onSubmit={(event) => void savePortfolio(event)} className="space-y-6 rounded-2xl border border-slate-200 bg-white p-5">
              <fieldset className="space-y-3">
                <legend className="font-semibold text-slate-900">معلومات المشروع</legend>
                <label className="block space-y-1 text-sm">
                  <span>العنوان</span>
                  <input
                    required
                    value={portfolioForm.title}
                    onChange={(event) => setPortfolioForm((current) => current && { ...current, title: event.target.value })}
                  />
                </label>
                <label className="block space-y-1 text-sm">
                  <span>Slug (اختياري)</span>
                  <input
                    dir="ltr"
                    value={portfolioForm.slug}
                    onChange={(event) => setPortfolioForm((current) => current && { ...current, slug: event.target.value })}
                    placeholder="restaurant-branding"
                  />
                </label>
                <label className="block space-y-1 text-sm">
                  <span>التصنيف / نوع العمل</span>
                  <select
                    value={portfolioForm.category}
                    onChange={(event) =>
                      setPortfolioForm((current) => current && { ...current, category: event.target.value as PortfolioCategory })
                    }
                  >
                    {PORTFOLIO_CATEGORIES.map((category) => (
                      <option key={category} value={category}>
                        {CATEGORY_LABELS[category]}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="block space-y-1 text-sm">
                  <span>وصف قصير</span>
                  <textarea
                    value={portfolioForm.short_description}
                    onChange={(event) =>
                      setPortfolioForm((current) => current && { ...current, short_description: event.target.value })
                    }
                  />
                </label>
                <label className="block space-y-1 text-sm">
                  <span>الوصف الكامل</span>
                  <textarea
                    value={portfolioForm.description}
                    onChange={(event) =>
                      setPortfolioForm((current) => current && { ...current, description: event.target.value })
                    }
                  />
                </label>
              </fieldset>

              <fieldset className="space-y-3">
                <legend className="font-semibold text-slate-900">دراسة الحالة</legend>
                {([
                  ['challenge', 'التحدي'],
                  ['solution', 'الحل'],
                  ['execution', 'ما تم تنفيذه'],
                  ['results', 'النتائج (اتركه فارغاً إن لم توجد)'],
                ] as const).map(([key, label]) => (
                  <label key={key} className="block space-y-1 text-sm">
                    <span>{label}</span>
                    <textarea
                      value={portfolioForm[key]}
                      onChange={(event) =>
                        setPortfolioForm((current) => current && { ...current, [key]: event.target.value })
                      }
                    />
                  </label>
                ))}
                <label className="block space-y-1 text-sm">
                  <span>المخرجات (سطر لكل مخرج)</span>
                  <textarea
                    value={portfolioForm.deliverables}
                    onChange={(event) =>
                      setPortfolioForm((current) => current && { ...current, deliverables: event.target.value })
                    }
                  />
                </label>
              </fieldset>

              <fieldset className="space-y-3">
                <legend className="font-semibold text-slate-900">القطاع والخدمات</legend>
                <label className="block space-y-1 text-sm">
                  <span>معرّفات القطاعات (sector_ids مفصولة بفاصلة)</span>
                  <input
                    dir="ltr"
                    value={portfolioForm.sector_ids}
                    onChange={(event) =>
                      setPortfolioForm((current) => current && { ...current, sector_ids: event.target.value })
                    }
                    placeholder="1,2"
                  />
                </label>
                <label className="block space-y-1 text-sm">
                  <span>معرّفات الخدمات (service_ids)</span>
                  <input
                    dir="ltr"
                    value={portfolioForm.service_ids}
                    onChange={(event) =>
                      setPortfolioForm((current) => current && { ...current, service_ids: event.target.value })
                    }
                    placeholder="10,11,12"
                  />
                </label>
                <label className="block space-y-1 text-sm">
                  <span>معرّف الباقة المرتبطة (اختياري)</span>
                  <input
                    dir="ltr"
                    value={portfolioForm.package_id}
                    onChange={(event) =>
                      setPortfolioForm((current) => current && { ...current, package_id: event.target.value })
                    }
                  />
                </label>
                <label className="block space-y-1 text-sm">
                  <span>الوسوم</span>
                  <input
                    value={portfolioForm.tags}
                    onChange={(event) => setPortfolioForm((current) => current && { ...current, tags: event.target.value })}
                  />
                </label>
              </fieldset>

              <fieldset className="space-y-3">
                <legend className="font-semibold text-slate-900">صور / فيديو / رابط المشروع</legend>
                <label className="block space-y-1 text-sm">
                  <span>صورة الغلاف</span>
                  <input
                    dir="ltr"
                    required
                    value={portfolioForm.image_url}
                    onChange={(event) =>
                      setPortfolioForm((current) => current && { ...current, image_url: event.target.value })
                    }
                  />
                </label>
                <label className="block space-y-1 text-sm">
                  <span>رابط المشروع (https فقط)</span>
                  <input
                    dir="ltr"
                    value={portfolioForm.project_url}
                    onChange={(event) =>
                      setPortfolioForm((current) => current && { ...current, project_url: event.target.value })
                    }
                  />
                </label>
                <label className="block space-y-1 text-sm">
                  <span>رابط فيديو أساسي (YouTube/Vimeo)</span>
                  <input
                    dir="ltr"
                    value={portfolioForm.video_url}
                    onChange={(event) =>
                      setPortfolioForm((current) => current && { ...current, video_url: event.target.value })
                    }
                  />
                </label>
                <label className="block space-y-1 text-sm">
                  <span>نوع العرض الأساسي</span>
                  <select
                    value={portfolioForm.primary_media_type}
                    onChange={(event) =>
                      setPortfolioForm(
                        (current) =>
                          current && {
                            ...current,
                            primary_media_type: event.target.value as PortfolioMediaType | '',
                          },
                      )
                    }
                  >
                    <option value="">تلقائي</option>
                    {PORTFOLIO_MEDIA_TYPES.map((type) => (
                      <option key={type} value={type}>
                        {MEDIA_TYPE_LABELS[type]}
                      </option>
                    ))}
                  </select>
                </label>

                <div className="space-y-3 rounded-xl border border-slate-200 p-3">
                  <div className="flex items-center justify-between gap-2">
                    <p className="text-sm font-medium">وسائط المشروع</p>
                    <button
                      type="button"
                      className="rounded-lg border px-3 py-1.5 text-sm"
                      onClick={() =>
                        setPortfolioForm(
                          (current) =>
                            current && {
                              ...current,
                              media: [...current.media, emptyMedia()],
                            },
                        )
                      }
                    >
                      إضافة وسيط
                    </button>
                  </div>
                  {portfolioForm.media.map((row, index) => (
                    <div key={row.id ?? `new-${index}`} className="space-y-2 rounded-lg bg-slate-50 p-3">
                      <div className="grid gap-2 sm:grid-cols-2">
                        <label className="block space-y-1 text-xs">
                          <span>النوع</span>
                          <select
                            value={row.type}
                            onChange={(event) =>
                              setPortfolioForm((current) => {
                                if (!current) return current
                                const media = [...current.media]
                                media[index] = { ...media[index], type: event.target.value as PortfolioMediaType }
                                return { ...current, media }
                              })
                            }
                          >
                            {PORTFOLIO_MEDIA_TYPES.map((type) => (
                              <option key={type} value={type}>
                                {MEDIA_TYPE_LABELS[type]}
                              </option>
                            ))}
                          </select>
                        </label>
                        <label className="block space-y-1 text-xs">
                          <span>العنوان</span>
                          <input
                            value={row.title ?? ''}
                            onChange={(event) =>
                              setPortfolioForm((current) => {
                                if (!current) return current
                                const media = [...current.media]
                                media[index] = { ...media[index], title: event.target.value }
                                return { ...current, media }
                              })
                            }
                          />
                        </label>
                        <label className="block space-y-1 text-xs sm:col-span-2">
                          <span>الرابط / مسار الملف</span>
                          <input
                            dir="ltr"
                            value={row.url ?? ''}
                            onChange={(event) =>
                              setPortfolioForm((current) => {
                                if (!current) return current
                                const media = [...current.media]
                                media[index] = { ...media[index], url: event.target.value }
                                return { ...current, media }
                              })
                            }
                          />
                        </label>
                        <label className="block space-y-1 text-xs sm:col-span-2">
                          <span>صورة مصغّرة (للفيديو)</span>
                          <input
                            dir="ltr"
                            value={row.thumbnail_url ?? ''}
                            onChange={(event) =>
                              setPortfolioForm((current) => {
                                if (!current) return current
                                const media = [...current.media]
                                media[index] = { ...media[index], thumbnail_url: event.target.value }
                                return { ...current, media }
                              })
                            }
                          />
                        </label>
                      </div>
                      <div className="flex flex-wrap gap-3 text-xs">
                        <label className="inline-flex items-center gap-1">
                          <input
                            type="checkbox"
                            checked={Boolean(row.is_featured)}
                            onChange={(event) =>
                              setPortfolioForm((current) => {
                                if (!current) return current
                                const media = current.media.map((entry, entryIndex) => ({
                                  ...entry,
                                  is_featured: entryIndex === index ? event.target.checked : false,
                                }))
                                return { ...current, media }
                              })
                            }
                          />
                          أساسي
                        </label>
                        <label className="inline-flex items-center gap-1">
                          <input
                            type="checkbox"
                            checked={row.is_public !== false}
                            onChange={(event) =>
                              setPortfolioForm((current) => {
                                if (!current) return current
                                const media = [...current.media]
                                media[index] = { ...media[index], is_public: event.target.checked }
                                return { ...current, media }
                              })
                            }
                          />
                          عام
                        </label>
                        <button
                          type="button"
                          className="text-red-700"
                          onClick={() =>
                            setPortfolioForm(
                              (current) =>
                                current && {
                                  ...current,
                                  media: current.media.filter((_, entryIndex) => entryIndex !== index),
                                },
                            )
                          }
                        >
                          حذف
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              </fieldset>

              <fieldset className="space-y-3">
                <legend className="font-semibold text-slate-900">الظهور والنشر</legend>
                <label className="flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={portfolioForm.is_sample}
                    onChange={(event) =>
                      setPortfolioForm((current) => current && { ...current, is_sample: event.target.checked })
                    }
                  />
                  نموذج عرض
                </label>
                <label className="flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={portfolioForm.is_featured}
                    onChange={(event) =>
                      setPortfolioForm((current) => current && { ...current, is_featured: event.target.checked })
                    }
                  />
                  مميز
                </label>
                <label className="flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm">
                  <input
                    type="checkbox"
                    checked={portfolioForm.is_published}
                    onChange={(event) =>
                      setPortfolioForm((current) => current && { ...current, is_published: event.target.checked })
                    }
                  />
                  منشور على الموقع العام
                </label>
                <label className="block space-y-1 text-sm">
                  <span>ترتيب العرض</span>
                  <input
                    type="number"
                    min={0}
                    value={portfolioForm.sort_order}
                    onChange={(event) =>
                      setPortfolioForm((current) => current && { ...current, sort_order: event.target.value })
                    }
                  />
                </label>
              </fieldset>

              <div className="flex gap-2">
                <button type="submit" disabled={saving} className="min-h-11 rounded-xl bg-slate-900 px-5 text-sm text-white disabled:opacity-60">
                  {saving ? 'جاري الحفظ...' : 'حفظ'}
                </button>
                <button type="button" onClick={() => setPortfolioForm(null)} className="min-h-11 rounded-xl border px-4 text-sm">
                  إلغاء
                </button>
              </div>
            </form>
          ) : null}
        </div>
      ) : (
        <div className="space-y-4">
          {testimonials.state.status === 'loading' ? <DashboardPanelSkeleton label="جاري تحميل الآراء..." /> : null}
          {testimonials.state.status === 'error' ? (
            <DashboardErrorState message={testimonials.state.message} onRetry={() => void testimonials.reload()} />
          ) : null}
          <button
            type="button"
            onClick={() => {
              setTestimonialForm({ ...emptyTestimonial })
              setError(null)
              setNotice(null)
            }}
            className="min-h-11 rounded-xl bg-brand-primary px-4 text-sm font-medium text-white hover:bg-brand-primary-hover"
          >
            إضافة رأي حقيقي
          </button>
          {testimonialItems.length === 0 && testimonials.state.status === 'ready' ? (
            <p className="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
              لا توجد آراء بعد. لا تُضف مراجعات مختلقة.
            </p>
          ) : null}
          <ul className="space-y-2">
            {testimonialItems.map((item: Testimonial) => (
              <li
                key={item.id}
                className="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between"
              >
                <div>
                  <p className="font-medium">{item.author_name}</p>
                  <p className="text-xs text-slate-500">{item.is_published ? 'منشور' : 'مسودة'}</p>
                </div>
                <div className="flex gap-2">
                  <button
                    type="button"
                    onClick={() =>
                      setTestimonialForm({
                        id: item.id,
                        author_name: item.author_name,
                        author_role: item.author_role ?? '',
                        quote: item.quote,
                        is_published: Boolean(item.is_published),
                        sort_order: String(item.sort_order),
                      })
                    }
                    className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm"
                  >
                    تعديل
                  </button>
                  <button
                    type="button"
                    onClick={() =>
                      void deleteAdminTestimonial(item.id)
                        .then(() => testimonials.reload())
                        .catch((caught) => setError(describeApiError(caught, 'تعذر الحذف.')))
                    }
                    className="rounded-lg border border-red-200 px-3 py-1.5 text-sm text-red-700"
                  >
                    حذف
                  </button>
                </div>
              </li>
            ))}
          </ul>
          {testimonialForm ? (
            <form onSubmit={(event) => void saveTestimonial(event)} className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5">
              <label className="block space-y-1 text-sm">
                <span>اسم صاحب الرأي</span>
                <input
                  required
                  value={testimonialForm.author_name}
                  onChange={(event) =>
                    setTestimonialForm((current) => current && { ...current, author_name: event.target.value })
                  }
                />
              </label>
              <label className="block space-y-1 text-sm">
                <span>الصفة</span>
                <input
                  value={testimonialForm.author_role}
                  onChange={(event) =>
                    setTestimonialForm((current) => current && { ...current, author_role: event.target.value })
                  }
                />
              </label>
              <label className="block space-y-1 text-sm">
                <span>النص</span>
                <textarea
                  required
                  value={testimonialForm.quote}
                  onChange={(event) => setTestimonialForm((current) => current && { ...current, quote: event.target.value })}
                />
              </label>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={testimonialForm.is_published}
                  onChange={(event) =>
                    setTestimonialForm((current) => current && { ...current, is_published: event.target.checked })
                  }
                />
                منشور على الموقع العام
              </label>
              <div className="flex gap-2">
                <button type="submit" disabled={saving} className="min-h-11 rounded-xl bg-slate-900 px-5 text-sm text-white disabled:opacity-60">
                  {saving ? 'جاري الحفظ...' : 'حفظ'}
                </button>
                <button type="button" onClick={() => setTestimonialForm(null)} className="min-h-11 rounded-xl border px-4 text-sm">
                  إلغاء
                </button>
              </div>
            </form>
          ) : null}
        </div>
      )}
    </section>
  )
}
