import { FormEvent, useEffect, useMemo, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { MarketingMediaPickerModal } from '../../components/owner/website/MarketingMediaPickerModal'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import {
  getOwnerMarketingSection,
  updateOwnerMarketingSection,
  type MarketingContent,
  type MarketingMedia,
  type MarketingSection,
} from '../../services/marketingCms'
import { describeApiError } from '../../utils/errors'
import {
  DOMAIN_SECTION_LINKS,
  HERO_PLATFORM_SETTINGS_FIELDS,
  labelForContentKey,
  sectionDisplayName,
} from '../../utils/marketingCmsLabels'

type DraftContent = {
  content_key: string
  value_text: string
  value_html: string
  media_id: number | null
  media: MarketingContent['media']
  sort_order: number
  is_enabled: boolean
  metadata: Record<string, unknown> | null
}

const fieldClass =
  'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

function toDraft(contents: MarketingContent[]): DraftContent[] {
  return contents.map((content) => ({
    content_key: content.content_key,
    value_text: content.value_text ?? content.text ?? '',
    value_html: content.value_html ?? content.html ?? '',
    media_id: content.media_id,
    media: content.media,
    sort_order: content.sort_order,
    is_enabled: content.is_enabled,
    metadata: content.metadata,
  }))
}

export function OwnerWebsiteSectionPage() {
  const { key = '' } = useParams()
  const [section, setSection] = useState<MarketingSection | null>(null)
  const [adminTitle, setAdminTitle] = useState('')
  const [enabled, setEnabled] = useState(true)
  const [sortOrder, setSortOrder] = useState(0)
  const [drafts, setDrafts] = useState<DraftContent[]>([])
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [dirty, setDirty] = useState(false)
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [pickerForKey, setPickerForKey] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    setError(null)
    void getOwnerMarketingSection(key)
      .then((response) => {
        if (cancelled) return
        const data = response.data
        setSection(data)
        setAdminTitle(data.admin_title)
        setEnabled(data.is_enabled)
        setSortOrder(data.sort_order)
        setDrafts(toDraft(data.contents))
        setDirty(false)
      })
      .catch((caught) => {
        if (!cancelled) {
          setError(describeApiError(caught, 'تعذر تحميل القسم.'))
        }
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [key])

  useEffect(() => {
    const onBeforeUnload = (event: BeforeUnloadEvent) => {
      if (!dirty) return
      event.preventDefault()
      event.returnValue = ''
    }
    window.addEventListener('beforeunload', onBeforeUnload)
    return () => window.removeEventListener('beforeunload', onBeforeUnload)
  }, [dirty])

  function confirmLeave(): boolean {
    if (!dirty) return true
    return window.confirm('هناك تغييرات غير محفوظة. هل تريد المغادرة؟')
  }

  const domain = DOMAIN_SECTION_LINKS[key]
  const title = section ? sectionDisplayName(section) : key

  const grouped = useMemo(() => {
    return drafts.map((draft) => ({
      draft,
      meta: labelForContentKey(key, draft.content_key),
    }))
  }, [drafts, key])

  function markDirty() {
    setDirty(true)
    setMessage(null)
  }

  function updateDraft(contentKey: string, patch: Partial<DraftContent>) {
    setDrafts((current) =>
      current.map((row) => (row.content_key === contentKey ? { ...row, ...patch } : row)),
    )
    markDirty()
  }

  function applyMedia(contentKey: string, media: MarketingMedia | null) {
    updateDraft(contentKey, {
      media_id: media?.id ?? null,
      media: media
        ? {
            id: media.id,
            url: media.url,
            alt: media.alt_text,
            title: media.title,
            width: media.width,
            height: media.height,
            is_registry_only: media.is_registry_only,
            local_public_path: media.local_public_path,
          }
        : null,
    })
    setPickerForKey(null)
  }

  async function handleSave(event: FormEvent) {
    event.preventDefault()
    if (!section) return
    setSaving(true)
    setError(null)
    setMessage(null)
    try {
      const response = await updateOwnerMarketingSection(section.key, {
        admin_title: adminTitle,
        is_enabled: enabled,
        sort_order: sortOrder,
        contents: drafts.map((draft) => ({
          content_key: draft.content_key,
          value_text: draft.value_text,
          value_html: draft.value_html || null,
          media_id: draft.media_id,
          sort_order: draft.sort_order,
          is_enabled: draft.is_enabled,
          metadata: draft.metadata,
        })),
      })
      setSection(response.data)
      setDrafts(toDraft(response.data.contents))
      setDirty(false)
      setMessage('تم حفظ التغييرات.')
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ القسم.'))
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return <p className="text-sm text-slate-600">جاري تحميل القسم...</p>
  }

  if (!section) {
    return (
      <div className="space-y-3">
        <FeedbackBanner kind="error">{error || 'القسم غير موجود.'}</FeedbackBanner>
        <Link to="/owner/website" className="text-sm text-slate-900 underline">
          العودة لإدارة الموقع
        </Link>
      </div>
    )
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
            {title}
          </p>
          <h1 className="mt-1 text-2xl font-semibold text-slate-900">{title}</h1>
          <p className="text-sm text-slate-600">
            {key === 'about'
              ? 'محتوى مختصر يظهر في الصفحة الرئيسية. صفحة من نحن الكاملة تُدار من الصفحات (CmsPage).'
              : `مفتاح القسم: ${section.key}`}
          </p>
        </div>
        <Link
          to="/owner/website/media"
          className="inline-flex min-h-11 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm"
        >
          مكتبة الصور
        </Link>
      </header>

      {message ? <FeedbackBanner kind="success">{message}</FeedbackBanner> : null}
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      {key === 'hero' ? (
        <div className="rounded-2xl border border-amber-200 bg-amber-50 p-5">
          <h2 className="font-semibold text-amber-950">مصدر حقول الـHero الحالي على الموقع العام</h2>
          <p className="mt-2 text-sm text-amber-900">
            النصوص والأزرار المعروضة للزائر حاليًا تأتي من إعدادات المنصة. حقول محتوى الموقع أدناه جاهزة لـPhase 3 ولا تغيّر الواجهة العامة الآن.
          </p>
          <ul className="mt-4 space-y-2">
            {HERO_PLATFORM_SETTINGS_FIELDS.map((field) => (
              <li key={field.key} className="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-white/70 px-3 py-2 text-sm">
                <span>
                  <strong>{field.label}</strong>
                  <span className="ms-2 text-amber-800">— مصدره: {field.source}</span>
                </span>
                <Link to={field.settingsPath} className="font-medium text-slate-900 underline">
                  فتح الإعدادات
                </Link>
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      {domain ? (
        <div className="rounded-2xl border border-slate-200 bg-white p-5">
          <h2 className="font-semibold text-slate-900">{domain.title}</h2>
          <p className="mt-2 text-sm text-slate-600">{domain.description}</p>
          <Link
            to={domain.href}
            className="mt-3 inline-flex min-h-10 items-center rounded-xl bg-slate-900 px-4 text-sm text-white"
          >
            {domain.cta}
          </Link>
          <p className="mt-3 text-xs text-slate-500">يمكنك أيضًا تعديل عناوين المقطع الترويجي أدناه.</p>
        </div>
      ) : null}

      <form onSubmit={handleSave} className="space-y-5">
        <div className="rounded-2xl border border-slate-200 bg-white p-5">
          <h2 className="text-lg font-semibold text-slate-900">إعدادات عامة</h2>
          <div className="mt-4 grid gap-4 md:grid-cols-2">
            <label className="block text-sm">
              <span className="font-medium text-slate-700">عنوان الإدارة</span>
              <input
                className={fieldClass}
                value={adminTitle}
                onChange={(event) => {
                  setAdminTitle(event.target.value)
                  markDirty()
                }}
              />
            </label>
            <label className="block text-sm">
              <span className="font-medium text-slate-700">الترتيب</span>
              <input
                type="number"
                min={0}
                className={fieldClass}
                value={sortOrder}
                onChange={(event) => {
                  setSortOrder(Number(event.target.value) || 0)
                  markDirty()
                }}
              />
            </label>
            <label className="flex items-center gap-2 text-sm md:col-span-2">
              <input
                type="checkbox"
                checked={enabled}
                onChange={(event) => {
                  setEnabled(event.target.checked)
                  markDirty()
                }}
              />
              <span>مفعّل في واجهة المحتوى (للـAPI العام بعد Phase 3)</span>
            </label>
          </div>
        </div>

        <div className="space-y-4">
          <h2 className="text-lg font-semibold text-slate-900">المحتوى</h2>
          {grouped.map(({ draft, meta }) => (
            <article key={draft.content_key} className="rounded-2xl border border-slate-200 bg-white p-5">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <h3 className="font-semibold text-slate-900">{meta.label}</h3>
                  <p className="text-xs text-slate-500">{draft.content_key}</p>
                  {meta.help ? <p className="mt-1 text-sm text-slate-600">{meta.help}</p> : null}
                </div>
                <label className="flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={draft.is_enabled}
                    onChange={(event) => updateDraft(draft.content_key, { is_enabled: event.target.checked })}
                  />
                  مفعّل
                </label>
              </div>

              <div className="mt-4 grid gap-4 md:grid-cols-[1fr_8rem]">
                <div>
                  {meta.kind === 'readonly_note' ? (
                    <p className="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">{draft.value_text}</p>
                  ) : null}
                  {meta.kind === 'text' || meta.kind === 'url' ? (
                    <input
                      className={fieldClass}
                      value={draft.value_text}
                      onChange={(event) => updateDraft(draft.content_key, { value_text: event.target.value })}
                    />
                  ) : null}
                  {meta.kind === 'textarea' || meta.kind === 'html' ? (
                    <textarea
                      rows={meta.kind === 'html' ? 6 : 4}
                      className={fieldClass}
                      value={meta.kind === 'html' ? draft.value_html : draft.value_text}
                      onChange={(event) =>
                        updateDraft(
                          draft.content_key,
                          meta.kind === 'html'
                            ? { value_html: event.target.value }
                            : { value_text: event.target.value },
                        )
                      }
                    />
                  ) : null}
                  {meta.kind === 'media' ? (
                    <div className="space-y-3">
                      {draft.media?.url ? (
                        <div className="overflow-hidden rounded-xl border border-slate-200">
                          <img
                            src={draft.media.url}
                            alt={draft.media.alt || meta.label}
                            className="h-40 w-full object-cover"
                          />
                        </div>
                      ) : (
                        <p className="rounded-xl border border-dashed border-slate-300 px-3 py-8 text-center text-sm text-slate-500">
                          لم تُختر صورة بعد
                        </p>
                      )}
                      <div className="flex flex-wrap gap-2">
                        <button
                          type="button"
                          onClick={() => setPickerForKey(draft.content_key)}
                          className="rounded-xl bg-slate-900 px-4 py-2 text-sm text-white"
                        >
                          اختر صورة
                        </button>
                        {draft.media_id ? (
                          <button
                            type="button"
                            onClick={() => applyMedia(draft.content_key, null)}
                            className="rounded-xl border border-slate-200 px-4 py-2 text-sm"
                          >
                            إزالة الصورة
                          </button>
                        ) : null}
                      </div>
                    </div>
                  ) : null}
                </div>
                <label className="block text-sm">
                  <span className="font-medium text-slate-700">الترتيب</span>
                  <input
                    type="number"
                    min={0}
                    className={fieldClass}
                    value={draft.sort_order}
                    onChange={(event) =>
                      updateDraft(draft.content_key, { sort_order: Number(event.target.value) || 0 })
                    }
                  />
                </label>
              </div>
            </article>
          ))}
        </div>

        <div className="sticky bottom-3 z-10 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-sm backdrop-blur">
          <button
            type="submit"
            disabled={saving || !dirty}
            className="inline-flex min-h-11 items-center rounded-xl bg-slate-900 px-5 text-sm text-white disabled:opacity-50"
          >
            {saving ? 'جاري الحفظ...' : 'حفظ التغييرات'}
          </button>
          <Link
            to="/owner/website"
            onClick={(event) => {
              if (!confirmLeave()) event.preventDefault()
            }}
            className="inline-flex min-h-11 items-center rounded-xl border border-slate-200 px-5 text-sm"
          >
            رجوع
          </Link>
          {dirty ? <span className="self-center text-xs text-amber-700">تغييرات غير محفوظة</span> : null}
        </div>
      </form>

      <MarketingMediaPickerModal
        open={pickerForKey !== null}
        selectedId={drafts.find((row) => row.content_key === pickerForKey)?.media_id}
        onClose={() => setPickerForKey(null)}
        onSelect={(media) => {
          if (pickerForKey) applyMedia(pickerForKey, media)
        }}
      />
    </section>
  )
}
