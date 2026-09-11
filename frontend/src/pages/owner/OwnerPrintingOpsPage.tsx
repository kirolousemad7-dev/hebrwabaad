import { Link } from 'react-router-dom'
import { useCallback, useEffect, useMemo, useState } from 'react'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { getCalendarAssignees, type CalendarAssignee } from '../../services/calendar'
import {
  assignPrintingRequest,
  exportOperationsCsv,
  getDepartmentOptions,
  getPrintingBoard,
  getPrintingHistory,
  getPrintingOperations,
  getPrintingSummary,
  PRINTING_OPS_STATUS_LABELS,
  PRINTING_STATUS_ORDER,
  updatePrintingStatus,
  type DepartmentOption,
  type PrintingOpsItem,
  type PrintingStatusHistoryItem,
  type PrintingSummaryCounts,
} from '../../services/operations'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

type OpsTab = 'overview' | 'board' | 'table' | 'overdue'

const TABS: Array<{ key: OpsTab; label: string }> = [
  { key: 'overview', label: 'نظرة عامة' },
  { key: 'board', label: 'اللوحة' },
  { key: 'table', label: 'الجدول' },
  { key: 'overdue', label: 'المتأخر' },
]

function statusLabel(status: string): string {
  return PRINTING_OPS_STATUS_LABELS[status] ?? status
}

function categoryTone(category: string): string {
  if (category === 'overdue') return 'border-red-300 bg-red-50/70'
  if (category === 'today') return 'border-amber-300 bg-amber-50/60'
  if (category === 'approaching') return 'border-slate-300 bg-slate-50'
  return 'border-slate-200 bg-white'
}

function PrintingCard({
  item,
  busyId,
  onTransition,
  onAssign,
}: {
  item: PrintingOpsItem
  busyId: number | null
  onTransition: (item: PrintingOpsItem, status: string) => void
  onAssign: (item: PrintingOpsItem) => void
}) {
  const transitions = item.allowed_transitions ?? []

  return (
    <li className={`rounded-xl border px-3 py-2.5 ${categoryTone(item.category)}`}>
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0 flex-1">
          <Link
            to={`/printing-requests/${item.id}`}
            className="font-medium text-slate-900 underline-offset-2 hover:underline"
          >
            {item.product_name}
          </Link>
          <p className="mt-0.5 text-[11px] text-slate-600">
            {item.user?.name ?? '—'} · {statusLabel(item.status)}
            {item.required_date ? ` · مطلوب ${item.required_date}` : ''}
          </p>
          {item.assigned_to ? (
            <p className="mt-0.5 text-[11px] text-slate-500">معيّن: {item.assigned_to.name}</p>
          ) : null}
        </div>
        <button
          type="button"
          onClick={() => onAssign(item)}
          className="rounded border border-slate-300 bg-white px-2 py-1 text-[11px]"
        >
          تعيين
        </button>
      </div>
      {transitions.length > 0 ? (
        <div className="mt-2 flex flex-wrap gap-1">
          {transitions.map((next) => (
            <button
              key={next}
              type="button"
              disabled={busyId === item.id}
              onClick={() => onTransition(item, next)}
              className="rounded-full border border-slate-300 bg-white px-2 py-0.5 text-[11px] disabled:opacity-50"
            >
              → {statusLabel(next)}
            </button>
          ))}
        </div>
      ) : null}
    </li>
  )
}

