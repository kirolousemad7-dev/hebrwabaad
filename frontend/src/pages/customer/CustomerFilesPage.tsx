import { FileLibrary } from '../../components/files/FileLibrary'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getCustomerProjects } from '../../services/customerDashboard'
import { getCustomerOrders } from '../../services/orders'
import { FILE_COPY } from '../../utils/files'

export function CustomerFilesPage() {
  const { state } = useAsyncData(async () => {
    const [projects, orders] = await Promise.all([getCustomerProjects(), getCustomerOrders()])
    return {
      data: {
        projects: projects.data.map((project) => ({ id: project.id, label: project.title })),
        orders: orders.data.map((order) => ({ id: order.id, label: order.reference })),
      },
    }
  })

  return (
    <section className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold">{FILE_COPY.title}</h1>
        <p className="mt-1 text-sm text-slate-600">ملفات مشاريعك وطلباتك فقط. التنزيل يتم بعد التحقق من صلاحيتك.</p>
      </header>
      <FileLibrary
        scope="customer"
        projects={state.status === 'ready' ? state.data.projects : []}
        orders={state.status === 'ready' ? state.data.orders : []}
      />
    </section>
  )
}
