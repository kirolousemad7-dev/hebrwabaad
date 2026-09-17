import { FormEvent, useState } from 'react'
import { Link } from 'react-router-dom'
import { DashboardEmptyState, DashboardErrorState, DashboardPanelSkeleton, DashboardSection } from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useAsyncData } from '../../hooks/useAsyncData'
import {
  createSupplierPortalContact,
  createSupplierPortalDocument,
  createSupplierPortalService,
  getSupplierDashboard,
  getSupplierPortalContacts,
  getSupplierPortalDocuments,
  getSupplierPortalServices,
} from '../../services/supplierPortal'
import { describeApiError } from '../../utils/errors'

export function SupplierDashboardPage() {
  const { state, reload } = useAsyncData(() => getSupplierDashboard())

  if (state.status === 'loading') return <DashboardPanelSkeleton label="جاري تحميل لوحة المورد..." />
  if (state.status === 'error') return <DashboardErrorState message={state.message} onRetry={() => void reload()} />

  const { supplier, completion, counts, access } = state.data

  return (
    <DashboardSection title="لوحة المورد" description={supplier.display_name || supplier.name}>
      {!access.is_active_supplier ? (
        <FeedbackBanner kind="error">حالة الحساب: {access.status}. بانتظار موافقة الإدارة أو تم تقييد الوصول.</FeedbackBanner>
      ) : null}
      {access.owner_change_request ? <FeedbackBanner kind="error">مطلوب تعديل: {access.owner_change_request}</FeedbackBanner> : null}

      <div className="rounded-2xl border bg-white p-4">
        <div className="mb-2 flex items-center justify-between text-sm">
          <span>اكتمال الملف</span>
          <strong>{completion.percent}%</strong>
        </div>
        <div className="h-2 overflow-hidden rounded-full bg-slate-100">
          <div className="h-full bg-slate-900" style={{ width: `${completion.percent}%` }} />
        </div>
        {completion.missing.length ? (
          <ul className="mt-3 list-disc pr-5 text-sm text-slate-600">
            {completion.missing.slice(0, 8).map((item) => <li key={item}>{item}</li>)}
          </ul>
        ) : null}
      </div>

      <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <li className="rounded-2xl border bg-white p-4">جهات اتصال: {counts.contacts}</li>
        <li className="rounded-2xl border bg-white p-4">خدمات: {counts.services}</li>
        <li className="rounded-2xl border bg-white p-4">منتجات: {counts.products}</li>
        <li className="rounded-2xl border bg-white p-4">معرض: {counts.portfolio}</li>
        <li className="rounded-2xl border bg-white p-4">مستندات: {counts.documents}</li>
        <li className="rounded-2xl border bg-white p-4">الحالة: {access.status}</li>
      </ul>

      <div className="flex flex-wrap gap-2 text-sm">
        <Link className="rounded-lg border px-3 py-2" to="/supplier/profile">الملف</Link>
        <Link className="rounded-lg border px-3 py-2" to="/supplier/services">الخدمات</Link>
        <Link className="rounded-lg border px-3 py-2" to="/supplier/products">المنتجات</Link>
        <Link className="rounded-lg border px-3 py-2" to="/supplier/portfolio">المعرض</Link>
        <Link className="rounded-lg border px-3 py-2" to="/supplier/documents">المستندات</Link>
      </div>
    </DashboardSection>
  )
}

function PortalListPage({
  title,
  description,
  loader,
  create,
  fields,
}: {
  title: string
  description: string
  loader: () => Promise<{ data: { items: Array<Record<string, unknown>> } }>
  create?: (payload: Record<string, unknown>) => Promise<unknown>
  fields: Array<{ name: string; placeholder: string; required?: boolean }>
}) {
  const { state, reload } = useAsyncData(loader)
  const [error, setError] = useState<string | null>(null)

  async function onCreate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!create) return
    const form = new FormData(event.currentTarget)
    const payload: Record<string, unknown> = {}
    for (const field of fields) {
      payload[field.name] = String(form.get(field.name) || '') || undefined
    }
    try {
      await create(payload)
      event.currentTarget.reset()
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر الحفظ.'))
    }
  }

  return (
    <DashboardSection title={title} description={description}>
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      {create ? (
        <form onSubmit={(event) => void onCreate(event)} className="grid gap-2 rounded-2xl border bg-white p-4 md:grid-cols-2">
          {fields.map((field) => (
            <input key={field.name} name={field.name} required={field.required} placeholder={field.placeholder} className="rounded-md border px-3 py-2 text-sm" />
          ))}
          <button type="submit" className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white md:col-span-2">إضافة</button>
        </form>
      ) : null}
      {state.status === 'loading' ? <DashboardPanelSkeleton label="..." /> : null}
      {state.status === 'error' ? <DashboardErrorState message={state.message} onRetry={() => void reload()} /> : null}
      {state.status === 'ready' && state.data.items.length === 0 ? <DashboardEmptyState title="لا توجد عناصر بعد." description="" /> : null}
      {state.status === 'ready' ? (
        <ul className="space-y-2">
          {state.data.items.map((item) => (
            <li key={String(item.id)} className="rounded-xl border bg-white p-3 text-sm">
              {String(item.name || item.title || item.id)}
            </li>
          ))}
        </ul>
      ) : null}
    </DashboardSection>
  )
}

export function SupplierServicesPage() {
  return (
    <PortalListPage
      title="خدماتي"
      description="إدارة خدمات المورد والتسعير الداخلي."
      loader={getSupplierPortalServices}
      create={createSupplierPortalService}
      fields={[
        { name: 'name', placeholder: 'اسم الخدمة *', required: true },
        { name: 'pricing_model', placeholder: 'نموذج التسعير (FIXED/...)' },
        { name: 'minimum_price', placeholder: 'حد أدنى' },
        { name: 'delivery_time', placeholder: 'مدة التسليم' },
      ]}
    />
  )
}

export function SupplierContactsPage() {
  return (
    <PortalListPage
      title="جهات الاتصال"
      description="جهات اتصال المورد الداخلية."
      loader={getSupplierPortalContacts}
      create={createSupplierPortalContact}
      fields={[
        { name: 'name', placeholder: 'الاسم *', required: true },
        { name: 'position', placeholder: 'المنصب' },
        { name: 'email', placeholder: 'البريد' },
        { name: 'phone', placeholder: 'الهاتف' },
      ]}
    />
  )
}

export function SupplierDocumentsPage() {
  return (
    <PortalListPage
      title="المستندات"
      description="مستندات داخلية — غير ظاهرة للعملاء."
      loader={getSupplierPortalDocuments}
      create={createSupplierPortalDocument}
      fields={[
        { name: 'title', placeholder: 'العنوان *', required: true },
        { name: 'category', placeholder: 'الفئة' },
        { name: 'path', placeholder: 'مسار الملف *', required: true },
      ]}
    />
  )
}

export function SupplierModulePlaceholderPage({ title, description }: { title: string; description: string }) {
  return (
    <DashboardSection title={title} description={description}>
      <DashboardEmptyState title="قريباً" description="سيتم ربط هذا القسم بمهام/مشاريع/عروض الأسعار في مرحلة لاحقة. لا يمكن الوصول لبيانات عملاء أو موردين آخرين من هنا." />
    </DashboardSection>
  )
}
