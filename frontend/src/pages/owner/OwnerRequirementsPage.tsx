import { FormEvent, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import {
  downloadRequirementAttachment,
  getOwnerRequirement,
  getOwnerRequirements,
  updateOwnerRequirement,
  type RequirementItem,
} from '../../services/needsDiscovery'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

const STATUS_OPTIONS = [
  { value: '', label: 'كل الحالات' },
  { value: 'NEW', label: 'جديد' },
  { value: 'QUALIFIED', label: 'مؤهل' },
  { value: 'IN_PROGRESS', label: 'قيد المتابعة' },
  { value: 'CONVERTED', label: 'محوّل' },
  { value: 'CLOSED', label: 'مغلق' },
]

export function OwnerRequirementsPage() {
  const { id } = useParams()
  if (id) {
    return <OwnerRequirementDetailPage id={Number(id)} />
  }
  return <OwnerRequirementsListPage />
}

function OwnerRequirementsListPage() {
  const [items, setItems] = useState<RequirementItem[]>([])
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
  const [page, setPage] = useState(1)
  const [statusFilter, setStatusFilter] = useState('')
  const [query, setQuery] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  async function loadList() {
    setLoading(true)
    setError(null)
    try {
      const response = await getOwnerRequirements({
        page,
        per_page: 20,
        status: statusFilter || undefined,
        q: query.trim() || undefined,
      })
      setItems(response.data.items ?? [])
      setMeta(response.data.meta)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل الاحتياجات.'))
      setItems([])
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void loadList()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, statusFilter])

  function applyFilters(event?: FormEvent) {
    event?.preventDefault()
    setPage(1)
    void loadList()
  }

  return (
    <section className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold text-slate-900">اكتشف احتياجك</h1>
        <p className="mt-1 text-sm text-slate-600">طلبات الاحتياجات الواردة من ويدجت الموقع.</p>
      </header>

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      <form onSubmit={applyFilters} className="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-[1fr_12rem_auto]">
        <input
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder="بحث بالاسم أو الجوال أو المرجع…"
          className={fieldClass}
        />
        <select
          value={statusFilter}
          onChange={(event) => {
            setStatusFilter(event.target.value)
            setPage(1)
          }}
          className={fieldClass}
        >
          {STATUS_OPTIONS.map((option) => (
            <option key={option.value || 'all'} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
        <button type="submit" className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">
          تصفية
        </button>
      </form>

      <DashboardSection title="الاحتياجات" description={`${meta.total} طلب`}>
        {loading ? <DashboardPanelSkeleton label="جاري تحميل الاحتياجات" /> : null}
        {!loading && error ? <DashboardErrorState message={error} onRetry={() => void loadList()} /> : null}
        {!loading && !error && items.length === 0 ? (
          <DashboardEmptyState title="لا توجد طلبات بعد" description="ستظهر هنا طلبات ويدجت اكتشف احتياجك." />
        ) : null}
        {!loading && items.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="border-b border-slate-200 text-slate-500">
                <tr>
                  <th className="px-3 py-2 text-right font-medium">المرجع</th>
                  <th className="px-3 py-2 text-right font-medium">العميل</th>
                  <th className="px-3 py-2 text-right font-medium">الخدمة</th>
                  <th className="px-3 py-2 text-right font-medium">الميزانية</th>
                  <th className="px-3 py-2 text-right font-medium">الموعد</th>
                  <th className="px-3 py-2 text-right font-medium">الحالة</th>
                  <th className="px-3 py-2 text-right font-medium">المسؤول</th>
                </tr>
              </thead>
              <tbody>
                {items.map((item) => (
                  <tr key={item.id} className="border-b border-slate-100 hover:bg-slate-50">
                    <td className="px-3 py-3">
                      <Link to={`/owner/requirements/${item.id}`} className="font-medium text-[#315CFF] underline">
                        {item.reference}
                      </Link>
                      <p className="mt-1 line-clamp-1 text-xs text-slate-500">{item.summary}</p>
                    </td>
                    <td className="px-3 py-3">
                      <p>{item.customer.name}</p>
                      <p className="text-xs text-slate-500">{item.customer.phone || item.customer.email}</p>
                    </td>
                    <td className="px-3 py-3">{item.service || '—'}</td>
                    <td className="px-3 py-3">{item.budget || '—'}</td>
                    <td className="px-3 py-3">{item.deadline || '—'}</td>
                    <td className="px-3 py-3">{item.status_label}</td>
                    <td className="px-3 py-3">{item.assigned_team_member?.name || '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : null}

        {meta.last_page > 1 ? (
          <div className="mt-4 flex items-center justify-between gap-3 text-sm">
            <button
              type="button"
              disabled={page <= 1}
              onClick={() => setPage((prev) => Math.max(1, prev - 1))}
              className="rounded-lg border border-slate-300 px-3 py-1.5 disabled:opacity-40"
            >
              السابق
            </button>
            <span>
              صفحة {meta.current_page} من {meta.last_page}
            </span>
            <button
              type="button"
              disabled={page >= meta.last_page}
              onClick={() => setPage((prev) => prev + 1)}
              className="rounded-lg border border-slate-300 px-3 py-1.5 disabled:opacity-40"
            >
              التالي
            </button>
          </div>
        ) : null}
      </DashboardSection>
    </section>
  )
}

function OwnerRequirementDetailPage({ id }: { id: number }) {
  const [item, setItem] = useState<RequirementItem | null>(null)
  const [notes, setNotes] = useState('')
  const [status, setStatus] = useState('NEW')
  const [assignedTo, setAssignedTo] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await getOwnerRequirement(id)
      setItem(response.data)
      setNotes(response.data.notes || '')
      setStatus(response.data.status)
      setAssignedTo(response.data.assigned_team_member ? String(response.data.assigned_team_member.id) : '')
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل الطلب.'))
      setItem(null)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])

  async function save(extra: { qualify?: boolean } = {}) {
    setSaving(true)
    setError(null)
    setMessage(null)
    try {
      const response = await updateOwnerRequirement(id, {
        notes,
        status,
        assigned_to: assignedTo ? Number(assignedTo) : null,
        ...extra,
      })
      setItem(response.data)
      setNotes(response.data.notes || '')
      setStatus(response.data.status)
      setAssignedTo(response.data.assigned_team_member ? String(response.data.assigned_team_member.id) : '')
      setMessage(extra.qualify ? 'تم تأهيل الطلب وإنشاء مهمة متابعة.' : 'تم حفظ التعديلات.')
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ التعديلات.'))
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return <DashboardPanelSkeleton label="جاري تحميل الطلب" />
  }

  if (!item) {
    return (
      <section className="space-y-4">
        <Link to="/owner/requirements" className="text-sm text-[#315CFF] underline">
          العودة
        </Link>
        <DashboardErrorState message={error || 'الطلب غير موجود.'} onRetry={() => void load()} />
      </section>
    )
  }

  return (
    <section className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <Link to="/owner/requirements" className="text-sm text-[#315CFF] underline">
            العودة للاحتياجات
          </Link>
          <h1 className="mt-2 text-2xl font-semibold text-slate-900">{item.reference}</h1>
          <p className="mt-1 text-sm text-slate-600">{item.summary}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            disabled={saving || item.qualified}
            onClick={() => void save({ qualify: true })}
            className="rounded-lg bg-[#315CFF] px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
          >
            تأهيل وإنشاء مهمة
          </button>
          <button
            type="button"
            disabled={saving}
            onClick={() => void save()}
            className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-800"
          >
            حفظ
          </button>
        </div>
      </header>

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      {message ? <FeedbackBanner kind="success">{message}</FeedbackBanner> : null}

      <div className="grid gap-4 lg:grid-cols-2">
        <DashboardSection title="العميل">
          <dl className="space-y-2 text-sm">
            <div>
              <dt className="text-slate-500">الاسم</dt>
              <dd>{item.customer.name}</dd>
            </div>
            <div>
              <dt className="text-slate-500">الجوال</dt>
              <dd dir="ltr">{item.customer.phone || '—'}</dd>
            </div>
            <div>
              <dt className="text-slate-500">البريد</dt>
              <dd dir="ltr">{item.customer.email || '—'}</dd>
            </div>
            <div>
              <dt className="text-slate-500">الشركة</dt>
              <dd>{item.customer.company || '—'}</dd>
            </div>
            {item.crm_lead ? (
              <div>
                <dt className="text-slate-500">CRM</dt>
                <dd>
                  <Link to={`/crm/leads/${item.crm_lead.id}`} className="text-[#315CFF] underline">
                    {item.crm_lead.reference}
                  </Link>
                </dd>
              </div>
            ) : null}
          </dl>
        </DashboardSection>

        <DashboardSection title="تفاصيل الاحتياج">
          <dl className="space-y-2 text-sm">
            <div>
              <dt className="text-slate-500">الخدمة</dt>
              <dd>{item.service || '—'}</dd>
            </div>
            <div>
              <dt className="text-slate-500">التصنيف</dt>
              <dd>{item.category || '—'}</dd>
            </div>
            <div>
              <dt className="text-slate-500">الميزانية</dt>
              <dd>{item.budget || '—'}</dd>
            </div>
            <div>
              <dt className="text-slate-500">الموعد</dt>
              <dd>{item.deadline || '—'}</dd>
            </div>
            <div>
              <dt className="text-slate-500">الوصف</dt>
              <dd className="whitespace-pre-wrap">{item.description || '—'}</dd>
            </div>
          </dl>
        </DashboardSection>

        <DashboardSection title="المرفقات والخدمات المقترحة">
          {item.attachments.length === 0 ? (
            <p className="text-sm text-slate-500">لا توجد مرفقات.</p>
          ) : (
            <ul className="space-y-2 text-sm">
              {item.attachments.map((file) => (
                <li key={file.index}>
                  <button
                    type="button"
                    className="text-[#315CFF] underline"
                    onClick={() => void downloadRequirementAttachment(item.id, file.index, file.original_name)}
                  >
                    {file.original_name}
                  </button>
                </li>
              ))}
            </ul>
          )}
          {item.recommended_services.length ? (
            <ul className="mt-4 list-disc space-y-1 pr-4 text-sm text-slate-700">
              {item.recommended_services.map((service) => (
                <li key={service.id}>{service.name}</li>
              ))}
            </ul>
          ) : null}
        </DashboardSection>

        <DashboardSection title="المتابعة">
          <div className="space-y-3">
            <label className="block text-sm">
              <span className="mb-1 block text-slate-600">الحالة</span>
              <select value={status} onChange={(event) => setStatus(event.target.value)} className={fieldClass}>
                {STATUS_OPTIONS.filter((option) => option.value).map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-sm">
              <span className="mb-1 block text-slate-600">معرّف عضو الفريق المعيّن</span>
              <input
                value={assignedTo}
                onChange={(event) => setAssignedTo(event.target.value)}
                placeholder="معرّف المستخدم"
                className={fieldClass}
                inputMode="numeric"
              />
            </label>
            <label className="block text-sm">
              <span className="mb-1 block text-slate-600">ملاحظات</span>
              <textarea
                value={notes}
                onChange={(event) => setNotes(event.target.value)}
                rows={5}
                className={fieldClass}
              />
            </label>
            {item.task ? (
              <p className="text-sm text-slate-600">
                مهمة مرتبطة: <span className="font-medium text-slate-900">{item.task.title}</span>
              </p>
            ) : null}
            <p className="text-sm text-slate-600">
              المسؤول الحالي: {item.assigned_team_member?.name || 'غير معيّن'}
            </p>
          </div>
        </DashboardSection>
      </div>
    </section>
  )
}
