import { Link } from 'react-router-dom'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../../components/catalog/CatalogStatus'
import { CustomerOrderCard } from '../../components/orders/CustomerOrderCard'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getCustomerOrders } from '../../services/orders'

export function CustomerOrdersPage() {
  const { state, reload } = useAsyncData(getCustomerOrders)

  if (state.status === 'loading') {
    return <CatalogSkeleton variant="list" label="جاري تحميل الطلبات..." />
  }

  if (state.status === 'error') {
    return (
      <CatalogErrorState
        message="حدث خطأ أثناء تحميل البيانات. حاول مرة أخرى."
        onRetry={() => void reload()}
      />
    )
  }

  return (
    <section className="space-y-5">
      <header className="space-y-1">
        <h1 className="text-2xl font-semibold">طلباتي</h1>
        <p className="text-sm text-slate-600">تتبع دورة حياة طلباتك من الاستلام حتى التسليم.</p>
      </header>

      {state.data.length === 0 ? (
        <CatalogEmptyState
          title="لا توجد طلبات حتى الآن."
          description="استكشف خدمات حبر أو الباقات أو ابدأ من المستشار الذكي."
          actions={[
            { to: '/services', label: 'استكشف الخدمات', variant: 'primary' },
            { to: '/packages', label: 'استكشف الباقات', variant: 'secondary' },
            { to: '/consultant', label: 'ابدأ مع المستشار الذكي', variant: 'secondary' },
            { to: '/build-package', label: 'أنشئ باقة مخصصة', variant: 'secondary' },
          ]}
        />
      ) : (
        <ul className="grid gap-4 lg:grid-cols-2">
          {state.data.map((order) => (
            <li key={order.id}>
              <CustomerOrderCard order={order} />
            </li>
          ))}
        </ul>
      )}

      <Link to="/dashboard" className="inline-block text-sm underline">
        العودة إلى لوحة التحكم
      </Link>
    </section>
  )
}
