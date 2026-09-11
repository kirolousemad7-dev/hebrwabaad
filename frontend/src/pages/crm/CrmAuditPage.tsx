import { useEffect, useState } from 'react'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { getCrmAuditLogs, type CrmAuditLog } from '../../services/crm'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

export function CrmAuditPage() {
  const [action, setAction] = useState('')
  const [page, setPage] = useState(1)
  const [items, setItems] = useState<CrmAuditLog[]>([])
  const [meta, setMeta] = useState<{ current_page: number; last_page: number; total: number } | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await getCrmAuditLogs({
        action: action || undefined,
        page,
        per_page: 25,
      })
      setItems(response.data.items ?? [])
      setMeta(response.data.meta)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل سجل التدقيق.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [action, page])

  return (
    <DashboardSection title="سجل التدقيق" description="سجل إجراءات المبيعات للمديرين.">
      <input
        value={action}
        onChange={(event) => {
          setAction(event.target.value)
          setPage(1)
        }}
        placeholder="تصفية حسب الإجراء (مثل assigned / lost)"
        className={`${fieldClass} max-w-md`}
      />

      {loading ? <DashboardPanelSkeleton label="جاري تحميل السجل..." /> : null}
      {error ? <DashboardErrorState message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && items.length === 0 ? (
        <DashboardEmptyState title="لا سجلات." description="ستظهر هنا إجراءات التعيين والتحويل والخسارة وغيرها." />
      ) : null}

      {!loading && !error && items.length > 0 ? (
        <>
          <div className="overflow-x-auto rounded-2xl border bg-white">
            <table className="min-w-full text-sm">
              <thead className="bg-slate-50 text-right">
                <tr>
                  <th className="px-3 py-2">الوقت</th>
                  <th className="px-3 py-2">المستخدم</th>
                  <th className="px-3 py-2">الإجراء</th>
                  <th className="px-3 py-2">الكيان</th>
                </tr>
              </thead>
              <tbody>
                {items.map((log) => (
                  <tr key={log.id} className="border-t align-top">
                    <td className="px-3 py-2 text-xs text-slate-500">
                      {log.created_at ? new Date(log.created_at).toLocaleString('ar-SA') : '—'}
                    </td>
                    <td className="px-3 py-2">{log.user?.name ?? '—'}</td>
                    <td className="px-3 py-2 font-medium">{log.action}</td>
                    <td className="px-3 py-2 text-xs" dir="ltr">
                      {log.auditable_type ? `${log.auditable_type.split('\\').pop()} #${log.auditable_id}` : '—'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {meta && meta.last_page > 1 ? (
            <div className="flex items-center justify-between gap-3 text-sm">
              <p>
                صفحة {meta.current_page.toLocaleString('ar-SA')} من {meta.last_page.toLocaleString('ar-SA')}
              </p>
              <div className="flex gap-2">
                <button type="button" disabled={meta.current_page <= 1} className="rounded-lg border px-3 py-1 disabled:opacity-50" onClick={() => setPage((p) => p - 1)}>
                  السابق
                </button>
                <button type="button" disabled={meta.current_page >= meta.last_page} className="rounded-lg border px-3 py-1 disabled:opacity-50" onClick={() => setPage((p) => p + 1)}>
                  التالي
                </button>
              </div>
            </div>
          ) : null}
        </>
      ) : null}
    </DashboardSection>
  )
}
