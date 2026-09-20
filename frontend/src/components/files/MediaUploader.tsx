import { DragEvent, useEffect, useMemo, useRef, useState } from 'react'
import { FeedbackBanner } from '../ui/FeedbackBanner'
import {
  MEDIA_ACCEPT,
  deleteMedia,
  downloadMedia,
  duplicateMedia,
  formatMediaSize,
  listEntityMedia,
  previewMedia,
  setPrimaryMedia,
  uploadMedia,
  type MediaEntityType,
  type MediaItem,
  type MediaVisibility,
} from '../../services/media'
import { describeApiError } from '../../utils/errors'

type UploadRow = {
  key: string
  file: File
  progress: number
  status: 'queued' | 'uploading' | 'done' | 'error' | 'cancelled'
  error?: string
  previewUrl?: string
  controller?: AbortController
  attempts: number
}

type Props = {
  entityType: MediaEntityType
  entityId: number
  title?: string
  visibility?: MediaVisibility
  canManage?: boolean
}

function isAllowed(file: File, maxBytes: number): string | null {
  const ext = file.name.split('.').pop()?.toLowerCase() ?? ''
  const allowed = MEDIA_ACCEPT.split(',').map((part) => part.replace('.', '').trim())
  if (!allowed.includes(ext)) {
    return 'نوع الملف غير مسموح.'
  }
  if (file.size > maxBytes) {
    return 'حجم الملف أكبر من الحد المسموح.'
  }
  return null
}

