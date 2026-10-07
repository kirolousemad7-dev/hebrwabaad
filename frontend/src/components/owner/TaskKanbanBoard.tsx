import { useMemo, useState } from 'react'
import {
  DndContext,
  DragOverlay,
  PointerSensor,
  closestCorners,
  useDroppable,
  useSensor,
  useSensors,
  type DragEndEvent,
  type DragStartEvent,
} from '@dnd-kit/core'
import { SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { createManagedTask } from '../../services/workspaceTasks'
import {
  moveWork,
  rescheduleWork,
  setWorkPriority,
  setWorkProgress,
  setWorkStatus,
  type UnifiedWorkItem,
  type UnifiedWorkKanban,
} from '../../services/operations'
import { describeApiError } from '../../utils/errors'
import { clampProgress, formatProgressPercent, progressBarClass } from '../../utils/taskProgress'

export const KANBAN_COLUMNS = [
  { key: 'open', label: 'جديد', droppable: true },
  { key: 'in_progress', label: 'قيد التنفيذ', droppable: true },
  { key: 'waiting_client', label: 'بانتظار العميل', droppable: true },
  { key: 'review', label: 'مراجعة', droppable: true },
  { key: 'completed', label: 'مكتمل', droppable: true },
  { key: 'overdue', label: 'متأخر', droppable: false },
] as const

type ColumnKey = (typeof KANBAN_COLUMNS)[number]['key']

type BoardColumns = UnifiedWorkKanban['columns']

type BoardFilters = {
  q: string
  project_id: string
  assigned_to: string
  priority: string
  from: string
  to: string
}

type TaskKanbanBoardProps = {
  columns: BoardColumns
  projects: Array<{ id: number; title: string }>
  assignees: Array<{ id: number; name: string }>
  filters: BoardFilters
  onFiltersChange: (filters: BoardFilters) => void
  onReload: () => Promise<void>
  onError: (message: string) => void
  onNotice: (message: string) => void
}

const fieldClass =
  'h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

const PRIORITY_OPTIONS = [
  { value: 'LOW', label: 'منخفضة' },
  { value: 'MEDIUM', label: 'عادية' },
  { value: 'HIGH', label: 'عالية' },
  { value: 'URGENT', label: 'عاجلة' },
]

function priorityLabel(priority: string): string {
  return PRIORITY_OPTIONS.find((option) => option.value === priority)?.label ?? priority
}

function columnOf(id: string, columns: BoardColumns): ColumnKey | null {
  if (id.startsWith('column:')) {
    const key = id.slice('column:'.length) as ColumnKey
    return KANBAN_COLUMNS.some((column) => column.key === key) ? key : null
  }
  for (const column of KANBAN_COLUMNS) {
    if ((columns[column.key] ?? []).some((item) => item.id === id)) {
      return column.key
    }
  }
  return null
}

function emptyColumns(source: BoardColumns): BoardColumns {
  return {
    open: [],
    in_progress: [],
    waiting_client: [],
    review: [],
    overdue: [],
    completed: [],
    cancelled: source.cancelled ?? [],
  }
}

function ProgressMeter({ percent }: { percent: number }) {
  const value = clampProgress(percent)
  return (
    <div>
      <div className="mb-1 flex items-center justify-between text-[11px] text-slate-500">
        <span>التنفيذ</span>
        <span className="font-medium text-slate-700">{formatProgressPercent(value)}</span>
      </div>
      <div className="h-1.5 overflow-hidden rounded-full bg-slate-100" role="presentation">
        <div className={`h-full rounded-full ${progressBarClass(value)}`} style={{ width: `${value}%` }} />
      </div>
    </div>
  )
}

function TaskCardBody({ item, statusLabel }: { item: UnifiedWorkItem; statusLabel: string }) {
  const percent = clampProgress(item.progress_percent ?? 0)
  const context = item.customer_name || item.project_title

  return (
    <div className="space-y-2">
      <div className="flex items-start justify-between gap-2">
        <p className="line-clamp-2 text-sm font-medium text-slate-900">{item.title}</p>
        <span className="shrink-0 rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-600">{statusLabel}</span>
      </div>
      {context ? <p className="truncate text-xs text-slate-500">{context}</p> : null}
      <div className="flex flex-wrap items-center gap-1 text-[11px] text-slate-600">
        {item.assignee_name ? <span className="rounded-md bg-slate-100 px-1.5 py-0.5">{item.assignee_name}</span> : null}
        <span className="rounded-md bg-slate-100 px-1.5 py-0.5">{priorityLabel(item.priority)}</span>
        {item.due_at ? <span className="rounded-md bg-slate-100 px-1.5 py-0.5">{item.due_at.slice(0, 10)}</span> : null}
      </div>
      {(item.tags ?? []).length > 0 ? (
        <div className="flex flex-wrap gap-1">
          {(item.tags ?? []).slice(0, 3).map((tag) => (
            <span key={tag} className="rounded-full border border-slate-200 px-1.5 py-0.5 text-[10px] text-slate-500">
              {tag}
            </span>
          ))}
        </div>
      ) : null}
      <ProgressMeter percent={percent} />
    </div>
  )
}

function SortableTaskCard({
  item,
  column,
  onOpen,
  onMove,
}: {
  item: UnifiedWorkItem
  column: ColumnKey
  onOpen: (item: UnifiedWorkItem) => void
  onMove: (item: UnifiedWorkItem, status: string) => void
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: item.id })
  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
  }
  const statusLabel = KANBAN_COLUMNS.find((entry) => entry.key === column)?.label ?? item.status

  return (
    <article
      ref={setNodeRef}
      style={style}
      className={`rounded-xl border border-slate-200 bg-white p-3 shadow-sm ${isDragging ? 'opacity-40' : ''}`}
    >
      <div
        className="cursor-grab active:cursor-grabbing"
        {...attributes}
        {...listeners}
        aria-label={item.title}
        onClick={() => {
          if (!isDragging) {
            onOpen(item)
          }
        }}
        onKeyDown={(event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault()
            onOpen(item)
          }
        }}
      >
        <TaskCardBody item={item} statusLabel={statusLabel} />
      </div>
      <div className="mt-2 flex flex-wrap items-center gap-1">
        <button type="button" onClick={() => onOpen(item)} className="rounded-md border border-slate-200 px-2 py-1 text-[11px]">
          التفاصيل
        </button>
        {item.capabilities.can_edit ? (
          <button type="button" onClick={() => onOpen(item)} className="rounded-md border border-slate-200 px-2 py-1 text-[11px]">
            تعديل
          </button>
        ) : null}
        <label className="ms-auto text-[11px] text-slate-500 sm:hidden">
          <span className="sr-only">تغيير الحالة</span>
          <select
            value={column === 'overdue' ? (item.board_status ?? 'open') : column}
            onChange={(event) => onMove(item, event.target.value)}
            className="rounded-md border border-slate-200 bg-white px-1 py-1"
          >
            {KANBAN_COLUMNS.filter((entry) => entry.droppable).map((entry) => (
              <option key={entry.key} value={entry.key}>
                {entry.label}
              </option>
            ))}
          </select>
        </label>
      </div>
    </article>
  )
}

