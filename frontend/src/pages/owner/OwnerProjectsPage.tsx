import { FormEvent, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useToast } from '../../context/ToastContext'
import { getOperationsProjects, type OperationsProjectListItem } from '../../services/operations'
import {
  buildCreateWorkspaceProjectPayload,
  createWorkspaceProject,
  getProjectAccountManagers,
  getProjectCustomers,
} from '../../services/workspaceProjects'
import { describeApiError } from '../../utils/errors'
import { PROJECT_STATUS_LABELS } from '../../utils/workspaceProjects'

const fieldClass =
  'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

type Option = { id: number; name: string }

function healthBadge(status: string, label: string) {
  const tone =
    status === 'overdue'
      ? 'border-red-300 bg-red-50 text-red-900'
      : status === 'needs_attention'
        ? 'border-amber-300 bg-amber-50 text-amber-900'
        : 'border-slate-200 bg-slate-50 text-slate-700'
  return (
    <span className={`rounded-full border px-2 py-0.5 text-xs ${tone}`}>{label || status}</span>
  )
}

export function OwnerProjectsPage() {
  const toast = useToast()
  const [items, setItems] = useState<OperationsProjectListItem[]>([])
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [formOpen, setFormOpen] = useState(false)

  async function load(nextPage = page) {
    setLoading(true)
    setError(null)
    try {
      const response = await getOperationsProjects(nextPage)
      setItems(response.data.items ?? [])
      setLastPage(response.data.meta?.last_page ?? 1)
      setPage(response.data.meta?.current_page ?? nextPage)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل المشاريع.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load(1)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  return (
    <section className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">المشاريع</h1>
          <p className="mt-1 text-sm text-slate-600">مساحة عمل المشاريع مع صحة التقدم والمهام.</p>
        </div>
        <button
          type="button"
          onClick={() => {
            setNotice(null)
            setFormOpen((open) => !open)
          }}
          className="inline-flex min-h-11 items-center rounded-lg bg-slate-900 px-4 text-sm font-medium text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
        >
          {formOpen ? 'إغلاق النموذج' : 'إضافة مشروع'}
        </button>
      </header>

      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      {formOpen ? (
        <OwnerCreateProjectForm
          onCancel={() => setFormOpen(false)}
          onCreated={async () => {
            setFormOpen(false)
            setNotice('تم إنشاء المشروع بنجاح.')
            toast.success('تم إنشاء المشروع بنجاح.')
            await load(1)
          }}
        />
      ) : null}

      {loading ? <DashboardPanelSkeleton label="جاري تحميل المشاريع..." /> : null}
      {!loading && items.length === 0 && !error ? (
        <DashboardEmptyState title="لا توجد مشاريع." description="أنشئ مشروعاً وعيّن مدير حساب وعميلًا نشطًا." />
      ) : null}
      {!loading && error && items.length === 0 ? (
        <DashboardErrorState message={error} onRetry={() => void load(page)} />
      ) : null}

      {!loading && items.length > 0 ? (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {items.map((project) => (
            <Link
              key={project.id}
              to={`/owner/projects/${project.id}`}
              className="rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-amber-300"
            >
              <div className="flex items-start justify-between gap-2">
                <h2 className="font-semibold text-slate-900">{project.title}</h2>
                {healthBadge(project.health?.status ?? 'on_track', project.health?.label ?? 'على المسار')}
              </div>
              <p className="mt-2 text-xs text-slate-500">
                {project.customer?.name ?? 'بدون عميل'}
                {project.account_manager ? ` · ${project.account_manager.name}` : ''}
              </p>
              <div className="mt-3">
                <div className="mb-1 flex justify-between text-xs text-slate-500">
                  <span>التقدم</span>
                  <span>{Math.round(project.progress ?? 0).toLocaleString('ar-SA')}%</span>
                </div>
                <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                  <div
                    className="h-full rounded-full bg-amber-500/80"
                    style={{ width: `${Math.min(100, Math.max(0, project.progress ?? 0))}%` }}
                  />
                </div>
              </div>
              <p className="mt-2 text-xs text-slate-500">
                الموعد: {project.deadline ?? '—'} · الحالة: {project.status}
              </p>
            </Link>
          ))}
        </div>
      ) : null}

      {lastPage > 1 ? (
        <div className="flex items-center justify-center gap-2">
          <button
            type="button"
            disabled={page <= 1 || loading}
            className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm disabled:opacity-50"
            onClick={() => void load(page - 1)}
          >
            السابق
          </button>
          <span className="text-sm text-slate-600">
            {page.toLocaleString('ar-SA')} / {lastPage.toLocaleString('ar-SA')}
          </span>
          <button
            type="button"
            disabled={page >= lastPage || loading}
            className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm disabled:opacity-50"
            onClick={() => void load(page + 1)}
          >
            التالي
          </button>
        </div>
      ) : null}
    </section>
  )
}

function OwnerCreateProjectForm({
  onCancel,
  onCreated,
}: {
  onCancel: () => void
  onCreated: () => Promise<void>
}) {
  const [customers, setCustomers] = useState<Option[]>([])
  const [managers, setManagers] = useState<Option[]>([])
  const [optionsLoading, setOptionsLoading] = useState(true)
  const [optionsError, setOptionsError] = useState<string | null>(null)
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [customerId, setCustomerId] = useState('')
  const [accountManagerId, setAccountManagerId] = useState('')
  const [status, setStatus] = useState('')
  const [startedAt, setStartedAt] = useState('')
  const [deadline, setDeadline] = useState('')
  const [formError, setFormError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  async function loadOptions() {
    setOptionsLoading(true)
    setOptionsError(null)
    try {
      const [customersResponse, managersResponse] = await Promise.all([
        getProjectCustomers(),
        getProjectAccountManagers(),
      ])
      setCustomers(
        (customersResponse.data ?? []).map((row) => ({
          id: row.id,
          name: row.name,
        })),
      )
      setManagers(managersResponse.data ?? [])
    } catch (caught) {
      setOptionsError(describeApiError(caught, 'تعذر تحميل العملاء أو مديري الحساب.'))
    } finally {
      setOptionsLoading(false)
    }
  }

  useEffect(() => {
    void loadOptions()
  }, [])

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (saving) {
      return
    }

    setFormError(null)
    setSaving(true)

    try {
      await createWorkspaceProject(
        buildCreateWorkspaceProjectPayload({
          title,
          description,
          customerId,
          accountManagerId,
          status,
          startedAt,
          deadline,
        }),
      )
      await onCreated()
    } catch (caught) {
      setFormError(describeApiError(caught, 'تعذر إنشاء المشروع.'))
    } finally {
      setSaving(false)
    }
  }

  return (
    <form
      onSubmit={(event) => void onSubmit(event)}
      className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
    >
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-lg font-semibold text-slate-900">إنشاء مشروع</h2>
        <button
          type="button"
          onClick={onCancel}
          className="text-sm underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
        >
          إلغاء
        </button>
      </div>

      {optionsLoading ? <p className="text-sm text-slate-500">جاري تحميل الخيارات...</p> : null}
      {optionsError ? (
        <div className="space-y-2">
          <FeedbackBanner kind="error">{optionsError}</FeedbackBanner>
          <button
            type="button"
            onClick={() => void loadOptions()}
            className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm"
          >
            إعادة المحاولة
          </button>
        </div>
      ) : null}
      {formError ? <FeedbackBanner kind="error">{formError}</FeedbackBanner> : null}

      <label className="block text-sm">
        اسم المشروع
        <input
          required
          value={title}
          onChange={(event) => setTitle(event.target.value)}
          className={fieldClass}
          disabled={optionsLoading || Boolean(optionsError)}
        />
      </label>
      <label className="block text-sm">
        الوصف
        <textarea
          value={description}
          onChange={(event) => setDescription(event.target.value)}
          className={fieldClass}
          rows={3}
          disabled={optionsLoading || Boolean(optionsError)}
        />
      </label>
      <div className="grid gap-4 md:grid-cols-2">
        <label className="block text-sm">
          العميل
          <select
            required
            aria-label="عميل المشروع"
            value={customerId}
            onChange={(event) => setCustomerId(event.target.value)}
            className={fieldClass}
            disabled={optionsLoading || Boolean(optionsError)}
          >
            <option value="">{customers.length === 0 ? 'لا يوجد عملاء' : 'اختر عميلاً'}</option>
            {customers.map((customer) => (
              <option key={customer.id} value={customer.id}>
                {customer.name}
              </option>
            ))}
          </select>
        </label>
        <label className="block text-sm">
          مدير الحساب
          <select
            required
            aria-label="مدير الحساب"
            value={accountManagerId}
            onChange={(event) => setAccountManagerId(event.target.value)}
            className={fieldClass}
            disabled={optionsLoading || Boolean(optionsError)}
          >
            <option value="">{managers.length === 0 ? 'لا يوجد مديرو حساب' : 'اختر مدير حساب'}</option>
            {managers.map((manager) => (
              <option key={manager.id} value={manager.id}>
                {manager.name}
              </option>
            ))}
          </select>
        </label>
      </div>
      <label className="block text-sm">
        الحالة
        <select
          aria-label="حالة المشروع"
          value={status}
          onChange={(event) => setStatus(event.target.value)}
          className={fieldClass}
          disabled={optionsLoading || Boolean(optionsError)}
        >
          <option value="">الافتراضي (تخطيط)</option>
          {Object.entries(PROJECT_STATUS_LABELS).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>
      </label>
      <div className="grid gap-4 md:grid-cols-2">
        <label className="block text-sm">
          تاريخ البدء
          <input
            type="date"
            value={startedAt}
            onChange={(event) => setStartedAt(event.target.value)}
            className={fieldClass}
            disabled={optionsLoading || Boolean(optionsError)}
          />
        </label>
        <label className="block text-sm">
          الموعد النهائي
          <input
            type="date"
            value={deadline}
            onChange={(event) => setDeadline(event.target.value)}
            className={fieldClass}
            disabled={optionsLoading || Boolean(optionsError)}
          />
        </label>
      </div>
      <button
        type="submit"
        disabled={saving || optionsLoading || Boolean(optionsError) || customers.length === 0 || managers.length === 0}
        className="rounded-lg bg-slate-900 px-4 py-2.5 text-white disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
      >
        {saving ? 'جاري الحفظ...' : 'إنشاء المشروع'}
      </button>
    </form>
  )
}
