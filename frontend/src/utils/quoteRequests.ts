import type { QuoteRequestSource, QuoteRequestStatus } from '../services/quoteRequests'
import { absolutePublicPath } from './printingQuotations'

export const QUOTE_REQUEST_STATUS_LABELS: Record<string, string> = {
  NEW: 'جديد',
  UNDER_REVIEW: 'قيد المراجعة',
  NEEDS_INFORMATION: 'نحتاج معلومات إضافية',
  READY_TO_PRICE: 'جاهز للتسعير',
  QUOTED: 'تم إرسال عرض السعر',
  REVISION_REQUESTED: 'طلب تعديل',
  ACCEPTED: 'تم القبول',
  REJECTED: 'مرفوض',
  EXPIRED: 'منتهي',
  CANCELLED: 'ملغي',
}

export const QUOTE_REQUEST_SOURCE_LABELS: Record<string, string> = {
  SERVICE: 'خدمة',
  PACKAGE: 'باقة',
  PACKAGE_TIER: 'مستوى باقة',
  CUSTOM_PACKAGE: 'باقة مخصصة',
  PRINTING_REQUEST: 'طلب طباعة',
  EVENT_REQUEST: 'طلب فعالية',
  ORDER: 'طلب',
  CONSULTANT_RECOMMENDATION: 'توصية المستشار',
  PORTFOLIO: 'معرض أعمال',
}

export const COMMERCIAL_QUOTATION_STATUS_LABELS: Record<string, string> = {
  DRAFT: 'مسودة',
  SENT: 'مُرسل',
  VIEWED: 'تمت المشاهدة',
  ACCEPTED: 'مقبول',
  REJECTED: 'مرفوض',
  EXPIRED: 'منتهي',
  CANCELLED: 'ملغى',
}

export const QUOTE_LINE_CATEGORY_LABELS: Record<string, string> = {
  CREATIVE: 'إبداعي',
  PRODUCTION: 'إنتاج',
  PRINTING: 'طباعة',
  SHIPPING: 'شحن',
  RENTAL: 'تأجير',
  OTHER: 'أخرى',
}

export const QUOTE_LINE_CATEGORIES = [
  'CREATIVE',
  'PRODUCTION',
  'PRINTING',
  'SHIPPING',
  'RENTAL',
  'OTHER',
] as const

export const REVISION_REASON_OPTIONS = [
  { code: 'price', label: 'السعر' },
  { code: 'quantity', label: 'الكمية' },
  { code: 'services', label: 'الخدمات' },
  { code: 'timeline', label: 'موعد التنفيذ' },
  { code: 'payment_terms', label: 'شروط الدفع' },
  { code: 'other', label: 'أخرى' },
] as const

export type RevisionReasonCode = (typeof REVISION_REASON_OPTIONS)[number]['code']

const QUOTE_EVENT_LABELS: Record<string, string> = {
  created: 'تم الإنشاء',
  review_started: 'بدأت المراجعة',
  assigned: 'تم التعيين',
  information_requested: 'طلب معلومات إضافية',
  customer_responded: 'رد العميل',
  cancelled: 'تم الإلغاء',
  internal_notes_updated: 'تحديث ملاحظات داخلية',
  quotation_sent: 'تم إرسال عرض السعر',
  revision_requested: 'طلب تعديل',
  accepted: 'تم القبول',
  rejected: 'تم الرفض',
  updated: 'تم التحديث',
  sent: 'تم الإرسال',
  viewed: 'تمت المشاهدة',
  superseded: 'استُبدل بمراجعة أحدث',
  revised: 'تم إنشاء مراجعة',
  expired: 'انتهت الصلاحية',
}

const PAYMENT_STATUS_LABELS: Record<string, string> = {
  PENDING: 'قيد الانتظار',
  PAID: 'مدفوع',
  FAILED: 'فشل',
  CANCELLED: 'ملغى',
  REFUNDED: 'مسترد',
  PARTIAL: 'مدفوع جزئياً',
  NONE: 'لا يوجد',
}

export function quoteRequestStatusLabel(status: string): string {
  return QUOTE_REQUEST_STATUS_LABELS[status] ?? status
}

export function quoteRequestSourceLabel(source: string): string {
  return QUOTE_REQUEST_SOURCE_LABELS[source] ?? source
}