export function MediaUploader({
  entityType,
  entityId,
  title = 'الملفات',
  visibility,
  canManage = true,
}: Props) {
  const inputRef = useRef<HTMLInputElement>(null)
  const [items, setItems] = useState<MediaItem[]>([])
  const [queue, setQueue] = useState<UploadRow[]>([])
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [dragOver, setDragOver] = useState(false)
  const [busyId, setBusyId] = useState<number | null>(null)
  const maxBytes = 40 * 1024 * 1024

  async function reload() {
    setError(null)
    try {
      const response = await listEntityMedia(entityType, entityId)
      setItems(response.data.items)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل الملفات.'))
    }
  }

  useEffect(() => {
    void reload()
  }, [entityType, entityId])

  useEffect(() => {
    return () => {
      for (const row of queue) {
        if (row.previewUrl) URL.revokeObjectURL(row.previewUrl)
      }
    }
  }, [queue])

  const pending = useMemo(() => queue.filter((row) => row.status === 'uploading' || row.status === 'queued'), [queue])

  function enqueue(files: FileList | File[]) {
    const next: UploadRow[] = []
    for (const file of Array.from(files)) {
      const validation = isAllowed(file, maxBytes)
      const previewUrl = file.type.startsWith('image/') || file.type === 'application/pdf' || file.type.startsWith('video/')
        ? URL.createObjectURL(file)
        : undefined
      next.push({
        key: `${file.name}-${file.size}-${file.lastModified}-${Math.random()}`,
        file,
        progress: 0,
        status: validation ? 'error' : 'queued',
        error: validation ?? undefined,
        previewUrl,
        attempts: 0,
      })
    }
    setQueue((current) => [...next, ...current])
    for (const row of next) {
      if (row.status === 'queued') {
        void runUpload(row)
      }
    }
  }

  async function runUpload(row: UploadRow) {
    const controller = new AbortController()
    setQueue((current) =>
      current.map((item) =>
        item.key === row.key
          ? { ...item, status: 'uploading', progress: 0, controller, attempts: item.attempts + 1, error: undefined }
          : item,
      ),
    )

    try {
      await uploadMedia({
        file: row.file,
        entityType,
        entityId,
        visibility,
        signal: controller.signal,
        onProgress: (percent) => {
          setQueue((current) =>
            current.map((item) => (item.key === row.key ? { ...item, progress: percent } : item)),
          )
        },
      })
      setQueue((current) =>
        current.map((item) => (item.key === row.key ? { ...item, status: 'done', progress: 100, controller: undefined } : item)),
      )
      setNotice('تم رفع الملف وربطه تلقائياً.')
      await reload()
    } catch (caught) {
      const message = describeApiError(caught, 'تعذر رفع الملف.')
      const cancelled = message.toLowerCase().includes('cancel')
      setQueue((current) =>
        current.map((item) =>
          item.key === row.key
            ? {
                ...item,
                status: cancelled ? 'cancelled' : 'error',
                error: cancelled ? 'تم الإلغاء' : message,
                controller: undefined,
              }
            : item,
        ),
      )
    }
  }

  function cancelUpload(key: string) {
    setQueue((current) => {
      const target = current.find((item) => item.key === key)
      target?.controller?.abort()
      return current.map((item) =>
        item.key === key ? { ...item, status: 'cancelled', error: 'تم الإلغاء', controller: undefined } : item,
      )
    })
  }

  function retryUpload(key: string) {
    const target = queue.find((item) => item.key === key)
    if (!target) return
    void runUpload(target)
  }

  function onDrop(event: DragEvent<HTMLDivElement>) {
    event.preventDefault()
    setDragOver(false)
    if (!canManage) return
    if (event.dataTransfer.files?.length) {
      enqueue(event.dataTransfer.files)
    }
  }

  async function onDownload(item: MediaItem) {
    try {
      await downloadMedia(item)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تنزيل الملف.'))
    }
  }

  async function onPreview(item: MediaItem) {
    try {
      await previewMedia(item)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر فتح المعاينة.'))
    }
  }

  async function onDelete(item: MediaItem) {
    if (!canManage || busyId) return
    setBusyId(item.id)
    try {
      await deleteMedia(item.id)
      setNotice('تم حذف الملف.')
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حذف الملف.'))
    } finally {
      setBusyId(null)
    }
  }

  async function onDuplicate(item: MediaItem) {
    if (!canManage || busyId) return
    setBusyId(item.id)
    try {
      await duplicateMedia(item.id)
      setNotice('تم نسخ الملف.')
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر نسخ الملف.'))
    } finally {
      setBusyId(null)
    }
  }

  async function onSetPrimary(item: MediaItem) {
    if (!canManage || busyId || item.is_primary) return
    setBusyId(item.id)
    try {
      await setPrimaryMedia(item.id)
      setNotice('تم تعيين الصورة الأساسية.')
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تعيين الصورة الأساسية.'))
    } finally {
      setBusyId(null)
    }
  }

  return (
    <section className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <header className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 className="text-lg font-semibold text-slate-900">{title}</h2>
          <p className="text-sm text-slate-600">يُرفع الملف مرة واحدة ويُربط تلقائياً بهذا السجل.</p>
        </div>
        {pending.length > 0 ? <span className="text-xs text-slate-500">{pending.length} قيد الرفع</span> : null}
      </header>

      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}

      {canManage ? (
        <div
          onDragOver={(event) => {
            event.preventDefault()
            setDragOver(true)
          }}
          onDragLeave={() => setDragOver(false)}
          onDrop={onDrop}
          className={`rounded-xl border-2 border-dashed px-4 py-8 text-center transition ${
            dragOver ? 'border-[#315CFF] bg-blue-50' : 'border-slate-300 bg-slate-50'
          }`}
        >
          <p className="text-sm text-slate-700">اسحب الملفات هنا أو اختر من جهازك</p>
          <p className="mt-1 text-xs text-slate-500">PDF، صور، مستندات مكتبية، فيديو · حتى 40MB</p>
          <button
            type="button"
            className="mt-3 rounded-lg bg-slate-900 px-3 py-2 text-sm text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
            onClick={() => inputRef.current?.click()}
          >
            اختيار ملفات
          </button>
          <input
            ref={inputRef}
            type="file"
            multiple
            accept={MEDIA_ACCEPT}
            className="hidden"
            onChange={(event) => {
              if (event.target.files?.length) {
                enqueue(event.target.files)
                event.target.value = ''
              }
            }}
          />
        </div>
      ) : null}

      {queue.length > 0 ? (
        <ul className="space-y-2">
          {queue.map((row) => (
            <li key={row.key} className="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                  <p className="truncate font-medium text-slate-900">{row.file.name}</p>
                  <p className="text-xs text-slate-500">{formatMediaSize(row.file.size)}</p>
                  {row.previewUrl && row.file.type.startsWith('image/') ? (
                    <img src={row.previewUrl} alt="" className="mt-2 h-16 w-16 rounded object-cover" />
                  ) : null}
                  {row.previewUrl && row.file.type === 'application/pdf' ? (
                    <p className="mt-2 text-xs text-slate-600">معاينة PDF جاهزة بعد الرفع</p>
                  ) : null}
                  {row.file.type.startsWith('video/') ? (
                    <p className="mt-2 text-xs text-slate-600">فيديو · {row.file.type} · {formatMediaSize(row.file.size)}</p>
                  ) : null}
                  {row.status === 'uploading' || row.status === 'done' ? (
                    <div className="mt-2 h-2 overflow-hidden rounded bg-slate-200">
                      <div className="h-full bg-[#315CFF] transition-all" style={{ width: `${row.progress}%` }} />
                    </div>
                  ) : null}
                  {row.error ? <p className="mt-1 text-xs text-red-700">{row.error}</p> : null}
                </div>
                <div className="flex flex-wrap gap-2">
                  {row.status === 'uploading' ? (
                    <button type="button" className="text-xs underline" onClick={() => cancelUpload(row.key)}>
                      إلغاء
                    </button>
                  ) : null}
                  {row.status === 'error' || row.status === 'cancelled' ? (
                    <button type="button" className="text-xs underline" onClick={() => retryUpload(row.key)}>
                      إعادة المحاولة
                    </button>
                  ) : null}
                </div>
              </div>
            </li>
          ))}
        </ul>
      ) : null}

      <ul className="space-y-2">
        {items.length === 0 ? (
          <li className="rounded-xl border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500">
            لا توجد ملفات بعد.
          </li>
        ) : (
          items.map((item) => (
            <li key={item.id} className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm">
              <div className="min-w-0">
                <p className="truncate font-medium text-slate-900">
                  {item.original_name}
                  {item.is_primary ? (
                    <span className="ms-2 rounded bg-emerald-50 px-1.5 py-0.5 text-[11px] font-medium text-emerald-800">
                      أساسي
                    </span>
                  ) : null}
                </p>
                <p className="text-xs text-slate-500">
                  {formatMediaSize(item.size)} · {item.visibility}
                  {item.is_image && item.metadata?.width ? ` · ${String(item.metadata.width)}×${String(item.metadata.height)}` : ''}
                  {item.is_video ? ' · فيديو' : ''}
                </p>
              </div>
              <div className="flex flex-wrap gap-2">
                {item.can_preview ? (
                  <button type="button" className="underline" onClick={() => void onPreview(item)}>
                    معاينة
                  </button>
                ) : null}
                <button type="button" className="underline" onClick={() => void onDownload(item)}>
                  تنزيل
                </button>
                {canManage ? (
                  <>
                    {item.is_image && !item.is_primary ? (
                      <button type="button" className="underline" disabled={busyId === item.id} onClick={() => void onSetPrimary(item)}>
                        تعيين كأساسي
                      </button>
                    ) : null}
                    <button type="button" className="underline" disabled={busyId === item.id} onClick={() => void onDuplicate(item)}>
                      نسخ
                    </button>
                    <button type="button" className="underline text-red-700" disabled={busyId === item.id} onClick={() => void onDelete(item)}>
                      حذف
                    </button>
                  </>
                ) : null}
              </div>
            </li>
          ))
        )}
      </ul>
    </section>
  )
}
