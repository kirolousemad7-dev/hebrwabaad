import { ManagedOrderDetail } from '../../components/orders/ManagedOrderDetail'

export function WorkspaceOrderDetailPage() {
  return <ManagedOrderDetail listPath="/workspace/orders" projectPath={(id) => `/workspace/projects/${id}`} />
}
