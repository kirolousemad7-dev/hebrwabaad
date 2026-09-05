import { FormEvent, useMemo, useState } from 'react'
import { FeedbackBanner } from '../ui/FeedbackBanner'
import { WorkspaceEmptyState, WorkspaceErrorState } from '../workspace/WorkspaceStatus'
import { useToast } from '../../context/ToastContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import { downloadManagedFile, getManagedFiles, previewManagedFile, uploadManagedFile, type FileScope } from '../../services/files'
import type { ManagedFileItem } from '../../types/api'
import { describeApiError } from '../../utils/errors'
import { FILE_ACCEPT, FILE_COPY, fileContextLabel, formatFileSize } from '../../utils/files'
import { formatDashboardDateTime } from '../../utils/ownerDashboard'

export type FileUploadContextOption = {
  id: number
  label: string
}

type FileLibraryProps = {
  scope: FileScope
  query?: string
  canUpload?: boolean
  projects?: FileUploadContextOption[]
  orders?: FileUploadContextOption[]
  tasks?: FileUploadContextOption[]
}

export function FileLibrary({
  scope,
  query = '',
  canUpload = true,
  projects = [],
  orders = [],
  tasks = [],
}: FileLibraryProps) {
  const toast = useToast()
  const { state, reload } = useAsyncData(() => getManagedFiles(scope, query || '?per_page=15'))
  const [file, setFile] = useState<File | null>(null)
  const [context, setContext] = useState('')
  const [uploading, setUploading] = useState(false)
  const [feedback, setFeedback] = useState<{ kind: 'success' | 'error'; text: string } | null>(null)

  const contextOptions = useMemo(() => {
    const items: Array<{ value: string; label: string }> = []
    for (const project of projects) {
      items.push({ value: `project:${project.id}`, label: `مشروع · ${project.label}` })
    }
    for (const order of orders) {
      items.push({ value: `order:${order.id}`, label: `طلب · ${order.label}` })
    }
    for (const task of tasks) {
      items.push({ value: `task:${task.id}`, label: `مهمة · ${task.label}` })
    }
    return items
  }, [projects, orders, tasks])

  async function onUpload(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFeedback(null)

    if (!file) {
      setFeedback({ kind: 'error', text: FILE_COPY.chooseFile })
      return
    }

    const [kind, rawId] = context.split(':')
    if (!kind || !rawId) {
      setFeedback({ kind: 'error', text: FILE_COPY.contextRequired })
      return
    }

    const body = new FormData()
    body.append('file', file)
    if (kind === 'project') {
      body.append('project_id', rawId)
    }
    if (kind === 'order') {
      body.append('order_id', rawId)
    }
    if (kind === 'task') {
      body.append('task_id', rawId)
    }

    setUploading(true)
    try {
      await uploadManagedFile(scope, body)
      setFile(null)
      setFeedback({ kind: 'success', text: FILE_COPY.success })
      toast.success(FILE_COPY.success)
      await reload()
    } catch (caught) {
      setFeedback({ kind: 'error', text: describeApiError(caught, 'تعذر رفع الملف.') })
    } finally {
      setUploading(false)
    }
  }

  async function onDownload(item: ManagedFileItem) {
    try {
      await downloadManagedFile(scope, item)
    } catch (caught) {
      setFeedback({ kind: 'error', text: describeApiError(caught, 'تعذر تنزيل الملف.') })
    }
  }

  async function onPreview(item: ManagedFileItem) {
    try {
      await previewManagedFile(scope, item)
    } catch (caught) {
      setFeedback({ kind: 'error', text: describeApiError(caught, 'تعذر عرض الملف.') })
    }
  }

  return (
    <section className="space-y-6">
      {canUpload && contextOptions.length > 0 ? (
        <form onSubmit={(event) => void onUpload(event)} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
          <div className="grid gap-4 md:grid-cols-2">
            <label className="block text-sm">
              <span className="font-medium">السياق</span>
              <select
                value={context}
                onChange={(event) => setContext(event.target.value)}
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
              >
                <option value="">اختر المشروع أو الطلب أو المهمة</option>
                {contextOptions.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-sm">
              <span className="font-medium">{FILE_COPY.chooseFile}</span>
              <input
                type="file"
                accept={FILE_ACCEPT}
                onChange={(event) => setFile(event.target.files?.[0] ?? null)}
                className="mt-1 w-full text-sm file:me-3 file:rounded-lg file:border-0 file:bg-slate-900 file:px-3 file:py-2 file:text-white"
              />
              {file ? (
                <span className="mt-1 block truncate text-xs text-slate-500">
                  {file.name} · {formatFileSize(file.size)}
                </span>
              ) : null}
            </label>
          </div>
          <button
            type="submit"
            disabled={uploading}
            className="inline-flex min-h-11 items-center rounded-lg bg-slate-900 px-4 text-sm font-medium text-white disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          >
            {uploading ? FILE_COPY.uploading : FILE_COPY.upload}
          </button>
        </form>
      ) : null}

      {feedback ? <FeedbackBanner kind={feedback.kind}>{feedback.text}</FeedbackBanner> : null}

      {state.status === 'loading' ? (
        <div className="space-y-2" aria-busy="true" aria-label={FILE_COPY.loading}>
          {[0, 1, 2].map((index) => (
            <div key={index} className="h-16 animate-pulse rounded-xl bg-slate-100" />
          ))}
        </div>
      ) : null}

      {state.status === 'error' ? (
        <WorkspaceErrorState message={FILE_COPY.error} onRetry={() => void reload()} />
      ) : null}

      {state.status === 'ready' && state.data.items.length === 0 ? (
        <WorkspaceEmptyState
          title={FILE_COPY.empty}
          description="ستظهر هنا الملفات المرتبطة بالمشاريع أو الطلبات أو المهام المصرّح لك بها."
        />
      ) : null}

      {state.status === 'ready' && state.data.items.length > 0 ? (
        <ul className="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white">
          {state.data.items.map((item) => (
            <li key={item.id} className="flex min-w-0 flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
              <div className="min-w-0">
                <p className="truncate font-medium">{item.original_name}</p>
                <p className="mt-1 text-sm text-slate-500">
                  {item.extension.toUpperCase()} · {formatFileSize(item.size)} · {fileContextLabel(item)} ·{' '}
                  {formatDashboardDateTime(item.created_at)}
                </p>
              </div>
              <div className="flex flex-wrap gap-2">
                {item.can_preview ? (
                  <button
                    type="button"
                    onClick={() => void onPreview(item)}
                    className="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                  >
                    {FILE_COPY.preview}
                  </button>
                ) : null}
                <button
                  type="button"
                  onClick={() => void onDownload(item)}
                  className="inline-flex min-h-11 items-center rounded-lg bg-slate-900 px-3 text-sm text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                >
                  {FILE_COPY.download}
                </button>
              </div>
            </li>
          ))}
        </ul>
      ) : null}
    </section>
  )
}
