import { apiDelete, apiDownload, apiGet, apiPatch, apiPost, apiPut, publicFetch } from './api'

export type PrintingQuotationLineItem = {
  description?: string | null
  quantity?: number | string | null
  unit_price?: number | string | null
  line_total?: number | string | null
}

export type PrintingQuotationPaymentSummary = {
  total?: string | number | null
  paid?: string | number | null
  pending?: string | number | null
  remaining?: string | number | null
  deposit_required?: string | number | null
  amount_due_now?: string | number | null
  requirement_met?: boolean
  /** Customer-safe confirmed refunds total when API provides it */
  refunded_total?: string | number | null
  [key: string]: unknown
}

export type PublicPrintingQuotation = {
  reference: string
  revision: number
  status: string
  currency: string
  subtotal: string | number
  tax_amount: string | number
  discount_amount: string | number
  total: string | number
  deposit_required?: string | number | null
  payment_policy?: string | null
  valid_until?: string | null
  notes?: string | null
  terms?: string | null
  line_items?: PrintingQuotationLineItem[]
  product_name?: string | null
  quantity?: number | string | null
  accepted_at?: string | null
  rejected_at?: string | null
  payment_summary?: PrintingQuotationPaymentSummary | null
  paytabs_available?: boolean
  tracking_token?: string | null
  tracking_url?: string | null
}

export type PublicPrintingTrackEvent = {
  event?: string
  label?: string
  status_label?: string
  created_at?: string | null
  meta?: Record<string, unknown> | null
}

export type PublicPrintingTrack = {
  status_key: string
  status_label: string
  product_name?: string | null
  required_date?: string | null
  delivered_at?: string | null
  reference?: string | null
  timeline?: PublicPrintingTrackEvent[]
  files?: Array<{ id?: number; name?: string; url?: string | null }>
}

export type PrintingQuotation = {
  id: number
  reference: string
  revision: number
  printing_request_id: number
  customer_id?: number | null
  created_by?: number | null
  status: string
  currency: string
  subtotal: string | number
  tax_amount: string | number
  discount_amount: string | number
  total: string | number
  deposit_required?: string | number | null
  payment_policy?: string | null
  valid_until?: string | null
  notes?: string | null
  terms?: string | null
  public_token?: string | null
  public_token_hint?: string | null
  public_path?: string | null
  public_url?: string | null
  token_revoked_at?: string | null
  sent_at?: string | null
  viewed_at?: string | null
  accepted_at?: string | null
  rejected_at?: string | null
  expired_at?: string | null
  supersedes_id?: number | null
  tracking_token_hint?: string | null
  payment_summary?: PrintingQuotationPaymentSummary | null
  customer?: { id: number; name?: string | null; email?: string | null } | null
  creator?: { id: number; name?: string | null } | null
  printing_request?: {
    id: number
    product_name?: string | null
    quantity?: number | null
    status?: string | null
    required_date?: string | null
  } | null
  payments?: Array<{
    id: number
    amount?: string | number
    status?: string
    method?: string
    reference_number?: string | null
  }> | null
}

export type PrintingQuotationListData = {
  items: PrintingQuotation[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export type CreatePrintingQuotationPayload = {
  printing_request_id: number
  subtotal?: number | string
  tax_amount?: number | string
  discount_amount?: number | string
  total?: number | string
  deposit_required?: number | string
  payment_policy?: 'NONE' | 'DEPOSIT' | 'FULL' | string
  currency?: string
  valid_until?: string | null
  notes?: string | null
  terms?: string | null
}

export type UpdatePrintingQuotationPayload = Partial<
  Omit<CreatePrintingQuotationPayload, 'printing_request_id'>
>

export type RecordPrintingQuotationPaymentPayload = {
  method: 'INSTAPAY' | 'BANK_TRANSFER' | string
  amount?: number | string
  reference_number?: string | null
  payer_name?: string | null
  notes?: string | null
  mark_paid?: boolean
}

export type PrintingExecutionEligibility = {
  eligible: boolean
  reasons: string[]
  payment_summary?: PrintingQuotationPaymentSummary | null
  quotation_id?: number | null
  quotation_reference?: string | null
}

export type InboundWebhookIntegration = {
  id: number
  name: string
  integration_type: string
  secret_hint?: string | null
  secret?: string | null
  is_active: boolean
  created_by?: number | null
  created_at?: string | null
  updated_at?: string | null
  endpoint?: string | null
  allowed_events?: string[]
}

export type InboundWebhookReceipt = {
  id: number
  event?: string | null
  delivery_id?: string | null
  status?: string | null
  created_at?: string | null
  processed_at?: string | null
  error?: string | null
}

function listQuery(params: Record<string, string | number | boolean | undefined | null>): string {
  const search = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '') continue
    search.set(key, String(value))
  }
  const query = search.toString()
  return query === '' ? '' : `?${query}`
}

export function getPublicPrintingQuotation(token: string) {
  return publicFetch<PublicPrintingQuotation>(
    `/api/public/printing-quotations/${encodeURIComponent(token)}`,
  )
}

