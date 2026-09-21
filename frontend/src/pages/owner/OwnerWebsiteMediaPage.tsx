import { FormEvent, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useAsyncData } from '../../hooks/useAsyncData'
import {
  deleteOwnerMarketingMedia,
  listOwnerMarketingMedia,
  listOwnerMarketingMediaOrphans,
  replaceOwnerMarketingMedia,
  updateOwnerMarketingMedia,
  uploadOwnerMarketingMedia,
  type MarketingMedia,
  type MarketingMediaListResponse,
} from '../../services/marketingCms'
import { describeApiError } from '../../utils/errors'
import { formatBytes } from '../../utils/marketingCmsLabels'

type Tab = 'all' | 'orphans'

function asMediaListResponse(items: MarketingMedia[]): MarketingMediaListResponse {
  return {
    items,
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: items.length,
      total: items.length,
    },
  }
}

export function OwnerWebsiteMediaPage() {
  const [tab, setTab] = useState<Tab>('all')
  const listLoader = useMemo(
    () => async (): Promise<{ data: MarketingMediaListResponse }> => {
      if (tab === 'orphans') {
        const response = await listOwnerMarketingMediaOrphans()
        return { data: asMediaListResponse(response.data) }
      }
      const response = await listOwnerMarketingMedia({ per_page: 48 })
      return { data: response.data }
    },
    [tab],
  )
  const { state, reload } = useAsyncData(listLoader, [tab])

  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [editing, setEditing] = useState<MarketingMedia | null>(null)
  const [uploadFile, setUploadFile] = useState<File | null>(null)
  const [uploadPreview, setUploadPreview] = useState<string | null>(null)
  const [uploadAlt, setUploadAlt] = useState('')
  const [uploadTitle, setUploadTitle] = useState('')
  const [uploading, setUploading] = useState(false)
  const [replaceTarget, setReplaceTarget] = useState<MarketingMedia | null>(null)
  const [replaceFile, setReplaceFile] = useState<File | null>(null)
  const [replacePreview, setReplacePreview] = useState<string | null>(null)

  const items: MarketingMedia[] = state.status === 'ready' ? state.data.items : []

  function onPickUpload(file: File | null) {
    setUploadFile(file)
    if (uploadPreview) URL.revokeObjectURL(uploadPreview)
    setUploadPreview(file ? URL.createObjectURL(file) : null)
    if (file && !uploadTitle) setUploadTitle(file.name.replace(/\.[^.]+$/, ''))
  }

  function onPickReplace(file: File | null) {
    setReplaceFile(file)
    if (replacePreview) URL.revokeObjectURL(replacePreview)
    setReplacePreview(file ? URL.createObjectURL(file) : null)
  }

  async function handleUpload(event: FormEvent) {
    event.preventDefault()
    if (!uploadFile) {
      setError('اختر ملف صورة أولًا.')
      return
    }
    if (uploadFile.size > 8 * 1024 * 1024) {
      setError('الحد الأقصى لحجم الصورة 8 ميجابايت.')
      return
    }
    setUploading(true)
    setError(null)
    setMessage(null)
    try {
      const form = new FormData()
      form.append('file', uploadFile)
      form.append('alt_text', uploadAlt)
      form.append('title', uploadTitle || uploadFile.name)
      await uploadOwnerMarketingMedia(form)
      setMessage('تم رفع الصورة.')
      onPickUpload(null)
      setUploadAlt('')
      setUploadTitle('')
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر رفع الصورة.'))
    } finally {
      setUploading(false)
    }
  }

  async function saveEdit(event: FormEvent) {
    event.preventDefault()
    if (!editing) return
    setBusyId(editing.id)
    setError(null)
    setMessage(null)
    try {
      await updateOwnerMarketingMedia(editing.id, {
        alt_text: editing.alt_text,
        title: editing.title,
        is_active: editing.is_active,
      })
      setMessage('تم تحديث بيانات الصورة.')
      setEditing(null)
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحديث الصورة.'))
    } finally {
      setBusyId(null)
    }
  }

  async function handleReplace(event: FormEvent) {
    event.preventDefault()
    if (!replaceTarget || !replaceFile) return
    setBusyId(replaceTarget.id)
    setError(null)
    setMessage(null)
    try {
      const form = new FormData()
      form.append('file', replaceFile)
      form.append('alt_text', replaceTarget.alt_text)
      form.append('title', replaceTarget.title)
      const result = await replaceOwnerMarketingMedia(replaceTarget.id, form)
      setMessage(
        `تم رفع صورة بديلة (ID ${result.data.new.id}). الصورة السابقة بقيت محفوظة. حدّث مراجع المحتوى إلى الصورة الجديدة عند الحاجة. الاستبدال يظهر على الموقع العام بعد Phase 3.`,
      )
      setReplaceTarget(null)
      onPickReplace(null)
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر استبدال الصورة.'))
    } finally {
      setBusyId(null)
    }
  }

  async function handleDelete(media: MarketingMedia) {
    if (media.is_registry_only) {
      setError('لا يمكن حذف الصور الافتراضية (Registry). استخدم الاستبدال بدلًا من الحذف.')
      return
    }
    if (media.usage_count > 0) {
      setError(`لا يمكن حذف الصورة لأنها مستخدمة في ${media.usage_count} أماكن.`)
      return
    }
    if (!window.confirm(`حذف «${media.title || media.original_name}» نهائيًا؟`)) {
      return
    }
    setBusyId(media.id)
    setError(null)
    setMessage(null)
    try {
      await deleteOwnerMarketingMedia(media.id)
      setMessage('تم حذف الصورة.')
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حذف الصورة.'))
    } finally {
      setBusyId(null)
    }
  }

  return (
    <section className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-sm text-slate-500">
            <Link to="/owner/website" className="underline">
              إدارة الموقع
            </Link>
            <span aria-hidden> / </span>
            مكتبة الصور
          </p>
          <h1 className="mt-1 text-2xl font-semibold text-slate-900">مكتبة الصور</h1>
          <p className="text-sm text-slate-600">رفع واستبدال وإدارة صور المحتوى التسويقي بأمان.</p>
        </div>
        <nav className="flex flex-wrap gap-2" aria-label="تنقل إدارة الموقع">
          <Link
            to="/owner/website"
            className="inline-flex min-h-11 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm"
          >
            محتوى الموقع
          </Link>
          <span className="inline-flex min-h-11 items-center rounded-xl bg-slate-900 px-4 text-sm text-white">
            مكتبة الصور
          </span>
        </nav>
      </header>

      {message ? <FeedbackBanner kind="success">{message}</FeedbackBanner> : null}
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      <DashboardSection title="رفع صورة جديدة" description="صور فقط — بحد أقصى 8 ميجابايت.">
        <form onSubmit={handleUpload} className="grid gap-4 lg:grid-cols-[16rem_1fr]">
          <label className="flex aspect-[4/3] cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 text-sm text-slate-600">
            {uploadPreview ? (
              <img src={uploadPreview} alt="معاينة قبل الرفع" className="h-full w-full rounded-2xl object-cover" />
            ) : (
              <span>اختر صورة للمعاينة</span>
            )}
            <input
              type="file"
              accept="image/jpeg,image/png,image/webp,image/gif"
              className="sr-only"
              onChange={(event) => onPickUpload(event.target.files?.[0] ?? null)}
            />
          </label>
          <div className="space-y-3">
            <label className="block text-sm">
              <span className="font-medium text-slate-700">العنوان</span>
              <input
                value={uploadTitle}
                onChange={(event) => setUploadTitle(event.target.value)}
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              />
            </label>
            <label className="block text-sm">
              <span className="font-medium text-slate-700">النص البديل (Alt)</span>
              <input
                value={uploadAlt}
                onChange={(event) => setUploadAlt(event.target.value)}
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              />
            </label>
            {uploadFile ? (
              <p className="text-xs text-slate-500">
                {uploadFile.name} · {formatBytes(uploadFile.size)}
              </p>
            ) : null}
            <button
              type="submit"
              disabled={uploading || !uploadFile}
              className="inline-flex min-h-11 items-center rounded-xl bg-slate-900 px-5 text-sm text-white disabled:opacity-50"
            >
              {uploading ? 'جاري الرفع...' : 'رفع الصورة'}
            </button>
          </div>
        </form>
      </DashboardSection>

      <div className="flex flex-wrap gap-2">
        <button
          type="button"
          onClick={() => setTab('all')}
          className={`rounded-xl px-4 py-2 text-sm ${tab === 'all' ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white'}`}
        >
          كل الصور
        </button>
        <button
          type="button"
          onClick={() => setTab('orphans')}
          className={`rounded-xl px-4 py-2 text-sm ${tab === 'orphans' ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white'}`}
        >
          غير مستخدمة
        </button>
      </div>

      <DashboardSection
        title={tab === 'orphans' ? 'صور غير مستخدمة' : 'مكتبة الصور'}
        description={tab === 'orphans' ? 'يمكن حذفها بأمان إذا لم تكن افتراضية.' : undefined}
      >
        {state.status === 'loading' ? (
          <DashboardPanelSkeleton
            label={tab === 'orphans' ? 'جاري تحميل الصور غير المستخدمة...' : 'جاري تحميل مكتبة الصور...'}
          />
        ) : null}
        {state.status === 'error' ? (
          <DashboardErrorState message={state.message} onRetry={reload} />
        ) : null}
        {state.status === 'ready' && items.length === 0 ? (
          <DashboardEmptyState title="لا توجد صور" description="ارفع صورة أو شغّل الـseeder للصور الافتراضية." />
        ) : null}

        {state.status === 'ready' && items.length > 0 ? (
          <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {items.map((media) => {
              const busy = busyId === media.id
              const deleteBlocked = media.usage_count > 0 || media.is_registry_only
              return (
                <li key={media.id} className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                  <div className="aspect-[4/3] bg-slate-100">
                    {media.url ? (
                      <img src={media.url} alt={media.alt_text || media.title} className="h-full w-full object-cover" />
                    ) : null}
                  </div>
                  <div className="space-y-2 p-4">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                      <div>
                        <h2 className="font-semibold text-slate-900">{media.title || media.original_name}</h2>
                        <p className="text-xs text-slate-500">{media.original_name}</p>
                      </div>
                      {media.is_registry_only ? (
                        <span className="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] text-amber-800">
                          صورة افتراضية
                        </span>
                      ) : null}
                    </div>
                    <p className="text-xs text-slate-600">
                      {formatBytes(media.size)}
                      {media.width && media.height ? ` · ${media.width}×${media.height}` : ''}
                      {' · '}
                      مستخدمة في {media.usage_count} أماكن
                    </p>
                    <p className="line-clamp-2 text-sm text-slate-600">Alt: {media.alt_text || '—'}</p>
                    <div className="flex flex-wrap gap-2 pt-1">
                      <button
                        type="button"
                        disabled={busy}
                        onClick={() => setEditing(media)}
                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm"
                      >
                        تعديل
                      </button>
                      <button
                        type="button"
                        disabled={busy}
                        onClick={() => {
                          setReplaceTarget(media)
                          onPickReplace(null)
                        }}
                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm"
                      >
                        استبدال
                      </button>
                      <button
                        type="button"
                        disabled={busy || deleteBlocked}
                        title={
                          media.is_registry_only
                            ? 'لا يمكن حذف الصور الافتراضية'
                            : media.usage_count > 0
                              ? `لا يمكن حذف الصورة لأنها مستخدمة في ${media.usage_count} أماكن.`
                              : 'حذف'
                        }
                        onClick={() => void handleDelete(media)}
                        className="rounded-lg border border-rose-200 px-3 py-1.5 text-sm text-rose-700 disabled:opacity-40"
                      >
                        حذف
                      </button>
                    </div>
                  </div>
                </li>
              )
            })}
          </ul>
        ) : null}
      </DashboardSection>

      {editing ? (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-3 sm:items-center">
          <button type="button" className="absolute inset-0" aria-label="إغلاق" onClick={() => setEditing(null)} />
          <form
            onSubmit={saveEdit}
            role="dialog"
            aria-modal="true"
            aria-labelledby="edit-media-title"
            className="relative z-10 w-full max-w-md rounded-2xl bg-white p-5 shadow-xl"
          >
            <h2 id="edit-media-title" className="text-lg font-semibold">
              تعديل بيانات الصورة
            </h2>
            <label className="mt-4 block text-sm">
              العنوان
              <input
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                value={editing.title}
                onChange={(event) => setEditing({ ...editing, title: event.target.value })}
              />
            </label>
            <label className="mt-3 block text-sm">
              النص البديل
              <input
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                value={editing.alt_text}
                onChange={(event) => setEditing({ ...editing, alt_text: event.target.value })}
              />
            </label>
            <label className="mt-3 flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={editing.is_active}
                onChange={(event) => setEditing({ ...editing, is_active: event.target.checked })}
              />
              نشطة
            </label>
            <div className="mt-4 flex gap-2">
              <button type="submit" className="rounded-xl bg-slate-900 px-4 py-2 text-sm text-white">
                حفظ
              </button>
              <button type="button" onClick={() => setEditing(null)} className="rounded-xl border px-4 py-2 text-sm">
                إلغاء
              </button>
            </div>
          </form>
        </div>
      ) : null}

      {replaceTarget ? (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-3 sm:items-center">
          <button type="button" className="absolute inset-0" aria-label="إغلاق" onClick={() => setReplaceTarget(null)} />
          <form
            onSubmit={handleReplace}
            role="dialog"
            aria-modal="true"
            aria-labelledby="replace-media-title"
            className="relative z-10 w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl"
          >
            <h2 id="replace-media-title" className="text-lg font-semibold">
              استبدال الصورة
            </h2>
            <p className="mt-2 text-sm text-slate-600">
              سيتم رفع صورة جديدة دون حذف القديمة. استبدال الصورة سيؤثر على الموقع بعد ربط المحتوى في Phase 3.
            </p>
            <label className="mt-4 flex aspect-[4/3] cursor-pointer items-center justify-center overflow-hidden rounded-xl border border-dashed border-slate-300 bg-slate-50">
              {replacePreview ? (
                <img src={replacePreview} alt="معاينة الاستبدال" className="h-full w-full object-cover" />
              ) : (
                <span className="text-sm text-slate-600">اختر الصورة الجديدة</span>
              )}
              <input
                type="file"
                accept="image/jpeg,image/png,image/webp,image/gif"
                className="sr-only"
                onChange={(event) => onPickReplace(event.target.files?.[0] ?? null)}
              />
            </label>
            <div className="mt-4 flex gap-2">
              <button
                type="submit"
                disabled={!replaceFile || busyId === replaceTarget.id}
                className="rounded-xl bg-slate-900 px-4 py-2 text-sm text-white disabled:opacity-50"
              >
                تأكيد الاستبدال
              </button>
              <button type="button" onClick={() => setReplaceTarget(null)} className="rounded-xl border px-4 py-2 text-sm">
                إلغاء
              </button>
            </div>
          </form>
        </div>
      ) : null}
    </section>
  )
}
