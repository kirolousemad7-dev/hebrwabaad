import { useState } from 'react'
import { DashboardEmptyState, DashboardErrorState, DashboardPanelSkeleton, DashboardSection } from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useToast } from '../../context/ToastContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import { approveSupplierReview, getSupplierReviews, rejectSupplierReview, requestSupplierReviewChanges } from '../../services/supplierWorkspace'
import { CONTENT_STATUS_LABELS } from '../../services/work'
import { describeApiError } from '../../utils/errors'

export function OwnerSupplierReviewsPage() {
  const { state, reload } = useAsyncData(getSupplierReviews)
  const toast = useToast()
  const [notes, setNotes] = useState('')
  const [error, setError] = useState<string | null>(null)
  const items = state.status === 'ready' ? state.data.items : []

  return (
    <DashboardSection title="مراجعة محتوى الموردين" description="لا يُنشر أي محتوى مورد قبل موافقتك.">
      {state.status === 'loading' ? <DashboardPanelSkeleton label="جاري التحميل..." /> : null}
      {state.status === 'error' ? <DashboardErrorState message={state.message} onRetry={() => void reload()} /> : null}
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}
      <textarea value={notes} onChange={(event) => setNotes(event.target.value)} placeholder="ملاحظات الرفض أو طلب التعديل" className="min-h-20 w-full rounded-md border px-3 py-2 text-sm" />
      {items.length === 0 && state.status === 'ready' ? (
        <DashboardEmptyState title="لا توجد عناصر بانتظار المراجعة." description="ستظهر هنا ملفات ومعارض ومنتجات الموردين بعد الإرسال." />
      ) : (
        <ul className="space-y-3">
          {items.map((item) => (
            <li key={`${item.type}-${item.id}`} className="rounded-2xl border bg-white p-4">
              <p className="font-semibold">{item.title}</p>
              <p className="text-sm text-slate-500">{item.supplier_name} · {item.type} · {CONTENT_STATUS_LABELS[item.status]}</p>
              <div className="mt-3 flex flex-wrap gap-2">
                <button type="button" className="min-h-10 rounded-lg bg-emerald-700 px-3 text-sm text-white" onClick={() => void approveSupplierReview(item.type, item.id).then(() => { toast.success('تمت الموافقة والنشر.'); return reload() }).catch((caught) => setError(describeApiError(caught, 'تعذر النشر.')))}>موافقة ونشر</button>
                <button type="button" className="min-h-10 rounded-lg border px-3 text-sm" onClick={() => {
                  if (!notes.trim()) { setError('أدخل الملاحظات.'); return }
                  void requestSupplierReviewChanges(item.type, item.id, notes).then(() => reload())
                }}>طلب تعديلات</button>
                <button type="button" className="min-h-10 rounded-lg border border-red-300 px-3 text-sm text-red-800" onClick={() => {
                  if (!notes.trim()) { setError('أدخل سبب الرفض.'); return }
                  void rejectSupplierReview(item.type, item.id, notes).then(() => reload())
                }}>رفض</button>
              </div>
            </li>
          ))}
        </ul>
      )}
    </DashboardSection>
  )
}
