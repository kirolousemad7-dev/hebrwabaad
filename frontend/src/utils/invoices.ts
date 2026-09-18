import type { InvoiceStatus } from '../types/api'
import { formatMoney } from './catalog'

export const INVOICE_STATUS_LABELS: Record<InvoiceStatus, string> = {
  DRAFT: 'مسودة',
  ISSUED: 'صادرة',
  SENT: 'مُرسلة',
  PARTIALLY_PAID: 'مدفوعة جزئياً',
  PAID: 'مدفوعة',
  OVERDUE: 'متأخرة',
  CANCELLED: 'ملغاة',
  VOID: 'ملغاة نهائياً',
}

export const INVOICE_EVENT_LABELS: Record<string, string> = {
  created: 'إنشاء',
  updated: 'تحديث',
  issued: 'إصدار',
  sent: 'إرسال',
  cancelled: 'إلغاء',
  voided: 'إلغاء نهائي',
  payment_recorded: 'تسجيل دفعة',
  overdue: 'تأخر',
}

export function ownerInvoicePath(id: number): string {
  return `/owner/invoices/${id}`
}

export function ownerInvoiceEditPath(id: number): string {
  return `/owner/invoices/${id}/edit`
}

export function customerInvoicePath(id: number): string {
  return `/dashboard/invoices/${id}`
}

export function formatInvoiceAmount(amount: string | number | null | undefined, currency = 'SAR'): string {
  if (amount === null || amount === undefined || amount === '') {
    return '—'
  }

  return formatMoney(amount, currency)
}

export function invoiceStatusLabel(status: string | null | undefined): string {
  if (!status) {
    return '—'
  }

  return INVOICE_STATUS_LABELS[status as InvoiceStatus] ?? status
}

export function invoiceEventLabel(event: string): string {
  return INVOICE_EVENT_LABELS[event] ?? event
}
