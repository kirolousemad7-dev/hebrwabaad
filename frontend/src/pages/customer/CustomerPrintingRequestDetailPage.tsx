import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../../components/catalog/CatalogStatus'
import { PrintingPricingStatus } from '../../components/printing/PrintingPricingStatus'
import { PrintingRequestSpecs } from '../../components/printing/PrintingRequestSpecs'
import { useAsyncData } from '../../hooks/useAsyncData'
import {
  downloadCustomerPrintingRequestFile,
  getCustomerPrintingRequest,
  reorderCustomerPrintingRequest,
} from '../../services/printingRequests'
import { describeApiError } from '../../utils/errors'

type CustomerPrintingRequestDetailBodyProps = {
  id: number
}

function CustomerPrintingRequestDetailBody({ id }: CustomerPrintingRequestDetailBodyProps) {
  const navigate = useNavigate()
  const { state, reload } = useAsyncData(() => getCustomerPrintingRequest(id))
  const [fileError, setFileError] = useState<string | null>(null)
  const [downloading, setDownloading] = useState(false)
  const [reordering, setReordering] = useState(false)
  const [reorderError, setReorderError] = useState<string | null>(null)

  async function handleDownload() {
    if (state.status !== 'ready') {
      return
    }

    setFileError(null)
    setDownloading(true)

    try {
      await downloadCustomerPrintingRequestFile(id, state.data.filename)
    } catch (caught) {
      setFileError(describeApiError(caught, 'تعذر تنزيل الملف.'))
    } finally {
      setDownloading(false)
    }
  }

  async function handleReorder() {
    if (state.status !== 'ready') {
      return
    }

    setReorderError(null)
    setReordering(true)

    try {
      const created = await reorderCustomerPrintingRequest(id)
      navigate(`/customer/printing-requests/${created.data.id}`)
    } catch (caught) {
      setReorderError(
        describeApiError(caught, 'تعذر إعادة الطلب. يمكن إعادة الطلب بعد اكتمال التنفيذ أو الجاهزية.'),
      )
    } finally {
      setReordering(false)
    }
  }

  if (state.status === 'loading') {
    return <CatalogSkeleton variant="list" label="جاري تحميل الطلب..." />
  }

  if (state.status === 'error') {
    if (state.message.toLowerCase().includes('not found') || state.message.includes('Forbidden')) {
      return (
        <CatalogEmptyState
          title="لم نجد هذا الطلب."
          description="قد يكون الطلب غير موجود أو لا يخص حسابك."
          actions={[{ to: '/customer/printing-requests', label: 'طلبات الطباعة', variant: 'primary' }]}
        />
      )
    }

    return <CatalogErrorState message={`تعذر تحميل الطلب. ${state.message}`} onRetry={() => void reload()} />
  }

  const request = state.data
  const canReorder = String(request.status) === 'COMPLETED' || String(request.status) === 'READY_FOR_DELIVERY'

  return (
    <article className="space-y-6">
      <header className="space-y-2">
        <p className="text-sm text-slate-500">طلب #{request.id}</p>
        <h1 className="text-2xl font-semibold">{request.product_name}</h1>
      </header>
      <PrintingPricingStatus request={request} />
      <section className="space-y-3 rounded-xl border border-slate-200 bg-white p-5">
        <h2 className="font-semibold">المواصفات</h2>
        <PrintingRequestSpecs request={request} />
        <div className="flex flex-wrap gap-3">
          <button
            type="button"
            onClick={() => void handleDownload()}
            disabled={downloading}
            className="rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 disabled:opacity-60"
          >
            {downloading ? 'جاري التنزيل...' : 'تنزيل ملف التصميم'}
          </button>
          {canReorder ? (
            <button
              type="button"
              onClick={() => void handleReorder()}
              disabled={reordering}
              className="rounded-md bg-slate-900 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60"
            >
              {reordering ? 'جاري إنشاء الطلب...' : 'إعادة الطلب'}
            </button>
          ) : null}
        </div>
        {fileError ? (
          <p className="text-sm text-red-700" role="alert">
            {fileError}
          </p>
        ) : null}
        {reorderError ? (
          <p className="text-sm text-red-700" role="alert">
            {reorderError}
          </p>
        ) : null}
        {canReorder ? (
          <p className="text-sm text-slate-500">
            إعادة الطلب تنسخ المواصفات فقط وتعيد التسعير حسب البيانات الحالية — لا يُعاد استخدام السعر أو الدفع أو الاعتماد
            السابق.
          </p>
        ) : null}
      </section>
      <p className="text-sm">
        <Link
          to="/customer/printing-requests"
          className="underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
        >
          العودة إلى الطلبات
        </Link>
      </p>
    </article>
  )
}

export function CustomerPrintingRequestDetailPage() {
  const { id = '' } = useParams()
  const numericId = Number.parseInt(id, 10)

  if (!Number.isInteger(numericId) || numericId < 1) {
    return (
      <CatalogEmptyState
        title="لم نجد هذا الطلب."
        description="رقم الطلب غير صالح."
        actions={[{ to: '/customer/printing-requests', label: 'طلبات الطباعة', variant: 'primary' }]}
      />
    )
  }

  return <CustomerPrintingRequestDetailBody key={numericId} id={numericId} />
}
