import { Link } from 'react-router-dom'
import { useAsyncData } from '../../../hooks/useAsyncData'
import { getAdminPrintingRequests } from '../../../services/printingRequests'
import { WorkspaceEmptyState, WorkspaceErrorState } from '../WorkspaceStatus'
import { WorkspaceWidget } from '../WorkspaceWidget'

export function PrintingQueueWidget() {
  const { state, reload } = useAsyncData(() => getAdminPrintingRequests())

  return (
    <WorkspaceWidget title="طابور الطباعة">
      {state.status === 'loading' ? (
        <div className="h-24 animate-pulse rounded-xl bg-slate-100" aria-busy="true" />
      ) : null}

      {state.status === 'error' ? (
        <WorkspaceErrorState message={state.message} onRetry={() => void reload()} />
      ) : null}

      {state.status === 'ready' && state.data.length === 0 ? (
        <WorkspaceEmptyState title="لا توجد طلبات طباعة." description="ستظهر الطلبات هنا عند إرسال العملاء لها." />
      ) : null}

      {state.status === 'ready' && state.data.length > 0 ? (
        <div className="space-y-3 text-sm">
          <p>
            <span className="font-medium">
              {state.data.filter((request) => request.status === 'PENDING').length.toLocaleString('ar-SA')}
            </span>{' '}
            طلب قيد المراجعة
          </p>
          <ul className="space-y-2">
            {state.data.slice(0, 5).map((request) => (
              <li key={request.id} className="rounded-xl border border-slate-200 px-3 py-2">
                <p className="font-medium">{request.product_name}</p>
                <p className="text-slate-600">قيد المراجعة</p>
              </li>
            ))}
          </ul>
          <Link
            to="/printing-requests"
            className="inline-block rounded-lg bg-slate-900 px-3 py-2 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          >
            فتح طلبات الطباعة
          </Link>
        </div>
      ) : null}
    </WorkspaceWidget>
  )
}
