import { FormEvent, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useToast } from '../../context/ToastContext'
import {
  createCrmContact,
  getCrmCompanies,
  getCrmContacts,
  type CrmCompany,
  type CrmContact,
} from '../../services/crm'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

export function CrmContactsPage() {
  const toast = useToast()
  const [q, setQ] = useState('')
  const [companyId, setCompanyId] = useState('')
  const [page, setPage] = useState(1)
  const [items, setItems] = useState<CrmContact[]>([])
  const [companies, setCompanies] = useState<CrmCompany[]>([])
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
      const response = await getCrmContacts({
        q: q || undefined,
        company_id: companyId || undefined,
        page,
        per_page: 20,
      })
      setItems(response.data.items)
      setMeta(response.data.meta)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل جهات الاتصال.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void getCrmCompanies({ per_page: 50 })
      .then((response) => setCompanies(response.data.items))
      .catch(() => setCompanies([]))
  }, [])

  useEffect(() => {
    const handle = window.setTimeout(() => void load(), q ? 250 : 0)
    return () => window.clearTimeout(handle)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [q, companyId, page])

  async function handleCreate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (saving) return
    const form = new FormData(event.currentTarget)
    setSaving(true)
    setFormError(null)
    try {
      await createCrmContact({
        company_id: Number(form.get('company_id')),
        name: String(form.get('name') || '').trim(),
        phone: String(form.get('phone') || '').trim() || undefined,
        whatsapp: String(form.get('whatsapp') || '').trim() || undefined,
        email: String(form.get('email') || '').trim() || undefined,
        job_title: String(form.get('job_title') || '').trim() || undefined,
        department: String(form.get('department') || '').trim() || undefined,
        is_primary: form.get('is_primary') === '1',
        notes: String(form.get('notes') || '').trim() || undefined,
      })
      setCreating(false)
      toast.success('تمت إضافة جهة الاتصال.')
      await load()
    } catch (caught) {
      setFormError(describeApiError(caught, 'تعذر إنشاء جهة الاتصال.'))
    } finally {
      setSaving(false)
    }
  }

  return (
    <DashboardSection
      title="جهات الاتصال"
      description="دليل جهات الاتصال المرتبط بالشركات."
      action={
        <button type="button" className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white" onClick={() => setCreating(true)}>
          جهة اتصال جديدة
        </button>
      }
    >
      <div className="grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-2">
        <input
          value={q}
          onChange={(event) => {
            setQ(event.target.value)
            setPage(1)
          }}
          placeholder="بحث بالاسم / الهاتف / البريد"
          className={fieldClass}
        />
        <select
          value={companyId}
          onChange={(event) => {
            setCompanyId(event.target.value)
            setPage(1)
          }}
          className={fieldClass}
        >
          <option value="">كل الشركات</option>
          {companies.map((company) => (
            <option key={company.id} value={company.id}>
              {company.name}
            </option>
          ))}
        </select>
      </div>

      {creating ? (
        <form onSubmit={(event) => void handleCreate(event)} className="grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-2">
          {formError ? (
            <div className="sm:col-span-2">
              <FeedbackBanner kind="error">{formError}</FeedbackBanner>
            </div>
          ) : null}
          <select required name="company_id" className={fieldClass}>
            <option value="">الشركة *</option>
            {companies.map((company) => (
              <option key={company.id} value={company.id}>
                {company.name}
              </option>
            ))}
          </select>
          <input required name="name" placeholder="الاسم *" className={fieldClass} />
          <input name="job_title" placeholder="المسمى" className={fieldClass} />
          <input name="department" placeholder="القسم" className={fieldClass} />
          <input name="phone" placeholder="الهاتف" dir="ltr" className={fieldClass} />
          <input name="whatsapp" placeholder="واتساب" dir="ltr" className={fieldClass} />
          <input name="email" type="email" placeholder="البريد" dir="ltr" className={fieldClass} />
          <label className="flex items-center gap-2 text-sm">
            <input name="is_primary" type="checkbox" value="1" />
            أساسية
          </label>
          <textarea name="notes" rows={2} placeholder="ملاحظات" className={`${fieldClass} sm:col-span-2`} />
          <div className="flex gap-2 sm:col-span-2">
            <button type="submit" disabled={saving} className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white disabled:opacity-60">
              حفظ
            </button>
            <button type="button" className="min-h-11 rounded-xl border px-4 text-sm" onClick={() => setCreating(false)}>
              إلغاء
            </button>
          </div>
        </form>
      ) : null}

      {loading ? <DashboardPanelSkeleton label="جاري تحميل جهات الاتصال..." /> : null}
      {error ? <DashboardErrorState message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && items.length === 0 ? (
        <DashboardEmptyState title="لا جهات اتصال." description="أضف جهة اتصال مرتبطة بشركة." />
      ) : null}

      {!loading && !error && items.length > 0 ? (
        <>
          <div className="overflow-x-auto rounded-2xl border bg-white">
            <table className="min-w-full text-sm">
              <thead className="bg-slate-50 text-right">
                <tr>
                  <th className="px-3 py-2">الاسم</th>
                  <th className="px-3 py-2">الشركة</th>
                  <th className="px-3 py-2">التواصل</th>
                  <th className="px-3 py-2">المسمى</th>
                </tr>
              </thead>
              <tbody>
                {items.map((contact) => (
                  <tr key={contact.id} className="border-t">
                    <td className="px-3 py-2 font-medium">
                      {contact.name}
                      {contact.is_primary ? <span className="mr-2 text-xs text-amber-700">أساسية</span> : null}
                    </td>
                    <td className="px-3 py-2">
                      {contact.company ? (
                        <Link to={`/crm/companies/${contact.company.id}`} className="hover:underline">
                          {contact.company.name}
                        </Link>
                      ) : (
                        '—'
                      )}
                    </td>
                    <td className="px-3 py-2" dir="ltr">
                      {contact.phone || contact.whatsapp || contact.email || '—'}
                    </td>
                    <td className="px-3 py-2">{contact.job_title || '—'}</td>
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
