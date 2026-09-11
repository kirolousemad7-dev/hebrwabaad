import { FormEvent, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
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
  archiveCrmCompany,
  createCrmContact,
  getCrmCompany,
  updateCrmCompany,
  type CrmCompany,
} from '../../services/crm'
import { formatMoney } from '../../utils/catalog'
import { crmStatusLabel } from '../../utils/crmNav'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

export function CrmCompanyDetailPage() {
  const { id } = useParams()
  const companyId = Number(id)
  const toast = useToast()
  const [company, setCompany] = useState<CrmCompany | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [editing, setEditing] = useState(false)
  const [addingContact, setAddingContact] = useState(false)
  const [busy, setBusy] = useState(false)
  const [actionError, setActionError] = useState<string | null>(null)

  async function load() {
    if (!Number.isFinite(companyId) || companyId <= 0) {
      setError('معرّف غير صالح.')
      setLoading(false)
      return
    }
    setLoading(true)
    setError(null)
    try {
      const response = await getCrmCompany(companyId)
      setCompany(response.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل الشركة.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [companyId])

  async function handleUpdate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!company || busy) return
    const form = new FormData(event.currentTarget)
    setBusy(true)
    setActionError(null)
    try {
      const response = await updateCrmCompany(company.id, {
        name: String(form.get('name') || '').trim(),
        industry: String(form.get('industry') || '').trim() || null,
        phone: String(form.get('phone') || '').trim() || null,
        email: String(form.get('email') || '').trim() || null,
        city: String(form.get('city') || '').trim() || null,
        website: String(form.get('website') || '').trim() || null,
        notes: String(form.get('notes') || '').trim() || null,
      })
      setCompany(response.data)
      setEditing(false)
      toast.success('تم تحديث الشركة.')
    } catch (caught) {
      setActionError(describeApiError(caught, 'تعذر التحديث.'))
    } finally {
      setBusy(false)
    }
  }

  async function handleAddContact(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!company || busy) return
    const form = new FormData(event.currentTarget)
    setBusy(true)
    setActionError(null)
    try {
      await createCrmContact({
        company_id: company.id,
        name: String(form.get('name') || '').trim(),
        phone: String(form.get('phone') || '').trim() || undefined,
        email: String(form.get('email') || '').trim() || undefined,
        job_title: String(form.get('job_title') || '').trim() || undefined,
        is_primary: form.get('is_primary') === '1',
      })
      setAddingContact(false)
      toast.success('تمت إضافة جهة الاتصال.')
      await load()
    } catch (caught) {
      setActionError(describeApiError(caught, 'تعذر إضافة جهة الاتصال.'))
    } finally {
      setBusy(false)
    }
  }

  async function handleArchive() {
    if (!company || busy) return
    setBusy(true)
    setActionError(null)
    try {
      const response = await archiveCrmCompany(company.id)
      setCompany(response.data)
      toast.success('تم أرشفة الشركة.')
    } catch (caught) {
      setActionError(describeApiError(caught, 'تعذر الأرشفة.'))
    } finally {
      setBusy(false)
    }
  }

  if (loading) return <DashboardPanelSkeleton label="جاري تحميل الشركة..." />
  if (error) return <DashboardErrorState message={error} onRetry={() => void load()} />
  if (!company) return <DashboardEmptyState title="غير موجود" description="لم يتم العثور على الشركة." />

  return (
    <section className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-2">
          <Link to="/crm/companies" className="text-sm text-slate-600 hover:underline">
            ← العودة للشركات
          </Link>
          <h1 className="text-2xl font-semibold">{company.name}</h1>
          <div className="flex flex-wrap gap-2">
            <StatusBadge status={company.status} label={company.status === 'active' ? 'نشطة' : 'مؤرشفة'} />
            {company.industry ? <StatusBadge status="industry" label={company.industry} tone="progress" /> : null}
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          <button type="button" className="min-h-11 rounded-xl border px-4 text-sm" onClick={() => setEditing(true)}>
            تعديل
          </button>
          <button type="button" className="min-h-11 rounded-xl border px-4 text-sm" onClick={() => setAddingContact(true)}>
            جهة اتصال
          </button>
          {company.status === 'active' ? (
            <button type="button" disabled={busy} className="min-h-11 rounded-xl border border-amber-300 px-4 text-sm text-amber-900" onClick={() => void handleArchive()}>
              أرشفة
            </button>
          ) : null}
        </div>
      </div>

      {actionError ? <FeedbackBanner kind="error">{actionError}</FeedbackBanner> : null}

      {editing ? (
        <form onSubmit={(event) => void handleUpdate(event)} className="grid gap-3 rounded-2xl border border-amber-200 bg-amber-50/40 p-4 sm:grid-cols-2">
          <input required name="name" defaultValue={company.name} className={fieldClass} />
          <input name="industry" defaultValue={company.industry ?? ''} placeholder="القطاع" className={fieldClass} />
          <input name="phone" defaultValue={company.phone ?? ''} placeholder="الهاتف" dir="ltr" className={fieldClass} />
          <input name="email" defaultValue={company.email ?? ''} placeholder="البريد" dir="ltr" className={fieldClass} />
          <input name="city" defaultValue={company.city ?? ''} placeholder="المدينة" className={fieldClass} />
          <input name="website" defaultValue={company.website ?? ''} placeholder="الموقع" dir="ltr" className={fieldClass} />
          <textarea name="notes" rows={2} defaultValue={company.notes ?? ''} className={`${fieldClass} sm:col-span-2`} />
          <div className="flex gap-2 sm:col-span-2">
            <button type="submit" disabled={busy} className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white disabled:opacity-60">
              حفظ
            </button>
            <button type="button" className="min-h-11 rounded-xl border px-4 text-sm" onClick={() => setEditing(false)}>
              إلغاء
            </button>
          </div>
        </form>
      ) : null}

      {addingContact ? (
        <form onSubmit={(event) => void handleAddContact(event)} className="grid gap-3 rounded-2xl border border-amber-200 bg-amber-50/40 p-4 sm:grid-cols-2">
          <input required name="name" placeholder="الاسم *" className={fieldClass} />
          <input name="job_title" placeholder="المسمى الوظيفي" className={fieldClass} />
          <input name="phone" placeholder="الهاتف" dir="ltr" className={fieldClass} />
          <input name="email" type="email" placeholder="البريد" dir="ltr" className={fieldClass} />
          <label className="flex items-center gap-2 text-sm sm:col-span-2">
            <input name="is_primary" type="checkbox" value="1" />
            جهة اتصال أساسية
          </label>
          <div className="flex gap-2 sm:col-span-2">
            <button type="submit" disabled={busy} className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white disabled:opacity-60">
              إضافة
            </button>
            <button type="button" className="min-h-11 rounded-xl border px-4 text-sm" onClick={() => setAddingContact(false)}>
              إلغاء
            </button>
          </div>
        </form>
      ) : null}

      <div className="grid gap-4 lg:grid-cols-3">
        <div className="space-y-2 rounded-2xl border bg-white p-4 text-sm">
          <h2 className="font-semibold">الملف</h2>
          <p>الهاتف: <span dir="ltr">{company.phone || '—'}</span></p>
          <p>البريد: <span dir="ltr">{company.email || '—'}</span></p>
          <p>الموقع: <span dir="ltr">{company.website || '—'}</span></p>
          <p>المدينة: {company.city || '—'}{company.country ? ` · ${company.country}` : ''}</p>
          <p>المكلّف: {company.assignee?.name || '—'}</p>
          {company.notes ? <p className="whitespace-pre-wrap text-slate-700">{company.notes}</p> : null}
        </div>

        <div className="space-y-4 lg:col-span-2">
          <DashboardSection title="جهات الاتصال">
            {(company.contacts ?? []).length === 0 ? (
              <DashboardEmptyState title="لا جهات اتصال." description="أضف جهة اتصال لهذه الشركة." />
            ) : (
              <ul className="divide-y divide-slate-100 rounded-2xl border bg-white">
                {(company.contacts ?? []).map((contact) => (
                  <li key={contact.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                    <div>
                      <p className="font-medium">
                        {contact.name}
                        {contact.is_primary ? <span className="mr-2 text-xs text-amber-700">أساسية</span> : null}
                      </p>
                      <p className="text-xs text-slate-500">
                        {contact.job_title || '—'} · <span dir="ltr">{contact.phone || contact.email || '—'}</span>
                      </p>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </DashboardSection>

          <DashboardSection title="العملاء المحتملون">
            {(company.leads ?? []).length === 0 ? (
              <DashboardEmptyState title="لا عملاء مرتبطين." description="اربط عميلاً محتملاً بهذه الشركة." />
            ) : (
              <ul className="divide-y divide-slate-100 rounded-2xl border bg-white">
                {(company.leads ?? []).map((lead) => (
                  <li key={lead.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                    <Link to={`/crm/leads/${lead.id}`} className="font-medium hover:underline">
                      {lead.full_name}
                    </Link>
                    <div className="flex items-center gap-2">
                      <StatusBadge status={lead.status} label={crmStatusLabel(lead.status)} />
                      <span>{lead.deal_value != null ? formatMoney(lead.deal_value) : '—'}</span>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </DashboardSection>
        </div>
      </div>
    </section>
  )
}
