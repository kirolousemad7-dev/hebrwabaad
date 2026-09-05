import { ManagedOrdersBoard } from '../../components/orders/ManagedOrdersBoard'

export function OwnerOrdersPage() {
  return <ManagedOrdersBoard detailBase="/owner/orders" isOwner />
}
