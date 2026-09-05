import { ManagedOrdersBoard } from '../../components/orders/ManagedOrdersBoard'

export function WorkspaceOrdersPage() {
  return <ManagedOrdersBoard detailBase="/workspace/orders" isOwner={false} />
}
