import { useEffect, useState } from 'react'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { getCalendarAssignees, type CalendarAssignee } from '../../services/calendar'
import {
  activateAutomation,
  createAutomation,
  deactivateAutomation,
  dryRunAutomation,
  getAutomationRuns,
  getAutomations,
  seedAutomationTemplates,
  type AutomationDryRunResult,
  type WorkflowAutomation,
  type WorkflowAutomationRun,
} from '../../services/operations'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

const TRIGGER_OPTIONS = [
  { value: 'order.created', label: 'عند إنشاء طلب' },
  { value: 'order.status_changed', label: 'عند تغيير حالة طلب' },
  { value: 'crm.opportunity.won', label: 'عند ربح فرصة CRM' },
  { value: 'crm.lead.created', label: 'عند إنشاء عميل محتمل' },
  { value: 'calendar.task.overdue', label: 'مهمة تقويم متأخرة' },
  { value: 'calendar.task.created', label: 'عند إنشاء مهمة تقويم' },
  { value: 'calendar.task.completed', label: 'عند إكمال مهمة تقويم' },
  { value: 'project.deadline_approaching', label: 'اقتراب موعد مشروع' },
  { value: 'project.status_changed', label: 'عند تغيير حالة مشروع' },
  { value: 'crm.quotation.expiring', label: 'انتهاء صلاحية عرض سعر' },
  { value: 'printing.required_date_approaching', label: 'اقتراب موعد طباعة مطلوب' },
  { value: 'approval.approved', label: 'عند اعتماد موافقة' },
  { value: 'approval.rejected', label: 'عند رفض موافقة' },
] as const

const ACTION_OPTIONS = [
  { value: 'create_task', label: 'إنشاء مهمة' },
  { value: 'create_reminder', label: 'إنشاء تذكير' },
  { value: 'create_event', label: 'إنشاء حدث' },
  { value: 'notify_user', label: 'إشعار مستخدم' },
] as const

const DUE_OFFSET_OPTIONS = [
  { value: '1', label: 'بعد ساعة' },
  { value: '24', label: 'بعد يوم' },
  { value: '48', label: 'بعد يومين' },
  { value: '168', label: 'بعد أسبوع' },
] as const

function triggerLabel(value: string): string {
  return TRIGGER_OPTIONS.find((entry) => entry.value === value)?.label ?? value
}

function actionLabel(value: string): string {
  return ACTION_OPTIONS.find((entry) => entry.value === value)?.label ?? value
}

function runStatusLabel(status: string): string {
  if (status === 'success') return 'نجح'
  if (status === 'skipped') return 'تم التجاوز'
  if (status === 'failed') return 'فشل'
  return status
}

function runStatusClass(status: string): string {
  if (status === 'success') return 'text-emerald-800'
  if (status === 'failed') return 'text-red-800'
  return 'text-slate-600'
}

