import type {
  CalendarItemPriority,
  CalendarItemStatus,
  CalendarItemType,
  CalendarRecurrenceFreq,
  CalendarReminderOffset,
  CalendarSource,
  CalendarVisibility,
} from '../services/calendar'
import type { StatusTone } from './statusTone'

export const CALENDAR_TYPE_LABELS: Record<CalendarItemType, string> = {
  TASK: 'مهمة',
  MEETING: 'اجتماع',
  APPOINTMENT: 'موعد',
  FOLLOW_UP: 'متابعة',
  CALL: 'مكالمة',
  DEADLINE: 'موعد نهائي',
  REMINDER: 'تذكير',
  EVENT: 'فعالية',
  OTHER: 'أخرى',
}

export const CALENDAR_STATUS_LABELS: Record<CalendarItemStatus, string> = {
  SCHEDULED: 'مجدولة',
  IN_PROGRESS: 'قيد التنفيذ',
  COMPLETED: 'مكتملة',
  CANCELLED: 'ملغاة',
  OVERDUE: 'متأخرة',
}

export const CALENDAR_PRIORITY_LABELS: Record<CalendarItemPriority, string> = {
  LOW: 'منخفضة',
  MEDIUM: 'متوسطة',
  HIGH: 'عالية',
  URGENT: 'عاجلة',
}

export const CALENDAR_SOURCE_LABELS: Record<CalendarSource, string> = {
  MANUAL: 'يدوي',
  CRM: 'CRM',
  ORDER: 'طلب',
  PROJECT: 'مشروع',
  PRINTING: 'طباعة',
  PAYMENT: 'دفع',
  CONTENT: 'محتوى',
  SUPPLIER: 'مورد',
  GOOGLE: 'Google',
}

export const CALENDAR_VISIBILITY_LABELS: Record<CalendarVisibility, string> = {
  PRIVATE: 'خاص',
  PARTICIPANTS: 'المشاركون',
  TEAM: 'عام داخل الإدارة',
}

export const CALENDAR_REMINDER_LABELS: Record<CalendarReminderOffset, string> = {
  AT_START: 'عند الموعد',
  MINUTES_5: 'قبل 5 دقائق',
  MINUTES_10: 'قبل 10 دقائق',
  MINUTES_15: 'قبل 15 دقيقة',
  MINUTES_30: 'قبل 30 دقيقة',
  HOUR_1: 'قبل ساعة',
  HOURS_2: 'قبل ساعتين',
  DAY_1: 'قبل يوم',
  DAY_2: 'قبل يومين',
  WEEK_1: 'قبل أسبوع',
}

export const CALENDAR_RECURRENCE_LABELS: Record<CalendarRecurrenceFreq, string> = {
  DAILY: 'يومياً',
  WEEKLY: 'أسبوعياً',
  MONTHLY: 'شهرياً',
  YEARLY: 'سنوياً',
}

export type CalendarWorkloadLevel = 'light' | 'medium' | 'heavy' | 'critical'

export const CALENDAR_WORKLOAD_LEVEL_LABELS: Record<CalendarWorkloadLevel, string> = {
  light: 'خفيف',
  medium: 'متوسط',
  heavy: 'مرتفع',
  critical: 'حرج',
}

export const CALENDAR_QUICK_TYPES = ['TASK', 'MEETING', 'FOLLOW_UP', 'REMINDER'] as const

export const CALENDAR_WEEKDAY_LABELS = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'] as const

export const CALENDAR_MONTH_LABELS = [
  'يناير',
  'فبراير',
  'مارس',
  'أبريل',
  'مايو',
  'يونيو',
  'يوليو',
  'أغسطس',
  'سبتمبر',
  'أكتوبر',
  'نوفمبر',
  'ديسمبر',
] as const

export const CALENDAR_TYPES = Object.keys(CALENDAR_TYPE_LABELS) as CalendarItemType[]
export const CALENDAR_STATUSES = Object.keys(CALENDAR_STATUS_LABELS) as CalendarItemStatus[]
export const CALENDAR_PRIORITIES = Object.keys(CALENDAR_PRIORITY_LABELS) as CalendarItemPriority[]
export const CALENDAR_SOURCES = Object.keys(CALENDAR_SOURCE_LABELS) as CalendarSource[]
export const CALENDAR_VISIBILITIES = Object.keys(CALENDAR_VISIBILITY_LABELS) as CalendarVisibility[]
export const CALENDAR_REMINDERS = Object.keys(CALENDAR_REMINDER_LABELS) as CalendarReminderOffset[]
export const CALENDAR_RECURRENCE_FREQS = Object.keys(CALENDAR_RECURRENCE_LABELS) as CalendarRecurrenceFreq[]

export function calendarTypeLabel(type: string | null | undefined): string {
  if (!type) return '—'
  return CALENDAR_TYPE_LABELS[type as CalendarItemType] ?? type
}

