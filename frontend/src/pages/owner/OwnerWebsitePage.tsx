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
  listOwnerMarketingSections,
  updateOwnerMarketingSection,
  type MarketingSection,
} from '../../services/marketingCms'
import { describeApiError } from '../../utils/errors'
import { DOMAIN_SECTION_LINKS, sectionDisplayName } from '../../utils/marketingCmsLabels'
import { useState } from 'react'

function thumbnailFor(section: MarketingSection): string | null {
  const withMedia = section.contents.find((content) => content.media?.url)
  return withMedia?.media?.url ?? null
}

export function OwnerWebsitePage() {
  const { state, reload } = useAsyncData(listOwnerMarketingSections)
  const [busyKey, setBusyKey] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const sections = state.status === 'ready' ? state.data : []

  async function toggleEnabled(section: MarketingSection) {
    setBusyKey(section.key)
    setError(null)
    setMessage(null)
    try {
      await updateOwnerMarketingSection(section.key, { is_enabled: !section.is_enabled })
      setMessage(section.is_enabled ? 'تم تعطيل القسم.' : 'تم تفعيل القسم.')
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحديث حالة القسم.'))
    } finally {
      setBusyKey(null)
    }
  }

  async function saveOrder(section: MarketingSection, nextOrder: number) {
    if (nextOrder === section.sort_order) {
      return
    }
    setBusyKey(section.key)
    setError(null)
    setMessage(null)
    try {
      await updateOwnerMarketingSection(section.key, { sort_order: nextOrder })
      setMessage('تم تحديث الترتيب.')
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحديث الترتيب.'))
    } finally {
      setBusyKey(null)
    }
  }

  return (
    <section className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">إدارة الموقع</h1>
          <p className="text-sm text-slate-600">
            تحرير محتوى الأقسام التسويقية ومكتبة الصور. الموقع العام سيُربط في مرحلة لاحقة.
          </p>
        </div>
        <nav className="flex flex-wrap gap-2" aria-label="أقسام إدارة الموقع">
          <span className="inline-flex min-h-11 items-center rounded-xl bg-slate-900 px-4 text-sm text-white">
            محتوى الموقع
          </span>
          <Link
            to="/owner/website/media"
            className="inline-flex min-h-11 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-800 hover:bg-slate-50"
          >
            مكتبة الصور
          </Link>
        </nav>
      </header>

      {message ? <FeedbackBanner kind="success">{message}</FeedbackBanner> : null}
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      <DashboardSection title="أقسام الصفحة الرئيسية" description="فعّل أو عدّل أقسام المحتوى التسويقي.">
        {state.status === 'loading' ? <DashboardPanelSkeleton /> : null}
        {state.status === 'error' ? (
          <DashboardErrorState message={state.message} onRetry={reload} />
        ) : null}
        {state.status === 'ready' && sections.length === 0 ? (
          <DashboardEmptyState title="لا توجد أقسام" description="شغّل MarketingSectionSeeder لإضافة الأقسام الافتراضية." />
        ) : null}

        {state.status === 'ready' && sections.length > 0 ? (
          <ul className="grid gap-4 lg:grid-cols-2">
            {sections.map((section) => {
              const domain = DOMAIN_SECTION_LINKS[section.key]
              const thumb = thumbnailFor(section)
              const busy = busyKey === section.key

              return (
                <li key={section.key} className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                  <div className="flex gap-4 p-4">
                    <div className="h-20 w-24 shrink-0 overflow-hidden rounded-xl bg-slate-100">
                      {thumb ? (
                        <img src={thumb} alt="" className="h-full w-full object-cover" />
                      ) : (
                        <div className="flex h-full items-center justify-center text-[11px] text-slate-400">بدون صورة</div>
                      )}
                    </div>
                    <div className="min-w-0 flex-1 space-y-2">
                      <div className="flex flex-wrap items-start justify-between gap-2">
                        <div>
                          <h2 className="font-semibold text-slate-900">{sectionDisplayName(section)}</h2>
                          <p className="text-xs text-slate-500">
                            {section.key} · {section.type_label || section.type}
                          </p>
                        </div>
                        <span
                          className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                            section.is_enabled ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'
                          }`}
                        >
                          {section.is_enabled ? 'مفعّل' : 'معطّل'}
                        </span>
                      </div>
                      <p className="text-sm text-slate-600">{section.contents.length} عنصر محتوى</p>
                      <div className="flex flex-wrap items-center gap-2">
                        <label className="text-xs text-slate-500" htmlFor={`order-${section.key}`}>
                          الترتيب
                        </label>
                        <input
                          id={`order-${section.key}`}
                          type="number"
                          min={0}
                          defaultValue={section.sort_order}
                          disabled={busy}
                          className="w-20 rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                          onBlur={(event) => {
                            const value = Number(event.target.value)
                            if (!Number.isNaN(value)) {
                              void saveOrder(section, value)
                            }
                          }}
                        />
                      </div>
                    </div>
                  </div>

                  {domain ? (
                    <div className="border-t border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                      <p>{domain.description}</p>
                      <Link to={domain.href} className="mt-2 inline-flex text-sm font-medium text-slate-900 underline">
                        {domain.cta}
                      </Link>
                    </div>
                  ) : null}

                  <div className="flex flex-wrap gap-2 border-t border-slate-100 px-4 py-3">
                    <Link
                      to={`/owner/website/${section.key}`}
                      className="inline-flex min-h-10 items-center rounded-xl bg-slate-900 px-4 text-sm text-white"
                    >
                      تعديل
                    </Link>
                    <button
                      type="button"
                      disabled={busy}
                      onClick={() => void toggleEnabled(section)}
                      className="inline-flex min-h-10 items-center rounded-xl border border-slate-200 px-4 text-sm text-slate-800 hover:bg-slate-50 disabled:opacity-60"
                    >
                      {section.is_enabled ? 'تعطيل' : 'تفعيل'}
                    </button>
                  </div>
                </li>
              )
            })}
          </ul>
        ) : null}
      </DashboardSection>

      <DashboardSection title="الفوتر / عام" description="روابط الفوتر والصفحات النظامية تُدار من وحدة الصفحات.">
        <div className="rounded-2xl border border-slate-200 bg-white p-5">
          <p className="text-sm text-slate-600">
            Footer والصفحات القانونية (الشروط، الخصوصية، من نحن الكاملة) تبقى داخل CmsPage.
          </p>
          <Link
            to="/owner/pages"
            className="mt-3 inline-flex min-h-10 items-center rounded-xl bg-slate-900 px-4 text-sm text-white"
          >
            إدارة الصفحات والفوتر
          </Link>
        </div>
      </DashboardSection>
    </section>
  )
}
