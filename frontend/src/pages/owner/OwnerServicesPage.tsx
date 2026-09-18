import { FormEvent, useMemo, useState } from 'react'
import { useAsyncData } from '../../hooks/useAsyncData'
import {
  createService,
  deleteService,
  getManagedServices,
  setServiceActive,
  updateService,
  type ServiceInput,
} from '../../services/catalog'
import { getDepartmentOptions } from '../../services/operations'
import { PRICING_MODES, SERVICE_CATEGORIES, type PricingMode, type Service } from '../../types/api'
import { PRICING_MODE_LABELS, servicePriceLabel, SERVICE_CATEGORY_LABELS } from '../../utils/catalog'
import { describeApiError } from '../../utils/errors'

type FormTab = 'commercial' | 'marketing' | 'seo' | 'operations'

type FormState = {
  id: number | null
  name: string
  slug: string
  summary: string
  description: string
  category: ServiceInput['category']
  subcategory: string
  base_price: string
  pricing_mode: PricingMode
  duration_days: string
  revision_rounds: string
  is_active: boolean
  is_featured: boolean
  is_public: boolean
  sort_order: string
  hero_image: string
  gallery: string
  features: string
  process_steps: string
  faq: string
  tags: string
  seo_title: string
  seo_description: string
  og_title: string
  og_description: string
  og_image: string
  canonical_url: string
  robots: string
  supplier_ids: string
  department_id: string
  task_title_template: string
  default_task_priority: string
  requires_review: boolean
  requires_customer_approval: boolean
  checklist_template: string
}

const emptyForm: FormState = {
  id: null,
  name: '',
  slug: '',
  summary: '',
  description: '',
  category: 'STRATEGY',
  subcategory: '',
  base_price: '0',
  pricing_mode: 'QUOTE',
  duration_days: '',
  revision_rounds: '',
  is_active: true,
  is_featured: false,
  is_public: true,
  sort_order: '0',
  hero_image: '',
  gallery: '',
  features: '',
  process_steps: '',
  faq: '',
  tags: '',
  seo_title: '',
  seo_description: '',
  og_title: '',
  og_description: '',
  og_image: '',
  canonical_url: '',
  robots: 'index,follow',
  supplier_ids: '',
  department_id: '',
  task_title_template: '',
  default_task_priority: '',
  requires_review: false,
  requires_customer_approval: false,
  checklist_template: '',
}

function toFormState(service: Service): FormState {
  return {
    id: service.id,
    name: service.name,
    slug: service.slug,
    summary: service.summary ?? '',
    description: service.description ?? '',
    category: service.category,
    subcategory: service.subcategory ?? '',
    base_price: service.base_price,
    pricing_mode: service.pricing_mode,
    duration_days: service.duration_days === null ? '' : String(service.duration_days),
    revision_rounds: service.revision_rounds == null ? '' : String(service.revision_rounds),
    is_active: service.is_active ?? true,
    is_featured: service.is_featured,
    is_public: service.is_public ?? true,
    sort_order: String(service.sort_order ?? 0),
    hero_image: service.hero_image ?? '',
    gallery: (service.gallery ?? []).join('\n'),
    features: (service.features ?? []).join('\n'),
    process_steps: (service.process_steps ?? [])
      .map((step) => `${step.title}|${step.description ?? ''}`)
      .join('\n'),
    faq: (service.faq ?? []).map((item) => `${item.question}|${item.answer}`).join('\n'),
    tags: (service.tags ?? []).join(', '),
    seo_title: service.seo_title ?? service.seo?.title ?? '',
    seo_description: service.seo_description ?? service.seo?.description ?? '',
    og_title: service.og_title ?? service.seo?.og_title ?? '',
    og_description: service.og_description ?? service.seo?.og_description ?? '',
    og_image: service.og_image ?? service.seo?.og_image ?? '',
    canonical_url: service.canonical_url ?? service.seo?.canonical_url ?? '',
    robots: service.robots ?? service.seo?.robots ?? 'index,follow',
    supplier_ids: (service.supplier_ids ?? []).join(', '),
    department_id: service.department_id == null ? '' : String(service.department_id),
    task_title_template: service.task_title_template ?? '',
    default_task_priority: service.default_task_priority ?? '',
    requires_review: service.requires_review ?? false,
    requires_customer_approval: service.requires_customer_approval ?? false,
    checklist_template: (service.checklist_template ?? []).join('\n'),
  }
}

