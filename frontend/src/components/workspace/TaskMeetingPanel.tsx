import { FormEvent, useEffect, useState } from 'react'
import { FeedbackBanner } from '../ui/FeedbackBanner'
import {
  cancelMeeting,
  createMeeting,
  listMeetingProviders,
  listMeetings,
  type MeetingProvider,
  type MeetingProviderInfo,
  type VideoMeeting,
} from '../../services/meetings'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#315CFF]'

type Props = {
  taskId: number
  taskTitle?: string
  projectId?: number | null
}

export function TaskMeetingPanel({ taskId, taskTitle, projectId }: Props) {
  const [providers, setProviders] = useState<MeetingProviderInfo[]>([])
  const [meetings, setMeetings] = useState<VideoMeeting[]>([])
  const [provider, setProvider] = useState<MeetingProvider | string>('NONE')
  const [title, setTitle] = useState(taskTitle ? `اجتماع: ${taskTitle}` : 'اجتماع مهمة')
  const [startAt, setStartAt] = useState('')
  const [endAt, setEndAt] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [copiedId, setCopiedId] = useState<number | null>(null)

  async function load() {
    setError(null)
    try {
      const [providerResponse, meetingResponse] = await Promise.all([
        listMeetingProviders(),
        listMeetings(`?task_id=${taskId}`),
      ])
      setProviders(providerResponse.data.providers)
      setMeetings(meetingResponse.data.items)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل الاجتماعات.'))
    }
  }

  useEffect(() => {
    void load()
  }, [taskId])

  async function handleCreate(event: FormEvent) {
    event.preventDefault()
    if (busy) return
    setBusy(true)
    setError(null)
    setNotice(null)
    try {
      await createMeeting({
        provider,
        title: title.trim() || 'اجتماع',
        start_at: new Date(startAt).toISOString(),
        end_at: endAt ? new Date(endAt).toISOString() : undefined,
        task_id: taskId,
        project_id: projectId ?? undefined,
      })
      setNotice('تم إنشاء الاجتماع.')
      setStartAt('')
      setEndAt('')
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إنشاء الاجتماع.'))
    } finally {
      setBusy(false)
    }
  }

  async function handleCancel(id: number) {
    if (busy) return
    setBusy(true)
    setError(null)
    try {
      await cancelMeeting(id)
      setNotice('تم إلغاء الاجتماع.')
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إلغاء الاجتماع.'))
    } finally {
      setBusy(false)
    }
  }

  async function copyLink(meeting: VideoMeeting) {
    if (!meeting.join_url) return
    try {
      await navigator.clipboard.writeText(meeting.join_url)
      setCopiedId(meeting.id)
      setTimeout(() => setCopiedId(null), 2000)
    } catch {
      setError('تعذر نسخ الرابط.')
    }
  }

  return (
    <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-4">
      <h2 className="text-sm font-semibold text-[#111318]">الاجتماعات</h2>
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}

      <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => void handleCreate(event)}>
        <label className="space-y-1 text-sm sm:col-span-2">
          <span>إنشاء اجتماع</span>
          <select className={fieldClass} value={provider} onChange={(e) => setProvider(e.target.value)}>
            {providers.map((item) => (
              <option key={item.provider} value={item.provider} disabled={!item.configured && item.provider !== 'NONE'}>
                {item.label_ar}
                {!item.configured && item.provider !== 'NONE' ? ' (غير مُعد)' : ''}
              </option>
            ))}
          </select>
        </label>
        <label className="space-y-1 text-sm sm:col-span-2">
          <span>العنوان</span>
          <input className={fieldClass} value={title} onChange={(e) => setTitle(e.target.value)} required />
        </label>
        <label className="space-y-1 text-sm">
          <span>البداية</span>
          <input className={fieldClass} type="datetime-local" value={startAt} onChange={(e) => setStartAt(e.target.value)} required />
        </label>
        <label className="space-y-1 text-sm">
          <span>النهاية</span>
          <input className={fieldClass} type="datetime-local" value={endAt} onChange={(e) => setEndAt(e.target.value)} />
        </label>
        <div className="sm:col-span-2">
          <button
            type="submit"
            disabled={busy}
            className="rounded-lg bg-[#315CFF] px-4 py-2 text-sm text-white disabled:opacity-50"
          >
            إنشاء اجتماع
          </button>
        </div>
      </form>

      {meetings.length === 0 ? (
        <p className="text-sm text-slate-500">لا توجد اجتماعات مرتبطة بهذه المهمة.</p>
      ) : (
        <ul className="space-y-3">
          {meetings.map((meeting) => (
            <li key={meeting.id} className="rounded-xl border border-slate-100 bg-[#F7F5EF] p-3 text-sm">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <p className="font-medium text-[#111318]">{meeting.title}</p>
                  <p className="text-xs text-slate-600">
                    {meeting.provider_label_ar || meeting.provider} · {meeting.status_label_ar || meeting.status}
                  </p>
                  {meeting.start_at ? (
                    <p className="mt-1 text-xs text-slate-500">{new Date(meeting.start_at).toLocaleString('ar')}</p>
                  ) : null}
                </div>
                <div className="flex flex-wrap gap-1">
                  {meeting.join_url ? (
                    <>
                      <a
                        href={meeting.join_url}
                        target="_blank"
                        rel="noreferrer"
                        className="rounded border border-slate-300 bg-white px-2 py-1 text-xs"
                      >
                        انضمام
                      </a>
                      <button
                        type="button"
                        className="rounded border border-slate-300 bg-white px-2 py-1 text-xs"
                        onClick={() => void copyLink(meeting)}
                      >
                        {copiedId === meeting.id ? 'تم النسخ' : 'نسخ الرابط'}
                      </button>
                    </>
                  ) : null}
                  {meeting.calendar_event_url ? (
                    <a
                      href={meeting.calendar_event_url}
                      target="_blank"
                      rel="noreferrer"
                      className="rounded border border-slate-300 bg-white px-2 py-1 text-xs"
                    >
                      فتح في التقويم
                    </a>
                  ) : null}
                  {meeting.can_manage && meeting.status !== 'CANCELLED' ? (
                    <button
                      type="button"
                      disabled={busy}
                      className="rounded border border-rose-300 bg-white px-2 py-1 text-xs text-rose-700 disabled:opacity-50"
                      onClick={() => void handleCancel(meeting.id)}
                    >
                      إلغاء
                    </button>
                  ) : null}
                </div>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
