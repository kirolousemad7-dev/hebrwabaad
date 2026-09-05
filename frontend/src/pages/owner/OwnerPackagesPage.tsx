import { FormEvent, useState } from 'react'
import { useAsyncData } from '../../hooks/useAsyncData'
import {
  createPackage,
  deletePackage,
  getManagedPackages,
  getManagedServices,
  setPackageActive,
  updatePackage,
  type PackageInput,
  type PackageItemInput,
} from '../../services/catalog'
import { PACKAGE_CATEGORIES, PRICING_MODES, type Package, type PricingMode, type Service } from '../../types/api'
import { packagePriceLabel, PACKAGE_CATEGORY_LABELS, PRICING_MODE_LABELS } from '../../utils/catalog'
import { describeApiError } from '../../utils/errors'

type TierFormState = {
  name: string
  slug: string
  description: string
  price: string
  duration_days: string
  revision_rounds: string
  is_active: boolean
}

type FormState = {
  id: number | null
  name: string
  slug: string
  description: string
  audience: string
  deliverables: string
  category: PackageInput['category']
  pricing_mode: PricingMode
  price: string
  discount_amount: string
  duration_days: string
  revision_rounds: string
  sort_order: string
  is_active: boolean
  is_featured: boolean
  items: PackageItemInput[]
  tiers: TierFormState[]
}

const emptyForm: FormState = {
  id: null,
  name: '',
  slug: '',
  description: '',
  audience: '',
  deliverables: '',
  category: 'GENERAL',
  pricing_mode: 'QUOTE',
  price: '0',
  discount_amount: '0',
  duration_days: '',
  revision_rounds: '',
  sort_order: '0',
  is_active: true,
  is_featured: false,
  items: [],
  tiers: [],
}

function toFormState(pkg: Package): FormState {
  return {
    id: pkg.id,
    name: pkg.name,
    slug: pkg.slug,
    description: pkg.description ?? '',
    audience: pkg.audience ?? '',
    deliverables: pkg.deliverables.join('\n'),
    category: pkg.category,
    pricing_mode: pkg.pricing_mode,
    price: pkg.price,
    discount_amount: pkg.discount_amount,
    duration_days: pkg.duration_days === null ? '' : String(pkg.duration_days),
    revision_rounds: pkg.revision_rounds === null ? '' : String(pkg.revision_rounds),
    sort_order: String(pkg.sort_order ?? 0),
    is_active: pkg.is_active ?? true,
    is_featured: pkg.is_featured,
    items: pkg.items.map((item) => ({
      service_id: item.service_id,
      quantity: item.quantity,
      sort_order: item.sort_order,
      notes: item.notes,
    })),
    tiers: pkg.tiers.map((tier) => ({
      name: tier.name,
      slug: tier.slug,
      description: tier.description ?? '',
      price: tier.price ?? '',
      duration_days: tier.duration_days === null ? '' : String(tier.duration_days),
      revision_rounds: tier.revision_rounds === null ? '' : String(tier.revision_rounds),
      is_active: tier.is_active ?? true,
    })),
  }
}

function optionalNumber(value: string): number | null {
  return value.trim() === '' ? null : Number.parseInt(value, 10)
}