export function OwnerServicesPage() {
  const { state, reload } = useAsyncData(getManagedServices)
  const departments = useAsyncData(() => getDepartmentOptions())
  const [form, setForm] = useState<FormState | null>(null)
  const [tab, setTab] = useState<FormTab>('commercial')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)

  const departmentOptions = useMemo(
    () => (departments.state.status === 'ready' ? departments.state.data.items : []),
    [departments.state],
  )

  function patch(changes: Partial<FormState>) {
    setForm((current) => (current === null ? current : { ...current, ...changes }))
  }

  function openCreate() {
    setError(null)
    setNotice(null)
    setTab('commercial')
    setForm({ ...emptyForm })
  }

  function openEdit(service: Service) {
    setError(null)
    setNotice(null)
    setTab('commercial')
    setForm(toFormState(service))
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    if (form === null) {
      return
    }

    const basePrice = Number.parseFloat(form.base_price)

    if (form.name.trim() === '') {
      setError('اسم الخدمة مطلوب.')
      return
    }

    if (Number.isNaN(basePrice) || basePrice < 0) {
      setError('السعر يجب أن يكون رقماً غير سالب.')
      return
    }

    const checklist = form.checklist_template
      .split('\n')
      .map((line) => line.trim())
      .filter((line) => line !== '')

    const features = form.features
      .split('\n')
      .map((line) => line.trim())
      .filter(Boolean)
    const gallery = form.gallery
      .split('\n')
      .map((line) => line.trim())
      .filter(Boolean)
    const tags = form.tags
      .split(',')
      .map((line) => line.trim())
      .filter(Boolean)
    const process_steps = form.process_steps
      .split('\n')
      .map((line) => line.trim())
      .filter(Boolean)
      .map((line) => {
        const [title, ...rest] = line.split('|')
        return { title: title.trim(), description: rest.join('|').trim() || null }
      })
    const faq = form.faq
      .split('\n')
      .map((line) => line.trim())
      .filter(Boolean)
      .map((line) => {
        const [question, ...rest] = line.split('|')
        return { question: question.trim(), answer: rest.join('|').trim() }
      })
      .filter((item) => item.question && item.answer)
    const supplier_ids = form.supplier_ids
      .split(',')
      .map((part) => Number.parseInt(part.trim(), 10))
      .filter((id) => Number.isInteger(id) && id > 0)

    const payload: ServiceInput = {
      name: form.name.trim(),
      slug: form.slug.trim() === '' ? null : form.slug.trim(),
      summary: form.summary.trim() === '' ? null : form.summary.trim(),
      short_description: form.summary.trim() === '' ? null : form.summary.trim(),
      description: form.description.trim() === '' ? null : form.description.trim(),
      category: form.category,
      subcategory: form.subcategory.trim() === '' ? null : form.subcategory.trim(),
      base_price: basePrice,
      pricing_mode: form.pricing_mode,
      duration_days: form.duration_days.trim() === '' ? null : Number.parseInt(form.duration_days, 10),
      revision_rounds: form.revision_rounds.trim() === '' ? null : Number.parseInt(form.revision_rounds, 10),
      is_active: form.is_active,
      is_featured: form.is_featured,
      is_public: form.is_public,
      sort_order: Number.parseInt(form.sort_order || '0', 10) || 0,
      hero_image: form.hero_image.trim() === '' ? null : form.hero_image.trim(),
      gallery,
      features,
      process_steps,
      faq,
      tags,
      seo_title: form.seo_title.trim() === '' ? null : form.seo_title.trim(),
      seo_description: form.seo_description.trim() === '' ? null : form.seo_description.trim(),
      og_title: form.og_title.trim() === '' ? null : form.og_title.trim(),
      og_description: form.og_description.trim() === '' ? null : form.og_description.trim(),
      og_image: form.og_image.trim() === '' ? null : form.og_image.trim(),
      canonical_url: form.canonical_url.trim() === '' ? null : form.canonical_url.trim(),
      robots: form.robots.trim() === '' ? null : form.robots.trim(),
      supplier_ids,
      department_id: form.department_id.trim() === '' ? null : Number.parseInt(form.department_id, 10),
      task_title_template: form.task_title_template.trim() === '' ? null : form.task_title_template.trim(),
      default_task_priority: form.default_task_priority.trim() === '' ? null : form.default_task_priority,
      requires_review: form.requires_review,
      requires_customer_approval: form.requires_customer_approval,
      checklist_template: checklist.length > 0 ? checklist : null,
    }

    setSaving(true)
    setError(null)

    try {
      if (form.id === null) {
        await createService(payload)
        setNotice('تمت إضافة الخدمة.')
      } else {
        await updateService(form.id, payload)
        setNotice('تم تحديث الخدمة.')
      }

      setForm(null)
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ الخدمة.'))
    } finally {
      setSaving(false)
    }
  }

  async function toggleActive(service: Service) {
    setError(null)
    setNotice(null)

    try {
      await setServiceActive(service.id, !(service.is_active ?? true))
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تغيير حالة الخدمة.'))
    }
  }

  async function remove(service: Service) {
    setError(null)
    setNotice(null)

    try {
      await deleteService(service.id)
      setNotice('تم حذف الخدمة.')
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حذف الخدمة.'))
    }
  }

  return (
    <section className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">إدارة الخدمات</h1>
          <p className="text-sm text-slate-600">
            التسعير والنطاق التجاري منفصلان عن إعدادات التشغيل (القسم، قالب المهمة، الاعتماد).
          </p>
        </div>
        <button
          type="button"
          onClick={openCreate}
          className="rounded-md bg-slate-900 px-4 py-2 text-sm text-white"
        >
          خدمة جديدة
        </button>
      </header>

      {notice ? (
        <p className="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
          {notice}
        </p>
      ) : null}

      {error ? (
        <p className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>
      ) : null}

      {form !== null ? (
        <form onSubmit={handleSubmit} className="space-y-4 rounded-lg border border-slate-200 bg-white p-5">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <h2 className="font-semibold">{form.id === null ? 'خدمة جديدة' : 'تعديل الخدمة'}</h2>
            <div className="flex flex-wrap gap-2 text-sm">
              {(
                [
                  ['commercial', 'تجاري'],
                  ['marketing', 'صفحة الخدمة'],
                  ['seo', 'SEO'],
                  ['operations', 'تشغيلي'],
                ] as const
              ).map(([key, label]) => (
                <button
                  key={key}
                  type="button"
                  onClick={() => setTab(key)}
                  className={`rounded-full px-3 py-1 ${tab === key ? 'bg-slate-900 text-white' : 'border border-slate-200'}`}
                >
                  {label}
                </button>
              ))}
            </div>
          </div>

          {tab === 'commercial' ? (
            <>
              <div className="grid gap-4 sm:grid-cols-2">
                <label className="block space-y-1 text-sm">
                  <span>الاسم</span>
                  <input
                    required
                    value={form.name}
                    onChange={(event) => patch({ name: event.target.value })}
                    className="w-full rounded-md border border-slate-300 px-3 py-2"
                  />
                </label>

                <label className="block space-y-1 text-sm">
                  <span>المعرّف (يُولَّد تلقائياً إذا تُرك فارغاً)</span>
                  <input
                    value={form.slug}
                    onChange={(event) => patch({ slug: event.target.value })}
                    className="w-full rounded-md border border-slate-300 px-3 py-2"
                    dir="ltr"
                  />
                </label>

                <label className="block space-y-1 text-sm">
                  <span>التصنيف</span>
                  <select
                    value={form.category}
                    onChange={(event) => patch({ category: event.target.value as FormState['category'] })}
                    className="w-full rounded-md border border-slate-300 px-3 py-2"
                  >
                    {SERVICE_CATEGORIES.map((category) => (
                      <option key={category} value={category}>
                        {SERVICE_CATEGORY_LABELS[category]}
                      </option>
                    ))}
                  </select>
                </label>

                <label className="block space-y-1 text-sm">
                  <span>التصنيف الفرعي</span>
                  <input
                    value={form.subcategory}
                    onChange={(event) => patch({ subcategory: event.target.value })}
                    className="w-full rounded-md border border-slate-300 px-3 py-2"
                  />
                </label>

                <label className="block space-y-1 text-sm">
                  <span>السعر الأساسي (ريال)</span>
                  <input
                    type="number"
                    min={0}
                    step="0.01"
                    required
                    value={form.base_price}
                    onChange={(event) => patch({ base_price: event.target.value })}
                    className="w-full rounded-md border border-slate-300 px-3 py-2"
                  />
                </label>

                <label className="block space-y-1 text-sm">
                  <span>حالة السعر</span>
                  <select
                    value={form.pricing_mode}
                    onChange={(event) => patch({ pricing_mode: event.target.value as PricingMode })}
                    className="w-full rounded-md border border-slate-300 px-3 py-2"
                  >
                    {PRICING_MODES.map((mode) => (
                      <option key={mode} value={mode}>
                        {PRICING_MODE_LABELS[mode]}
                      </option>
                    ))}
                  </select>
                </label>

                <label className="block space-y-1 text-sm">
                  <span>مدة التنفيذ (أيام)</span>
                  <input
                    type="number"
                    min={0}
                    value={form.duration_days}
                    onChange={(event) => patch({ duration_days: event.target.value })}
                    className="w-full rounded-md border border-slate-300 px-3 py-2"
                  />
                </label>

                <label className="block space-y-1 text-sm">
                  <span>جولات التعديل المشمولة</span>
                  <input
                    type="number"
                    min={0}
                    value={form.revision_rounds}
                    onChange={(event) => patch({ revision_rounds: event.target.value })}
                    className="w-full rounded-md border border-slate-300 px-3 py-2"
                    placeholder="اتركه فارغاً إن لم يُحدَّد"
                  />
                </label>

                <label className="block space-y-1 text-sm">
                  <span>ترتيب العرض</span>
                  <input
                    type="number"
                    min={0}
                    value={form.sort_order}
                    onChange={(event) => patch({ sort_order: event.target.value })}
                    className="w-full rounded-md border border-slate-300 px-3 py-2"
                  />
                </label>

                <label className="block space-y-1 text-sm">
                  <span>وصف مختصر</span>
                  <input
                    value={form.summary}
                    onChange={(event) => patch({ summary: event.target.value })}
                    className="w-full rounded-md border border-slate-300 px-3 py-2"
                  />
                </label>
              </div>

              <label className="block space-y-1 text-sm">
                <span>الوصف التفصيلي</span>
                <textarea
                  rows={3}
                  value={form.description}
                  onChange={(event) => patch({ description: event.target.value })}
                  className="w-full rounded-md border border-slate-300 px-3 py-2"
                />
              </label>

              <div className="flex flex-wrap gap-6 text-sm">
                <label className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    checked={form.is_active}
                    onChange={(event) => patch({ is_active: event.target.checked })}
                  />
                  <span>نشطة</span>
                </label>
                <label className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    checked={form.is_public}
                    onChange={(event) => patch({ is_public: event.target.checked })}
                  />
                  <span>ظاهرة للجمهور</span>
                </label>
                <label className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    checked={form.is_featured}
                    onChange={(event) => patch({ is_featured: event.target.checked })}
                  />
                  <span>مميّزة</span>
                </label>
              </div>
            </>
          ) : null}

          {tab === 'marketing' ? (
            <div className="grid gap-4">
              <label className="block space-y-1 text-sm">
                <span>صورة الهيرو (مسار/رابط)</span>
                <input
                  value={form.hero_image}
                  onChange={(event) => patch({ hero_image: event.target.value })}
                  className="w-full rounded-md border border-slate-300 px-3 py-2"
                  dir="ltr"
                />
              </label>
              <label className="block space-y-1 text-sm">
                <span>المعرض (رابط في كل سطر)</span>
                <textarea rows={3} value={form.gallery} onChange={(event) => patch({ gallery: event.target.value })} className="w-full rounded-md border border-slate-300 px-3 py-2" dir="ltr" />
              </label>
              <label className="block space-y-1 text-sm">
                <span>المميزات (سطر لكل بند)</span>
                <textarea rows={4} value={form.features} onChange={(event) => patch({ features: event.target.value })} className="w-full rounded-md border border-slate-300 px-3 py-2" />
              </label>
              <label className="block space-y-1 text-sm">
                <span>خطوات العمل (عنوان|وصف لكل سطر)</span>
                <textarea rows={4} value={form.process_steps} onChange={(event) => patch({ process_steps: event.target.value })} className="w-full rounded-md border border-slate-300 px-3 py-2" placeholder={'اكتشاف|جلسات فهم\nتنفيذ|تسليم'} />
              </label>
              <label className="block space-y-1 text-sm">
                <span>الأسئلة الشائعة (سؤال|جواب لكل سطر)</span>
                <textarea rows={4} value={form.faq} onChange={(event) => patch({ faq: event.target.value })} className="w-full rounded-md border border-slate-300 px-3 py-2" />
              </label>
              <label className="block space-y-1 text-sm">
                <span>الوسوم (مفصولة بفواصل)</span>
                <input value={form.tags} onChange={(event) => patch({ tags: event.target.value })} className="w-full rounded-md border border-slate-300 px-3 py-2" />
              </label>
              <label className="block space-y-1 text-sm">
                <span>معرّفات الموردين المرتبطين (مفصولة بفواصل)</span>
                <input value={form.supplier_ids} onChange={(event) => patch({ supplier_ids: event.target.value })} className="w-full rounded-md border border-slate-300 px-3 py-2" dir="ltr" />
              </label>
              {form.id ? (
                <p className="text-xs text-slate-500">
                  الصفحة العامة:{' '}
                  <a className="underline" href={`/services/${form.slug}`} target="_blank" rel="noreferrer">
                    /services/{form.slug}
                  </a>
                </p>
              ) : null}
            </div>
          ) : null}

          {tab === 'seo' ? (
            <div className="grid gap-4 sm:grid-cols-2">
              <label className="block space-y-1 text-sm sm:col-span-2">
                <span>عنوان SEO</span>
                <input value={form.seo_title} onChange={(event) => patch({ seo_title: event.target.value })} className="w-full rounded-md border border-slate-300 px-3 py-2" />
              </label>
              <label className="block space-y-1 text-sm sm:col-span-2">
                <span>وصف SEO</span>
                <textarea rows={3} value={form.seo_description} onChange={(event) => patch({ seo_description: event.target.value })} className="w-full rounded-md border border-slate-300 px-3 py-2" />
              </label>
              <label className="block space-y-1 text-sm">
                <span>OG Title</span>
                <input value={form.og_title} onChange={(event) => patch({ og_title: event.target.value })} className="w-full rounded-md border border-slate-300 px-3 py-2" />
              </label>
              <label className="block space-y-1 text-sm">
                <span>OG Image</span>
                <input value={form.og_image} onChange={(event) => patch({ og_image: event.target.value })} className="w-full rounded-md border border-slate-300 px-3 py-2" dir="ltr" />
              </label>
              <label className="block space-y-1 text-sm sm:col-span-2">
                <span>OG Description</span>
                <textarea rows={2} value={form.og_description} onChange={(event) => patch({ og_description: event.target.value })} className="w-full rounded-md border border-slate-300 px-3 py-2" />
              </label>
              <label className="block space-y-1 text-sm">
                <span>Canonical</span>
                <input value={form.canonical_url} onChange={(event) => patch({ canonical_url: event.target.value })} className="w-full rounded-md border border-slate-300 px-3 py-2" dir="ltr" />
              </label>
              <label className="block space-y-1 text-sm">
                <span>Robots</span>
                <input value={form.robots} onChange={(event) => patch({ robots: event.target.value })} className="w-full rounded-md border border-slate-300 px-3 py-2" dir="ltr" />
              </label>
            </div>
          ) : null}

          {tab === 'operations' ? (
            <div className="grid gap-4 sm:grid-cols-2">
              <label className="block space-y-1 text-sm sm:col-span-2">
                <span>القسم التشغيلي المسؤول</span>
                <select
                  value={form.department_id}
                  onChange={(event) => patch({ department_id: event.target.value })}
                  className="w-full rounded-md border border-slate-300 px-3 py-2"
                >
                  <option value="">— بدون تعيين —</option>
                  {departmentOptions.map((department) => (
                    <option key={department.id} value={department.id}>
                      {department.name}
                    </option>
                  ))}
                </select>
              </label>

              <label className="block space-y-1 text-sm sm:col-span-2">
                <span>قالب عنوان المهمة</span>
                <input
                  value={form.task_title_template}
                  onChange={(event) => patch({ task_title_template: event.target.value })}
                  className="w-full rounded-md border border-slate-300 px-3 py-2"
                  placeholder="مثال: إعداد :name أو تنفيذ :quantity × :name"
                />
                <span className="text-xs text-slate-500">المتغيرات: :name :service :quantity</span>
              </label>

              <label className="block space-y-1 text-sm">
                <span>أولوية المهمة الافتراضية</span>
                <select
                  value={form.default_task_priority}
                  onChange={(event) => patch({ default_task_priority: event.target.value })}
                  className="w-full rounded-md border border-slate-300 px-3 py-2"
                >
                  <option value="">MEDIUM</option>
                  <option value="LOW">LOW</option>
                  <option value="MEDIUM">MEDIUM</option>
                  <option value="HIGH">HIGH</option>
                  <option value="URGENT">URGENT</option>
                </select>
              </label>

              <div className="flex flex-col gap-3 text-sm">
                <label className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    checked={form.requires_review}
                    onChange={(event) => patch({ requires_review: event.target.checked })}
                  />
                  <span>يتطلب مراجعة داخلية</span>
                </label>
                <label className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    checked={form.requires_customer_approval}
                    onChange={(event) => patch({ requires_customer_approval: event.target.checked })}
                  />
                  <span>يتطلب اعتماد العميل (البوابة الحالية)</span>
                </label>
              </div>

              <label className="block space-y-1 text-sm sm:col-span-2">
                <span>قائمة تحقق (سطر لكل بند)</span>
                <textarea
                  rows={4}
                  value={form.checklist_template}
                  onChange={(event) => patch({ checklist_template: event.target.value })}
                  className="w-full rounded-md border border-slate-300 px-3 py-2"
                  placeholder={'مراجعة الموجز\nتسليم المسودة'}
                />
              </label>
            </div>
          ) : null}

          <div className="flex gap-3">
            <button
              type="submit"
              disabled={saving}
              className="rounded-md bg-slate-900 px-4 py-2 text-sm text-white disabled:opacity-60"
            >
              {saving ? 'جاري الحفظ...' : 'حفظ'}
            </button>
            <button
              type="button"
              onClick={() => setForm(null)}
              className="rounded-md border border-slate-300 px-4 py-2 text-sm"
            >
              إلغاء
            </button>
          </div>
        </form>
      ) : null}

      {state.status === 'loading' ? <p className="text-sm text-slate-500">جاري التحميل...</p> : null}

      {state.status === 'error' ? (
        <p className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
          {state.message}
        </p>
      ) : null}

      {state.status === 'ready' && state.data.length === 0 ? (
        <p className="rounded-md border border-slate-200 bg-white px-3 py-6 text-center text-sm text-slate-500">
          لا توجد خدمات بعد.
        </p>
      ) : null}

      {state.status === 'ready' && state.data.length > 0 ? (
        <ul className="space-y-3">
          {state.data.map((service) => (
            <li
              key={service.id}
              className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4"
            >
              <div className="space-y-1">
                <div className="flex items-center gap-2">
                  <span className="font-semibold">{service.name}</span>
                  <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                    {SERVICE_CATEGORY_LABELS[service.category]}
                  </span>
                  <span
                    className={
                      service.is_active
                        ? 'rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800'
                        : 'rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-600'
                    }
                  >
                    {service.is_active ? 'منشورة' : 'معطّلة'}
                  </span>
                </div>
                <p className="text-sm text-slate-600">
                  {servicePriceLabel(service)}
                  {service.department_id ? ' · مرتبط بقسم' : ' · بلا قسم'}
                  {service.requires_customer_approval ? ' · اعتماد عميل' : ''}
                  {service.packages_count !== undefined
                    ? ` · مستخدمة في ${service.packages_count} باقة`
                    : ''}
                </p>
              </div>

              <div className="flex gap-3 text-sm">
                <button type="button" onClick={() => openEdit(service)} className="underline">
                  تعديل
                </button>
                <button type="button" onClick={() => void toggleActive(service)} className="underline">
                  {service.is_active ? 'تعطيل' : 'تنشيط'}
                </button>
                <button
                  type="button"
                  onClick={() => void remove(service)}
                  className="text-red-700 underline"
                >
                  حذف
                </button>
              </div>
            </li>
          ))}
        </ul>
      ) : null}
    </section>
  )
}