function KanbanColumn({
  columnKey,
  label,
  items,
  onOpen,
  onMove,
}: {
  columnKey: ColumnKey
  label: string
  items: UnifiedWorkItem[]
  onOpen: (item: UnifiedWorkItem) => void
  onMove: (item: UnifiedWorkItem, status: string) => void
}) {
  const { setNodeRef, isOver } = useDroppable({ id: `column:${columnKey}` })

  return (
    <section className="flex w-[300px] shrink-0 flex-col rounded-xl border border-slate-200 bg-slate-50/80">
      <header className="flex items-center justify-between px-3 py-2">
        <h3 className="text-sm font-semibold text-slate-800">{label}</h3>
        <span className="rounded-full bg-white px-2 py-0.5 text-xs text-slate-500">{items.length.toLocaleString('en-US')}</span>
      </header>
      <SortableContext items={items.map((item) => item.id)} strategy={verticalListSortingStrategy}>
        <div
          ref={setNodeRef}
          className={`flex max-h-[68vh] min-h-28 flex-1 flex-col gap-2 overflow-y-auto px-2 pb-2 ${isOver ? 'bg-sky-50/70' : ''}`}
        >
          {items.length === 0 ? <p className="px-1 py-6 text-center text-xs text-slate-400">لا مهام</p> : null}
          {items.map((item) => (
            <SortableTaskCard key={item.id} item={item} column={columnKey} onOpen={onOpen} onMove={onMove} />
          ))}
        </div>
      </SortableContext>
    </section>
  )
}