export function acceptPublicPrintingQuotation(token: string) {
  return publicFetch<PublicPrintingQuotation>(
    `/api/public/printing-quotations/${encodeURIComponent(token)}/accept`,
    { method: 'POST', body: JSON.stringify({}) },
  )
}

export function rejectPublicPrintingQuotation(token: string, reason?: string) {
  return publicFetch<PublicPrintingQuotation>(
    `/api/public/printing-quotations/${encodeURIComponent(token)}/reject`,
    {
      method: 'POST',
      body: JSON.stringify(reason ? { reason } : {}),
    },
  )
}

export function checkoutPublicPrintingQuotation(token: string) {
  return publicFetch<{
    payment_id: number
    checkout_url: string
    amount: string
    currency: string
  }>(`/api/public/printing-quotations/${encodeURIComponent(token)}/checkout`, {
    method: 'POST',
    body: JSON.stringify({}),
  })
}

export function getPublicPaymentStatus(paymentId: number, token: string) {
  return publicFetch<{ payment_id: number; status: string }>(
    `/api/public/payments/${paymentId}/status?token=${encodeURIComponent(token)}`,
  )
}

export function getPublicPrintingTrack(token: string) {
  return publicFetch<PublicPrintingTrack>(`/api/public/printing-track/${encodeURIComponent(token)}`)
}

export function getPrintingQuotations(filters: {
  page?: number
  per_page?: number
  status?: string
  printing_request_id?: number
} = {}) {
  return apiGet<PrintingQuotationListData>(
    `/api/operations/printing-quotations${listQuery(filters)}`,
  )
}

export function getPrintingQuotation(id: number) {
  return apiGet<PrintingQuotation>(`/api/operations/printing-quotations/${id}`)
}

export function createPrintingQuotation(payload: CreatePrintingQuotationPayload) {
  return apiPost<PrintingQuotation>('/api/operations/printing-quotations', payload)
}

export function updatePrintingQuotation(id: number, payload: UpdatePrintingQuotationPayload) {
  return apiPatch<PrintingQuotation>(`/api/operations/printing-quotations/${id}`, payload)
}

export function sendPrintingQuotation(id: number) {
  return apiPost<PrintingQuotation & { public_token?: string; public_path?: string; public_url?: string }>(
    `/api/operations/printing-quotations/${id}/send`,
  )
}

export function emailPrintingQuotation(id: number, payload?: { email?: string | null }) {
  return apiPost<{
    status?: string
    public_url?: string | null
    public_path?: string | null
    mail_enabled?: boolean
    message?: string
  }>(`/api/operations/printing-quotations/${id}/email`, payload ?? {})
}

export function createCustomerPortalAccess(customerId: number) {
  return apiPost<{
    token?: string
    portal_url?: string | null
    portal_path?: string | null
    expires_at?: string | null
  }>(`/api/operations/customers/${customerId}/portal-access`, {})
}

export function revisePrintingQuotation(id: number) {
  return apiPost<PrintingQuotation>(`/api/operations/printing-quotations/${id}/revise`)
}

export async function downloadPrintingQuotationPdf(id: number, reference = 'quotation') {
  await apiDownload(`/api/operations/printing-quotations/${id}/pdf`, `${reference}.pdf`)
}

export function getPrintingQuotationEligibility(printingRequestId: number) {
  return apiGet<PrintingExecutionEligibility>(
    `/api/operations/printing-quotations/request/${printingRequestId}/eligibility`,
  )
}

export function recordPrintingQuotationPayment(
  id: number,
  payload: RecordPrintingQuotationPaymentPayload,
) {
  return apiPost<{ payment: unknown; quotation: PrintingQuotation }>(
    `/api/operations/printing-quotations/${id}/payments`,
    payload,
  )
}

export function getPrintingQuotationTimeline(id: number) {
  return apiGet<{ items: Array<Record<string, unknown>> }>(
    `/api/operations/printing-quotations/${id}/timeline`,
  )
}

export function getInboundWebhooks() {
  return apiGet<{ items: InboundWebhookIntegration[] }>('/api/operations/inbound-webhooks')
}

export function createInboundWebhook(payload: {
  name: string
  integration_type?: string
  is_active?: boolean
}) {
  return apiPost<InboundWebhookIntegration>('/api/operations/inbound-webhooks', payload)
}

export function updateInboundWebhook(
  id: number,
  payload: { name?: string; is_active?: boolean },
) {
  return apiPut<InboundWebhookIntegration>(`/api/operations/inbound-webhooks/${id}`, payload)
}

export function deleteInboundWebhook(id: number) {
  return apiDelete<{ deleted: boolean }>(`/api/operations/inbound-webhooks/${id}`)
}

export function getInboundWebhookReceipts(id: number) {
  return apiGet<{ items: InboundWebhookReceipt[] }>(
    `/api/operations/inbound-webhooks/${id}/receipts`,
  )
}
