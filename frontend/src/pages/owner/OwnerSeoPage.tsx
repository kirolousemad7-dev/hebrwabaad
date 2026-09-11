import { FormEvent, useEffect, useMemo, useState } from 'react'
import { DashboardErrorState, DashboardPanelSkeleton } from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getAdminSeoPages, updateAdminSeoPage } from '../../services/seo'
import { describeApiError } from '../../utils/errors'
import {
  descriptionLengthHint,
  SEO_PAGE_PATHS,
  titleLengthHint,
  type SeoPage,
  type SeoPageKey,
} from '../../utils/seo'

const EMPTY: Partial<SeoPage> = {
  title: '',
  description: '',
  keywords: '',
  canonical_url: '',
  og_title: '',
  og_description: '',
  og_image: '',
  twitter_title: '',
  twitter_description: '',
  twitter_image: '',
  robots: 'index,follow',
}

function formatUpdatedAt(value?: string | null) {
  if (!value) {
    return '—'
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return '—'
  }

  return new Intl.DateTimeFormat('ar', { dateStyle: 'medium', timeStyle: 'short' }).format(date)
}

function robotsLabel(value: string | null | undefined) {
  return value?.includes('noindex') ? 'noindex' : 'index'
}

export function OwnerSeoPage() {
  const { state, reload } = useAsyncData(getAdminSeoPages)
  const [selected, setSelected] = useState<SeoPageKey>('home')
  const [form, setForm] = useState(EMPTY)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const pages = state.status === 'ready' ? state.data : []
  const current = pages.find((page) => page.page_key === selected)

  useEffect(() => {
    if (!current) {
      return
    }

    setForm({
      title: current.title ?? '',
      description: current.description ?? '',
      keywords: current.keywords ?? '',
      canonical_url: current.canonical_url ?? '',
      og_title: current.og_title ?? '',
      og_description: current.og_description ?? '',
      og_image: current.og_image ?? '',
      twitter_title: current.twitter_title ?? '',
      twitter_description: current.twitter_description ?? '',
      twitter_image: current.twitter_image ?? '',
      robots: current.robots || 'index,follow',
    })
    setMessage(null)
    setError(null)
  }, [current])

  async function save(event: FormEvent) {
    event.preventDefault()
    setSaving(true)
    setError(null)
    setMessage(null)

    try {
      await updateAdminSeoPage(selected, form)
      setMessage('تم حفظ إعدادات SEO.')
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ إعدادات SEO.'))
    } finally {
      setSaving(false)
    }
  }

  const titleHint = titleLengthHint(String(form.title ?? ''))
  const descriptionHint = descriptionLengthHint(String(form.description ?? ''))
  const previewImage = form.og_image || '/brand/logo.png'
  const selectedPath = useMemo(() => SEO_PAGE_PATHS[selected] ?? '/', [selected])

  return (
    <section className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold">إدارة SEO</h1>
        <p className="text-sm text-slate-600">حدّث بيانات الصفحات العامة دون تعديل الشفرة. العملاء لا يصلون إلى هذه الصفحة.</p>
      </header>

      {state.status === 'loading' ? <DashboardPanelSkeleton label="جاري تحميل صفحات SEO..." /> : null}
      {state.status === 'error' ? <DashboardErrorState message={state.message} onRetry={() => void reload()} /> : null}

      {state.status === 'ready' ? (
        <div className="space-y-6">
          <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
            <table className="min-w-full text-start text-sm">
              <thead className="border-b border-slate-200 bg-slate-50 text-slate-600">
                <tr>
                  <th className="px-4 py-3 font-medium">الصفحة</th>
                  <th className="px-4 py-3 font-medium">عنوان SEO</th>
                  <th className="hidden px-4 py-3 font-medium lg:table-cell">الوصف</th>
                  <th className="hidden px-4 py-3 font-medium md:table-cell">OG Image</th>
                  <th className="px-4 py-3 font-medium">الفهرسة</th>
                  <th className="hidden px-4 py-3 font-medium xl:table-cell">آخر تحديث</th>
                  <th className="px-4 py-3 font-medium">تعديل</th>
                </tr>
              </thead>
              <tbody>
                {pages.map((page) => (
                  <tr key={page.page_key} className={selected === page.page_key ? 'bg-amber-50' : 'border-t border-slate-100'}>
                    <td className="px-4 py-3 font-medium">{page.label ?? page.page_key}</td>
                    <td className="max-w-48 truncate px-4 py-3">{page.title || '—'}</td>
                    <td className="hidden max-w-64 truncate px-4 py-3 lg:table-cell">{page.description || '—'}</td>
                    <td className="hidden max-w-40 truncate px-4 py-3 md:table-cell" dir="ltr">
                      {page.og_image || '—'}
                    </td>
                    <td className="px-4 py-3">{robotsLabel(page.robots)}</td>
                    <td className="hidden px-4 py-3 xl:table-cell">{formatUpdatedAt(page.updated_at)}</td>
                    <td className="px-4 py-3">
                      <button
                        type="button"
                        onClick={() => setSelected(page.page_key as SeoPageKey)}
                        className="rounded-lg bg-slate-900 px-3 py-1.5 text-white"
                      >
                        تعديل
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <form onSubmit={(event) => void save(event)} className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
              {message ? <FeedbackBanner kind="success">{message}</FeedbackBanner> : null}
              {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
              <h2 className="text-lg font-semibold">تعديل: {current?.label ?? selected}</h2>
              <p className="text-xs text-slate-500">آخر تحديث: {formatUpdatedAt(current?.updated_at)}</p>
              <label className="block space-y-1 text-sm">
                <span className={titleHint.tone === 'warn' ? 'text-amber-800' : undefined}>
                  عنوان SEO ({titleHint.count}/60 مستحسن، 70 حد أقصى)
                </span>
                <input
                  value={form.title ?? ''}
                  onChange={(event) => setForm((currentForm) => ({ ...currentForm, title: event.target.value }))}
                  maxLength={70}
                />
              </label>
              <label className="block space-y-1 text-sm">
                <span className={descriptionHint.tone === 'warn' ? 'text-amber-800' : undefined}>
                  الوصف ({descriptionHint.count}/160 مستحسن، 320 حد أقصى)
                </span>
                <textarea
                  value={form.description ?? ''}
                  onChange={(event) => setForm((currentForm) => ({ ...currentForm, description: event.target.value }))}
                  maxLength={320}
                />
              </label>
              <label className="block space-y-1 text-sm">
                <span>الكلمات المفتاحية</span>
                <input
                  value={form.keywords ?? ''}
                  onChange={(event) => setForm((currentForm) => ({ ...currentForm, keywords: event.target.value }))}
                />
              </label>
              <label className="block space-y-1 text-sm">
                <span>Canonical URL</span>
                <input
                  dir="ltr"
                  value={form.canonical_url ?? ''}
                  onChange={(event) => setForm((currentForm) => ({ ...currentForm, canonical_url: event.target.value }))}
                />
              </label>
              <label className="block space-y-1 text-sm">
                <span>Robots</span>
                <select
                  value={form.robots ?? 'index,follow'}
                  onChange={(event) => setForm((currentForm) => ({ ...currentForm, robots: event.target.value }))}
                >
                  <option value="index,follow">index,follow</option>
                  <option value="noindex,follow">noindex,follow</option>
                  <option value="index,nofollow">index,nofollow</option>
                  <option value="noindex,nofollow">noindex,nofollow</option>
                </select>
              </label>
              <label className="block space-y-1 text-sm">
                <span>OG Title</span>
                <input
                  value={form.og_title ?? ''}
                  onChange={(event) => setForm((currentForm) => ({ ...currentForm, og_title: event.target.value }))}
                  maxLength={70}
                />
              </label>
              <label className="block space-y-1 text-sm">
                <span>OG Description</span>
                <textarea
                  value={form.og_description ?? ''}
                  onChange={(event) => setForm((currentForm) => ({ ...currentForm, og_description: event.target.value }))}
                  maxLength={320}
                />
              </label>
              <label className="block space-y-1 text-sm">
                <span>OG Image</span>
                <input
                  dir="ltr"
                  value={form.og_image ?? ''}
                  onChange={(event) => setForm((currentForm) => ({ ...currentForm, og_image: event.target.value }))}
                />
              </label>
              <label className="block space-y-1 text-sm">
                <span>Twitter Title</span>
                <input
                  value={form.twitter_title ?? ''}
                  onChange={(event) => setForm((currentForm) => ({ ...currentForm, twitter_title: event.target.value }))}
                  maxLength={70}
                />
              </label>
              <label className="block space-y-1 text-sm">
                <span>Twitter Description</span>
                <textarea
                  value={form.twitter_description ?? ''}
                  onChange={(event) =>
                    setForm((currentForm) => ({ ...currentForm, twitter_description: event.target.value }))
                  }
                  maxLength={320}
                />
              </label>
              <label className="block space-y-1 text-sm">
                <span>Twitter Image</span>
                <input
                  dir="ltr"
                  value={form.twitter_image ?? ''}
                  onChange={(event) => setForm((currentForm) => ({ ...currentForm, twitter_image: event.target.value }))}
                />
              </label>
              <button
                type="submit"
                disabled={saving}
                className="min-h-11 rounded-xl bg-slate-900 px-5 text-sm text-white disabled:opacity-60"
              >
                {saving ? 'جاري الحفظ...' : 'حفظ'}
              </button>
            </div>

            <aside className="space-y-4">
              <div className="rounded-2xl border border-slate-200 bg-white p-4 text-sm">
                <p className="text-xs text-slate-500">معاينة بحث Google</p>
                <p className="mt-2 text-xl text-[#1a0dab]">{form.title || 'حبر وأبعاد'}</p>
                <p className="text-xs text-[#006621]" dir="ltr">
                  {form.canonical_url || `https://hebr.example${selectedPath}`}
                </p>
                <p className="mt-1 text-[#4d5156]">{form.description || 'الوصف سيظهر هنا.'}</p>
              </div>
              <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white text-sm">
                <p className="border-b border-slate-100 px-4 py-2 text-xs text-slate-500">معاينة المشاركة الاجتماعية</p>
                <div
                  className="h-36 bg-slate-100 bg-cover bg-center"
                  style={{ backgroundImage: `url(${previewImage})` }}
                  aria-hidden="true"
                />
                <div className="space-y-1 p-4">
                  <p className="text-xs uppercase tracking-wide text-slate-500" dir="ltr">
                    hebr
                  </p>
                  <p className="font-semibold">{form.og_title || form.title || 'حبر وأبعاد'}</p>
                  <p className="text-slate-600">{form.og_description || form.description || 'الوصف الاجتماعي سيظهر هنا.'}</p>
                </div>
              </div>
            </aside>
          </form>
        </div>
      ) : null}
    </section>
  )
}