export function calendarStatusLabel(status: string | null | undefined): string {
  if (!status) return '—'
  return CALENDAR_STATUS_LABELS[status as CalendarItemStatus] ?? status
}

export function calendarPriorityLabel(priority: string | null | undefined): string {
  if (!priority) return '—'
  return CALENDAR_PRIORITY_LABELS[priority as CalendarItemPriority] ?? priority
}

export function calendarSourceLabel(source: string | null | undefined): string {
  if (!source) return '—'
  return CALENDAR_SOURCE_LABELS[source as CalendarSource] ?? source
}

export function calendarVisibilityLabel(visibility: string | null | undefined): string {
  if (!visibility) return '—'
  return CALENDAR_VISIBILITY_LABELS[visibility as CalendarVisibility] ?? visibility
}

export function calendarReminderLabel(offset: string | null | undefined): string {
  if (!offset) return '—'
  return CALENDAR_REMINDER_LABELS[offset as CalendarReminderOffset] ?? offset
}

export function calendarRecurrenceLabel(freq: string | null | undefined): string {
  if (!freq) return 'بدون تكرار'
  return CALENDAR_RECURRENCE_LABELS[freq as CalendarRecurrenceFreq] ?? freq
}

export function calendarWorkloadLevel(count: number): CalendarWorkloadLevel {
  if (count >= 12) return 'critical'
  if (count >= 8) return 'heavy'
  if (count >= 4) return 'medium'
  return 'light'
}

export function calendarWorkloadLevelLabel(count: number): string {
  return CALENDAR_WORKLOAD_LEVEL_LABELS[calendarWorkloadLevel(count)]
}

export function calendarSourceBadgeClass(source: string | null | undefined): string {
  switch (source) {
    case 'CRM':
      return 'border-sky-300 bg-sky-50 text-sky-900'
    case 'ORDER':
      return 'border-violet-300 bg-violet-50 text-violet-900'
    case 'PROJECT':
      return 'border-teal-300 bg-teal-50 text-teal-900'
    case 'PRINTING':
      return 'border-orange-300 bg-orange-50 text-orange-900'
    case 'PAYMENT':
      return 'border-emerald-300 bg-emerald-50 text-emerald-900'
    case 'CONTENT':
      return 'border-fuchsia-300 bg-fuchsia-50 text-fuchsia-900'
    case 'SUPPLIER':
      return 'border-amber-300 bg-amber-50 text-amber-950'
    case 'GOOGLE':
      return 'border-blue-300 bg-blue-50 text-blue-900'
    default:
      return 'border-slate-200 bg-slate-50 text-slate-700'
  }
}

export function calendarItemChipClass(item: {
  type?: string | null
  status?: string | null
  is_linked?: boolean
}): string {
  const base = calendarTypeAccentClass(item.type)
  const completed = item.status === 'COMPLETED' ? 'opacity-55' : ''
  const overdue = item.status === 'OVERDUE' ? 'border-red-400/80' : ''
  const linked = item.is_linked ? 'border-dashed' : ''
  return [base, completed, overdue, linked].filter(Boolean).join(' ')
}

export function calendarStatusTone(status: string | null | undefined): StatusTone {
  switch (status) {
    case 'COMPLETED':
      return 'success'
    case 'CANCELLED':
      return 'neutral'
    case 'OVERDUE':
      return 'danger'
    case 'IN_PROGRESS':
      return 'progress'
    default:
      return 'pending'
  }
}

export function calendarPriorityTone(priority: string | null | undefined): StatusTone {
  switch (priority) {
    case 'URGENT':
      return 'danger'
    case 'HIGH':
      return 'warning'
    case 'LOW':
      return 'neutral'
    default:
      return 'review'
  }
}

export function calendarTypeAccentClass(type: string | null | undefined): string {
  switch (type) {
    case 'MEETING':
    case 'APPOINTMENT':
    case 'CALL':
      return 'border-sky-300 bg-sky-50 text-sky-900'
    case 'FOLLOW_UP':
      return 'border-amber-300 bg-amber-50 text-amber-950'
    case 'DEADLINE':
    case 'TASK':
      return 'border-slate-300 bg-slate-50 text-slate-900'
    case 'REMINDER':
      return 'border-orange-200 bg-orange-50 text-orange-900'
    case 'EVENT':
      return 'border-teal-200 bg-teal-50 text-teal-900'
    default:
      return 'border-slate-200 bg-white text-slate-800'
  }
}

export function calendarWorkloadLevelClass(level: CalendarWorkloadLevel): string {
  switch (level) {
    case 'critical':
      return 'border-red-300 bg-red-50 text-red-900'
    case 'heavy':
      return 'border-orange-300 bg-orange-50 text-orange-950'
    case 'medium':
      return 'border-amber-300 bg-amber-50 text-amber-950'
    default:
      return 'border-emerald-200 bg-emerald-50 text-emerald-900'
  }
}
