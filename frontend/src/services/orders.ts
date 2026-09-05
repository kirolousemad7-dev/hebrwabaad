import type { CustomerOrder, ManagedOrder, ManagedOrderListData, OrderLookups } from '../types/api'
import { apiGet, apiPatch, apiPost } from './api'

export function getCustomerOrders() {
  return apiGet<CustomerOrder[]>('/api/customer/orders')
}

export function getCustomerOrder(id: number) {
  return apiGet<CustomerOrder>(`/api/customer/orders/${id}`)
}

export function createCustomerPackageOrder(packageSlug: string, tierSlug?: string | null) {
  return apiPost<CustomerOrder & { reused: boolean }>('/api/customer/orders', {
    package_slug: packageSlug,
    package_tier_slug: tierSlug ?? null,
  })
}

export function getManagedOrderLookups() {
  return apiGet<OrderLookups>('/api/orders/lookups')
}

export function getManagedOrders(query = '') {
  return apiGet<ManagedOrderListData>(`/api/orders${query}`)
}

export function getManagedOrder(id: number) {
  return apiGet<ManagedOrder>(`/api/orders/${id}`)
}

export function createManagedOrder(payload: {
  title: string
  description?: string
  customer_id: number
  account_manager_id?: number
  project_id?: number
  service_id?: number
  package_id?: number
}) {
  return apiPost<ManagedOrder>('/api/orders', payload)
}

export function updateManagedOrderStatus(id: number, status: string) {
  return apiPatch<ManagedOrder>(`/api/orders/${id}/status`, { status })
}
