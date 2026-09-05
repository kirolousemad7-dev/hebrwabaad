import { FileLibrary } from '../../components/files/FileLibrary'
import { useAuth } from '../../context/AuthContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getManagedOrders } from '../../services/orders'
import { getWorkspaceProjects } from '../../services/workspaceProjects'
import { getMyTasks } from '../../services/workspaceTasks'
import { FILE_COPY } from '../../utils/files'

export function WorkspaceFilesPage() {
  const { user } = useAuth()
  const canUseOrders = user?.role === 'ACCOUNT_MANAGER' || user?.role === 'OWNER'

  const { state } = useAsyncData(async () => {
    const [projects, tasks, orders] = await Promise.all([
      getWorkspaceProjects('?per_page=50').catch(() => ({ data: { items: [] } })),
      getMyTasks('?per_page=50').catch(() => ({ data: { items: [] } })),
      canUseOrders ? getManagedOrders('?per_page=50').catch(() => ({ data: { items: [] } })) : Promise.resolve({ data: { items: [] } }),
    ])

    return {
      data: {
        projects: projects.data.items.map((project) => ({ id: project.id, label: project.title })),
        tasks: tasks.data.items.map((task) => ({ id: task.id, label: task.title })),
        orders: orders.data.items.map((order) => ({ id: order.id, label: order.reference })),
      },
    }
  })

  return (
    <section className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold">{FILE_COPY.title}</h1>
        <p className="mt-1 text-sm text-slate-600">الملفات المرتبطة بالمشاريع أو المهام المصرّح لك بها فقط.</p>
      </header>
      <FileLibrary
        scope="workspace"
        projects={state.status === 'ready' ? state.data.projects : []}
        tasks={state.status === 'ready' ? state.data.tasks : []}
        orders={state.status === 'ready' ? state.data.orders : []}
      />
    </section>
  )
}
