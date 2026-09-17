import { apiGet, apiPost, apiPostForm } from './api'

export type SupplierQuoteStatus =
  | 'REQUESTED'
  | 'RECEIVED'
  | 'UNDER_REVIEW'
  | 'SELECTED'
  | 'REJECTED'
  | 'EXPIRED'

export type SupplierQuoteMargin = {
  gross_margin: string
  margin_percentage: number | null
}

export type QuotationSupplierQuote = {
  id: number
  commercial_quotation_id: number
  commercial_quotation_item_id: number
  supplier?: { id: number; name?: string | null; slug?: string | null } | null
  item?: { id: number; description?: string | null } | null
  cost?: string | number | null
  currency?: string
  valid_until?: string | null
  delivery_days?: number | null
  notes?: string | null
  attachments?: Array<{ path?: string; original_name?: string; mime_type?: string; size?: number }>
  status: SupplierQuoteStatus | string
  status_label_ar?: string
  customer_price?: string | number | null
  margin?: SupplierQuoteMargin | null
  requested_at?: string | null
  received_at?: string | null
  selected_at?: string | null
  rejected_at?: string | null
  rejection_reason?: string | null
}

export type SourcingItem = {
  id: number
  description: string
  quantity?: string | number
  unit_price?: string | number
  customer_price: string | number
  selected_supplier_quote_id?: number | null
  supplier_options: QuotationSupplierQuote[]
  margin?: SupplierQuoteMargin | null
}

export type QuotationSourcingPayload = {
  items: SourcingItem[]
  totals: {
    customer_total: string
    selected_supplier_cost: string
    gross_margin: string
    margin_percentage: number | null
  }
}

export type SupplierSourcingRequest = {
  id: number
  status: SupplierQuoteStatus | string
  status_label_ar?: string
  quotation?: { id: number; reference?: string; revision?: number; status?: string } | null
  item?: { id: number; description?: string | null; quantity?: string | number | null } | null
  cost?: string | number | null
  currency?: string
  valid_until?: string | null
  delivery_days?: number | null
  notes?: string | null
  attachments?: Array<{ path?: string; original_name?: string }>
  requested_at?: string | null
  received_at?: string | null
}

export function getQuotationSourcing(quotationId: number) {
  return apiGet<{ sourcing: QuotationSourcingPayload; quotes: QuotationSupplierQuote[] }>(
    `/operations/commercial-quotations/${quotationId}/sourcing`,
  )
}

export function searchSourcingSuppliers(q = '') {
  const query = q.trim() ? `?q=${encodeURIComponent(q.trim())}` : ''
  return apiGet<{ items: Array<{ id: number; name: string; slug?: string; email?: string | null }> }>(
    `/operations/sourcing/suppliers${query}`,
  )
}

export function requestSupplierQuote(
  quotationId: number,
  itemId: number,
  payload: { supplier_id: number; currency?: string; valid_until?: string | null; notes?: string | null },
) {
  return apiPost<QuotationSupplierQuote>(
    `/operations/commercial-quotations/${quotationId}/items/${itemId}/supplier-quotes`,
    payload,
  )
}

export function selectSupplierQuote(quoteId: number) {
  return apiPost<QuotationSupplierQuote>(`/operations/supplier-quotes/${quoteId}/select`)
}

export function rejectSupplierQuote(quoteId: number, reason?: string) {
  return apiPost<QuotationSupplierQuote>(`/operations/supplier-quotes/${quoteId}/reject`, { reason })
}

export function replaceSupplierQuote(
  quoteId: number,
  payload: { supplier_id: number; rejection_reason?: string; notes?: string | null },
) {
  return apiPost<QuotationSupplierQuote>(`/operations/supplier-quotes/${quoteId}/replace`, payload)
}

export function markSupplierQuoteUnderReview(quoteId: number) {
  return apiPost<QuotationSupplierQuote>(`/operations/supplier-quotes/${quoteId}/under-review`)
}

export function listSupplierSourcingRequests() {
  return apiGet<{ items: SupplierSourcingRequest[] }>('/supplier/sourcing-requests')
}

export function getSupplierSourcingRequest(id: number) {
  return apiGet<SupplierSourcingRequest>(`/supplier/sourcing-requests/${id}`)
}

export function respondSupplierSourcingRequest(
  id: number,
  payload: {
    cost: string
    currency?: string
    valid_until?: string
    delivery_days?: string
    notes?: string
    attachments?: FileList | File[] | null
  },
) {
  const form = new FormData()
  form.append('cost', payload.cost)
  if (payload.currency) form.append('currency', payload.currency)
  if (payload.valid_until) form.append('valid_until', payload.valid_until)
  if (payload.delivery_days) form.append('delivery_days', payload.delivery_days)
  if (payload.notes) form.append('notes', payload.notes)
  const files = payload.attachments
  if (files) {
    Array.from(files as ArrayLike<File>).forEach((file) => form.append('attachments[]', file))
  }

  return apiPostForm<SupplierSourcingRequest>(`/supplier/sourcing-requests/${id}/respond`, form)
}
