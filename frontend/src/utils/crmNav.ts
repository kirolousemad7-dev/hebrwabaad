import type { DashboardNavItem } from './dashboardNav'
import { CRM_ROLES as ROLE_CRM_ROLES } from './roles'

export const CRM_ROLES = ROLE_CRM_ROLES

export type CrmRole = (typeof CRM_ROLES)[number]

export const CRM_MANAGER_ROLES = ['OWNER', 'ADMIN_MANAGER', 'SALES_MANAGER'] as const

export const CRM_NAV: DashboardNavItem[] = [
  { to: '/crm', label: 'لوحة المبيعات', end: true, icon: 'home' },
  { to: '/crm/inbox', label: 'صندوق الوارد', icon: 'messages' },
  { to: '/crm/leads', label: 'العملاء المحتملون', icon: 'crm' },
  { to: '/crm/companies', label: 'الشركات', icon: 'suppliers' },
  { to: '/crm/contacts', label: 'جهات الاتصال', icon: 'employees' },
  { to: '/crm/opportunities', label: 'الفرص', icon: 'projects' },
  { to: '/crm/pipeline', label: 'خط الأنابيب', icon: 'projects' },
  { to: '/crm/follow-ups', label: 'المتابعات', icon: 'tasks' },
  { to: '/crm/calendar', label: 'تقويم CRM', icon: 'tasks' },
  { to: '/crm/work-calendar', label: 'التقويم', icon: 'calendar' },
  { to: '/crm/quotations', label: 'عروض الأسعار', icon: 'orders' },
  { to: '/crm/targets', label: 'الأهداف', icon: 'seo' },
  { to: '/crm/forecast', label: 'التوقعات', icon: 'seo' },
  { to: '/crm/reports', label: 'التقارير', icon: 'seo' },
  { to: '/crm/team', label: 'الفريق', icon: 'employees' },
  { to: '/crm/audit', label: 'سجل التدقيق', icon: 'files' },
  { to: '/crm/settings', label: 'الإعدادات', icon: 'profile' },
]

const MANAGER_ONLY_PATHS = new Set(['/crm/settings', '/crm/team', '/crm/reports', '/crm/audit'])

export function isCrmManager(role: string | undefined): boolean {
  return role !== undefined && (CRM_MANAGER_ROLES as readonly string[]).includes(role)
}

export function crmNavForRole(role: string | undefined): DashboardNavItem[] {
  if (isCrmManager(role)) {
    return CRM_NAV
  }

  return CRM_NAV.filter((item) => !MANAGER_ONLY_PATHS.has(item.to))
}

export const CRM_STATUS_LABELS: Record<string, string> = {
  NEW: 'جديد',
  ATTEMPTED_CONTACT: 'محاولة تواصل',
  CONTACTED: 'تم التواصل',
  QUALIFIED: 'مؤهل',
  UNQUALIFIED: 'غير مؤهل',
  FOLLOW_UP: 'متابعة',
  INTERESTED: 'مهتم',
  PROPOSAL_SENT: 'عرض مُرسل',
  NEGOTIATION: 'تفاوض',
  WON: 'تم الإغلاق',
  LOST: 'خسارة',
}

export const CRM_PRIORITY_LABELS: Record<string, string> = {
  LOW: 'منخفضة',
  MEDIUM: 'متوسطة',
  HIGH: 'عالية',
  URGENT: 'عاجلة',
}

export const CRM_FOLLOW_UP_TYPE_LABELS: Record<string, string> = {
  CALL: 'اتصال',
  WHATSAPP: 'واتساب',
  EMAIL: 'بريد',
  MEETING: 'اجتماع',
  DEMO: 'عرض تجريبي',
  PROPOSAL: 'عرض سعر',
  OTHER: 'أخرى',
}

export const CRM_FOLLOW_UP_STATUS_LABELS: Record<string, string> = {
  SCHEDULED: 'مجدولة',
  COMPLETED: 'مكتملة',
  MISSED: 'فائتة',
  OVERDUE: 'متأخرة',
  RESCHEDULED: 'أُعيدت جدولتها',
  CANCELLED: 'ملغاة',
}

export const CRM_ACTIVITY_TYPE_LABELS: Record<string, string> = {
  CALL: 'اتصال',
  WHATSAPP: 'واتساب',
  EMAIL: 'بريد',
  MEETING: 'اجتماع',
  VIDEO_MEETING: 'اجتماع مرئي',
  DEMO: 'عرض تجريبي',
  PROPOSAL: 'عرض سعر',
  NOTE: 'ملاحظة',
  FOLLOW_UP: 'متابعة',
  TASK: 'مهمة',
  STAGE_CHANGE: 'تغيير مرحلة',
  ASSIGNMENT: 'تعيين',
  CONVERSION: 'تحويل',
}

export const CRM_QUOTATION_STATUS_LABELS: Record<string, string> = {
  DRAFT: 'مسودة',
  PENDING_APPROVAL: 'بانتظار الموافقة',
  APPROVED: 'معتمد',
  SENT: 'مُرسل',
  VIEWED: 'تم الاطلاع',
  ACCEPTED: 'مقبول',
  REJECTED: 'مرفوض',
  EXPIRED: 'منتهٍ',
}

export const CRM_TARGET_TYPE_LABELS: Record<string, string> = {
  revenue: 'إيرادات',
  deals_won: 'صفقات رابحة',
  qualified_leads: 'عملاء مؤهلون',
  new_customers: 'عملاء جدد',
  calls: 'مكالمات',
  meetings: 'اجتماعات',
  quotations: 'عروض أسعار',
}

export const CRM_ASSIGNMENT_MODE_LABELS: Record<string, string> = {
  manual: 'يدوي',
  round_robin: 'توزيع دوري',
  by_source: 'حسب المصدر',
  by_service: 'حسب الخدمة',
}

export function crmStatusLabel(status: string | null | undefined): string {
  if (!status) {
    return '—'
  }

  return CRM_STATUS_LABELS[status] ?? status
}

export function crmPriorityLabel(priority: string | null | undefined): string {
  if (!priority) {
    return '—'
  }

  return CRM_PRIORITY_LABELS[priority] ?? priority
}

export function crmTargetTypeLabel(type: string | null | undefined): string {
  if (!type) {
    return '—'
  }

  return CRM_TARGET_TYPE_LABELS[type] ?? type
}