export function TaskKanbanBoard({
  columns,
  projects,
  assignees,
  filters,
  onFiltersChange,
  onReload,
  onError,
  onNotice,
}: TaskKanbanBoardProps) {
  const [draft, setDraft] = useState<BoardColumns | null>(null)
  const [activeId, setActiveId] = useState<string | null>(null)
  const [selected, setSelected] = useState<UnifiedWorkItem | null>(null)
  const [saving, setSaving] = useState(false)
  const [drawerError, setDrawerError] = useState<string | null>(null)
  const [progressDraft, setProgressDraft] = useState(0)
  const [detailStatus, setDetailStatus] = useState('open')
  const [detailPriority, setDetailPriority] = useState('MEDIUM')
  const [detailDue, setDetailDue] = useState('')
  const [creating, setCreating] = useState(false)
  const [createError, setCreateError] = useState<string | null>(null)
  const [createForm, setCreateForm] = useState({
    title: '',
    project_id: '',
    assigned_to: '',
    priority: 'MEDIUM',
    deadline: '',
  })
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 8 } }))
  const view = draft ?? columns

  const stats = useMemo(() => {
    const all = KANBAN_COLUMNS.flatMap((column) => view[column.key] ?? [])
    const progressValues = all.map((item) => clampProgress(item.progress_percent ?? 0))
    const average = progressValues.length === 0 ? 0 : Math.round(progressValues.reduce((sum, value) => sum + value, 0) / progressValues.length)
    return {
      total: all.length,
      active: (view.in_progress ?? []).length,
      overdue: (view.overdue ?? []).length,
      completed: (view.completed ?? []).length,
      average,
    }
  }, [view])

  const activeItem = activeId ? KANBAN_COLUMNS.flatMap((column) => view[column.key] ?? []).find((item) => item.id === activeId) ?? null : null

  function openItem(item: UnifiedWorkItem) {
    setSelected(item)
    setProgressDraft(clampProgress(item.progress_percent ?? 0))
    setDetailStatus(item.board_status && item.board_status !== 'overdue' ? item.board_status : 'open')
    setDetailPriority(item.priority || 'MEDIUM')
    setDetailDue((item.due_at ?? '').slice(0, 10))
    setDrawerError(null)
  }

  async function persistMove(item: UnifiedWorkItem, status: string, orderedIds: string[], sortOrder: number) {
    const previous = view
    const moved = { ...item, board_status: status, sort_order: sortOrder }
    const next = emptyColumns(previous)
    for (const column of KANBAN_COLUMNS) {
      next[column.key] = (previous[column.key] ?? []).filter((row) => row.id !== item.id)
    }
    const target = status as ColumnKey
    const without = next[target] ?? []
    const ids = orderedIds.filter((id) => id !== item.id)
    const insertAt = Math.max(0, Math.min(sortOrder, ids.length))
    const rebuilt = [...without]
    rebuilt.splice(insertAt, 0, moved)
    next[target] = rebuilt
    setDraft(next)
    try {
      const response = await moveWork(item.id, { status, sort_order: sortOrder, ordered_ids: orderedIds })
      await onReload()
      setDraft(null)
      if (response.data.is_overdue && status !== 'completed') {
        onNotice('تم تحديث الحالة. المهمة ما زالت في عمود المتأخر لأن الموعد انتهى.')
      }
    } catch (caught) {
      setDraft(null)
      onError(describeApiError(caught, 'تعذر حفظ نقل المهمة. أُعيدت إلى مكانها.'))
    }
  }

  function handleDragStart(event: DragStartEvent) {
    setActiveId(String(event.active.id))
  }

  function handleDragEnd(event: DragEndEvent) {
    setActiveId(null)
    const active = String(event.active.id)
    const over = event.over ? String(event.over.id) : null
    if (!over) {
      return
    }
    const from = columnOf(active, view)
    const to = columnOf(over, view)
    if (!from || !to) {
      return
    }
    const column = KANBAN_COLUMNS.find((entry) => entry.key === to)
    if (!column?.droppable) {
      onError('عمود المتأخر يتبع الموعد النهائي، ولا يُستخدم كحالة مستقلة.')
      return
    }
    const item = (view[from] ?? []).find((row) => row.id === active)
    if (!item) {
      return
    }
    const destination = (view[to] ?? []).filter((row) => row.id !== item.id)
    const overIndex = destination.findIndex((row) => row.id === over)
    const sortOrder = over.startsWith('column:') || overIndex < 0 ? destination.length : overIndex
    const ordered = [...destination]
    ordered.splice(sortOrder, 0, item)
    void persistMove(item, to, ordered.map((row) => row.id), sortOrder)
  }

  async function saveDetails() {
    if (!selected) {
      return
    }
    setSaving(true)
    setDrawerError(null)
    try {
      const percent = clampProgress(progressDraft)
      let progressSkipped = false
      if (percent !== clampProgress(selected.progress_percent ?? 0)) {
        if (selected.source_type !== 'task') {
          progressSkipped = true
        } else {
          await setWorkProgress(selected.id, percent)
        }
      }
      const currentStatus = selected.board_status && selected.board_status !== 'overdue' ? selected.board_status : 'open'
      if (detailStatus !== currentStatus) {
        await setWorkStatus(selected.id, detailStatus)
      }
      if (detailPriority !== selected.priority && selected.capabilities.can_edit) {
        await setWorkPriority(selected.id, detailPriority)
      }
      if (detailDue !== (selected.due_at ?? '').slice(0, 10) && selected.capabilities.can_reschedule) {
        await rescheduleWork(selected.id, { due_at: detailDue || null })
      }
      await onReload()
      setSelected((current) =>
        current
          ? {
              ...current,
              progress_percent: percent,
              board_status: detailStatus,
              priority: detailPriority,
              due_at: detailDue || current.due_at,
            }
          : current,
      )
      if (progressSkipped) {
        setDrawerError('نسبة التنفيذ متاحة لمهام المشاريع فقط. حُفظت بقية الحقول.')
      } else {
        onNotice(percent === 100 && !selected.is_completed ? 'حُفظت النسبة. يمكنك تحويل المهمة إلى مكتمل.' : 'تم حفظ المهمة.')
      }
    } catch (caught) {
      setDrawerError(describeApiError(caught, 'تعذر حفظ المهمة.'))
    } finally {
      setSaving(false)
    }
  }

  async function markComplete() {
    if (!selected) {
      return
    }
    setSaving(true)
    setDrawerError(null)
    try {
      await setWorkStatus(selected.id, 'completed')
      onNotice('تم تحويل المهمة إلى مكتمل.')
      setSelected(null)
      await onReload()
    } catch (caught) {
      setDrawerError(describeApiError(caught, 'تعذر إكمال المهمة.'))
    } finally {
      setSaving(false)
    }
  }

  async function createTask() {
    setCreateError(null)
    if (!createForm.title.trim() || !createForm.project_id || !createForm.assigned_to) {
      setCreateError('العنوان والمشروع والمسؤول مطلوبة.')
      return
    }
    setSaving(true)
    try {
      await createManagedTask({
        title: createForm.title.trim(),
        project_id: Number(createForm.project_id),
        assigned_to: Number(createForm.assigned_to),
        priority: createForm.priority,
        deadline: createForm.deadline || undefined,
        status: 'TODO',
      })
      setCreating(false)
      setCreateForm({ title: '', project_id: '', assigned_to: '', priority: 'MEDIUM', deadline: '' })
      onNotice('تمت إضافة المهمة.')
      await onReload()
    } catch (caught) {
      setCreateError(describeApiError(caught, 'تعذر إضافة المهمة.'))
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="max-w-full space-y-3">
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-5">
        {[
          ['إجمالي المهام', stats.total],
          ['قيد التنفيذ', stats.active],
          ['متأخرة', stats.overdue],
          ['مكتملة', stats.completed],
          ['متوسط التنفيذ', `${stats.average.toLocaleString('en-US')}%`],
        ].map(([label, value]) => (
          <div key={String(label)} className="rounded-xl border border-slate-200 bg-white px-3 py-2">
            <p className="text-[11px] text-slate-500">{label}</p>
            <p className="text-lg font-semibold text-slate-900">{typeof value === 'number' ? value.toLocaleString('en-US') : value}</p>
          </div>
        ))}
      </div>

      <div className="grid gap-2 rounded-xl border border-slate-200 bg-white p-3 sm:grid-cols-2 xl:grid-cols-6">
        <input
          value={filters.q}
          onChange={(event) => onFiltersChange({ ...filters, q: event.target.value })}
          placeholder="بحث عن مهمة"
          className={fieldClass}
          aria-label="بحث عن مهمة"
        />
        <select
          value={filters.project_id}
          onChange={(event) => onFiltersChange({ ...filters, project_id: event.target.value })}
          className={fieldClass}
          aria-label="المشروع"
        >
          <option value="">كل المشاريع</option>
          {projects.map((project) => (
            <option key={project.id} value={project.id}>
              {project.title}
            </option>
          ))}
        </select>
        <select
          value={filters.assigned_to}
          onChange={(event) => onFiltersChange({ ...filters, assigned_to: event.target.value })}
          className={fieldClass}
          aria-label="المسؤول"
        >
          <option value="">كل المسؤولين</option>
          {assignees.map((person) => (
            <option key={person.id} value={person.id}>
              {person.name}
            </option>
          ))}
        </select>
        <select
          value={filters.priority}
          onChange={(event) => onFiltersChange({ ...filters, priority: event.target.value })}
          className={fieldClass}
          aria-label="الأولوية"
        >
          <option value="">كل الأولويات</option>
          {PRIORITY_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
        <input
          type="date"
          value={filters.from}
          onChange={(event) => onFiltersChange({ ...filters, from: event.target.value })}
          className={fieldClass}
          aria-label="من تاريخ"
        />
        <div className="flex gap-2">
          <input
            type="date"
            value={filters.to}
            onChange={(event) => onFiltersChange({ ...filters, to: event.target.value })}
            className={fieldClass}
            aria-label="إلى تاريخ"
          />
          <button type="button" onClick={() => setCreating(true)} className="shrink-0 rounded-lg bg-slate-900 px-3 text-sm text-white">
            إضافة
          </button>
        </div>
      </div>

      <DndContext sensors={sensors} collisionDetection={closestCorners} onDragStart={handleDragStart} onDragEnd={handleDragEnd}>
        <div className="max-w-full overflow-x-auto overscroll-x-contain pb-2">
          <div className="flex w-max gap-3">
            {KANBAN_COLUMNS.map((column) => (
              <KanbanColumn
                key={column.key}
                columnKey={column.key}
                label={column.label}
                items={view[column.key] ?? []}
                onOpen={openItem}
                onMove={(item, status) => void persistMove(item, status, [item.id], 0)}
              />
            ))}
          </div>
        </div>
        <DragOverlay>
          {activeItem ? (
            <div className="w-[300px] rounded-xl border border-sky-300 bg-white p-3 shadow-lg">
              <TaskCardBody
                item={activeItem}
                statusLabel={KANBAN_COLUMNS.find((column) => column.key === columnOf(activeItem.id, view))?.label ?? ''}
              />
            </div>
          ) : null}
        </DragOverlay>
      </DndContext>

      {selected ? (
        <div className="fixed inset-0 z-50 bg-slate-900/30" role="dialog" aria-modal="true">
          <button type="button" aria-label="إغلاق التفاصيل" className="absolute inset-0" onClick={() => setSelected(null)} />
          <aside className="absolute inset-y-0 start-0 h-full w-full max-w-md overflow-y-auto border-s border-slate-200 bg-white p-4 shadow-xl">
            <div className="mb-3 flex items-start justify-between gap-3">
              <h2 className="text-lg font-semibold text-slate-900">{selected.title}</h2>
              <button type="button" onClick={() => setSelected(null)} className="text-sm text-slate-500">
                إغلاق
              </button>
            </div>
            {drawerError ? <p className="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{drawerError}</p> : null}
            <div className="space-y-3 text-sm">
              <p className="text-slate-600">{selected.description || 'لا يوجد وصف.'}</p>
              <p>المشروع: {selected.project_title || '—'}</p>
              <p>العميل: {selected.customer_name || '—'}</p>
              <p>المسؤول: {selected.assignee_name || '—'}</p>
              <label className="block text-xs text-slate-500">
                الموعد
                <input id="task-detail-due" type="date" value={detailDue} onChange={(event) => setDetailDue(event.target.value)} className={`mt-1 ${fieldClass}`} />
              </label>
              <label className="block text-xs text-slate-500">
                الأولوية
                <select id="task-detail-priority" value={detailPriority} onChange={(event) => setDetailPriority(event.target.value)} className={`mt-1 ${fieldClass}`}>
                  {PRIORITY_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block text-xs text-slate-500">
                الحالة
                <select id="task-detail-status" value={detailStatus} onChange={(event) => setDetailStatus(event.target.value)} className={`mt-1 ${fieldClass}`}>
                  {KANBAN_COLUMNS.filter((column) => column.droppable).map((column) => (
                    <option key={column.key} value={column.key}>
                      {column.label}
                    </option>
                  ))}
                </select>
              </label>
              <div>
                <ProgressMeter percent={progressDraft} />
                <input
                  type="range"
                  min={0}
                  max={100}
                  value={progressDraft}
                  onChange={(event) => setProgressDraft(clampProgress(Number(event.target.value)))}
                  className="mt-2 w-full"
                  aria-label="نسبة التنفيذ"
                />
                <input
                  type="number"
                  min={0}
                  max={100}
                  value={progressDraft}
                  onChange={(event) => setProgressDraft(clampProgress(Number(event.target.value)))}
                  className={`mt-2 ${fieldClass}`}
                  aria-label="نسبة التنفيذ رقماً"
                />
              </div>
              {progressDraft === 100 && !selected.is_completed ? (
                <button type="button" onClick={() => void markComplete()} className="rounded-lg border border-emerald-300 px-3 py-2 text-emerald-800">
                  تحويل إلى مكتمل
                </button>
              ) : null}
              <button type="button" disabled={saving} onClick={() => void saveDetails()} className="w-full rounded-lg bg-slate-900 px-3 py-2 text-white disabled:opacity-50">
                {saving ? 'جارٍ الحفظ...' : 'حفظ'}
              </button>
            </div>
          </aside>
        </div>
      ) : null}

      {creating ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 p-4" role="dialog">
          <div className="w-full max-w-md rounded-xl border border-slate-200 bg-white p-4 shadow-xl">
            <h2 className="text-lg font-semibold">مهمة جديدة</h2>
            {createError ? <p className="mt-2 text-sm text-red-700">{createError}</p> : null}
            <div className="mt-3 space-y-2">
              <input
                value={createForm.title}
                onChange={(event) => setCreateForm((current) => ({ ...current, title: event.target.value }))}
                placeholder="عنوان المهمة"
                className={fieldClass}
              />
              <select
                value={createForm.project_id}
                onChange={(event) => setCreateForm((current) => ({ ...current, project_id: event.target.value }))}
                className={fieldClass}
              >
                <option value="">المشروع</option>
                {projects.map((project) => (
                  <option key={project.id} value={project.id}>
                    {project.title}
                  </option>
                ))}
              </select>
              <select
                value={createForm.assigned_to}
                onChange={(event) => setCreateForm((current) => ({ ...current, assigned_to: event.target.value }))}
                className={fieldClass}
              >
                <option value="">المسؤول</option>
                {assignees.map((person) => (
                  <option key={person.id} value={person.id}>
                    {person.name}
                  </option>
                ))}
              </select>
              <select
                value={createForm.priority}
                onChange={(event) => setCreateForm((current) => ({ ...current, priority: event.target.value }))}
                className={fieldClass}
              >
                {PRIORITY_OPTIONS.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
              <input
                type="date"
                value={createForm.deadline}
                onChange={(event) => setCreateForm((current) => ({ ...current, deadline: event.target.value }))}
                className={fieldClass}
              />
            </div>
            <div className="mt-3 flex justify-end gap-2">
              <button type="button" onClick={() => setCreating(false)} className="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                إلغاء
              </button>
              <button type="button" disabled={saving} onClick={() => void createTask()} className="rounded-lg bg-slate-900 px-3 py-2 text-sm text-white">
                حفظ المهمة
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  )
}
