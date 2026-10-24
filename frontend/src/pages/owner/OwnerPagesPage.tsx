import { useState } from 'react'
import { Link } from 'react-router-dom'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { ownerPrimaryButtonClass, ownerTableHeadClass, ownerTableWrapClass, OwnerPageHeader } from '../../components/owner/ownerChrome'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { useAsyncData } from '../../hooks/useAsyncData'
import {
  deleteOwnerCmsPage,
  listOwnerCmsPages,
  publishOwnerCmsPage,
  updateOwnerCmsPageFooter,
  type CmsPage,
} from '../../services/cmsPages'
import { describeApiError } from '../../utils/errors'

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

export function OwnerPagesPage() {
  const { state, reload } = useAsyncData(listOwnerCmsPages)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const pages = state.status === 'ready' ? state.data : []

  async function runAction(id: number, action: () => Promise<unknown>, successMessage: string) {
    setBusyId(id)
    setError(null)
    setMessage(null)
    try {
      await action()
      setMessage(successMessage)
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تنفيذ الإجراء.'))
    } finally {
      setBusyId(null)
    }
  }

  async function togglePublish(page: CmsPage) {
    await runAction(
      page.id,
      () => publishOwnerCmsPage(page.id, !page.is_published),
      page.is_published ? 'تم إلغاء النشر.' : 'تم نشر الصفحة.',
    )
  }

  async function toggleFooter(page: CmsPage) {
    await runAction(
      page.id,
      () =>
        updateOwnerCmsPageFooter(page.id, {
          show_in_footer: !page.show_in_footer,
          footer_group: page.footer_group,
          footer_order: page.footer_order,
        }),
      page.show_in_footer ? 'تم إخفاء الصفحة من الفوتر.' : 'تم إظهار الصفحة في الفوتر.',
    )
  }

  async function removePage(page: CmsPage) {
    if (page.is_system) {
      setError('لا يمكن حذف الصفحات الأساسية. ألغِ النشر أو أخفِها من الفوتر بدلًا من الحذف.')
      return
    }

    if (!window.confirm(`حذف صفحة «${page.title}»؟`)) {
      return
    }

    await runAction(page.id, () => deleteOwnerCmsPage(page.id), 'تم حذف الصفحة.')
  }

  return (
    <section className="space-y-6">
      <OwnerPageHeader
        title="الصفحات"
        description="إدارة صفحات المحتوى العامة والفوتر والسياسات."
        action={
          <Link to="/owner/pages/new" className={ownerPrimaryButtonClass}>
            صفحة جديدة
          </Link>
        }
      />

      {message ? <FeedbackBanner kind="success">{message}</FeedbackBanner> : null}
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      {state.status === 'loading' ? <DashboardPanelSkeleton label="جاري تحميل الصفحات..." /> : null}
      {state.status === 'error' ? <DashboardErrorState message={state.message} onRetry={() => void reload()} /> : null}

      {state.status === 'ready' ? (
        <DashboardSection title={`كل الصفحات (${pages.length})`}>
          {pages.length === 0 ? (
            <DashboardEmptyState title="لا توجد صفحات" description="أنشئ أول صفحة محتوى للمنصة." />
          ) : (
            <div className={ownerTableWrapClass}>
              <table className="min-w-full text-start text-sm">
                <thead className={ownerTableHeadClass}>
                  <tr>
                    <th className="px-4 py-3 font-medium">اسم</th>
                    <th className="px-4 py-3 font-medium">النوع</th>
                    <th className="px-4 py-3 font-medium">الحالة</th>
                    <th className="hidden px-4 py-3 font-medium md:table-cell">ظهور في الفوتر</th>
                    <th className="hidden px-4 py-3 font-medium lg:table-cell">مجموعة الفوتر</th>
                    <th className="hidden px-4 py-3 font-medium xl:table-cell">الترتيب</th>
                    <th className="hidden px-4 py-3 font-medium lg:table-cell">آخر تحديث</th>
                    <th className="px-4 py-3 font-medium">إجراءات</th>
                  </tr>
                </thead>
                <tbody>
                  {pages.map((page) => {
                    const busy = busyId === page.id
                    return (
                      <tr key={page.id} className="border-t border-slate-100 hover:bg-[#f4f6f8]">
                        <td className="px-4 py-3 font-medium">{page.title}</td>
                        <td className="px-4 py-3">{page.page_type_label || page.page_type}</td>
                        <td className="px-4 py-3">
                          <StatusBadge
                            label={page.is_published ? 'منشورة' : 'مسودة'}
                            tone={page.is_published ? 'success' : 'neutral'}
                          />
                        </td>
                        <td className="hidden px-4 py-3 md:table-cell">{page.show_in_footer ? 'نعم' : 'لا'}</td>
                        <td className="hidden px-4 py-3 lg:table-cell">{page.footer_group || '—'}</td>
                        <td className="hidden px-4 py-3 xl:table-cell">{page.footer_order}</td>
                        <td className="hidden px-4 py-3 lg:table-cell">{formatUpdatedAt(page.updated_at)}</td>
                        <td className="px-4 py-3">
                          <div className="flex flex-wrap gap-2">
                            <Link
                              to={`/owner/pages/${page.id}`}
                              className="rounded-lg border border-slate-300 px-2.5 py-1 text-xs text-slate-800"
                            >
                              تعديل
                            </Link>
                            {page.is_published ? (
                              <a
                                href={page.path || `/${page.slug}`}
                                target="_blank"
                                rel="noreferrer"
                                className="rounded-lg border border-slate-300 px-2.5 py-1 text-xs text-slate-800"
                              >
                                معاينة
                              </a>
                            ) : null}
                            <button
                              type="button"
                              disabled={busy}
                              onClick={() => void togglePublish(page)}
                              className="rounded-lg border border-slate-300 px-2.5 py-1 text-xs text-slate-800 disabled:opacity-60"
                            >
                              {page.is_published ? 'إلغاء النشر' : 'نشر'}
                            </button>
                            <button
                              type="button"
                              disabled={busy}
                              onClick={() => void toggleFooter(page)}
                              className="rounded-lg border border-slate-300 px-2.5 py-1 text-xs text-slate-800 disabled:opacity-60"
                            >
                              {page.show_in_footer ? 'إخفاء من الفوتر' : 'إظهار في الفوتر'}
                            </button>
                            {page.is_system ? null : (
                              <button
                                type="button"
                                disabled={busy}
                                onClick={() => void removePage(page)}
                                className="rounded-lg border border-red-200 px-2.5 py-1 text-xs text-red-700 disabled:opacity-60"
                              >
                                حذف
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </DashboardSection>
      ) : null}
    </section>
  )
}
