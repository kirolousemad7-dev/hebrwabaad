import { FormEvent, useEffect, useMemo, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { DashboardErrorState, DashboardPanelSkeleton } from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import {
  CMS_PAGE_TYPE_OPTIONS,
  createOwnerCmsPage,
  getOwnerCmsPage,
  updateOwnerCmsPage,
  type UpsertCmsPagePayload,
} from '../../services/cmsPages'
import { describeApiError } from '../../utils/errors'
import { descriptionLengthHint, titleLengthHint } from '../../utils/seo'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

type PageForm = {
  title: string
  slug: string
  page_type: string
  content: string
  is_published: boolean
  meta_title: string
  meta_description: string
  meta_keywords: string
  og_title: string
  og_description: string
  og_image: string
  show_in_footer: boolean
  footer_group: string
  footer_order: string
}

const emptyForm = (): PageForm => ({
  title: '',
  slug: '',
  page_type: 'GENERAL',
  content: '',
  is_published: false,
  meta_title: '',
  meta_description: '',
  meta_keywords: '',
  og_title: '',
  og_description: '',
  og_image: '',
  show_in_footer: false,
  footer_group: '',
  footer_order: '0',
})

function slugify(value: string) {
  return value
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '')
}

export function OwnerPageEditorPage() {
  const { id: idParam } = useParams()
  const navigate = useNavigate()
  const isNew = !idParam || idParam === 'new'
  const pageId = !isNew ? Number(idParam) : null

  const [form, setForm] = useState<PageForm>(emptyForm)
  const [loading, setLoading] = useState(!isNew)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [slugTouched, setSlugTouched] = useState(false)
  const contentRef = useRef<HTMLTextAreaElement | null>(null)

  useEffect(() => {
    if (isNew || !pageId || Number.isNaN(pageId)) {
      setLoading(false)
      return
    }

    let cancelled = false
    setLoading(true)
    setLoadError(null)

    void getOwnerCmsPage(pageId)
      .then((response) => {
        if (cancelled) {
          return
        }
        const page = response.data
        setForm({
          title: page.title,
          slug: page.slug,
          page_type: page.page_type,
          content: page.content || '',
          is_published: Boolean(page.is_published),
          meta_title: page.meta_title || '',
          meta_description: page.meta_description || '',
          meta_keywords: page.meta_keywords || '',
          og_title: page.og_title || '',
          og_description: page.og_description || '',
          og_image: page.og_image || '',
          show_in_footer: Boolean(page.show_in_footer),
          footer_group: page.footer_group || '',
          footer_order: String(page.footer_order ?? 0),
        })
        setSlugTouched(true)
      })
      .catch((caught) => {
        if (!cancelled) {
          setLoadError(describeApiError(caught, 'تعذر تحميل الصفحة.'))
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [isNew, pageId])

  function updateField<K extends keyof PageForm>(key: K, value: PageForm[K]) {
    setForm((current) => ({ ...current, [key]: value }))
  }

  function insertMarkup(open: string, close = '') {
    const textarea = contentRef.current
    if (!textarea) {
      updateField('content', `${form.content}${open}${close}`)
      return
    }

    const start = textarea.selectionStart
    const end = textarea.selectionEnd
    const selected = form.content.slice(start, end) || (close ? 'نص' : '')
    const next = form.content.slice(0, start) + open + selected + close + form.content.slice(end)
    updateField('content', next)

    requestAnimationFrame(() => {
      textarea.focus()
      const cursor = start + open.length + selected.length + close.length
      textarea.setSelectionRange(cursor, cursor)
    })
  }

  function insertLink() {
    const url = window.prompt('رابط URL', 'https://')
    if (!url) {
      return
    }
    insertMarkup(`<a href="${url}">`, '</a>')
  }

  async function save(event: FormEvent) {
    event.preventDefault()
    setSaving(true)
    setError(null)
    setMessage(null)

    const payload: UpsertCmsPagePayload = {
      title: form.title.trim(),
      slug: form.slug.trim() || null,
      content: form.content,
      page_type: form.page_type,
      meta_title: form.meta_title.trim() || null,
      meta_description: form.meta_description.trim() || null,
      meta_keywords: form.meta_keywords.trim() || null,
      og_title: form.og_title.trim() || null,
      og_description: form.og_description.trim() || null,
      og_image: form.og_image.trim() || null,
      is_published: form.is_published,
      show_in_footer: form.show_in_footer,
      footer_group: form.footer_group.trim() || null,
      footer_order: Number(form.footer_order) || 0,
    }

    try {
      if (isNew) {
        const created = await createOwnerCmsPage(payload)
        setMessage('تم إنشاء الصفحة.')
        navigate(`/owner/pages/${created.data.id}`, { replace: true })
      } else if (pageId) {
        await updateOwnerCmsPage(pageId, payload)
        setMessage('تم حفظ الصفحة.')
      }
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ الصفحة.'))
    } finally {
      setSaving(false)
    }
  }

  const titleHint = titleLengthHint(form.meta_title || form.title)
  const descriptionHint = descriptionLengthHint(form.meta_description)
  const previewPath = useMemo(() => `/${form.slug.trim() || 'page-slug'}`, [form.slug])
  const previewTitle = form.meta_title.trim() || form.title.trim() || 'حبر وأبعاد'
  const previewDescription = form.meta_description.trim() || 'الوصف سيظهر هنا.'

  if (loading) {
    return <DashboardPanelSkeleton label="جاري تحميل الصفحة..." />
  }

  if (loadError) {
    return <DashboardErrorState message={loadError} onRetry={() => window.location.reload()} />
  }

  return (
    <section className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">{isNew ? 'صفحة جديدة' : 'تعديل الصفحة'}</h1>
          <p className="text-sm text-slate-600">حرّر المحتوى وبيانات SEO وظهور الفوتر.</p>
        </div>
        <Link to="/owner/pages" className="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-800">
          العودة للقائمة
        </Link>
      </header>

      <form onSubmit={(event) => void save(event)} className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
          {message ? <FeedbackBanner kind="success">{message}</FeedbackBanner> : null}
          {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

          <label className="block space-y-1 text-sm">
            <span>العنوان</span>
            <input
              className={fieldClass}
              required
              value={form.title}
              onChange={(event) => {
                const title = event.target.value
                updateField('title', title)
                if (!slugTouched) {
                  updateField('slug', slugify(title))
                }
              }}
            />
          </label>

          <label className="block space-y-1 text-sm">
            <span>Slug</span>
            <input
              className={fieldClass}
              dir="ltr"
              value={form.slug}
              onChange={(event) => {
                setSlugTouched(true)
                updateField('slug', event.target.value)
              }}
            />
          </label>

          <label className="block space-y-1 text-sm">
            <span>نوع الصفحة</span>
            <select
              className={fieldClass}
              value={form.page_type}
              onChange={(event) => updateField('page_type', event.target.value)}
            >
              {CMS_PAGE_TYPE_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </label>

          <div className="space-y-2">
            <span className="text-sm">المحتوى</span>
            <div className="flex flex-wrap gap-2">
              {[
                { label: 'H2', open: '<h2>', close: '</h2>' },
                { label: 'H3', open: '<h3>', close: '</h3>' },
                { label: 'P', open: '<p>', close: '</p>' },
                { label: 'Bold', open: '<strong>', close: '</strong>' },
                { label: 'UL', open: '<ul>\n<li>', close: '</li>\n</ul>' },
                { label: 'OL', open: '<ol>\n<li>', close: '</li>\n</ol>' },
              ].map((tool) => (
                <button
                  key={tool.label}
                  type="button"
                  className="rounded-lg border border-slate-300 px-2.5 py-1 text-xs text-slate-700"
                  onClick={() => insertMarkup(tool.open, tool.close)}
                >
                  {tool.label}
                </button>
              ))}
              <button
                type="button"
                className="rounded-lg border border-slate-300 px-2.5 py-1 text-xs text-slate-700"
                onClick={insertLink}
              >
                Link
              </button>
            </div>
            <textarea
              ref={contentRef}
              className={`${fieldClass} min-h-64 font-mono text-xs`}
              dir="rtl"
              required
              value={form.content}
              onChange={(event) => updateField('content', event.target.value)}
            />
          </div>

          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={form.is_published}
              onChange={(event) => updateField('is_published', event.target.checked)}
            />
            <span>منشورة</span>
          </label>

          <div className="space-y-3 border-t border-slate-100 pt-4">
            <h2 className="text-lg font-semibold">SEO</h2>
            <label className="block space-y-1 text-sm">
              <span className={titleHint.tone === 'warn' ? 'text-amber-800' : undefined}>
                Meta Title ({titleHint.count}/60 مستحسن)
              </span>
              <input
                className={fieldClass}
                value={form.meta_title}
                onChange={(event) => updateField('meta_title', event.target.value)}
                maxLength={70}
              />
            </label>
            <label className="block space-y-1 text-sm">
              <span className={descriptionHint.tone === 'warn' ? 'text-amber-800' : undefined}>
                Meta Description ({descriptionHint.count}/160 مستحسن)
              </span>
              <textarea
                className={fieldClass}
                value={form.meta_description}
                onChange={(event) => updateField('meta_description', event.target.value)}
                maxLength={320}
              />
            </label>
            <label className="block space-y-1 text-sm">
              <span>Meta Keywords</span>
              <input
                className={fieldClass}
                value={form.meta_keywords}
                onChange={(event) => updateField('meta_keywords', event.target.value)}
              />
            </label>
            <label className="block space-y-1 text-sm">
              <span>OG Title</span>
              <input
                className={fieldClass}
                value={form.og_title}
                onChange={(event) => updateField('og_title', event.target.value)}
              />
            </label>
            <label className="block space-y-1 text-sm">
              <span>OG Description</span>
              <textarea
                className={fieldClass}
                value={form.og_description}
                onChange={(event) => updateField('og_description', event.target.value)}
              />
            </label>
            <label className="block space-y-1 text-sm">
              <span>OG Image</span>
              <input
                className={fieldClass}
                dir="ltr"
                value={form.og_image}
                onChange={(event) => updateField('og_image', event.target.value)}
              />
            </label>
          </div>

          <div className="space-y-3 border-t border-slate-100 pt-4">
            <h2 className="text-lg font-semibold">الفوتر</h2>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={form.show_in_footer}
                onChange={(event) => updateField('show_in_footer', event.target.checked)}
              />
              <span>إظهار في الفوتر</span>
            </label>
            <label className="block space-y-1 text-sm">
              <span>مجموعة الفوتر</span>
              <input
                className={fieldClass}
                list="cms-footer-groups"
                value={form.footer_group}
                onChange={(event) => updateField('footer_group', event.target.value)}
                placeholder="تعرف علينا / السياسات"
              />
              <datalist id="cms-footer-groups">
                <option value="تعرف علينا" />
                <option value="السياسات" />
              </datalist>
            </label>
            <label className="block space-y-1 text-sm">
              <span>ترتيب الفوتر</span>
              <input
                className={fieldClass}
                type="number"
                min={0}
                value={form.footer_order}
                onChange={(event) => updateField('footer_order', event.target.value)}
              />
            </label>
          </div>

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
            <p className="mt-2 text-xl text-[#1a0dab]">{previewTitle}</p>
            <p className="text-xs text-[#006621]" dir="ltr">
              {`https://hebr.example${previewPath}`}
            </p>
            <p className="mt-1 text-[#4d5156]">{previewDescription}</p>
          </div>
        </aside>
      </form>
    </section>
  )
}
