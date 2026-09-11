import { FormEvent, useState } from 'react'
import { Link } from 'react-router-dom'
import { DashboardEmptyState, DashboardErrorState, DashboardPanelSkeleton, DashboardSection } from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { useToast } from '../../context/ToastContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import { CONTENT_STATUS_LABELS, uploadContentMedia, type ContentStatus } from '../../services/work'
import {
  createSupplierPortfolio,
  createSupplierProduct,
  getSupplierContent,
  resubmitSupplierPortfolio,
  resubmitSupplierProduct,
  submitSupplierPortfolio,
  submitSupplierProduct,
  type SupplierContentRow,
} from '../../services/supplierWorkspace'
import { describeApiError } from '../../utils/errors'

const TYPE_LABELS: Record<SupplierContentRow['type'], string> = {
  profile: 'ملف',
  profile_version: 'تعديل ملف',
  portfolio: 'معرض',
  product: 'منتج',
}

export function SupplierHomePage() {
  const { state, reload } = useAsyncData(getSupplierContent)
  const toast = useToast()
  const [error, setError] = useState<string | null>(null)
  const [portfolioTitle, setPortfolioTitle] = useState('')
  const [productName, setProductName] = useState('')
  const [coverUrl, setCoverUrl] = useState('/brand/logo.png')

  const items = state.status === 'ready' ? state.data.items : []
  const counts = state.status === 'ready' ? state.data.counts : null

  async function addPortfolio(event: FormEvent) {
    event.preventDefault()
    try {
      await createSupplierPortfolio({
        title: portfolioTitle,
        description: portfolioTitle,
        image: coverUrl || '/brand/logo.png',
        category: 'عام',
      })
      setPortfolioTitle('')
      toast.success('حُفظ العمل كمسودة.')
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إضافة العمل.'))
    }
  }

  async function addProduct(event: FormEvent) {
    event.preventDefault()
    try {
      await createSupplierProduct({
        name: productName,
        short_description: productName,
        contact_for_price: true,
      })
      setProductName('')
      toast.success('حُفظ المنتج كمسودة.')
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إضافة المنتج.'))
    }
  }

  async function submitRow(item: SupplierContentRow) {
    try {
      if (item.type === 'portfolio') {
        if (item.status === 'CHANGES_REQUESTED' || item.status === 'REJECTED') {
          await resubmitSupplierPortfolio(item.id)
        } else {
          await submitSupplierPortfolio(item.id)
        }
      } else if (item.type === 'product') {
        if (item.status === 'CHANGES_REQUESTED' || item.status === 'REJECTED') {
          await resubmitSupplierProduct(item.id)
        } else {
          await submitSupplierProduct(item.id)
        }
      }
      toast.success(item.status === 'DRAFT' ? 'أُرسل للمراجعة.' : 'تم إعادة الإرسال.')
      await reload()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر الإرسال.'))
    }
  }

  function canSubmit(status: ContentStatus) {
    return status === 'DRAFT' || status === 'CHANGES_REQUESTED' || status === 'REJECTED'
  }

  return (
    <DashboardSection title="محتواي" description="أنشئ مسودات الملف والمعرض والمنتجات ثم أرسلها لاعتماد المالك قبل النشر.">
      {state.status === 'loading' ? <DashboardPanelSkeleton label="جاري تحميل المحتوى..." /> : null}
      {state.status === 'error' ? <DashboardErrorState message={state.message} onRetry={() => void reload()} /> : null}
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      {counts ? (
        <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <li className="rounded-2xl border bg-white p-4"><p className="text-sm text-slate-500">مسودات</p><p className="text-2xl font-semibold">{counts.drafts}</p></li>
          <li className="rounded-2xl border bg-white p-4"><p className="text-sm text-slate-500">قيد المراجعة</p><p className="text-2xl font-semibold">{counts.under_review}</p></li>
          <li className="rounded-2xl border bg-white p-4"><p className="text-sm text-slate-500">منشور</p><p className="text-2xl font-semibold">{counts.published}</p></li>
          <li className="rounded-2xl border bg-white p-4"><p className="text-sm text-slate-500">يحتاج تعديلات</p><p className="text-2xl font-semibold">{counts.needs_changes}</p></li>
        </ul>
      ) : null}

      <div className="grid gap-4 lg:grid-cols-2">
        <form onSubmit={(event) => void addPortfolio(event)} className="space-y-2 rounded-2xl border bg-white p-4">
          <h3 className="font-semibold">إضافة عمل للمعرض</h3>
          <input required value={portfolioTitle} onChange={(event) => setPortfolioTitle(event.target.value)} placeholder="عنوان العمل" className="w-full rounded-md border px-3 py-2 text-sm" />
          <input type="file" accept="image/*" onChange={(event) => {
            const file = event.target.files?.[0]
            if (!file) return
            void uploadContentMedia(file, 'cover').then((uploaded) => setCoverUrl(uploaded.data.url)).catch((caught) => setError(describeApiError(caught, 'تعذر رفع الصورة.')))
          }} className="w-full text-sm" />
          <button type="submit" className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white">حفظ مسودة</button>
        </form>
        <form onSubmit={(event) => void addProduct(event)} className="space-y-2 rounded-2xl border bg-white p-4">
          <h3 className="font-semibold">إضافة منتج</h3>
          <input required value={productName} onChange={(event) => setProductName(event.target.value)} placeholder="اسم المنتج" className="w-full rounded-md border px-3 py-2 text-sm" />
          <button type="submit" className="min-h-11 rounded-xl bg-slate-900 px-4 text-sm text-white">حفظ مسودة</button>
        </form>
      </div>

      {items.length === 0 && state.status === 'ready' ? (
        <DashboardEmptyState title="لا يوجد محتوى بعد." description="ابدأ من الملف الشخصي أو أضف عملاً أو منتجاً." action={<Link to="/supplier/profile" className="text-sm underline">تعديل الملف</Link>} />
      ) : (
        <ul className="space-y-2">
          {items.map((item) => (
            <li key={`${item.type}-${item.id}`} className="rounded-xl border bg-white px-4 py-3">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p className="font-medium">{item.title}</p>
                  <p className="text-sm text-slate-500">{TYPE_LABELS[item.type]} · {CONTENT_STATUS_LABELS[item.status]}</p>
                  <p className="text-xs text-slate-400">{item.updated_at ? new Date(item.updated_at).toLocaleDateString('ar-SA') : '—'}</p>
                  {item.review_notes ? <p className="text-sm text-amber-800">{item.review_notes}</p> : null}
                </div>
                <div className="flex flex-wrap gap-2">
                  {item.type === 'profile' || item.type === 'profile_version' ? (
                    <Link to="/supplier/profile" className="min-h-10 rounded-lg border px-3 text-sm leading-10">تعديل / عرض</Link>
                  ) : null}
                  {canSubmit(item.status) && (item.type === 'portfolio' || item.type === 'product') ? (
                    <button type="button" className="min-h-10 rounded-lg bg-slate-900 px-3 text-sm text-white" onClick={() => void submitRow(item)}>
                      {item.status === 'DRAFT' ? 'إرسال' : 'إعادة إرسال'}
                    </button>
                  ) : null}
                </div>
              </div>
            </li>
          ))}
        </ul>
      )}
    </DashboardSection>
  )
}