export function OwnerAutomationsPage() {
  const [items, setItems] = useState<WorkflowAutomation[]>([])
  const [assignees, setAssignees] = useState<CalendarAssignee[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [showForm, setShowForm] = useState(false)
  const [name, setName] = useState('')
  const [trigger, setTrigger] = useState<string>(TRIGGER_OPTIONS[0].value)
  const [condition, setCondition] = useState('')
  const [actionType, setActionType] = useState<string>(ACTION_OPTIONS[0].value)
  const [actionTitle, setActionTitle] = useState('')
  const [assigneeId, setAssigneeId] = useState('')
  const [dueOffset, setDueOffset] = useState('24')
  const [saving, setSaving] = useState(false)
  const [runsFor, setRunsFor] = useState<WorkflowAutomation | null>(null)
  const [runs, setRuns] = useState<WorkflowAutomationRun[]>([])
  const [runsLoading, setRunsLoading] = useState(false)
  const [dryRunFor, setDryRunFor] = useState<WorkflowAutomation | null>(null)
  const [dryRunResult, setDryRunResult] = useState<AutomationDryRunResult | null>(null)
  const [dryRunLoading, setDryRunLoading] = useState(false)
  const [activateConfirm, setActivateConfirm] = useState<WorkflowAutomation | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await getAutomations()
      setItems(response.data.items ?? [])
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل الأتمتة.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
    void getCalendarAssignees()
      .then((response) => setAssignees(response.data.items ?? []))
      .catch(() => setAssignees([]))
  }, [])

  async function toggleActive(automation: WorkflowAutomation) {
    if (!automation.is_active) {
      setActivateConfirm(automation)
      return
    }

    setBusyId(automation.id)
    setError(null)
    try {
      await deactivateAutomation(automation.id)
      setNotice('تم إيقاف الأتمتة.')
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحديث حالة الأتمتة.'))
    } finally {
      setBusyId(null)
    }
  }

  async function confirmActivate() {
    if (!activateConfirm) return
    setBusyId(activateConfirm.id)
    setError(null)
    try {
      await activateAutomation(activateConfirm.id)
      setNotice('تم تفعيل الأتمتة.')
      setActivateConfirm(null)
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تفعيل الأتمتة.'))
    } finally {
      setBusyId(null)
    }
  }

  async function openDryRun(automation: WorkflowAutomation) {
    setDryRunFor(automation)
    setDryRunResult(null)
    setDryRunLoading(true)
    try {
      const response = await dryRunAutomation(automation.id)
      setDryRunResult(response.data)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تشغيل المعاينة.'))
      setDryRunFor(null)
    } finally {
      setDryRunLoading(false)
    }
  }

  async function handleSeed() {
    setError(null)
    try {
      await seedAutomationTemplates()
      setNotice('تم إضافة القوالب.')
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إضافة القوالب.'))
    }
  }

  async function handleCreate() {
    if (saving) return
    if (!name.trim() || !actionTitle.trim()) {
      setError('العنوان وعنوان الإجراء مطلوبان.')
      return
    }

    setSaving(true)
    setError(null)
    try {
      const hours = Number(dueOffset) || 24
      const startsAt = new Date(Date.now() + hours * 60 * 60 * 1000).toISOString()
      const action: Record<string, unknown> = {
        type: actionType,
        title: actionTitle.trim(),
        due_offset_hours: hours,
        starts_at: startsAt,
      }
      if (assigneeId) {
        action.assignee_ids = [Number(assigneeId)]
        if (actionType === 'notify_user') {
          action.user_id = Number(assigneeId)
        }
      }

      await createAutomation({
        name: name.trim(),
        trigger,
        conditions: condition.trim()
          ? [{ field: 'note', op: 'contains', value: condition.trim() }]
          : [],
        actions: [action],
        is_active: false,
      })
      setNotice('تم إنشاء الأتمتة.')
      setShowForm(false)
      setName('')
      setCondition('')
      setActionTitle('')
      setAssigneeId('')
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إنشاء الأتمتة.'))
    } finally {
      setSaving(false)
    }
  }

  async function openRuns(automation: WorkflowAutomation) {
    setRunsFor(automation)
    setRunsLoading(true)
    try {
      const response = await getAutomationRuns(automation.id)
      setRuns(response.data.items ?? [])
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل سجل التشغيل.'))
      setRuns([])
    } finally {
      setRunsLoading(false)
    }
  }

  return (
    <section className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">الأتمتة</h1>
          <p className="mt-1 text-sm text-slate-600">قواعد تشغيل تلقائية للمهام والتذكيرات.</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            onClick={() => void handleSeed()}
            className="min-h-11 rounded-xl border border-slate-300 px-4 text-sm"
          >
            إضافة قوالب
          </button>
          <button
            type="button"
            onClick={() => setShowForm((current) => !current)}
            className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm font-medium text-white"
          >
            أتمتة جديدة
          </button>
        </div>
      </header>

      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      {showForm ? (
        <DashboardSection title="إنشاء أتمتة">
          <div className="grid gap-3 sm:grid-cols-2">
            <label className="block text-sm">
              العنوان
              <input value={name} onChange={(event) => setName(event.target.value)} className={`mt-1 ${fieldClass}`} />
            </label>
            <label className="block text-sm">
              عندما (المشغّل)
              <select value={trigger} onChange={(event) => setTrigger(event.target.value)} className={`mt-1 ${fieldClass}`}>
                {TRIGGER_OPTIONS.map((entry) => (
                  <option key={entry.value} value={entry.value}>
                    {entry.label}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-sm sm:col-span-2">
              إذا (شرط اختياري)
              <input
                value={condition}
                onChange={(event) => setCondition(event.target.value)}
                placeholder="نص شرط بسيط"
                className={`mt-1 ${fieldClass}`}
              />
            </label>
            <label className="block text-sm">
              نفّذ (الإجراء)
              <select
                value={actionType}
                onChange={(event) => setActionType(event.target.value)}
                className={`mt-1 ${fieldClass}`}
              >
                {ACTION_OPTIONS.map((entry) => (
                  <option key={entry.value} value={entry.value}>
                    {entry.label}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-sm">
              عنوان الإجراء
              <input
                value={actionTitle}
                onChange={(event) => setActionTitle(event.target.value)}
                className={`mt-1 ${fieldClass}`}
              />
            </label>
            <label className="block text-sm">
              المعيّن
              <select
                value={assigneeId}
                onChange={(event) => setAssigneeId(event.target.value)}
                className={`mt-1 ${fieldClass}`}
              >
                <option value="">افتراضي</option>
                {assignees.map((row) => (
                  <option key={row.id} value={row.id}>
                    {row.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-sm">
              موعد الاستحقاق
              <select
                value={dueOffset}
                onChange={(event) => setDueOffset(event.target.value)}
                className={`mt-1 ${fieldClass}`}
              >
                {DUE_OFFSET_OPTIONS.map((entry) => (
                  <option key={entry.value} value={entry.value}>
                    {entry.label}
                  </option>
                ))}
              </select>
            </label>
          </div>
          <button
            type="button"
            disabled={saving}
            onClick={() => void handleCreate()}
            className="mt-4 min-h-10 rounded-lg bg-slate-900 px-4 text-sm text-white disabled:opacity-50"
          >
            حفظ
          </button>
        </DashboardSection>
      ) : null}

      {loading ? <DashboardPanelSkeleton label="جاري تحميل الأتمتة..." /> : null}
      {!loading && items.length === 0 && !error ? (
        <DashboardEmptyState title="لا توجد أتمتة." description="أنشئ قاعدة أو أضف القوالب الجاهزة." />
      ) : null}
      {!loading && error && items.length === 0 ? (
        <DashboardErrorState message={error} onRetry={() => void load()} />
      ) : null}

      {!loading && items.length > 0 ? (
        <div className="space-y-3">
          {items.map((automation) => {
            const firstAction = automation.actions[0]
            const actionTypeValue =
              firstAction && typeof firstAction.type === 'string' ? firstAction.type : '—'
            return (
              <article
                key={automation.id}
                className="rounded-2xl border border-slate-200 bg-white p-4"
              >
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <h2 className="font-semibold text-slate-900">
                      {automation.name}
                      {automation.is_template ? (
                        <span className="ms-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                          قالب
                        </span>
                      ) : null}
                    </h2>
                    <p className="mt-1 text-sm text-slate-600">
                      عندما: {triggerLabel(String(automation.trigger))} · نفّذ: {actionLabel(actionTypeValue)}
                    </p>
                    <p className="mt-1 text-xs text-slate-500">
                      آخر تشغيل: {automation.last_run_at ?? '—'}
                    </p>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <button
                      type="button"
                      disabled={busyId === automation.id}
                      onClick={() => void toggleActive(automation)}
                      className={`rounded-lg px-3 py-1.5 text-xs ${
                        automation.is_active
                          ? 'border border-amber-300 bg-amber-50 text-amber-900'
                          : 'border border-slate-300 bg-white text-slate-700'
                      }`}
                    >
                      {automation.is_active ? 'نشطة — إيقاف' : 'غير نشطة — تفعيل'}
                    </button>
                    <button
                      type="button"
                      onClick={() => void openDryRun(automation)}
                      className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                    >
                      معاينة
                    </button>
                    <button
                      type="button"
                      onClick={() => void openRuns(automation)}
                      className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                    >
                      سجل التشغيل
                    </button>
                  </div>
                </div>
              </article>
            )
          })}
        </div>
      ) : null}

      {runsFor ? (
        <div className="fixed inset-0 z-40 flex justify-end bg-slate-900/40">
          <aside className="h-full w-full max-w-md overflow-y-auto border-s border-slate-200 bg-white p-5 shadow-xl">
            <div className="flex items-start justify-between gap-3">
              <div>
                <h2 className="text-lg font-semibold">سجل التشغيل</h2>
                <p className="text-sm text-slate-600">{runsFor.name}</p>
              </div>
              <button
                type="button"
                className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm"
                onClick={() => setRunsFor(null)}
              >
                إغلاق
              </button>
            </div>
            {runsLoading ? <p className="mt-4 text-sm text-slate-500">جاري التحميل...</p> : null}
            {!runsLoading && runs.length === 0 ? (
              <p className="mt-4 text-sm text-slate-500">لا تشغيلات مسجّلة.</p>
            ) : null}
            <ul className="mt-4 space-y-2">
              {runs.map((run) => (
                <li key={run.id} className="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                  <p className={`font-medium ${runStatusClass(run.status)}`}>{runStatusLabel(run.status)}</p>
                  <p className="mt-1 text-xs text-slate-500">{run.executed_at ?? '—'}</p>
                </li>
              ))}
            </ul>
          </aside>
        </div>
      ) : null}

      {dryRunFor ? (
        <div className="fixed inset-0 z-40 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center">
          <div className="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5">
            <div className="flex items-start justify-between gap-3">
              <div>
                <h2 className="text-lg font-semibold">معاينة التشغيل</h2>
                <p className="text-sm text-slate-600">{dryRunFor.name}</p>
              </div>
              <button
                type="button"
                className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm"
                onClick={() => {
                  setDryRunFor(null)
                  setDryRunResult(null)
                }}
              >
                إغلاق
              </button>
            </div>
            {dryRunLoading ? <p className="mt-4 text-sm text-slate-500">جاري المعاينة...</p> : null}
            {dryRunResult ? (
              <div className="mt-4 space-y-3 text-sm">
                <p>
                  المشغّل: <span className="font-medium">{triggerLabel(dryRunResult.trigger)}</span>
                </p>
                <p>
                  الشروط:{' '}
                  <span className={dryRunResult.conditions_pass ? 'text-emerald-800' : 'text-red-800'}>
                    {dryRunResult.conditions_pass ? 'تمر' : 'لا تمر'}
                  </span>
                </p>
                {dryRunResult.would_skip ? (
                  <p className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-amber-900">
                    سيتم التجاوز: {dryRunResult.would_skip}
                  </p>
                ) : null}
                <div>
                  <p className="mb-1 font-medium text-slate-800">الإجراءات المخططة</p>
                  <ul className="space-y-1.5">
                    {dryRunResult.planned_actions.map((action, index) => (
                      <li key={`${action.type}-${index}`} className="rounded-xl border border-slate-200 px-3 py-2">
                        {'would_execute' in action && action.would_execute ? (
                          <span>
                            سينفَّذ: {actionLabel(action.type)}
                          </span>
                        ) : (
                          <span className="text-slate-500">
                            متجاوز: {action.type}
                            {'skipped' in action ? ` (${action.skipped})` : ''}
                          </span>
                        )}
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            ) : null}
          </div>
        </div>
      ) : null}

      {activateConfirm ? (
        <div className="fixed inset-0 z-40 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center">
          <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5">
            <h2 className="text-lg font-semibold">تأكيد التفعيل</h2>
            <p className="mt-2 text-sm text-slate-600">
              سيتم تفعيل «{activateConfirm.name}».
            </p>
            <ul className="mt-3 space-y-1 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-sm text-slate-700">
              <li>المشغّل: {triggerLabel(String(activateConfirm.trigger))}</li>
              <li>
                الشروط:{' '}
                {Array.isArray(activateConfirm.conditions)
                  ? activateConfirm.conditions.length === 0
                    ? 'بدون شروط'
                    : `${activateConfirm.conditions.length} شرط`
                  : 'مخصصة'}
              </li>
              <li>الإجراءات: {activateConfirm.actions.length.toLocaleString('ar-SA')}</li>
            </ul>
            <div className="mt-4 flex justify-end gap-2">
              <button
                type="button"
                className="rounded-lg border border-slate-300 px-4 py-2 text-sm"
                onClick={() => setActivateConfirm(null)}
              >
                إلغاء
              </button>
              <button
                type="button"
                className="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white"
                onClick={() => void confirmActivate()}
              >
                تأكيد التفعيل
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </section>
  )
}