export function commercialQuotationStatusLabel(status: string): string {
  return COMMERCIAL_QUOTATION_STATUS_LABELS[status] ?? status
}

export function quoteEventLabelAr(eventType: string): string {
  return QUOTE_EVENT_LABELS[eventType] ?? eventType
}

export function revisionReasonLabelAr(code: string | null | undefined): string {
  if (!code) return ''
  const match = REVISION_REASON_OPTIONS.find((option) => option.code === code)
  return match?.label ?? code
}

export function paymentPolicyMessagingAr(policy: string | null | undefined): string {
  switch (String(policy || 'NONE')) {
    case 'DEPOSIT':
      return 'مطلوب دفع مقدم'
    case 'FULL':
      return 'مطلوب دفع كامل'
    case 'NONE':
      return 'لا توجد دفعة مطلوبة'
    default:
      return 'لا توجد دفعة مطلوبة'
  }
}

export function paymentStatusLabelAr(status: string | null | undefined): string {
  if (!status) return '—'
  return PAYMENT_STATUS_LABELS[status] ?? status
}

export function paymentNextStepLabel(input: {
  status?: string | null
  payment_policy?: string | null
  amount_due?: number | string | null
  requirement_met?: boolean | null
  can_checkout?: boolean | null
}): string {
  if (String(input.status) !== 'ACCEPTED') {
    return 'بانتظار قبول العرض'
  }
  if (input.requirement_met) {
    return 'تم استيفاء متطلبات الدفع'
  }
  const policy = String(input.payment_policy || 'NONE')
  if (policy === 'NONE') {
    return paymentPolicyMessagingAr('NONE')
  }
  const due = Number(input.amount_due ?? 0)
  if (due <= 0) {
    return 'لا يوجد مبلغ مستحق حالياً'
  }
  if (input.can_checkout === true || policy === 'DEPOSIT' || policy === 'FULL') {
    return policy === 'FULL' ? 'الخطوة التالية: دفع كامل المبلغ' : 'الخطوة التالية: دفع العربون'
  }
  return paymentPolicyMessagingAr(policy)
}

export function commercialPublicQuotePath(token: string): string {
  return `/cq/${token}`
}

export function absoluteCommercialQuotePath(token: string): string {
  return absolutePublicPath(commercialPublicQuotePath(token))
}

export type RequestQuotePathInput = {
  source_type: QuoteRequestSource | string
  source_id?: number | string | null
  title?: string | null
  city?: string | null
  notes?: string | null
  payload?: unknown
}

export function buildRequestQuotePath(input: RequestQuotePathInput): string {
  const search = new URLSearchParams()
  search.set('source_type', String(input.source_type))
  if (input.source_id != null && input.source_id !== '') {
    search.set('source_id', String(input.source_id))
  }
  if (input.title) {
    search.set('title', input.title)
  }
  if (input.city) {
    search.set('city', input.city)
  }
  if (input.notes) {
    search.set('notes', input.notes)
  }
  if (input.payload !== undefined && input.payload !== null) {
    search.set('payload', JSON.stringify(input.payload))
  }
  return `/request-quote?${search.toString()}`
}

export function isQuotePricingMode(mode: string | null | undefined): boolean {
  return mode === 'QUOTE'
}

export function formatQuoteDate(value: string | null | undefined): string {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleDateString('ar-SA', { year: 'numeric', month: 'short', day: 'numeric' })
}

export function extractLatestRevisionReason(
  events?: Array<{ event_type: string; meta?: Record<string, unknown> | null }> | null,
): { reason?: string; reason_code?: string } | null {
  if (!events?.length) return null
  for (let i = events.length - 1; i >= 0; i -= 1) {
    const event = events[i]
    if (event.event_type !== 'revision_requested') continue
    const meta = event.meta ?? {}
    const reason = typeof meta.reason === 'string' ? meta.reason : undefined
    const reason_code = typeof meta.reason_code === 'string' ? meta.reason_code : undefined
    if (reason || reason_code) {
      return { reason, reason_code }
    }
    return { reason: undefined, reason_code: undefined }
  }
  return null
}

export const OPEN_QUOTE_STATUSES: QuoteRequestStatus[] = [
  'NEW',
  'UNDER_REVIEW',
  'NEEDS_INFORMATION',
  'READY_TO_PRICE',
  'QUOTED',
  'REVISION_REQUESTED',
]
