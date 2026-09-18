import { apiDownload, apiGet, apiPatch, apiPost } from './api'
import type { ApiSuccess } from '../types/api'

export type InvoiceStatus =
  | 'DRAFT'
  | 'ISSUED'
  | 'SENT'
  | 'PARTIALLY_PAID'
  | 'PAID'
  | 'OVERDUE'
  | 'CANCELLED'
  | 'VOID'

export type InvoiceItem = {
  id?: number
  service_id?: number | null
  description: string
  quantity: string
  unit_price: string
  discount_amount?: string
  tax_amount?: string
  line_total?: string
  sort_order?: number
}

export type Invoice = {
  id: number
  number: string
  status: InvoiceStatus
  status_label?: string
  currency: string
  customer?: { id: number; name: string; email?: string | null } | null
  company?: { id: number; name: string } | null
  commercial_quotation_id?: number | null
  order_id?: number | null
  project_id?: number | null
  issue_date?: string | null
  due_date?: string | null
  subtotal: string
  discount_amount: string
  tax_amount: string
  total: string
  amount_paid: string
  amount_due: string
  notes?: string | null
  terms?: string | null
  internal_notes?: string | null
  items: InvoiceItem[]
  payments?: Array<{
    id: number
    amount: string
    currency: string
    payment_method?: string | null
    status?: string | null
    reference_number?: string | null
    paid_at?: string | null
  }>
  events?: Array<{
    id: number
    event: string
    meta?: Record<string, unknown> | null
    actor_id?: number | null
    created_at?: string | null
  }>
  created_at?: string | null
  updated_at?: string | null
}

export type InvoiceListFilters = {
  q?: string
  status?: string
  customer_id?: string | number
  from?: string
  to?: string
  page?: number
}

type ListPayload = {
  items: Invoice[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

function query(filters: InvoiceListFilters = {}): string {
  const params = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== undefined && value !== null && String(value) !== '') {
      params.set(key, String(value))
    }
  })
  const qs = params.toString()
  return qs ? `?${qs}` : ''
}

export function getOwnerInvoices(filters: InvoiceListFilters = {}): Promise<ApiSuccess<ListPayload>> {
  return apiGet(`/api/operations/invoices${query(filters)}`)
}

export function getOwnerInvoice(id: number | string): Promise<ApiSuccess<Invoice>> {
  return apiGet(`/api/operations/invoices/${id}`)
}

export function createOwnerInvoice(body: Record<string, unknown>): Promise<ApiSuccess<Invoice>> {
  return apiPost('/api/operations/invoices', body)
}

export function updateOwnerInvoice(id: number | string, body: Record<string, unknown>): Promise<ApiSuccess<Invoice>> {
  return apiPatch(`/api/operations/invoices/${id}`, body)
}

export function issueOwnerInvoice(id: number | string): Promise<ApiSuccess<Invoice>> {
  return apiPost(`/api/operations/invoices/${id}/issue`)
}

export function sendOwnerInvoice(id: number | string): Promise<ApiSuccess<Invoice>> {
  return apiPost(`/api/operations/invoices/${id}/send`)
}

export function cancelOwnerInvoice(id: number | string): Promise<ApiSuccess<Invoice>> {
  return apiPost(`/api/operations/invoices/${id}/cancel`)
}

export function voidOwnerInvoice(id: number | string): Promise<ApiSuccess<Invoice>> {
  return apiPost(`/api/operations/invoices/${id}/void`)
}

export function recordOwnerInvoicePayment(
  id: number | string,
  body: Record<string, unknown>,
): Promise<ApiSuccess<Invoice>> {
  return apiPost(`/api/operations/invoices/${id}/payments`, body)
}

export function downloadOwnerInvoicePdf(id: number | string): Promise<void> {
  return apiDownload(`/api/operations/invoices/${id}/pdf`, `invoice-${id}.pdf`)
}

export function getCustomerInvoices(filters: InvoiceListFilters = {}): Promise<ApiSuccess<ListPayload>> {
  return apiGet(`/api/customer/invoices${query(filters)}`)
}

export function getCustomerInvoice(id: number | string): Promise<ApiSuccess<Invoice>> {
  return apiGet(`/api/customer/invoices/${id}`)
}

export function downloadCustomerInvoicePdf(id: number | string): Promise<void> {
  return apiDownload(`/api/customer/invoices/${id}/pdf`, `invoice-${id}.pdf`)
}
