import { FormEvent, useEffect, useMemo, useState } from 'react'
import { FeedbackBanner } from '../ui/FeedbackBanner'
import { StatusBadge } from '../ui/StatusBadge'
import type {
  CalendarActivity,
  CalendarAssignee,
  CalendarChecklistItem,
  CalendarComment,
  CalendarFileItem,
  CalendarItem,
  CalendarItemPayload,
  CalendarTemplate,
} from '../../services/calendar'
import {
  buildRecurrenceRule,
  checkCalendarConflicts,
  createCalendarComment,
  deleteCalendarComment,
  deleteCalendarFile,
  downloadCalendarItemIcs,
  getCalendarActivities,
  getCalendarComments,
  getCalendarFiles,
  getCalendarTemplates,
  parseRecurrenceFreq,
  resolveCalendarItemId,
  updateCalendarChecklist,
  uploadCalendarFile,
} from '../../services/calendar'
import {
  CALENDAR_PRIORITIES,
  CALENDAR_QUICK_TYPES,
  CALENDAR_RECURRENCE_FREQS,
  CALENDAR_REMINDERS,
  CALENDAR_STATUSES,
  CALENDAR_TYPES,
  CALENDAR_VISIBILITIES,
  calendarPriorityLabel,
  calendarRecurrenceLabel,
  calendarReminderLabel,
  calendarSourceBadgeClass,
  calendarSourceLabel,
  calendarStatusLabel,
  calendarStatusTone,
  calendarTypeLabel,
  calendarVisibilityLabel,
} from '../../utils/calendarLabels'
import {
  dateTimeLocalFromIso,
  formatDateTimeShort,
  isoFromDateTimeLocal,
  toDateInput,
  toDateTimeLocalValue,
} from '../../utils/calendarDates'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

type Mode = 'create' | 'edit' | 'detail'

type CalendarItemModalProps = {
  mode: Mode
  item?: CalendarItem | null
  assignees: CalendarAssignee[]
  defaultStartsAt?: Date
  defaultType?: string
  busy?: boolean
  error?: string | null
  onClose: () => void
  onSubmit: (payload: CalendarItemPayload) => Promise<void>
  onEdit?: () => void
  onDelete?: () => Promise<void>
  onComplete?: () => Promise<void>
  onDuplicate?: (startsAt: string) => Promise<void>
  onRefreshItem?: () => Promise<void>
}

