export const PRINTING_QUOTATION_STATUS_LABELS: Record<string, string> = {
  DRAFT: 'مسودة',
  SENT: 'مُرسل',
  VIEWED: 'تمت المشاهدة',
  ACCEPTED: 'مقبول',
  REJECTED: 'مرفوض',
  EXPIRED: 'منتهي',
  CANCELLED: 'ملغى',
}

export const PRINTING_PAYMENT_POLICY_LABELS: Record<string, string> = {
  NONE: 'بدون دفع مسبق',
  DEPOSIT: 'عربون',
  FULL: 'دفع كامل',
}

export function printingQuotationStatusLabel(status: string): string {
  return PRINTING_QUOTATION_STATUS_LABELS[status] ?? status
}

export function printingPaymentPolicyLabel(policy: string): string {
  return PRINTING_PAYMENT_POLICY_LABELS[policy] ?? policy
}

export function printingPublicQuotePath(token: string): string {
  return `/q/${token}`
}

export function printingPublicTrackPath(token: string): string {
  return `/track/${token}`
}

export function absolutePublicPath(path: string): string {
  if (path.startsWith('http://') || path.startsWith('https://')) {
    return path
  }
  const normalized = path.startsWith('/') ? path : `/${path}`
  return `${window.location.origin}${normalized}`
}
