import { FormEvent, useEffect, useState } from 'react'
import { useAuth } from '../../../context/AuthContext'
import {
  createWorkspaceNote,
  getWorkspaceNotes,
  type WorkspaceNote,
} from '../../../services/operations'
import { formatDateTimeShort } from '../../../utils/calendarDates'
import { describeApiError } from '../../../utils/errors'
import { projectInitials } from '../../../utils/workspaceProjects'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

type Props = {
  projectId: number
  taskId?: number | null
  canWrite: boolean
  title?: string
  limit?: number
  onShowAll?: () => void
}

export function ProjectNotesThread({ projectId, taskId = null, canWrite, title, limit, onShowAll }: Props) {
  const { user } = useAuth()
  const [items, setItems] = useState<WorkspaceNote[]>([])
  const [body, setBody] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    getWorkspaceNotes(projectId, taskId)
      .then((response) => {
        if (!cancelled) {
          setItems(response.data.items ?? [])
          setError(null)
        }
      })
      .catch((caught) => {
        if (!cancelled) setError(describeApiError(caught, 'تعذر تحميل الملاحظات.'))
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [projectId, taskId])

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    const text = body.trim()
    if (!text || saving) return
    setSaving(true)
    setError(null)
    try {
      const response = await createWorkspaceNote(projectId, { body: text, task_id: taskId })
      setItems((current) => [...current, response.data])
      setBody('')
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ الملاحظة.'))
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-3">
      {title ? <h3 className="text-sm font-semibold text-slate-900">{title}</h3> : null}
      <p className="text-xs text-slate-500">الملاحظات تُضاف بالترتيب من الأقدم إلى الأحدث ولا تُستبدل.</p>
      {error ? <p className="text-sm text-red-700">{error}</p> : null}
      {loading ? <p className="text-sm text-slate-500">جاري تحميل الملاحظات...</p> : null}
      {!loading && items.length === 0 ? (
        <p className="rounded-xl border border-dashed border-slate-200 bg-[#f4f6f8] px-3 py-4 text-sm text-slate-500">
          لا توجد ملاحظات بعد
        </p>
      ) : null}
      <ul className="space-y-2">
        {(limit ? items.slice(-limit) : items).map((note) => {
          const mine = user?.id != null && note.author?.id === user.id
          return (
            <li
              key={note.id}
              className={`rounded-xl border px-3 py-2.5 ${mine ? 'border-brand-cobalt-100 bg-brand-cobalt-100/40' : 'border-slate-200 bg-white'}`}
            >
              <div className="flex items-start gap-2">
                <span className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[11px] font-semibold text-slate-700">
                  {projectInitials(note.author?.name ?? 'م')}
                </span>
                <div className="min-w-0">
                  <p className="text-xs text-slate-500">
                    <span className="font-medium text-slate-800">{note.author?.name ?? 'مستخدم'}</span>
                    {' · '}
                    {formatDateTimeShort(note.created_at)}
                  </p>
                  <p className="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-800">{note.body}</p>
                </div>
              </div>
            </li>
          )
        })}
      </ul>
      {limit && items.length > limit && onShowAll ? (
        <button type="button" onClick={onShowAll} className="text-sm text-brand-cobalt-700 underline">
          عرض كل الملاحظات
        </button>
      ) : null}
      {canWrite && !limit ? (
        <form onSubmit={(event) => void onSubmit(event)} className="space-y-2">
          <textarea
            className={fieldClass}
            rows={3}
            value={body}
            onChange={(event) => setBody(event.target.value)}
            placeholder="أضف ملاحظة"
          />
          <button
            type="submit"
            disabled={saving || body.trim() === ''}
            className="inline-flex min-h-10 items-center rounded-lg bg-brand-cobalt-500 px-3 text-sm font-medium text-white transition hover:bg-brand-cobalt-700 disabled:opacity-50"
          >
            إضافة ملاحظة
          </button>
        </form>
      ) : null}
      {!canWrite && !limit ? <p className="text-xs text-slate-500">لا تملك صلاحية إضافة ملاحظة هنا.</p> : null}
    </div>
  )
}
