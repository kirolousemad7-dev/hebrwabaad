import { FormEvent, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { useToast } from '../../context/ToastContext'
import {
  createCrmCompany,
  getCrmCompanies,
  type CrmCompany,
} from '../../services/crm'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

export function CrmCompaniesPage() {
  const toast = useToast()
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const [items, setItems] = useState<CrmCompany[]>([])
  const [meta, setMeta] = useState<{ current_page: number; last_page: number; total: number } | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [creating, setCreating] = useState(false)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await getCrmCompanies({ q: q || undefined, page, per_page: 15 })
      setItems(response.data.items)
      setMeta(response.data.meta)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل الشركات.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    const handle = window.setTimeout(() => void load(), q ? 250 : 0)
    return () => window.clearTimeout(handle)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [q, page])

  async function handleCreate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (saving) return
    const form = new FormData(event.currentTarget)
    setSaving(true)
    setFormError(null)
    try {
      await createCrmCompany({
        name: String(form.get('name') || '').trim(),
        industry: String(form.get('industry') || '').trim() || undefined,
        phone: String(form.get('phone') || '').trim() || undefined,
        email: String(form.get('email') || '').trim() || undefined,
        city: String(form.get('city') || '').trim() || undefined,
        website: String(form.get('website') || '').trim() || undefined,
        notes: String(form.get('notes') || '').trim() || undefined,
      })
      setCreating(false)
      toast.success('تم إنشاء الشركة.')
      await load()
    } catch (caught) {
      setFormError(describeApiError(caught, 'تعذر إنشاء الشركة.'))
    } finally {
      setSaving(false)
    }
  }

  return (
    <DashboardSection
      title="الشركات"
      description="إدارة حسابات الشركات المرتبطة بالمبيعات."
      action={
        <button type="button" className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white" onClick={() => setCreating(true)}>
          شركة جديدة
        </button>
      }
    >
      <input
        value={q}
        onChange={(event) => {
          setQ(event.target.value)
          setPage(1)
        }}
        placeholder="بحث بالاسم / الهاتف / البريد"
        className={`${fieldClass} max-w-md`}
      />

      {creating ? (
        <form onSubmit={(event) => void handleCreate(event)} className="grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-2">
          {formError ? (
            <div className="sm:col-span-2">
              <FeedbackBanner kind="error">{formError}</FeedbackBanner>
            </div>
          ) : null}
          <input required name="name" placeholder="اسم الشركة *" className={fieldClass} />
          <input name="industry" placeholder="القطاع" className={fieldClass} />
          <input name="phone" placeholder="الهاتف" dir="ltr" className={fieldClass} />
          <input name="email" type="email" placeholder="البريد" dir="ltr" className={fieldClass} />
          <input name="city" placeholder="المدينة" className={fieldClass} />
          <input name="website" placeholder="الموقع" dir="ltr" className={fieldClass} />
          <textarea name="notes" rows={2} placeholder="ملاحظات" className={`${fieldClass} sm:col-span-2`} />
          <div className="flex gap-2 sm:col-span-2">
            <button type="submit" disabled={saving} className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white disabled:opacity-60">
              {saving ? 'جاري...' : 'حفظ'}
            </button>
            <button type="button" className="min-h-11 rounded-xl border px-4 text-sm" onClick={() => setCreating(false)}>
              إلغاء
            </button>
          </div>
        </form>
      ) : null}

      {loading ? <DashboardPanelSkeleton label="جاري تحميل الشركات..." /> : null}
      {error ? <DashboardErrorState message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && items.length === 0 ? (
        <DashboardEmptyState title="لا شركات." description="أضف أول شركة لربط العملاء المحتملين وجهات الاتصال." />
      ) : null}

      {!loading && !error && items.length > 0 ? (
        <>
          <div className="overflow-x-auto rounded-2xl border bg-white">
            <table className="min-w-full text-sm">
              <thead className="bg-slate-50 text-right">
                <tr>
                  <th className="px-3 py-2">الشركة</th>
                  <th className="px-3 py-2">المدينة</th>
                  <th className="px-3 py-2">جهات الاتصال</th>
                  <th className="px-3 py-2">العملاء</th>
                  <th className="px-3 py-2">الحالة</th>
                  <th className="px-3 py-2">إجراء</th>
                </tr>
              </thead>
              <tbody>
                {items.map((company) => (
                  <tr key={company.id} className="border-t">
                    <td className="px-3 py-2">
                      <div className="font-medium">{company.name}</div>
                      <div className="text-xs text-slate-500" dir="ltr">
                        {company.email || company.phone || '—'}
                      </div>
                    </td>
                    <td className="px-3 py-2">{company.city || '—'}</td>
                    <td className="px-3 py-2">{(company.contacts_count ?? 0).toLocaleString('ar-SA')}</td>
                    <td className="px-3 py-2">{(company.leads_count ?? 0).toLocaleString('ar-SA')}</td>
                    <td className="px-3 py-2">
                      <StatusBadge status={company.status} label={company.status === 'active' ? 'نشطة' : 'مؤرشفة'} />
                    </td>
                    <td className="px-3 py-2">
                      <Link className="rounded-lg border px-2 py-1" to={`/crm/companies/${company.id}`}>
                        فتح
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {meta && meta.last_page > 1 ? (
            <div className="flex items-center justify-between gap-3 text-sm">
              <p>
                صفحة {meta.current_page.toLocaleString('ar-SA')} من {meta.last_page.toLocaleString('ar-SA')}
              </p>
              <div className="flex gap-2">
                <button type="button" disabled={meta.current_page <= 1} className="rounded-lg border px-3 py-1 disabled:opacity-50" onClick={() => setPage((p) => p - 1)}>
                  السابق
                </button>
                <button type="button" disabled={meta.current_page >= meta.last_page} className="rounded-lg border px-3 py-1 disabled:opacity-50" onClick={() => setPage((p) => p + 1)}>
                  التالي
                </button>
              </div>
            </div>
          ) : null}
        </>
      ) : null}
    </DashboardSection>
  )
}
