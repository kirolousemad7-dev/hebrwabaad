import type { CustomerOrder, OrderStatus, OrderTimelineStep } from '../types/api'

export const ORDER_STATUS_LABELS: Record<OrderStatus, string> = {
  RECEIVED: 'تم استلام الطلب',
  CONFIRMED: 'تم تأكيد الطلب',
  IN_PROGRESS: 'قيد التنفيذ',
  REVIEW: 'قيد المراجعة',
  REVISION: 'التعديلات',
  COMPLETED: 'مكتمل',
  DELIVERED: 'تم التسليم',
}

export const ORDER_STATUS_SEQUENCE: OrderStatus[] = [
  'RECEIVED',
  'CONFIRMED',
  'IN_PROGRESS',
  'REVIEW',
  'REVISION',
  'COMPLETED',
  'DELIVERED',
]

const ORDER_PROGRESS: Record<OrderStatus, number> = {
  RECEIVED: 0,
  CONFIRMED: 16,
  IN_PROGRESS: 33,
  REVIEW: 50,
  REVISION: 66,
  COMPLETED: 83,
  DELIVERED: 100,
}

export const ALLOWED_ORDER_TRANSITIONS: Record<OrderStatus, OrderStatus[]> = {
  RECEIVED: ['CONFIRMED'],
  CONFIRMED: ['IN_PROGRESS'],
  IN_PROGRESS: ['REVIEW'],
  REVIEW: ['REVISION', 'COMPLETED'],
  REVISION: ['IN_PROGRESS', 'REVIEW'],
  COMPLETED: ['DELIVERED'],
  DELIVERED: [],
}

export function isOrderStatus(value: string): value is OrderStatus {
  return ORDER_STATUS_SEQUENCE.includes(value as OrderStatus)
}

export function orderProgress(status: string): number {
  return isOrderStatus(status) ? ORDER_PROGRESS[status] : 0
}

export function orderStatusLabel(status: string): string {
  return isOrderStatus(status) ? ORDER_STATUS_LABELS[status] : status
}

export function canTransitionOrder(from: string, to: string): boolean {
  if (!isOrderStatus(from) || !isOrderStatus(to)) {
    return false
  }

  return ALLOWED_ORDER_TRANSITIONS[from].includes(to)
}

export function timelineForStatus(status: string): OrderTimelineStep[] {
  const current = isOrderStatus(status) ? status : 'RECEIVED'
  const currentIndex = ORDER_STATUS_SEQUENCE.indexOf(current)

  return ORDER_STATUS_SEQUENCE.map((step, index) => ({
    status: step,
    label: ORDER_STATUS_LABELS[step],
    state: step === current ? 'current' : index < currentIndex ? 'completed' : 'pending',
    occurred_at: null,
  }))
}

export function customerOrderPath(id: number): string {
  return `/dashboard/orders/${id}`
}

export function formatOrderProgress(percent: number): string {
  return `${percent.toLocaleString('ar-SA')}٪`
}

export function ordersSectionView(orders: CustomerOrder[]): { kind: 'empty' | 'ready'; title?: string; count?: number } {
  if (orders.length === 0) {
    return { kind: 'empty', title: 'لا توجد طلبات حتى الآن.' }
  }

  return { kind: 'ready', count: orders.length }
}

export function describeOrderError(status: number): string {
  if (status === 401) {
    return 'يلزم تسجيل الدخول لعرض الطلبات.'
  }

  if (status === 403) {
    return 'لا يمكنك الوصول إلى هذا الطلب.'
  }

  if (status === 404) {
    return 'تعذر تحميل الطلب.'
  }

  if (status === 422 || status === 409) {
    return 'لا يمكن الانتقال إلى هذه المرحلة حاليًا.'
  }

  return 'حدث خطأ أثناء تحميل البيانات. حاول مرة أخرى.'
}

export function describeOrderLoadError(message: string): string {
  const lower = message.toLowerCase()

  if (lower.includes('unauthorized') || lower.includes('forbidden') || message.includes('لا يمكنك')) {
    return describeOrderError(403)
  }

  if (lower.includes('not found') || message.includes('تعذر تحميل الطلب')) {
    return describeOrderError(404)
  }

  return describeOrderError(500)
}
