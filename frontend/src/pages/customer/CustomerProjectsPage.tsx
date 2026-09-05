import { Link } from 'react-router-dom'
import { CustomerProjectCard } from '../../components/customer/CustomerProjectCard'
import { CatalogEmptyState, CatalogErrorState, CatalogSkeleton } from '../../components/catalog/CatalogStatus'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getCustomerProjects } from '../../services/customerDashboard'

export function CustomerProjectsPage() {
  const { state, reload } = useAsyncData(getCustomerProjects)

  if (state.status === 'loading') {
    return <CatalogSkeleton variant="list" label="جاري تحميل المشاريع..." />
  }

  if (state.status === 'error') {
    return <CatalogErrorState message="حدث خطأ أثناء تحميل البيانات. حاول مرة أخرى." onRetry={() => void reload()} />
  }

  return (
    <section className="space-y-5">
      <header className="space-y-1">
        <h1 className="text-2xl font-semibold">مشاريعي</h1>
        <p className="text-sm text-slate-600">تعرض هذه الصفحة المشاريع المرتبطة بحسابك فقط.</p>
      </header>

      {state.data.length === 0 ? (
        <CatalogEmptyState
          title="لا توجد مشاريع حاليًا"
          description="استكشف خدمات حبر أو ابدأ من المستشار الذكي."
          actions={[
            { to: '/services', label: 'استكشف خدمات HEBR', variant: 'primary' },
            { to: '/consultant', label: 'ابدأ الاستشارة الذكية', variant: 'secondary' },
          ]}
        />
      ) : (
        <ul className="grid gap-4 lg:grid-cols-2">
          {state.data.map((project) => (
            <li key={project.id}>
              <CustomerProjectCard project={project} />
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
