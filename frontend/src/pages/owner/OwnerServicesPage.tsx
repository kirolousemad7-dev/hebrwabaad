import { FormEvent, useState } from 'react'
import { useAsyncData } from '../../hooks/useAsyncData'
import {
  createService,
  deleteService,
  getManagedServices,
  setServiceActive,
  updateService,
  type ServiceInput,
} from '../../services/catalog'
import { PRICING_MODES, SERVICE_CATEGORIES, type PricingMode, type Service } from '../../types/api'
import { PRICING_MODE_LABELS, servicePriceLabel, SERVICE_CATEGORY_LABELS } from '../../utils/catalog'
import { describeApiError } from '../../utils/errors'

type FormState = {
  id: number | null
  name: string
  slug: string
  summary: string
  description: string
  category: ServiceInput['category']
  base_price: string
  pricing_mode: PricingMode
  duration_days: string
  is_active: boolean
  is_featured: boolean
}

const emptyForm: FormState = {
  id: null,
  name: '',
  slug: '',
  summary: '',
  description: '',
  category: 'STRATEGY',
  base_price: '0',
  pricing_mode: 'QUOTE',
  duration_days: '',
  is_active: true,
  is_featured: false,
}

function toFormState(service: Service): FormState {
  return {
    id: service.id,
    name: service.name,
    slug: service.slug,
    summary: service.summary ?? '',
    description: service.description ?? '',
    category: service.category,
    base_price: service.base_price,
    pricing_mode: service.pricing_mode,
    duration_days: service.duration_days === null ? '' : String(service.duration_days),
    is_active: service.is_active ?? true,
    is_featured: service.is_featured,
  }
}

export function OwnerServicesPage() {
  const { state, reload } = useAsyncData(getManagedServices)
  const [form, setForm] = useState<FormState | null>(null)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)

  function patch(changes: Partial<FormState>) {
    setForm((current) => (current === null ? current : { ...current, ...changes }))
  }

  function openCreate() {
    setError(null)
    setNotice(null)
    setForm({ ...emptyForm })
  }

  function openEdit(service: Service) {
    setError(null)
    setNotice(null)
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

    const payload: ServiceInput = {
      name: form.name.trim(),
      slug: form.slug.trim() === '' ? null : form.slug.trim(),
      summary: form.summary.trim() === '' ? null : form.summary.trim(),
      description: form.description.trim() === '' ? null : form.description.trim(),
      category: form.category,
      base_price: basePrice,
      pricing_mode: form.pricing_mode,
      duration_days: form.duration_days.trim() === '' ? null : Number.parseInt(form.duration_days, 10),
      is_active: form.is_active,
      is_featured: form.is_featured,
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
          <p className="text-sm text-slate-600">إضافة وتعديل وتعطيل خدمات الكتالوج.</p>
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
          <h2 className="font-semibold">{form.id === null ? 'خدمة جديدة' : 'تعديل الخدمة'}</h2>

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
              <span>منشورة</span>
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