export function CalendarItemModal({
  mode,
  item,
  assignees,
  defaultStartsAt,
  defaultType,
  busy = false,
  error,
  onClose,
  onSubmit,
  onEdit,
  onDelete,
  onComplete,
  onDuplicate,
  onRefreshItem,
}: CalendarItemModalProps) {
  const initialStarts = useMemo(() => {
    if (item?.starts_at) return dateTimeLocalFromIso(item.starts_at)
    return toDateTimeLocalValue(defaultStartsAt ?? new Date())
  }, [item?.starts_at, defaultStartsAt])

  const [title, setTitle] = useState(item?.title ?? '')
  const [description, setDescription] = useState(item?.description ?? '')
  const [type, setType] = useState(item?.type ?? defaultType ?? 'TASK')
  const [status, setStatus] = useState(item?.status ?? 'SCHEDULED')
  const [priority, setPriority] = useState(item?.priority ?? 'MEDIUM')
  const [visibility, setVisibility] = useState(item?.visibility ?? 'PARTICIPANTS')
  const [startsAt, setStartsAt] = useState(initialStarts)
  const [endsAt, setEndsAt] = useState(item?.ends_at ? dateTimeLocalFromIso(item.ends_at) : '')
  const [allDay, setAllDay] = useState(Boolean(item?.all_day))
  const [assigneeIds, setAssigneeIds] = useState<number[]>(
    item?.assignees?.map((entry) => entry.id) ?? [],
  )
  const [reminders, setReminders] = useState<string[]>(
    item?.reminders?.map((entry) => String(entry.offset)) ?? [],
  )
  const [location, setLocation] = useState(item?.location ?? '')
  const [meetingUrl, setMeetingUrl] = useState(item?.meeting_url ?? '')
  const [recurrenceFreq, setRecurrenceFreq] = useState(parseRecurrenceFreq(item?.recurrence_rule))
  const [recurrenceUntil, setRecurrenceUntil] = useState(
    item?.recurrence_until ? toDateInput(new Date(item.recurrence_until)) : '',
  )
  const [recurrenceCount, setRecurrenceCount] = useState(
    item?.recurrence_count != null ? String(item.recurrence_count) : '',
  )
  const [checklistDraft, setChecklistDraft] = useState<CalendarChecklistItem[]>(
    item?.checklist?.length ? item.checklist.map((row) => ({ ...row })) : [],
  )
  const [checklistText, setChecklistText] = useState('')
  const [templates, setTemplates] = useState<CalendarTemplate[]>([])
  const [templateId, setTemplateId] = useState('')
  const [formError, setFormError] = useState<string | null>(null)
  const [conflictWarning, setConflictWarning] = useState<string | null>(null)

  const [comments, setComments] = useState<CalendarComment[]>([])
  const [activities, setActivities] = useState<CalendarActivity[]>([])
  const [files, setFiles] = useState<CalendarFileItem[]>([])
  const [commentBody, setCommentBody] = useState('')
  const [detailBusy, setDetailBusy] = useState(false)
  const [detailError, setDetailError] = useState<string | null>(null)

  const apiId = item ? resolveCalendarItemId(item) : null
  const linkedReadOnly = Boolean(item?.is_linked)
  const showForm = mode === 'create' || mode === 'edit'
  const displayError = formError || error || detailError

  useEffect(() => {
    if (mode !== 'create') return
    let cancelled = false
    void getCalendarTemplates()
      .then((response) => {
        if (!cancelled) setTemplates(response.data.items ?? [])
      })
      .catch(() => {
        if (!cancelled) setTemplates([])
      })
    return () => {
      cancelled = true
    }
  }, [mode])

  useEffect(() => {
    if (mode !== 'detail' || apiId == null) return
    let cancelled = false
    setDetailBusy(true)
    setDetailError(null)
    void Promise.all([
      getCalendarComments(apiId),
      getCalendarActivities(apiId),
      getCalendarFiles(apiId),
    ])
      .then(([commentsRes, activitiesRes, filesRes]) => {
        if (cancelled) return
        setComments(commentsRes.data.items ?? [])
        setActivities(activitiesRes.data.items ?? [])
        setFiles(filesRes.data.items ?? [])
      })
      .catch((caught) => {
        if (cancelled) return
        setDetailError(describeApiError(caught, 'تعذر تحميل تفاصيل العنصر.'))
      })
      .finally(() => {
        if (!cancelled) setDetailBusy(false)
      })
    return () => {
      cancelled = true
    }
  }, [mode, apiId])

  function applyTemplate(id: string) {
    setTemplateId(id)
    const template = templates.find((entry) => String(entry.id) === id)
    if (!template) return
    setType(template.type)
    if (template.priority) setPriority(template.priority)
    if (template.title_pattern) setTitle(template.title_pattern)
    if (template.description) setDescription(template.description)
    if (template.default_reminders?.length) setReminders(template.default_reminders.map(String))
    if (template.default_duration_minutes && startsAt) {
      const start = new Date(isoFromDateTimeLocal(startsAt))
      start.setMinutes(start.getMinutes() + template.default_duration_minutes)
      setEndsAt(toDateTimeLocalValue(start))
    }
  }

  function buildPayload(): CalendarItemPayload {
    const count = recurrenceCount.trim() ? Number(recurrenceCount) : null
    return {
      title: title.trim(),
      description: description.trim() || null,
      type,
      status,
      priority,
      visibility,
      starts_at: isoFromDateTimeLocal(startsAt, allDay),
      ends_at: endsAt ? isoFromDateTimeLocal(endsAt, allDay) : null,
      all_day: allDay,
      assignee_ids: assigneeIds,
      reminders,
      location: location.trim() || null,
      meeting_url: meetingUrl.trim() || null,
      checklist: checklistDraft,
      recurrence_rule: buildRecurrenceRule(recurrenceFreq || null),
      recurrence_until: recurrenceUntil ? `${recurrenceUntil}T23:59:59` : null,
      recurrence_count: count != null && Number.isFinite(count) ? count : null,
    }
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setFormError(null)
    setConflictWarning(null)

    if (!title.trim()) {
      setFormError('العنوان مطلوب.')
      return
    }

    const payload = buildPayload()

    if (payload.ends_at && assigneeIds.length > 0) {
      try {
        const conflicts = await checkCalendarConflicts({
          starts_at: payload.starts_at,
          ends_at: payload.ends_at,
          assignee_ids: assigneeIds,
          exclude_id: item && !item.is_occurrence ? Number(resolveCalendarItemId(item)) : null,
        })
        const count = conflicts.data.items?.length ?? 0
        if (count > 0) {
          const proceed = window.confirm(
            `يوجد ${count.toLocaleString('ar-SA')} تعارض محتمل في المواعيد. المتابعة بالحفظ؟`,
          )
          if (!proceed) {
            setConflictWarning(`تحذير: ${count.toLocaleString('ar-SA')} تعارض في الجدول.`)
            return
          }
        }
      } catch {
        /* conflict check is best-effort */
      }
    }

    try {
      await onSubmit(payload)
    } catch (caught) {
      setFormError(describeApiError(caught, 'تعذر حفظ عنصر التقويم.'))
    }
  }

  function toggleAssignee(id: number) {
    setAssigneeIds((current) =>
      current.includes(id) ? current.filter((entry) => entry !== id) : [...current, id],
    )
  }

  function toggleReminder(offset: string) {
    setReminders((current) =>
      current.includes(offset) ? current.filter((entry) => entry !== offset) : [...current, offset],
    )
  }

  async function saveChecklist(next: CalendarChecklistItem[]) {
    if (!apiId || !item?.can_edit || linkedReadOnly) {
      setChecklistDraft(next)
      return
    }
    setDetailBusy(true)
    setDetailError(null)
    try {
      await updateCalendarChecklist(apiId, next)
      setChecklistDraft(next)
      await onRefreshItem?.()
    } catch (caught) {
      setDetailError(describeApiError(caught, 'تعذر تحديث قائمة التحقق.'))
    } finally {
      setDetailBusy(false)
    }
  }

  async function addComment() {
    if (!apiId || !commentBody.trim()) return
    setDetailBusy(true)
    setDetailError(null)
    try {
      const response = await createCalendarComment(apiId, commentBody.trim())
      setComments((current) => [response.data, ...current])
      setCommentBody('')
    } catch (caught) {
      setDetailError(describeApiError(caught, 'تعذر إضافة التعليق.'))
    } finally {
      setDetailBusy(false)
    }
  }

  async function removeComment(commentId: number) {
    if (!window.confirm('حذف هذا التعليق؟')) return
    setDetailBusy(true)
    try {
      await deleteCalendarComment(commentId)
      setComments((current) => current.filter((entry) => entry.id !== commentId))
    } catch (caught) {
      setDetailError(describeApiError(caught, 'تعذر حذف التعليق.'))
    } finally {
      setDetailBusy(false)
    }
  }

  async function onUploadFile(fileList: FileList | null) {
    if (!apiId || !fileList?.[0]) return
    setDetailBusy(true)
    setDetailError(null)
    try {
      const response = await uploadCalendarFile(apiId, fileList[0])
      setFiles((current) => [response.data, ...current])
    } catch (caught) {
      setDetailError(describeApiError(caught, 'تعذر رفع الملف.'))
    } finally {
      setDetailBusy(false)
    }
  }

  async function removeFile(fileId: number) {
    if (!apiId || !window.confirm('فصل هذا المرفق؟')) return
    setDetailBusy(true)
    try {
      await deleteCalendarFile(apiId, fileId)
      setFiles((current) => current.filter((entry) => entry.id !== fileId))
    } catch (caught) {
      setDetailError(describeApiError(caught, 'تعذر فصل المرفق.'))
    } finally {
      setDetailBusy(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-3 sm:items-center" role="dialog" aria-modal="true">
      <button type="button" className="absolute inset-0 cursor-default" aria-label="إغلاق" onClick={onClose} />
      <div className="relative z-10 max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 shadow-xl sm:p-6">
        <div className="mb-4 flex items-start justify-between gap-3">
          <div>
            <h2 className="text-lg font-semibold text-slate-900">
              {mode === 'create' ? 'إضافة للجدول' : mode === 'edit' ? 'تعديل العنصر' : 'تفاصيل العنصر'}
            </h2>
            {linkedReadOnly ? (
              <p className="mt-1 text-xs text-slate-500">عنصر مرتبط/مشتق — للعرض أو التعديل من المصدر.</p>
            ) : null}
            {item?.is_occurrence ? (
              <p className="mt-1 text-xs text-amber-800">تكرار افتراضي — التعديل قد يطلب نطاق التكرار.</p>
            ) : null}
          </div>
          <button type="button" className="min-h-10 rounded-xl border px-3 text-sm" onClick={onClose}>
            إغلاق
          </button>
        </div>

        {displayError ? <FeedbackBanner kind="error">{displayError}</FeedbackBanner> : null}
        {conflictWarning ? <FeedbackBanner kind="warning">{conflictWarning}</FeedbackBanner> : null}

        {mode === 'detail' && item ? (
          <div className="space-y-5 text-sm">
            <div className="flex flex-wrap items-center gap-2">
              <StatusBadge status={item.status} label={calendarStatusLabel(item.status)} tone={calendarStatusTone(item.status)} />
              <StatusBadge status={item.type} label={calendarTypeLabel(item.type)} tone="progress" />
              <StatusBadge status={item.priority} label={calendarPriorityLabel(item.priority)} />
              <span className={`rounded-full border px-2 py-0.5 text-[11px] ${calendarSourceBadgeClass(item.source)}`}>
                {calendarSourceLabel(item.source)}
              </span>
              {item.is_linked ? (
                <span className="rounded-full border border-dashed border-slate-300 px-2 py-0.5 text-[11px] text-slate-600">
                  مرتبط
                </span>
              ) : null}
            </div>
            <h3 className="text-base font-semibold text-slate-900">{item.title}</h3>
            {item.description ? <p className="whitespace-pre-wrap text-slate-700">{item.description}</p> : null}

            <dl className="grid gap-2 sm:grid-cols-2">
              <div>
                <dt className="text-xs text-slate-500">البداية</dt>
                <dd>{formatDateTimeShort(item.starts_at)}</dd>
              </div>
              <div>
                <dt className="text-xs text-slate-500">النهاية</dt>
                <dd>{item.ends_at ? formatDateTimeShort(item.ends_at) : '—'}</dd>
              </div>
              <div>
                <dt className="text-xs text-slate-500">الموقع</dt>
                <dd>{item.location || '—'}</dd>
              </div>
              <div>
                <dt className="text-xs text-slate-500">رابط الاجتماع</dt>
                <dd>
                  {item.meeting_url ? (
                    <a href={item.meeting_url} target="_blank" rel="noopener noreferrer" className="underline">
                      فتح الرابط
                    </a>
                  ) : (
                    '—'
                  )}
                </dd>
              </div>
              <div>
                <dt className="text-xs text-slate-500">التكرار</dt>
                <dd>{calendarRecurrenceLabel(parseRecurrenceFreq(item.recurrence_rule))}</dd>
              </div>
              <div>
                <dt className="text-xs text-slate-500">الظهور</dt>
                <dd>{calendarVisibilityLabel(item.visibility)}</dd>
              </div>
            </dl>

            {item.assignees.length > 0 ? (
              <p className="text-slate-700">المعيّنون: {item.assignees.map((entry) => entry.name).join('، ')}</p>
            ) : null}

            {(item.related_label || item.related_href) ? (
              <div className="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2">
                <p className="text-xs text-slate-500">السجل المرتبط</p>
                <p className="font-medium text-slate-900">{item.related_label ?? 'سجل مرتبط'}</p>
                {item.related_href ? (
                  <div className="mt-2 flex flex-wrap gap-2">
                    <a href={item.related_href} className="min-h-9 rounded-xl border bg-white px-3 text-sm leading-9">
                      فتح السجل
                    </a>
                    {linkedReadOnly ? (
                      <a href={item.related_href} className="min-h-9 rounded-xl border border-amber-300 bg-amber-50 px-3 text-sm leading-9 text-amber-950">
                        تعديل من المصدر
                      </a>
                    ) : null}
                  </div>
                ) : (
                  <p className="mt-1 text-xs text-slate-500">لا يوجد مسار فتح متاح لصلاحيتك الحالية.</p>
                )}
              </div>
            ) : null}

            <section className="space-y-2">
              <h4 className="font-semibold text-slate-900">قائمة التحقق</h4>
              <ul className="space-y-1">
                {(checklistDraft.length ? checklistDraft : item.checklist ?? []).map((row, index) => (
                  <li key={row.id ?? `c-${index}`} className="flex items-center gap-2">
                    <input
                      type="checkbox"
                      checked={Boolean(row.done)}
                      disabled={!item.can_edit || linkedReadOnly || detailBusy}
                      onChange={() => {
                        const next = (checklistDraft.length ? checklistDraft : item.checklist ?? []).map((entry, i) =>
                          i === index ? { ...entry, done: !entry.done } : entry,
                        )
                        void saveChecklist(next)
                      }}
                    />
                    <span className={row.done ? 'text-slate-400 line-through' : ''}>{row.text}</span>
                  </li>
                ))}
              </ul>
              {item.can_edit && !linkedReadOnly ? (
                <div className="flex gap-2">
                  <input
                    value={checklistText}
                    onChange={(e) => setChecklistText(e.target.value)}
                    placeholder="بند جديد..."
                    className={fieldClass}
                  />
                  <button
                    type="button"
                    className="min-h-10 shrink-0 rounded-xl border px-3 text-sm"
                    disabled={detailBusy || !checklistText.trim()}
                    onClick={() => {
                      const next = [
                        ...(checklistDraft.length ? checklistDraft : item.checklist ?? []),
                        { text: checklistText.trim(), done: false },
                      ]
                      setChecklistText('')
                      void saveChecklist(next)
                    }}
                  >
                    إضافة
                  </button>
                </div>
              ) : null}
            </section>

            <section className="space-y-2">
              <h4 className="font-semibold text-slate-900">التعليقات ({comments.length.toLocaleString('ar-SA')})</h4>
              {item.can_edit && !linkedReadOnly ? (
                <div className="flex gap-2">
                  <input
                    value={commentBody}
                    onChange={(e) => setCommentBody(e.target.value)}
                    placeholder="تعليق..."
                    className={fieldClass}
                  />
                  <button type="button" className="min-h-10 shrink-0 rounded-xl bg-slate-900 px-3 text-sm text-white" disabled={detailBusy} onClick={() => void addComment()}>
                    إرسال
                  </button>
                </div>
              ) : null}
              <ul className="max-h-40 space-y-2 overflow-y-auto">
                {comments.map((comment) => (
                  <li key={comment.id} className="rounded-xl border border-slate-100 px-3 py-2">
                    <div className="flex items-start justify-between gap-2">
                      <div>
                        <p className="text-xs text-slate-500">{comment.user?.name ?? '—'} · {comment.created_at ? formatDateTimeShort(comment.created_at) : ''}</p>
                        <p className="mt-1 whitespace-pre-wrap">{comment.body}</p>
                      </div>
                      {item.can_edit && !linkedReadOnly ? (
                        <button type="button" className="text-xs text-red-700" onClick={() => void removeComment(comment.id)}>
                          حذف
                        </button>
                      ) : null}
                    </div>
                  </li>
                ))}
              </ul>
            </section>

            <section className="space-y-2">
              <h4 className="font-semibold text-slate-900">الملفات ({files.length.toLocaleString('ar-SA')})</h4>
              {item.can_edit && !linkedReadOnly ? (
                <input type="file" disabled={detailBusy} onChange={(e) => void onUploadFile(e.target.files)} />
              ) : null}
              <ul className="space-y-1">
                {files.map((file) => (
                  <li key={file.id} className="flex items-center justify-between gap-2 rounded-xl border border-slate-100 px-3 py-2">
                    <span className="truncate">{file.original_name}</span>
                    {item.can_edit && !linkedReadOnly ? (
                      <button type="button" className="text-xs text-red-700" onClick={() => void removeFile(file.id)}>
                        فصل
                      </button>
                    ) : null}
                  </li>
                ))}
              </ul>
            </section>

            <section className="space-y-2">
              <h4 className="font-semibold text-slate-900">النشاط</h4>
              {detailBusy && activities.length === 0 ? (
                <p className="text-xs text-slate-500">جاري التحميل...</p>
              ) : (
                <ul className="max-h-36 space-y-1 overflow-y-auto text-xs text-slate-600">
                  {activities.length === 0 ? (
                    <li>لا نشاط مسجّل.</li>
                  ) : (
                    activities.map((activity) => (
                      <li key={activity.id} className="rounded-lg bg-slate-50 px-2 py-1">
                        {activity.summary ?? activity.action}
                        {activity.created_at ? ` · ${formatDateTimeShort(activity.created_at)}` : ''}
                      </li>
                    ))
                  )}
                </ul>
              )}
            </section>

            <div className="flex flex-wrap gap-2 border-t border-slate-100 pt-4">
              {item.can_edit && !linkedReadOnly ? (
                <>
                  <button type="button" className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white" onClick={onEdit} disabled={busy}>
                    تعديل
                  </button>
                  {item.status !== 'COMPLETED' && item.status !== 'CANCELLED' ? (
                    <button
                      type="button"
                      className="min-h-11 rounded-xl border border-emerald-300 px-4 text-sm text-emerald-900 disabled:opacity-60"
                      onClick={() => void onComplete?.()}
                      disabled={busy}
                    >
                      إكمال
                    </button>
                  ) : null}
                  <button
                    type="button"
                    className="min-h-11 rounded-xl border border-red-300 px-4 text-sm text-red-800 disabled:opacity-60"
                    onClick={() => void onDelete?.()}
                    disabled={busy}
                  >
                    حذف
                  </button>
                </>
              ) : null}
              {onDuplicate && !linkedReadOnly ? (
                <button
                  type="button"
                  className="min-h-11 rounded-xl border px-4 text-sm"
                  disabled={busy}
                  onClick={() => {
                    const next = window.prompt('موعد النسخة (YYYY-MM-DDTHH:mm)', toDateTimeLocalValue(new Date()))
                    if (!next) return
                    void onDuplicate(isoFromDateTimeLocal(next))
                  }}
                >
                  تكرار
                </button>
              ) : null}
              {apiId != null ? (
                <button
                  type="button"
                  className="min-h-11 rounded-xl border px-4 text-sm"
                  disabled={busy || detailBusy}
                  onClick={() => void downloadCalendarItemIcs(apiId, `calendar-${apiId}.ics`)}
                >
                  تنزيل ICS
                </button>
              ) : null}
              {item.related_href ? (
                <a href={item.related_href} className="min-h-11 rounded-xl border px-4 text-sm leading-[2.75rem]">
                  فتح المرتبط
                </a>
              ) : null}
            </div>
          </div>
        ) : null}

        {showForm ? (
          <form onSubmit={(event) => void handleSubmit(event)} className="grid gap-3 sm:grid-cols-2">
            {mode === 'create' ? (
              <div className="flex flex-wrap gap-2 sm:col-span-2">
                {CALENDAR_QUICK_TYPES.map((entry) => (
                  <button
                    key={entry}
                    type="button"
                    onClick={() => setType(entry)}
                    className={`rounded-full border px-3 py-1 text-xs ${
                      type === entry ? 'border-amber-400 bg-amber-50 text-amber-950' : 'border-slate-200 bg-white'
                    }`}
                  >
                    {calendarTypeLabel(entry)}
                  </button>
                ))}
              </div>
            ) : null}

            {mode === 'create' && templates.length > 0 ? (
              <label className="space-y-1 sm:col-span-2">
                <span className="text-xs text-slate-600">قالب</span>
                <select value={templateId} onChange={(e) => applyTemplate(e.target.value)} className={fieldClass}>
                  <option value="">بدون قالب</option>
                  {templates.map((template) => (
                    <option key={template.id} value={template.id}>
                      {template.name}
                    </option>
                  ))}
                </select>
              </label>
            ) : null}

            <label className="space-y-1 sm:col-span-2">
              <span className="text-xs text-slate-600">العنوان *</span>
              <input required value={title} onChange={(event) => setTitle(event.target.value)} className={fieldClass} />
            </label>
            <label className="space-y-1 sm:col-span-2">
              <span className="text-xs text-slate-600">الوصف</span>
              <textarea rows={3} value={description} onChange={(event) => setDescription(event.target.value)} className={fieldClass} />
            </label>
            <label className="space-y-1">
              <span className="text-xs text-slate-600">النوع</span>
              <select value={type} onChange={(event) => setType(event.target.value)} className={fieldClass}>
                {CALENDAR_TYPES.map((entry) => (
                  <option key={entry} value={entry}>{calendarTypeLabel(entry)}</option>
                ))}
              </select>
            </label>
            <label className="space-y-1">
              <span className="text-xs text-slate-600">الحالة</span>
              <select value={status} onChange={(event) => setStatus(event.target.value)} className={fieldClass} disabled={linkedReadOnly}>
                {CALENDAR_STATUSES.map((entry) => (
                  <option key={entry} value={entry}>{calendarStatusLabel(entry)}</option>
                ))}
              </select>
            </label>
            <label className="space-y-1">
              <span className="text-xs text-slate-600">الأولوية</span>
              <select value={priority} onChange={(event) => setPriority(event.target.value)} className={fieldClass}>
                {CALENDAR_PRIORITIES.map((entry) => (
                  <option key={entry} value={entry}>{calendarPriorityLabel(entry)}</option>
                ))}
              </select>
            </label>
            <label className="space-y-1">
              <span className="text-xs text-slate-600">الظهور</span>
              <select value={visibility} onChange={(event) => setVisibility(event.target.value)} className={fieldClass}>
                {CALENDAR_VISIBILITIES.map((entry) => (
                  <option key={entry} value={entry}>{calendarVisibilityLabel(entry)}</option>
                ))}
              </select>
            </label>
            <label className="space-y-1">
              <span className="text-xs text-slate-600">البداية *</span>
              <input
                required
                type="datetime-local"
                value={startsAt}
                onChange={(event) => setStartsAt(event.target.value)}
                className={fieldClass}
                disabled={linkedReadOnly}
              />
            </label>
            <label className="space-y-1">
              <span className="text-xs text-slate-600">النهاية</span>
              <input
                type="datetime-local"
                value={endsAt}
                onChange={(event) => setEndsAt(event.target.value)}
                className={fieldClass}
                disabled={linkedReadOnly}
              />
            </label>
            <label className="flex items-center gap-2 sm:col-span-2">
              <input type="checkbox" checked={allDay} onChange={(event) => setAllDay(event.target.checked)} disabled={linkedReadOnly} />
              <span className="text-sm">يوم كامل</span>
            </label>
            <label className="space-y-1">
              <span className="text-xs text-slate-600">الموقع</span>
              <input value={location} onChange={(e) => setLocation(e.target.value)} className={fieldClass} />
            </label>
            <label className="space-y-1">
              <span className="text-xs text-slate-600">رابط الاجتماع</span>
              <input value={meetingUrl} onChange={(e) => setMeetingUrl(e.target.value)} className={fieldClass} />
            </label>
            <label className="space-y-1">
              <span className="text-xs text-slate-600">التكرار</span>
              <select value={recurrenceFreq} onChange={(e) => setRecurrenceFreq(e.target.value as typeof recurrenceFreq)} className={fieldClass}>
                <option value="">بدون تكرار</option>
                {CALENDAR_RECURRENCE_FREQS.map((entry) => (
                  <option key={entry} value={entry}>{calendarRecurrenceLabel(entry)}</option>
                ))}
              </select>
            </label>
            <label className="space-y-1">
              <span className="text-xs text-slate-600">حتى تاريخ</span>
              <input type="date" value={recurrenceUntil} onChange={(e) => setRecurrenceUntil(e.target.value)} className={fieldClass} disabled={!recurrenceFreq} />
            </label>
            <label className="space-y-1 sm:col-span-2">
              <span className="text-xs text-slate-600">عدد التكرارات (اختياري)</span>
              <input type="number" min={1} value={recurrenceCount} onChange={(e) => setRecurrenceCount(e.target.value)} className={fieldClass} disabled={!recurrenceFreq} />
            </label>

            {mode === 'create' ? (
              <div className="space-y-2 sm:col-span-2">
                <span className="text-xs text-slate-600">قائمة تحقق أولية</span>
                <div className="flex gap-2">
                  <input value={checklistText} onChange={(e) => setChecklistText(e.target.value)} className={fieldClass} placeholder="بند..." />
                  <button
                    type="button"
                    className="min-h-10 shrink-0 rounded-xl border px-3 text-sm"
                    onClick={() => {
                      if (!checklistText.trim()) return
                      setChecklistDraft((current) => [...current, { text: checklistText.trim(), done: false }])
                      setChecklistText('')
                    }}
                  >
                    إضافة
                  </button>
                </div>
                <ul className="space-y-1 text-xs">
                  {checklistDraft.map((row, index) => (
                    <li key={`draft-${index}`} className="flex justify-between gap-2">
                      <span>{row.text}</span>
                      <button type="button" className="text-red-700" onClick={() => setChecklistDraft((c) => c.filter((_, i) => i !== index))}>
                        حذف
                      </button>
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}

            <fieldset className="space-y-2 sm:col-span-2">
              <legend className="text-xs text-slate-600">المعيّنون</legend>
              <div className="flex max-h-36 flex-wrap gap-2 overflow-y-auto rounded-xl border border-slate-200 p-2">
                {assignees.length === 0 ? (
                  <p className="text-xs text-slate-500">لا يوجد أشخاص للتعيين.</p>
                ) : (
                  assignees.map((entry) => (
                    <label
                      key={entry.id}
                      className={`inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1 text-xs ${
                        assigneeIds.includes(entry.id) ? 'border-amber-400 bg-amber-50 text-amber-950' : 'border-slate-200 bg-white'
                      }`}
                    >
                      <input type="checkbox" className="sr-only" checked={assigneeIds.includes(entry.id)} onChange={() => toggleAssignee(entry.id)} />
                      {entry.name}
                    </label>
                  ))
                )}
              </div>
            </fieldset>

            <fieldset className="space-y-2 sm:col-span-2">
              <legend className="text-xs text-slate-600">التذكيرات</legend>
              <div className="flex flex-wrap gap-2">
                {CALENDAR_REMINDERS.map((entry) => (
                  <label
                    key={entry}
                    className={`inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1 text-xs ${
                      reminders.includes(entry) ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white'
                    }`}
                  >
                    <input type="checkbox" className="sr-only" checked={reminders.includes(entry)} onChange={() => toggleReminder(entry)} />
                    {calendarReminderLabel(entry)}
                  </label>
                ))}
              </div>
            </fieldset>

            <div className="flex flex-wrap gap-2 sm:col-span-2">
              <button type="submit" disabled={busy} className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white disabled:opacity-60">
                حفظ
              </button>
              <button type="button" className="min-h-11 rounded-xl border px-4 text-sm" onClick={onClose}>
                إلغاء
              </button>
            </div>
          </form>
        ) : null}
      </div>
    </div>
  )
}
