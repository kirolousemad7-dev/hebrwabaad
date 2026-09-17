import { FormEvent, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { DashboardErrorState, DashboardPanelSkeleton, DashboardSection } from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useToast } from '../../context/ToastContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import {
  approveAdminSupplier,
  archiveAdminSupplierProduct,
  archiveAdminSupplierService,
  createAdminSupplierContact,
  createAdminSupplierDocument,
  createAdminSupplierProduct,
  createAdminSupplierService,
  duplicateAdminSupplierProduct,
  duplicateAdminSupplierService,
  exportAdminSupplierProducts,
  exportAdminSupplierServices,
  getAdminSupplier,
  importAdminSupplierProducts,
  importAdminSupplierServices,
  publishAdminSupplier,
  rejectAdminSupplier,
  suspendAdminSupplier,
  updateAdminSupplier,
  verifyAdminSupplier,
} from '../../services/supplierWorkspace'
import { describeApiError } from '../../utils/errors'

const TABS = [
  'نظرة عامة',
  'الملف',
  'جهات الاتصال',
  'الخدمات',
  'المنتجات',
  'المعرض',
  'المستندات',
  'التسعير',
  'الملاحظات',
  'SEO',
  'النشر',
  'السجل',
] as const

export function OwnerSupplierDetailPage() {
  const { id } = useParams()
  const supplierId = Number(id)
  const { state, reload } = useAsyncData(() => getAdminSupplier(supplierId))
  const toast = useToast()
  const [tab, setTab] = useState<(typeof TABS)[number]>('نظرة عامة')
  const [error, setError] = useState<string | null>(null)

  if (state.status === 'loading') return <DashboardPanelSkeleton label="جاري تحميل المورد..." />
  if (state.status === 'error') return <DashboardErrorState message={state.message} onRetry={() => void reload()} />

  const { supplier, portfolio, products, reviews, contacts = [], services = [], documents = [], categories = [], tags = [] } = state.data

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    const payload: Record<string, unknown> = {}
    for (const [key, value] of form.entries()) {
      if (key === 'is_featured' || key === 'show_public_contact') {
        payload[key] = value === '1'
        continue
      }
      if (key === 'service_areas' || key === 'certifications') {
        payload[key] = String(value).split(',').map((part) => part.trim()).filter(Boolean)
        continue
      }
      payload[key] = String(value)
    }
    try {
      await updateAdminSupplier(supplierId, payload)
      toast.success('تم حفظ المورد.')
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر الحفظ.'))
    }
  }

  async function addContact(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    try {
      await createAdminSupplierContact(supplierId, {
        name: String(form.get('name')),
        position: String(form.get('position') || '') || undefined,
        email: String(form.get('email') || '') || undefined,
        phone: String(form.get('phone') || '') || undefined,
        whatsapp: String(form.get('whatsapp') || '') || undefined,
        is_primary: form.get('is_primary') === '1',
      })
      event.currentTarget.reset()
      toast.success('تمت إضافة جهة الاتصال.')
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إضافة جهة الاتصال.'))
    }
  }

  async function addService(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    try {
      await createAdminSupplierService(supplierId, {
        name: String(form.get('name')),
        pricing_model: String(form.get('pricing_model') || 'CUSTOM_QUOTE'),
        minimum_price: form.get('minimum_price') ? Number(form.get('minimum_price')) : undefined,
        maximum_price: form.get('maximum_price') ? Number(form.get('maximum_price')) : undefined,
        currency: String(form.get('currency') || 'SAR'),
        delivery_time: String(form.get('delivery_time') || '') || undefined,
        description: String(form.get('description') || '') || undefined,
      })
      event.currentTarget.reset()
      toast.success('تمت إضافة الخدمة.')
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إضافة الخدمة.'))
    }
  }

  async function addDocument(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    try {
      await createAdminSupplierDocument(supplierId, {
        title: String(form.get('title')),
        category: String(form.get('category') || '') || undefined,
        path: String(form.get('path')),
        original_name: String(form.get('original_name') || '') || undefined,
      })
      event.currentTarget.reset()
      toast.success('تمت إضافة المستند.')
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إضافة المستند.'))
    }
  }

  async function addProduct(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    try {
      await createAdminSupplierProduct(supplierId, {
        name: String(form.get('name')),
        sku: String(form.get('sku') || '') || undefined,
        category: String(form.get('category') || '') || undefined,
        description: String(form.get('description') || '') || undefined,
        unit: String(form.get('unit') || '') || undefined,
        price: form.get('price') ? Number(form.get('price')) : undefined,
        currency: String(form.get('currency') || 'SAR'),
        minimum_quantity: form.get('minimum_quantity') ? Number(form.get('minimum_quantity')) : undefined,
        lead_time: String(form.get('lead_time') || '') || undefined,
        availability: String(form.get('availability') || 'CONTACT'),
        visibility: String(form.get('visibility') || 'INTERNAL'),
      })
      event.currentTarget.reset()
      toast.success('تمت إضافة المنتج.')
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إضافة المنتج.'))
    }
  }

  return (
    <DashboardSection
      title={supplier.display_name || supplier.name}
      description={`${supplier.supplier_code ?? ''} · /${supplier.slug} · ${supplier.status} · ${supplier.verification_status}`}
      action={<Link to="/owner/suppliers" className="min-h-11 rounded-xl border px-4 text-sm leading-11">القائمة</Link>}
    >
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      <div className="flex flex-wrap items-center gap-3 rounded-2xl border bg-white p-4">
        {supplier.logo ? <img src={supplier.logo} alt="" className="h-14 w-14 rounded-xl object-cover" /> : null}
        <div className="min-w-0 flex-1">
          <div className="font-semibold">{supplier.display_name || supplier.name}</div>
          <div className="text-sm text-slate-600">{supplier.category ?? 'بدون تصنيف'} · {supplier.city ?? supplier.location}</div>
        </div>
        <div className="flex flex-wrap gap-2">
          <button type="button" className="rounded-lg border px-3 py-2 text-sm" onClick={() => void approveAdminSupplier(supplierId).then(() => { toast.success('تمت الموافقة.'); return reload() })}>موافقة</button>
          <button type="button" className="rounded-lg border px-3 py-2 text-sm" onClick={() => void rejectAdminSupplier(supplierId, 'مرفوض من لوحة المالك').then(() => { toast.success('تم الرفض.'); return reload() })}>رفض</button>
          <button type="button" className="rounded-lg border px-3 py-2 text-sm" onClick={() => void verifyAdminSupplier(supplierId, 'FULLY_VERIFIED').then(() => { toast.success('تم التحقق.'); return reload() })}>تحقق كامل</button>
          <button type="button" className="rounded-lg border px-3 py-2 text-sm" onClick={() => void suspendAdminSupplier(supplierId, 'إيقاف من لوحة المالك').then(() => { toast.success('تم الإيقاف.'); return reload() })}>إيقاف</button>
          <button type="button" className="rounded-lg bg-emerald-700 px-3 py-2 text-sm text-white" onClick={() => void publishAdminSupplier(supplierId).then(() => { toast.success('تم النشر.'); return reload() })}>نشر الملف</button>
        </div>
      </div>

      <div className="flex flex-wrap gap-2">
        {TABS.map((item) => (
          <button key={item} type="button" onClick={() => setTab(item)} className={`min-h-10 rounded-full px-4 text-sm ${tab === item ? 'bg-slate-900 text-white' : 'border bg-white'}`}>{item}</button>
        ))}
      </div>

      {tab === 'نظرة عامة' ? (
        <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <li className="rounded-2xl border bg-white p-4">الحالة: {supplier.status}</li>
          <li className="rounded-2xl border bg-white p-4">التحقق: {supplier.verification_status}</li>
          <li className="rounded-2xl border bg-white p-4">التأهيل: {supplier.onboarding_status}</li>
          <li className="rounded-2xl border bg-white p-4">منشور: {supplier.is_published ? 'نعم' : 'لا'}</li>
          <li className="rounded-2xl border bg-white p-4">الظهور: {supplier.visibility ?? 'PRIVATE'}</li>
          <li className="rounded-2xl border bg-white p-4">التوفر: {supplier.availability ?? '—'}</li>
          <li className="rounded-2xl border bg-white p-4">مدة التسليم: {supplier.delivery_time ?? '—'}</li>
          <li className="rounded-2xl border bg-white p-4">مناطق الخدمة: {(supplier.service_areas ?? []).join('، ') || '—'}</li>
          <li className="rounded-2xl border bg-white p-4">شهادات: {(supplier.certifications ?? []).join('، ') || '—'}</li>
          <li className="rounded-2xl border bg-white p-4">جهات اتصال: {contacts.length}</li>
          <li className="rounded-2xl border bg-white p-4">خدمات: {services.length}</li>
          <li className="rounded-2xl border bg-white p-4">منتجات: {products.length}</li>
          <li className="rounded-2xl border bg-white p-4">معرض: {portfolio.length}</li>
          <li className="rounded-2xl border bg-white p-4">مستندات: {documents.length}</li>
          <li className="rounded-2xl border bg-white p-4">تصنيفات: {categories.map((c) => c.name).join('، ') || '—'}</li>
          <li className="rounded-2xl border bg-white p-4">وسوم: {tags.map((t) => t.name).join('، ') || '—'}</li>
        </ul>
      ) : null}

      {tab === 'الملف' || tab === 'SEO' || tab === 'النشر' || tab === 'الملاحظات' ? (
        <form onSubmit={(event) => void save(event)} className="grid gap-3 rounded-2xl border bg-white p-4">
          {tab === 'الملف' ? (
            <>
              <input name="name" defaultValue={supplier.name} className="rounded-md border px-3 py-2" />
              <input name="display_name" defaultValue={supplier.display_name ?? ''} className="rounded-md border px-3 py-2" />
              <input name="legal_name" defaultValue={supplier.legal_name ?? ''} className="rounded-md border px-3 py-2" />
              <input name="short_description" defaultValue={supplier.short_description} className="rounded-md border px-3 py-2" />
              <textarea name="description" defaultValue={supplier.description ?? ''} className="min-h-28 rounded-md border px-3 py-2" />
              <input name="location" defaultValue={supplier.location} className="rounded-md border px-3 py-2" />
              <input name="city" defaultValue={supplier.city ?? ''} className="rounded-md border px-3 py-2" />
              <input name="country" defaultValue={supplier.country ?? ''} className="rounded-md border px-3 py-2" />
              <input name="phone" defaultValue={supplier.phone ?? ''} className="rounded-md border px-3 py-2" />
              <input name="whatsapp" defaultValue={supplier.whatsapp ?? ''} className="rounded-md border px-3 py-2" />
              <input name="email" defaultValue={supplier.email ?? ''} className="rounded-md border px-3 py-2" />
              <input name="availability" defaultValue={supplier.availability ?? ''} placeholder="التوفر" className="rounded-md border px-3 py-2" />
              <input name="delivery_time" defaultValue={supplier.delivery_time ?? ''} placeholder="مدة التسليم" className="rounded-md border px-3 py-2" />
              <input name="service_areas" defaultValue={(supplier.service_areas ?? []).join(', ')} placeholder="مناطق الخدمة (مفصولة بفاصلة)" className="rounded-md border px-3 py-2" />
              <input name="certifications" defaultValue={(supplier.certifications ?? []).join(', ')} placeholder="شهادات (مفصولة بفاصلة)" className="rounded-md border px-3 py-2" />
            </>
          ) : null}
          {tab === 'الملاحظات' ? (
            <>
              <textarea name="notes" defaultValue={supplier.notes ?? ''} placeholder="ملاحظات" className="min-h-24 rounded-md border px-3 py-2" />
              <textarea name="internal_notes" defaultValue={supplier.internal_notes ?? ''} placeholder="ملاحظات داخلية" className="min-h-24 rounded-md border px-3 py-2" />
            </>
          ) : null}
          {tab === 'SEO' ? (
            <>
              <input name="seo_title" defaultValue={supplier.seo_title ?? ''} className="rounded-md border px-3 py-2" />
              <textarea name="seo_description" defaultValue={supplier.seo_description ?? ''} className="min-h-20 rounded-md border px-3 py-2" />
              <input name="og_image" defaultValue={supplier.og_image ?? ''} className="rounded-md border px-3 py-2" />
              <input name="robots" defaultValue={supplier.robots ?? 'index,follow'} className="rounded-md border px-3 py-2" />
            </>
          ) : null}
          {tab === 'النشر' ? (
            <>
              <label className="text-sm"><input type="checkbox" name="is_featured" defaultChecked={supplier.is_featured} value="1" /> مميز</label>
              <label className="text-sm"><input type="checkbox" name="show_public_contact" defaultChecked={supplier.show_public_contact} value="1" /> إظهار بيانات التواصل للعامة</label>
              <select name="visibility" defaultValue={supplier.visibility ?? 'PRIVATE'} className="rounded-md border px-3 py-2">
                <option value="PRIVATE">PRIVATE (افتراضي)</option>
                <option value="INTERNAL">INTERNAL</option>
                <option value="PUBLIC">PUBLIC</option>
              </select>
            </>
          ) : null}
          <button type="submit" className="min-h-11 max-w-xs rounded-xl bg-slate-900 px-4 text-sm text-white">حفظ</button>
        </form>
      ) : null}

      {tab === 'جهات الاتصال' ? (
        <div className="grid gap-4">
          <form onSubmit={(event) => void addContact(event)} className="grid gap-2 rounded-2xl border bg-white p-4 md:grid-cols-3">
            <input required name="name" placeholder="الاسم" className="rounded-md border px-3 py-2 text-sm" />
            <input name="position" placeholder="المنصب" className="rounded-md border px-3 py-2 text-sm" />
            <input name="email" type="email" placeholder="البريد" className="rounded-md border px-3 py-2 text-sm" />
            <input name="phone" placeholder="الهاتف" className="rounded-md border px-3 py-2 text-sm" />
            <input name="whatsapp" placeholder="واتساب" className="rounded-md border px-3 py-2 text-sm" />
            <label className="flex items-center gap-2 text-sm"><input type="checkbox" name="is_primary" value="1" /> أساسي</label>
            <button type="submit" className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white md:col-span-3">إضافة جهة اتصال</button>
          </form>
          <ul className="space-y-2">{contacts.map((item) => <li key={String(item.id)} className="rounded-xl border bg-white p-3 text-sm">{String(item.name)} · {String(item.email ?? '—')} {item.is_primary ? '· أساسي' : ''}</li>)}</ul>
        </div>
      ) : null}

      {tab === 'الخدمات' || tab === 'التسعير' ? (
        <div className="grid gap-4">
          <div className="flex flex-wrap gap-2">
            <button type="button" className="rounded-lg border px-3 py-2 text-sm" onClick={() => void exportAdminSupplierServices(supplierId).then((res) => { navigator.clipboard?.writeText(JSON.stringify(res.data.items, null, 2)); toast.success('تم نسخ التصدير JSON.') }).catch((c) => setError(describeApiError(c, 'تعذر التصدير.')))}>تصدير الخدمات</button>
            <button type="button" className="rounded-lg border px-3 py-2 text-sm" onClick={() => void importAdminSupplierServices(supplierId, [{ name: 'خدمة مستوردة', pricing_model: 'CUSTOM_QUOTE' }]).then(() => { toast.success('تم الاستيراد.'); return reload() }).catch((c) => setError(describeApiError(c, 'تعذر الاستيراد.')))}>استيراد تجريبي</button>
          </div>
          <form onSubmit={(event) => void addService(event)} className="grid gap-2 rounded-2xl border bg-white p-4 md:grid-cols-3">
            <input required name="name" placeholder="اسم الخدمة" className="rounded-md border px-3 py-2 text-sm" />
            <select name="pricing_model" className="rounded-md border px-3 py-2 text-sm" defaultValue="CUSTOM_QUOTE">
              <option value="FIXED">FIXED</option>
              <option value="HOURLY">HOURLY</option>
              <option value="DAILY">DAILY</option>
              <option value="PER_PROJECT">PER_PROJECT</option>
              <option value="PER_UNIT">PER_UNIT</option>
              <option value="CUSTOM_QUOTE">CUSTOM_QUOTE</option>
            </select>
            <input name="currency" defaultValue="SAR" className="rounded-md border px-3 py-2 text-sm" />
            <input name="minimum_price" type="number" placeholder="حد أدنى" className="rounded-md border px-3 py-2 text-sm" />
            <input name="maximum_price" type="number" placeholder="حد أعلى" className="rounded-md border px-3 py-2 text-sm" />
            <input name="delivery_time" placeholder="مدة التسليم" className="rounded-md border px-3 py-2 text-sm" />
            <textarea name="description" placeholder="الوصف" className="min-h-20 rounded-md border px-3 py-2 text-sm md:col-span-3" />
            <button type="submit" className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white md:col-span-3">إضافة خدمة / تسعير</button>
          </form>
          <ul className="space-y-2">
            {services.map((item) => (
              <li key={String(item.id)} className="flex flex-wrap items-center justify-between gap-2 rounded-xl border bg-white p-3 text-sm">
                <span>{String(item.name)} · {String(item.pricing_model)} · {String(item.minimum_price ?? '—')}–{String(item.maximum_price ?? '—')} {String(item.currency ?? '')}</span>
                <span className="flex gap-2">
                  <button type="button" className="rounded border px-2 py-1" onClick={() => void duplicateAdminSupplierService(supplierId, Number(item.id)).then(() => reload())}>نسخ</button>
                  <button type="button" className="rounded border px-2 py-1" onClick={() => void archiveAdminSupplierService(supplierId, Number(item.id)).then(() => reload())}>أرشفة</button>
                </span>
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      {tab === 'المستندات' ? (
        <div className="grid gap-4">
          <form onSubmit={(event) => void addDocument(event)} className="grid gap-2 rounded-2xl border bg-white p-4 md:grid-cols-2">
            <input required name="title" placeholder="عنوان المستند" className="rounded-md border px-3 py-2 text-sm" />
            <input name="category" placeholder="الفئة (contracts/tax/...)" className="rounded-md border px-3 py-2 text-sm" />
            <input required name="path" placeholder="مسار الملف *" className="rounded-md border px-3 py-2 text-sm" />
            <input name="original_name" placeholder="الاسم الأصلي" className="rounded-md border px-3 py-2 text-sm" />
            <button type="submit" className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white md:col-span-2">إضافة مستند داخلي</button>
          </form>
          <ul className="space-y-2">{documents.map((item) => <li key={String(item.id)} className="rounded-xl border bg-white p-3 text-sm">{String(item.title)} · {String(item.visibility)} · {String(item.path)}</li>)}</ul>
        </div>
      ) : null}

      {tab === 'المعرض' ? (
        <ul className="space-y-2">{portfolio.map((item) => <li key={String(item.id)} className="rounded-xl border bg-white p-3">{String(item.title)} · {String(item.status)} · {String(item.visibility ?? '—')} · {String(item.client_type ?? '')}</li>)}</ul>
      ) : null}
      {tab === 'المنتجات' ? (
        <div className="grid gap-4">
          <div className="flex flex-wrap gap-2">
            <button type="button" className="rounded-lg border px-3 py-2 text-sm" onClick={() => void exportAdminSupplierProducts(supplierId).then((res) => { navigator.clipboard?.writeText(JSON.stringify(res.data.items, null, 2)); toast.success('تم نسخ التصدير JSON.') }).catch((c) => setError(describeApiError(c, 'تعذر التصدير.')))}>تصدير المنتجات</button>
            <button type="button" className="rounded-lg border px-3 py-2 text-sm" onClick={() => void importAdminSupplierProducts(supplierId, [{ name: 'منتج مستورد', price: 50, sku: 'IMP' }]).then(() => { toast.success('تم الاستيراد.'); return reload() }).catch((c) => setError(describeApiError(c, 'تعذر الاستيراد.')))}>استيراد تجريبي</button>
          </div>
          <form onSubmit={(event) => void addProduct(event)} className="grid gap-2 rounded-2xl border bg-white p-4 md:grid-cols-3">
            <input required name="name" placeholder="اسم المنتج *" className="rounded-md border px-3 py-2 text-sm" />
            <input name="sku" placeholder="SKU" className="rounded-md border px-3 py-2 text-sm" />
            <input name="category" placeholder="التصنيف" className="rounded-md border px-3 py-2 text-sm" />
            <input name="unit" placeholder="الوحدة" className="rounded-md border px-3 py-2 text-sm" />
            <input name="price" type="number" placeholder="السعر" className="rounded-md border px-3 py-2 text-sm" />
            <input name="currency" defaultValue="SAR" className="rounded-md border px-3 py-2 text-sm" />
            <input name="minimum_quantity" type="number" placeholder="الحد الأدنى" className="rounded-md border px-3 py-2 text-sm" />
            <input name="lead_time" placeholder="مدة التوريد" className="rounded-md border px-3 py-2 text-sm" />
            <select name="availability" defaultValue="CONTACT" className="rounded-md border px-3 py-2 text-sm">
              <option value="IN_STOCK">IN_STOCK</option>
              <option value="MADE_TO_ORDER">MADE_TO_ORDER</option>
              <option value="UNAVAILABLE">UNAVAILABLE</option>
              <option value="CONTACT">CONTACT</option>
            </select>
            <select name="visibility" defaultValue="INTERNAL" className="rounded-md border px-3 py-2 text-sm">
              <option value="PRIVATE">PRIVATE</option>
              <option value="INTERNAL">INTERNAL</option>
              <option value="PUBLIC">PUBLIC</option>
            </select>
            <textarea name="description" placeholder="الوصف" className="min-h-20 rounded-md border px-3 py-2 text-sm md:col-span-3" />
            <button type="submit" className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white md:col-span-3">إضافة منتج</button>
          </form>
          <ul className="space-y-2">
            {products.map((item) => (
              <li key={String(item.id)} className="flex flex-wrap items-center justify-between gap-2 rounded-xl border bg-white p-3 text-sm">
                <span>{String(item.name)} · {String(item.sku ?? '—')} · {String(item.status)} · {String(item.visibility ?? '—')}</span>
                <span className="flex gap-2">
                  <button type="button" className="rounded border px-2 py-1" onClick={() => void duplicateAdminSupplierProduct(supplierId, Number(item.id)).then(() => reload())}>نسخ</button>
                  <button type="button" className="rounded border px-2 py-1" onClick={() => void archiveAdminSupplierProduct(supplierId, Number(item.id)).then(() => reload())}>أرشفة</button>
                </span>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
      {tab === 'السجل' ? (
        <ul className="space-y-2">{reviews.map((item) => <li key={String(item.id)} className="rounded-xl border bg-white p-3">{String(item.action)} · {String(item.actor ?? '')} · {String(item.notes ?? '')}</li>)}</ul>
      ) : null}
    </DashboardSection>
  )
}