export function OwnerPrintingOpsPage() {
  const [tab, setTab] = useState<OpsTab>('overview')
  const [items, setItems] = useState<PrintingOpsItem[]>([])
  const [columns, setColumns] = useState<Record<string, PrintingOpsItem[]>>({})
  const [summary, setSummary] = useState<PrintingSummaryCounts | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [q, setQ] = useState('')
  const [busyId, setBusyId] = useState<number | null>(null)
  const [assignees, setAssignees] = useState<CalendarAssignee[]>([])
  const [departments, setDepartments] = useState<DepartmentOption[]>([])
  const [assignItem, setAssignItem] = useState<PrintingOpsItem | null>(null)
  const [assignUserId, setAssignUserId] = useState('')
  const [assignDeptId, setAssignDeptId] = useState('')
  const [assignSaving, setAssignSaving] = useState(false)
  const [historyItem, setHistoryItem] = useState<PrintingOpsItem | null>(null)
  const [history, setHistory] = useState<PrintingStatusHistoryItem[]>([])
  const [historyLoading, setHistoryLoading] = useState(false)
  const [exporting, setExporting] = useState(false)

  const loadLookups = useCallback(async () => {
    const [assigneeRes, deptRes] = await Promise.all([
      getCalendarAssignees().catch(() => ({ data: { items: [] as CalendarAssignee[] } })),
      getDepartmentOptions().catch(() => ({ data: { items: [] as DepartmentOption[] } })),
    ])
    setAssignees(assigneeRes.data.items ?? [])
    setDepartments(deptRes.data.items ?? [])
  }, [])

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      if (tab === 'board') {
        const [boardRes, summaryRes] = await Promise.all([getPrintingBoard(), getPrintingSummary()])
        setColumns(boardRes.data.columns ?? {})
        setSummary(summaryRes.data.summary ?? boardRes.data.summary ?? null)
        setItems([])
      } else {
        const category = tab === 'overdue' ? 'overdue' : 'all'
        const [listRes, summaryRes] = await Promise.all([
          getPrintingOperations({ category, q: q.trim() || undefined }),
          getPrintingSummary(),
        ])
        setItems(listRes.data.items ?? [])
        setSummary(summaryRes.data.summary ?? listRes.data.summary ?? null)
        setColumns({})
      }
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل تشغيل الطباعة.'))
      setItems([])
      setColumns({})
    } finally {
      setLoading(false)
    }
  }, [q, tab])

  useEffect(() => {
    void loadLookups()
  }, [loadLookups])

  useEffect(() => {
    void load()
  }, [load])

  const boardColumns = useMemo(
    () =>
      PRINTING_STATUS_ORDER.map((key) => ({
        key,
        label: statusLabel(key),
        items: columns[key] ?? [],
      })),
    [columns],
  )

  async function handleTransition(item: PrintingOpsItem, status: string) {
    setBusyId(item.id)
    setError(null)
    try {
      await updatePrintingStatus(item.id, status)
      setNotice(`تم تحديث الحالة إلى ${statusLabel(status)}.`)
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحديث حالة الطلب.'))
    } finally {
      setBusyId(null)
    }
  }

  function openAssign(item: PrintingOpsItem) {
    setAssignItem(item)
    setAssignUserId(item.assigned_to?.id != null ? String(item.assigned_to.id) : '')
    setAssignDeptId(item.assigned_department_id != null ? String(item.assigned_department_id) : '')
  }

  async function submitAssign() {
    if (!assignItem) return
    setAssignSaving(true)
    setError(null)
    try {
      await assignPrintingRequest(assignItem.id, {
        assigned_to: assignUserId ? Number(assignUserId) : null,
        assigned_department_id: assignDeptId ? Number(assignDeptId) : null,
      })
      setNotice('تم تحديث التعيين.')
      setAssignItem(null)
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تعيين الطلب.'))
    } finally {
      setAssignSaving(false)
    }
  }

  async function openHistory(item: PrintingOpsItem) {
    setHistoryItem(item)
    setHistoryLoading(true)
    try {
      const response = await getPrintingHistory(item.id)
      setHistory(response.data.items ?? [])
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل السجل.'))
      setHistory([])
    } finally {
      setHistoryLoading(false)
    }
  }

  async function handleExport() {
    setExporting(true)
    try {
      await exportOperationsCsv('printing')
      setNotice('تم تنزيل تصدير الطباعة.')
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تصدير CSV.'))
    } finally {
      setExporting(false)
    }
  }

  return (
    <section className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">تشغيل الطباعة</h1>
          <p className="mt-1 text-sm text-slate-600">متابعة الطلبات حسب الاستحقاق ودورة الحالة التشغيلية.</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Link
            to="/owner/printing-quotations"
            className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
          >
            عروض الأسعار
          </Link>
          <button
            type="button"
            disabled={exporting}
            onClick={() => void handleExport()}
            className="rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:opacity-50"
          >
            تصدير CSV
          </button>
        </div>
      </header>

      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      {summary ? (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
          {[
            { label: 'اليوم', value: summary.today },
            { label: 'قريب', value: summary.approaching },
            { label: 'متأخر', value: summary.overdue, danger: summary.overdue > 0 },
            { label: 'أخرى', value: summary.pending_other },
            { label: 'إجمالي معلّق', value: summary.total_pending },
          ].map((chip) => (
            <div
              key={chip.label}
              className={`rounded-2xl border px-3 py-2 ${
                chip.danger ? 'border-red-300 bg-red-50/70' : 'border-slate-200 bg-gradient-to-l from-amber-50/70 to-white'
              }`}
            >
              <p className="text-xs text-slate-500">{chip.label}</p>
              <p className={`text-xl font-semibold ${chip.danger ? 'text-red-900' : 'text-slate-900'}`}>
                {chip.value.toLocaleString('ar-SA')}
              </p>
            </div>
          ))}
        </div>
      ) : null}

      <div className="flex flex-wrap items-end gap-2">
        {TABS.map((entry) => (
          <button
            key={entry.key}
            type="button"
            onClick={() => setTab(entry.key)}
            className={`rounded-full px-3 py-1.5 text-sm ${
              tab === entry.key ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white'
            }`}
          >
            {entry.label}
          </button>
        ))}
        {tab === 'overview' || tab === 'table' || tab === 'overdue' ? (
          <div className="ms-auto flex gap-2">
            <input
              value={q}
              onChange={(event) => setQ(event.target.value)}
              placeholder="بحث..."
              className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
            />
            <button
              type="button"
              onClick={() => void load()}
              className="rounded-lg bg-slate-900 px-3 py-2 text-sm text-white"
            >
              بحث
            </button>
          </div>
        ) : null}
      </div>

      {loading ? <DashboardPanelSkeleton label="جاري تحميل طلبات الطباعة..." /> : null}
      {!loading && error && items.length === 0 && Object.keys(columns).length === 0 ? (
        <DashboardErrorState message={error} onRetry={() => void load()} />
      ) : null}

      {!loading && tab === 'board' ? (
        <div className="grid gap-3 overflow-x-auto md:grid-cols-2 xl:grid-cols-5">
          {boardColumns.map((column) => (
            <div key={column.key} className="min-w-[220px] rounded-2xl border border-slate-200 bg-slate-50/60 p-3">
              <h3 className="mb-2 text-sm font-semibold text-slate-800">
                {column.label}{' '}
                <span className="text-xs font-normal text-slate-500">
                  ({column.items.length.toLocaleString('ar-SA')})
                </span>
              </h3>
              {column.items.length === 0 ? (
                <p className="text-xs text-slate-500">لا عناصر.</p>
              ) : (
                <ul className="space-y-2">
                  {column.items.map((item) => (
                    <PrintingCard
                      key={item.id}
                      item={item}
                      busyId={busyId}
                      onTransition={handleTransition}
                      onAssign={openAssign}
                    />
                  ))}
                </ul>
              )}
            </div>
          ))}
        </div>
      ) : null}

      {!loading && (tab === 'overview' || tab === 'overdue') && items.length === 0 && !error ? (
        <DashboardEmptyState title="لا طلبات في هذا التصنيف." description="غيّر التبويب أو معايير البحث." />
      ) : null}

      {!loading && (tab === 'overview' || tab === 'overdue') && items.length > 0 ? (
        <DashboardSection title={tab === 'overdue' ? 'الطلبات المتأخرة' : 'الطلبات'}>
          <ul className="space-y-2">
            {items.map((item) => (
              <li key={item.id} className="space-y-1">
                <PrintingCard
                  item={item}
                  busyId={busyId}
                  onTransition={handleTransition}
                  onAssign={openAssign}
                />
                <button
                  type="button"
                  onClick={() => void openHistory(item)}
                  className="text-[11px] text-slate-500 underline"
                >
                  السجل
                </button>
              </li>
            ))}
          </ul>
        </DashboardSection>
      ) : null}

      {!loading && tab === 'table' ? (
        items.length === 0 && !error ? (
          <DashboardEmptyState title="لا طلبات." description="جرّب البحث أو التصنيف." />
        ) : (
          <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
            <table className="min-w-full text-sm">
              <thead className="border-b border-slate-100 bg-slate-50 text-xs text-slate-500">
                <tr>
                  <th className="px-3 py-2 text-start font-medium">المنتج</th>
                  <th className="px-3 py-2 text-start font-medium">الحالة</th>
                  <th className="px-3 py-2 text-start font-medium">المطلوب</th>
                  <th className="px-3 py-2 text-start font-medium">العميل</th>
                  <th className="px-3 py-2 text-start font-medium">التعيين</th>
                  <th className="px-3 py-2 text-start font-medium">إجراءات</th>
                </tr>
              </thead>
              <tbody>
                {items.map((item) => (
                  <tr key={item.id} className="border-b border-slate-50">
                    <td className="px-3 py-2">
                      <Link to={`/printing-requests/${item.id}`} className="underline">
                        {item.product_name}
                      </Link>
                    </td>
                    <td className="px-3 py-2">{statusLabel(item.status)}</td>
                    <td className="px-3 py-2">{item.required_date ?? '—'}</td>
                    <td className="px-3 py-2">{item.user?.name ?? '—'}</td>
                    <td className="px-3 py-2">{item.assigned_to?.name ?? '—'}</td>
                    <td className="px-3 py-2">
                      <div className="flex flex-wrap gap-1">
                        <button type="button" className="underline" onClick={() => openAssign(item)}>
                          تعيين
                        </button>
                        {(item.allowed_transitions ?? []).map((next) => (
                          <button
                            key={next}
                            type="button"
                            disabled={busyId === item.id}
                            className="underline disabled:opacity-50"
                            onClick={() => void handleTransition(item, next)}
                          >
                            {statusLabel(next)}
                          </button>
                        ))}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )
      ) : null}

      {assignItem ? (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4" role="dialog">
          <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-4 shadow-lg">
            <h2 className="text-lg font-semibold text-slate-900">تعيين طلب طباعة</h2>
            <p className="mt-1 text-sm text-slate-600">{assignItem.product_name}</p>
            <div className="mt-4 space-y-3">
              <label className="block text-xs text-slate-500">
                الموظف
                <select
                  value={assignUserId}
                  onChange={(event) => setAssignUserId(event.target.value)}
                  className={`mt-1 ${fieldClass}`}
                >
                  <option value="">بدون</option>
                  {assignees.map((row) => (
                    <option key={row.id} value={row.id}>
                      {row.name}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block text-xs text-slate-500">
                القسم
                <select
                  value={assignDeptId}
                  onChange={(event) => setAssignDeptId(event.target.value)}
                  className={`mt-1 ${fieldClass}`}
                >
                  <option value="">بدون</option>
                  {departments.map((row) => (
                    <option key={row.id} value={row.id}>
                      {row.name}
                    </option>
                  ))}
                </select>
              </label>
            </div>
            <div className="mt-4 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setAssignItem(null)}
                className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                إلغاء
              </button>
              <button
                type="button"
                disabled={assignSaving}
                onClick={() => void submitAssign()}
                className="rounded-lg bg-slate-900 px-3 py-2 text-sm text-white disabled:opacity-50"
              >
                حفظ
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {historyItem ? (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4" role="dialog">
          <div className="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-4 shadow-lg">
            <div className="flex items-start justify-between gap-2">
              <div>
                <h2 className="text-lg font-semibold text-slate-900">سجل الحالة</h2>
                <p className="text-sm text-slate-600">{historyItem.product_name}</p>
              </div>
              <button type="button" onClick={() => setHistoryItem(null)} className="text-sm underline">
                إغلاق
              </button>
            </div>
            {historyLoading ? <p className="mt-3 text-sm text-slate-500">جاري التحميل...</p> : null}
            {!historyLoading && history.length === 0 ? (
              <p className="mt-3 text-sm text-slate-500">لا سجلات.</p>
            ) : (
              <ul className="mt-3 max-h-80 space-y-2 overflow-y-auto">
                {history.map((row) => (
                  <li key={row.id} className="rounded-xl border border-slate-100 px-3 py-2 text-sm">
                    <p className="font-medium text-slate-800">
                      {row.from_status ? `${statusLabel(row.from_status)} → ` : ''}
                      {statusLabel(row.to_status)}
                    </p>
                    <p className="text-xs text-slate-500">
                      {row.actor?.name ?? '—'} · {row.created_at ?? ''}
                    </p>
                    {row.note ? <p className="mt-1 text-xs text-slate-600">{row.note}</p> : null}
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      ) : null}
    </section>
  )
}