export function OwnerPackagesPage() {
  const packages = useAsyncData(getManagedPackages)
  const services = useAsyncData(getManagedServices)
  const [form, setForm] = useState<FormState | null>(null)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)

  const serviceList: Service[] = services.state.status === 'ready' ? services.state.data : []

  function patch(changes: Partial<FormState>) {
    setForm((current) => (current === null ? current : { ...current, ...changes }))
  }

  function toggleItem(service: Service, checked: boolean) {
    setForm((current) => {
      if (current === null) {
        return current
      }

      if (!checked) {
        return { ...current, items: current.items.filter((item) => item.service_id !== service.id) }
      }

      if (current.items.some((item) => item.service_id === service.id)) {
        return current
      }

      return {
        ...current,
        items: [
          ...current.items,
          { service_id: service.id, quantity: 1, sort_order: current.items.length, notes: null },
        ],
      }
    })
  }

  function patchItem(serviceId: number, changes: Partial<PackageItemInput>) {
    setForm((current) =>
      current === null
        ? current
        : {
            ...current,
            items: current.items.map((item) =>
              item.service_id === serviceId ? { ...item, ...changes } : item,
            ),
          },
    )
  }

  function patchTier(index: number, changes: Partial<TierFormState>) {
    setForm((current) =>
      current === null
        ? current
        : {
            ...current,
            tiers: current.tiers.map((tier, position) =>
              position === index ? { ...tier, ...changes } : tier,
            ),
          },
    )
  }

  function addTier() {
    setForm((current) =>
      current === null
        ? current
        : {
            ...current,
            tiers: [
              ...current.tiers,
              {
                name: '',
                slug: '',
                description: '',
                price: '',
                duration_days: '',
                revision_rounds: '',
                is_active: true,
              },
            ],
          },
    )
  }

  function removeTier(index: number) {
    setForm((current) =>
      current === null
        ? current
        : { ...current, tiers: current.tiers.filter((_, position) => position !== index) },
    )
  }

  function openCreate() {
    setError(null)
    setNotice(null)
    setForm({ ...emptyForm, items: [] })
  }

  function openEdit(pkg: Package) {
    setError(null)
    setNotice(null)
    setForm(toFormState(pkg))
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    if (form === null) {
      return
    }

    const price = Number.parseFloat(form.price)
    const discount = Number.parseFloat(form.discount_amount === '' ? '0' : form.discount_amount)

    if (form.name.trim() === '') {
      setError('اسم الباقة مطلوب.')
      return
    }

    if (Number.isNaN(price) || price < 0) {
      setError('سعر الباقة يجب أن يكون رقماً غير سالب.')
      return
    }

    if (Number.isNaN(discount) || discount < 0 || discount > price) {
      setError('قيمة الخصم يجب أن تكون بين صفر وسعر الباقة.')
      return
    }

    const payload: PackageInput = {
      name: form.name.trim(),
      slug: form.slug.trim() === '' ? null : form.slug.trim(),
      description: form.description.trim() === '' ? null : form.description.trim(),
      audience: form.audience.trim() === '' ? null : form.audience.trim(),
      deliverables: form.deliverables
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== ''),
      category: form.category,
      pricing_mode: form.pricing_mode,
      price,
      discount_amount: discount,
      duration_days: optionalNumber(form.duration_days),
      revision_rounds: optionalNumber(form.revision_rounds),
      sort_order: Number.parseInt(form.sort_order, 10) || 0,
      is_active: form.is_active,
      is_featured: form.is_featured,
      items: form.items.map((item) => ({
        service_id: item.service_id,
        quantity: item.quantity,
        sort_order: item.sort_order,
        notes: item.notes === '' ? null : item.notes,
      })),
      tiers: form.tiers.map((tier, index) => ({
        name: tier.name.trim(),
        slug: tier.slug.trim(),
        description: tier.description.trim() === '' ? null : tier.description.trim(),
        price: tier.price.trim() === '' ? null : Number.parseFloat(tier.price),
        duration_days: optionalNumber(tier.duration_days),
        revision_rounds: optionalNumber(tier.revision_rounds),
        is_active: tier.is_active,
        sort_order: index,
      })),
    }

    setSaving(true)
    setError(null)

    try {
      if (form.id === null) {
        await createPackage(payload)
        setNotice('تمت إضافة الباقة.')
      } else {
        await updatePackage(form.id, payload)
        setNotice('تم تحديث الباقة.')
      }

      setForm(null)
      await packages.reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ الباقة.'))
    } finally {
      setSaving(false)
    }
  }

  async function toggleActive(pkg: Package) {
    setError(null)
    setNotice(null)

    try {
      await setPackageActive(pkg.id, !(pkg.is_active ?? true))
      await packages.reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تغيير حالة الباقة.'))
    }
  }

  async function remove(pkg: Package) {
    setError(null)
    setNotice(null)

    try {
      await deletePackage(pkg.id)
      setNotice('تم حذف الباقة.')
      await packages.reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حذف الباقة.'))
    }
  }

  return (
    <section className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">إدارة الباقات</h1>
          <p className="text-sm text-slate-600">تكوين الباقات واختيار الخدمات المشمولة فيها.</p>
        </div>
        <button
          type="button"
          onClick={openCreate}
          className="rounded-md bg-slate-900 px-4 py-2 text-sm text-white"
        >
          باقة جديدة
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
          <h2 className="font-semibold">{form.id === null ? 'باقة جديدة' : 'تعديل الباقة'}</h2>

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
                {PACKAGE_CATEGORIES.map((category) => (
                  <option key={category} value={category}>
                    {PACKAGE_CATEGORY_LABELS[category]}
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
              <span>جولات التعديل</span>
              <input
                type="number"
                min={0}
                value={form.revision_rounds}
                onChange={(event) => patch({ revision_rounds: event.target.value })}
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
              <span>السعر (ريال)</span>
              <input
                type="number"
                min={0}
                step="0.01"
                required
                value={form.price}
                onChange={(event) => patch({ price: event.target.value })}
                className="w-full rounded-md border border-slate-300 px-3 py-2"
              />
            </label>

            <label className="block space-y-1 text-sm">
              <span>الخصم (ريال)</span>
              <input
                type="number"
                min={0}
                step="0.01"
                value={form.discount_amount}
                onChange={(event) => patch({ discount_amount: event.target.value })}
                className="w-full rounded-md border border-slate-300 px-3 py-2"
              />
            </label>
          </div>

          <label className="block space-y-1 text-sm">
            <span>الوصف</span>
            <textarea
              rows={3}
              value={form.description}
              onChange={(event) => patch({ description: event.target.value })}
              className="w-full rounded-md border border-slate-300 px-3 py-2"
            />
          </label>

          <label className="block space-y-1 text-sm">
            <span>لمن هذه الباقة؟</span>
            <textarea
              rows={2}
              value={form.audience}
              onChange={(event) => patch({ audience: event.target.value })}
              className="w-full rounded-md border border-slate-300 px-3 py-2"
            />
          </label>

          <label className="block space-y-1 text-sm">
            <span>مكونات الحل (سطر لكل مكوّن)</span>
            <textarea
              rows={4}
              value={form.deliverables}
              onChange={(event) => patch({ deliverables: event.target.value })}
              className="w-full rounded-md border border-slate-300 px-3 py-2"
            />
          </label>

          <fieldset className="space-y-3 border-t border-slate-100 pt-4">
            <legend className="text-sm font-semibold">مستويات الباقة</legend>
            <p className="text-xs text-slate-500">
              اترك السعر فارغاً ليظهر المستوى للعميل كـ «طلب تسعير».
            </p>

            <ul className="space-y-3">
              {form.tiers.map((tier, index) => (
                <li key={index} className="space-y-3 rounded-md border border-slate-200 p-3">
                  <div className="grid gap-3 sm:grid-cols-2">
                    <label className="block space-y-1 text-xs">
                      <span>اسم المستوى</span>
                      <input
                        required
                        value={tier.name}
                        onChange={(event) => patchTier(index, { name: event.target.value })}
                        className="w-full rounded-md border border-slate-300 px-2 py-1"
                      />
                    </label>
                    <label className="block space-y-1 text-xs">
                      <span>المعرّف (بالإنجليزية)</span>
                      <input
                        required
                        dir="ltr"
                        value={tier.slug}
                        onChange={(event) => patchTier(index, { slug: event.target.value })}
                        className="w-full rounded-md border border-slate-300 px-2 py-1"
                      />
                    </label>
                    <label className="block space-y-1 text-xs">
                      <span>السعر (اختياري)</span>
                      <input
                        type="number"
                        min={0}
                        step="0.01"
                        value={tier.price}
                        onChange={(event) => patchTier(index, { price: event.target.value })}
                        className="w-full rounded-md border border-slate-300 px-2 py-1"
                      />
                    </label>
                    <label className="block space-y-1 text-xs">
                      <span>مدة التنفيذ (أيام)</span>
                      <input
                        type="number"
                        min={0}
                        value={tier.duration_days}
                        onChange={(event) => patchTier(index, { duration_days: event.target.value })}
                        className="w-full rounded-md border border-slate-300 px-2 py-1"
                      />
                    </label>
                    <label className="block space-y-1 text-xs">
                      <span>جولات التعديل</span>
                      <input
                        type="number"
                        min={0}
                        value={tier.revision_rounds}
                        onChange={(event) => patchTier(index, { revision_rounds: event.target.value })}
                        className="w-full rounded-md border border-slate-300 px-2 py-1"
                      />
                    </label>
                    <label className="flex items-center gap-2 text-xs sm:pt-5">
                      <input
                        type="checkbox"
                        checked={tier.is_active}
                        onChange={(event) => patchTier(index, { is_active: event.target.checked })}
                      />
                      <span>منشور</span>
                    </label>
                  </div>
                  <label className="block space-y-1 text-xs">
                    <span>وصف المستوى</span>
                    <textarea
                      rows={2}
                      value={tier.description}
                      onChange={(event) => patchTier(index, { description: event.target.value })}
                      className="w-full rounded-md border border-slate-300 px-2 py-1"
                    />
                  </label>
                  <button
                    type="button"
                    onClick={() => removeTier(index)}
                    className="text-xs text-red-700 underline"
                  >
                    حذف المستوى
                  </button>
                </li>
              ))}
            </ul>

            <button
              type="button"
              onClick={addTier}
              className="rounded-md border border-slate-300 px-3 py-1 text-xs"
            >
              إضافة مستوى
            </button>
          </fieldset>

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

          <fieldset className="space-y-3 border-t border-slate-100 pt-4">
            <legend className="text-sm font-semibold">الخدمات المشمولة</legend>

            {serviceList.length === 0 ? (
              <p className="text-sm text-slate-500">أضف خدمات أولاً حتى تتمكن من تكوين الباقة.</p>
            ) : null}

            <ul className="space-y-2">
              {serviceList.map((service) => {
                const item = form.items.find((entry) => entry.service_id === service.id)

                return (
                  <li key={service.id} className="rounded-md border border-slate-200 p-3">
                    <label className="flex items-center gap-2 text-sm">
                      <input
                        type="checkbox"
                        checked={item !== undefined}
                        onChange={(event) => toggleItem(service, event.target.checked)}
                      />
                      <span>{service.name}</span>
                      {service.is_active === false ? (
                        <span className="rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-600">
                          معطّلة
                        </span>
                      ) : null}
                    </label>

                    {item !== undefined ? (
                      <div className="mt-3 grid gap-3 sm:grid-cols-3">
                        <label className="block space-y-1 text-xs">
                          <span>الكمية</span>
                          <input
                            type="number"
                            min={1}
                            value={item.quantity}
                            onChange={(event) =>
                              patchItem(service.id, {
                                quantity: Math.max(1, Number.parseInt(event.target.value, 10) || 1),
                              })
                            }
                            className="w-full rounded-md border border-slate-300 px-2 py-1"
                          />
                        </label>
                        <label className="block space-y-1 text-xs">
                          <span>الترتيب</span>
                          <input
                            type="number"
                            min={0}
                            value={item.sort_order}
                            onChange={(event) =>
                              patchItem(service.id, {
                                sort_order: Math.max(0, Number.parseInt(event.target.value, 10) || 0),
                              })
                            }
                            className="w-full rounded-md border border-slate-300 px-2 py-1"
                          />
                        </label>
                        <label className="block space-y-1 text-xs">
                          <span>ملاحظات</span>
                          <input
                            value={item.notes ?? ''}
                            onChange={(event) => patchItem(service.id, { notes: event.target.value })}
                            className="w-full rounded-md border border-slate-300 px-2 py-1"
                          />
                        </label>
                      </div>
                    ) : null}
                  </li>
                )
              })}
            </ul>
          </fieldset>

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

      {packages.state.status === 'loading' ? (
        <p className="text-sm text-slate-500">جاري التحميل...</p>
      ) : null}

      {packages.state.status === 'error' ? (
        <p className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
          {packages.state.message}
        </p>
      ) : null}

      {packages.state.status === 'ready' && packages.state.data.length === 0 ? (
        <p className="rounded-md border border-slate-200 bg-white px-3 py-6 text-center text-sm text-slate-500">
          لا توجد باقات بعد.
        </p>
      ) : null}

      {packages.state.status === 'ready' && packages.state.data.length > 0 ? (
        <ul className="space-y-3">
          {packages.state.data.map((pkg) => (
            <li key={pkg.id} className="space-y-2 rounded-lg border border-slate-200 bg-white p-4">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="space-y-1">
                  <div className="flex items-center gap-2">
                    <span className="font-semibold">{pkg.name}</span>
                    <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                      {PACKAGE_CATEGORY_LABELS[pkg.category]}
                    </span>
                    <span
                      className={
                        pkg.is_active
                          ? 'rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800'
                          : 'rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-600'
                      }
                    >
                      {pkg.is_active ? 'منشورة' : 'معطّلة'}
                    </span>
                  </div>
                  <p className="text-sm text-slate-600">
                    {packagePriceLabel(pkg)} · {pkg.items.length} خدمة · {pkg.tiers.length} مستوى
                  </p>
                </div>

                <div className="flex gap-3 text-sm">
                  <button type="button" onClick={() => openEdit(pkg)} className="underline">
                    تعديل
                  </button>
                  <button type="button" onClick={() => void toggleActive(pkg)} className="underline">
                    {pkg.is_active ? 'تعطيل' : 'تنشيط'}
                  </button>
                  <button type="button" onClick={() => void remove(pkg)} className="text-red-700 underline">
                    حذف
                  </button>
                </div>
              </div>

              {pkg.items.length > 0 ? (
                <ul className="flex flex-wrap gap-2 text-xs text-slate-600">
                  {pkg.items.map((item) => (
                    <li key={item.id} className="rounded-full bg-slate-100 px-2 py-1">
                      {item.service?.name ?? `#${item.service_id}`}
                      {item.quantity > 1 ? ` × ${item.quantity}` : ''}
                    </li>
                  ))}
                </ul>
              ) : null}
            </li>
          ))}
        </ul>
      ) : null}
    </section>
  )
}
