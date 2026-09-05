import type { OwnerDashboardMetric } from '../types/api'
import { EMPLOYEE_ROLE_LABELS } from './staff'

export { EMPLOYEE_ROLE_LABELS }

export const ACTIVITY_TYPE_LABELS: Record<string, string> = {
  printing_request_submitted: 'طلب طباعة جديد',
  printing_quote_ready: 'عرض سعر جاهز',
  customer_registered: 'عميل جديد',
  supplier_added: 'مورد جديد',
}

export const ACTIVITY_STATUS_LABELS: Record<string, string> = {
  PENDING: 'قيد المراجعة',
  QUOTE_READY: 'عرض سعر جاهز',
  CUSTOMER: 'عميل',
  ACTIVE: 'نشط',
  INACTIVE: 'غير نشط',
}

export const UNAVAILABLE_METRIC_COPY: Record<string, string> = {
  no_recorded_revenue: 'لا توجد مدفوعات أو إيرادات مسجّلة في النظام بعد.',
  orders_not_implemented: 'إدارة الطلبات غير مفعّلة بعد.',
  projects_not_implemented: 'إدارة المشاريع غير مفعّلة بعد.',
  leads_not_implemented: 'إدارة العملاء المحتملين غير مفعّلة بعد.',
}

export function metricSecondaryNumber(
  metric: OwnerDashboardMetric,
  key: string,
): number | null {
  const value = metric.secondary[key]

  return typeof value === 'number' ? value : null
}

export function formatDashboardDateTime(value: string | null | undefined): string {
  if (!value) {
    return '—'
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return '—'
  }

  return date.toLocaleString('ar-SA', {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
}

export function formatShortAxisDate(value: string): string {
  const [year, month, day] = value.split('-').map(Number)
  const date = new Date(year, (month ?? 1) - 1, day ?? 1)

  return date.toLocaleDateString('ar-SA', { month: 'numeric', day: 'numeric' })
}
