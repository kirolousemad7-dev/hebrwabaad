import type { CustomerDashboardMetric, CustomerProject, UnavailableDomain } from '../types/api'

export const CUSTOMER_CONSULTANT_CTA = {
  heading: 'مش عارف إيه الخدمة المناسبة ليك؟',
  body: 'خلّي HEBR يساعدك.',
  label: 'ابدأ الاستشارة الذكية',
  path: '/consultant',
} as const

export const CUSTOMER_DASHBOARD_COPY = {
  loading: 'جاري تحميل لوحة التحكم...',
  error: 'حدث خطأ أثناء تحميل البيانات. حاول مرة أخرى.',
  emptyProjects: 'لا توجد مشاريع حاليًا',
  emptyProjectsCta: { to: '/services', label: 'استكشف خدمات HEBR' },
  emptyOrders: 'لا توجد طلبات حتى الآن',
  emptyMessages: 'لا توجد محادثات بعد.',
} as const

export const CUSTOMER_ACTIVITY_LABELS: Record<string, string> = {
  project_created: 'مشروع جديد',
  project_updated: 'تحديث مشروع',
  printing_request_submitted: 'طلب طباعة',
  printing_quote_ready: 'عرض سعر جاهز',
  ai_consultation_started: 'بداية استشارة',
  consultation_completed: 'اكتمال استشارة',
  quote_requested: 'طلب تواصل',
  order_created: 'طلب جديد',
  order_status_changed: 'تحديث حالة الطلب',
  conversation_created: 'محادثة دعم',
  file_uploaded: 'رفع ملف',
}

export const CUSTOMER_LIVE_ROUTES = [
  '/dashboard',
  '/customer',
  '/dashboard/projects',
  '/dashboard/orders',
  '/dashboard/messages',
  '/dashboard/files',
  '/dashboard/notifications',
  '/dashboard/profile',
  '/customer/printing-requests',
  '/consultant',
  '/services',
  '/packages',
  '/build-package',
  '/printing-packaging',
] as const

export function customerInitials(name: string | undefined): string {
  const trimmed = name?.trim() ?? ''
  if (trimmed === '') {
    return 'ح'
  }

  return trimmed.slice(0, 1)
}

export function isUnavailableDomain(value: unknown): value is UnavailableDomain {
  return (
    typeof value === 'object' &&
    value !== null &&
    'available' in value &&
    (value as UnavailableDomain).available === false &&
    (value as UnavailableDomain).status === 'unavailable'
  )
}

export function isLiveCustomerPath(path: string): boolean {
  const [pathname] = path.split('?')

  return CUSTOMER_LIVE_ROUTES.some((route) => {
    if (pathname === route) {
      return true
    }

    if (route === '/dashboard' || route === '/customer') {
      return false
    }

    return pathname.startsWith(`${route}/`)
  })
}

export function customerProjectPath(id: number): string {
  return `/dashboard/projects/${id}`
}

export type CustomerSectionView =
  | { kind: 'loading'; label: string }
  | { kind: 'error'; message: string; canRetry: true }
  | { kind: 'empty'; title: string; cta?: { to: string; label: string } }
  | { kind: 'unavailable'; title: string; message: string }
  | { kind: 'ready'; count: number }

export function dashboardLoadView(status: 'loading' | 'error' | 'ready'): CustomerSectionView {
  if (status === 'loading') {
    return { kind: 'loading', label: CUSTOMER_DASHBOARD_COPY.loading }
  }

  if (status === 'error') {
    return { kind: 'error', message: CUSTOMER_DASHBOARD_COPY.error, canRetry: true }
  }

  return { kind: 'ready', count: 1 }
}

export function projectsSectionView(projects: CustomerProject[]): CustomerSectionView {
  if (projects.length === 0) {
    return {
      kind: 'empty',
      title: CUSTOMER_DASHBOARD_COPY.emptyProjects,
      cta: CUSTOMER_DASHBOARD_COPY.emptyProjectsCta,
    }
  }

  return { kind: 'ready', count: projects.length }
}

export function unavailableSectionView(domain: UnavailableDomain, title: string): CustomerSectionView {
  return { kind: 'unavailable', title, message: domain.message }
}

export function metricCardView(metric: CustomerDashboardMetric, emptyLabel: string): { kind: 'value' | 'empty' | 'unavailable'; text: string } {
  if (!metric.available) {
    return { kind: 'unavailable', text: 'غير متاح بعد' }
  }

  if ((metric.value ?? 0) === 0) {
    return { kind: 'empty', text: emptyLabel }
  }

  return { kind: 'value', text: String(metric.value) }
}
