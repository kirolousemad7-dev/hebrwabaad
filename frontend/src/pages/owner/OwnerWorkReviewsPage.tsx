import { useEffect, useState } from 'react'
import { DashboardEmptyState, DashboardErrorState, DashboardPanelSkeleton, DashboardSection } from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useToast } from '../../context/ToastContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import { PORTFOLIO_CATEGORIES } from '../../services/marketing'
import {
  approvePublishWork,
  archiveWork,
  CONTENT_STATUS_LABELS,
  getWorkReview,
  getWorkReviews,
  rejectWork,
  requestWorkChanges,
  unpublishWork,
  type WorkSubmission,
} from '../../services/work'
import { describeApiError } from '../../utils/errors'
import { EMPLOYEE_ROLE_LABELS, EMPLOYEE_WORKSPACE_ROLES } from '../../utils/staff'

export function OwnerWorkReviewsPage() {
  const [status, setStatus] = useState('')
  const [role, setRole] = useState('')
  const [category, setCategory] = useState('')
  const { state, reload } = useAsyncData(() => getWorkReviews({
    status: status || undefined,
    role: role || undefined,
    category: category || undefined,
  }))
  const toast = useToast()
  const [preview, setPreview] = useState<WorkSubmission | null>(null)
  const [notes, setNotes] = useState('')
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    void reload()
  }, [status, role, category, reload])

  const items = state.status === 'ready' ? state.data.items : []

  async function openPreview(id: number) {
    const response = await getWorkReview(id)
    setPreview(response.data)
  }

  return (
    <DashboardSection title="مراجعة أعمال الموظفين" description="اعتمد وانشر بخطوة واحدة. الموظفون لا يستطيعون النشر بأنفسهم.">
      {state.status === 'loading' ? <DashboardPanelSkeleton label="جاري تحميل الأعمال..." /> : null}
      {state.status === 'error' ? <DashboardErrorState message={state.message} onRetry={() => void reload()} /> : null}
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      <div className="grid gap-3 sm:grid-cols-3">
        <label className="block text-sm">
          الحالة
          <select value={status} onChange={(event) => setStatus(event.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            <option value="">الكل</option>
            {Object.entries(CONTENT_STATUS_LABELS).map(([value, label]) => (
              <option key={value} value={value}>{label}</option>
            ))}
          </select>
        </label>
        <label className="block text-sm">
          الدور
          <select value={role} onChange={(event) => setRole(event.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            <option value="">الكل</option>
            {EMPLOYEE_WORKSPACE_ROLES.map((value) => (
              <option key={value} value={value}>{EMPLOYEE_ROLE_LABELS[value]}</option>
            ))}
          </select>
        </label>
        <label className="block text-sm">
          التصنيف
          <select value={category} onChange={(event) => setCategory(event.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            <option value="">الكل</option>
            {PORTFOLIO_CATEGORIES.map((value) => (
              <option key={value} value={value}>{value}</option>
            ))}
          </select>
        </label>
      </div>

      {items.length === 0 && state.status === 'ready' ? (
        <DashboardEmptyState title="لا توجد أعمال للمراجعة." description="ستظهر هنا أعمال الموظفين بعد إرسالها." />
      ) : (
        <ul className="space-y-3">
          {items.map((item) => (
            <li key={item.id} className="rounded-2xl border border-slate-200 bg-white p-4">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p className="font-semibold">{item.title}</p>
                  <p className="text-sm text-slate-500">{item.employee?.name} · {item.employee?.role} · {CONTENT_STATUS_LABELS[item.status]}</p>
                </div>
                <button type="button" className="min-h-10 rounded-lg border px-3 text-sm" onClick={() => void openPreview(item.id)}>معاينة</button>
              </div>
            </li>
          ))}
        </ul>
      )}

      {preview ? (
        <div className="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
          <h3 className="font-semibold">{preview.title}</h3>
          {preview.cover_url ? <img src={preview.cover_url} alt="" className="h-48 w-full rounded-xl object-cover" /> : null}
          <p className="text-sm leading-7 text-slate-700">{preview.description}</p>
          <p className="text-sm text-slate-500">{preview.tags.join('، ')}</p>
          <textarea value={notes} onChange={(event) => setNotes(event.target.value)} placeholder="ملاحظات المراجعة" className="min-h-20 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
          <div className="flex flex-wrap gap-2">
            <button type="button" className="min-h-11 rounded-xl bg-emerald-700 px-4 text-sm text-white" onClick={() => void approvePublishWork(preview.id).then(() => { toast.success('تمت الموافقة والنشر.'); setPreview(null); return reload() }).catch((caught) => setError(describeApiError(caught, 'تعذر النشر.')))}>موافقة ونشر</button>
            <button type="button" className="min-h-11 rounded-xl border px-4 text-sm" onClick={() => {
              if (!notes.trim()) { setError('أدخل ملاحظات التعديل.'); return }
              void requestWorkChanges(preview.id, notes).then(() => { toast.success('طُلبت تعديلات.'); setPreview(null); return reload() })
            }}>طلب تعديلات</button>
            <button type="button" className="min-h-11 rounded-xl border border-red-300 px-4 text-sm text-red-800" onClick={() => {
              if (!notes.trim()) { setError('أدخل سبب الرفض.'); return }
              void rejectWork(preview.id, notes).then(() => { toast.success('تم الرفض.'); setPreview(null); return reload() })
            }}>رفض</button>
            {preview.status === 'PUBLISHED' ? (
              <button type="button" className="min-h-11 rounded-xl border px-4 text-sm" onClick={() => void unpublishWork(preview.id).then(() => { toast.success('أُلغي النشر.'); setPreview(null); return reload() })}>إلغاء النشر</button>
            ) : null}
            <button type="button" className="min-h-11 rounded-xl border px-4 text-sm" onClick={() => {
              if (!window.confirm('أرشفة هذا العمل؟')) return
              void archiveWork(preview.id).then(() => { toast.success('تمت الأرشفة.'); setPreview(null); return reload() })
            }}>أرشفة</button>
            <button type="button" className="min-h-11 rounded-xl px-4 text-sm" onClick={() => setPreview(null)}>إغلاق</button>
          </div>
        </div>
      ) : null}
    </DashboardSection>
  )
}
