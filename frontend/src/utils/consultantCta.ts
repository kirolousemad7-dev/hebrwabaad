import type { ConsultantCta } from '../types/api'
import { isSafeInternalPath } from './orderIntent'

const ALLOWED_CTA_TYPES = new Set([
  'choose_package',
  'request_service',
  'request_quote',
  'plan_event',
  'talk_expert',
  'book_consultation',
  'printing',
])

export function isLiveConsultantCta(cta: ConsultantCta | null | undefined): cta is ConsultantCta {
  if (!cta) {
    return false
  }

  return ALLOWED_CTA_TYPES.has(cta.type) && isSafeInternalPath(cta.path)
}

export function consultantCtaEventName(type: string): 'package_clicked' | 'service_clicked' | 'quote_requested' {
  if (type === 'choose_package') {
    return 'package_clicked'
  }

  if (type === 'request_service') {
    return 'service_clicked'
  }

  return 'quote_requested'
}

export const READINESS_LABELS: Record<string, string> = {
  digital_presence: 'الحضور الرقمي',
  marketing: 'التسويق',
  branding: 'الهوية',
  customer_acquisition: 'اكتساب العملاء',
  customer_retention: 'الاحتفاظ بالعملاء',
  sales: 'المبيعات',
  online_infrastructure: 'البنية الرقمية',
}
