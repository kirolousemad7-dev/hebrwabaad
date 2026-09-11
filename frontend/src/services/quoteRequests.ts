import { API_BASE_URL, ApiRequestError, apiDownload, apiGet, apiPatch, apiPost, publicFetch } from './api'

export type QuoteRequestSource =
  | 'SERVICE'
  | 'PACKAGE'
  | 'PACKAGE_TIER'
  | 'CUSTOM_PACKAGE'
  | 'PRINTING_REQUEST'
  | 'EVENT_REQUEST'
  | 'ORDER'
  | 'CONSULTANT_RECOMMENDATION'
  | 'PORTFOLIO'

export type QuoteRequestStatus =
  | 'NEW'
  | 'UNDER_REVIEW'
  | 'NEEDS_INFORMATION'
  | 'READY_TO_PRICE'
  | 'QUOTED'
  | 'REVISION_REQUESTED'
  | 'ACCEPTED'
  | 'REJECTED'
  | 'EXPIRED'
  | 'CANCELLED'

export type CommercialQuotationStatus =
  | 'DRAFT'
  | 'SENT'
  | 'VIEWED'
  | 'ACCEPTED'
  | 'REJECTED'
  | 'EXPIRED'
  | 'CANCELLED'

export type QuotationLineCategory =
  | 'CREATIVE'
  | 'PRODUCTION'
  | 'PRINTING'
  | 'SHIPPING'
  | 'RENTAL'
  | 'OTHER'

export type QuotePaymentPolicy = 'NONE' | 'DEPOSIT' | 'FULL' | string

export type QuoteRequestEvent = {
  id: number
  event_type: string
  actor_type?: string | null
  meta?: Record<string, unknown> | null
  created_at?: string | null
  actor?: { id: number; name?: string | null } | null
}

export type QuoteRequestFile = {
  id: number
  original_name?: string | null
  mime_type?: string | null
  size?: number | null
  created_at?: string | null
}

export type CommercialQuotationItem = {
  id?: number
  description: string
  quantity: number | string
  unit_price: number | string
  subtotal?: number | string
  category?: QuotationLineCategory | string | null
  meta?: Record<string, unknown> | null
}

export type CommercialQuotationPaymentSummary = {
  required?: string | number | null
  paid?: string | number | null
  outstanding?: string | number | null
  remaining?: string | number | null
  total?: string | number | null
  deposit_required?: string | number | null
  payment_policy?: string | null
  requirement_met?: boolean
  amount_due_now?: string | number | null
  refunded_total?: string | number | null
  [key: string]: unknown
}

export type CommercialQuotationRevisionSummary = {
  id: number
  reference?: string
  revision?: number
  status?: string
  total?: string | number | null
  created_at?: string | null
  superseded?: boolean
  is_current?: boolean
}

export type CommercialQuotation = {
  id: number
  reference: string
  revision: number
  quote_request_id: number
  customer_id?: number | null
  order_id?: number | null
  project_id?: number | null
  status: CommercialQuotationStatus | string
  currency: string
  subtotal: string | number
  discount_amount?: string | number | null
  tax_amount?: string | number | null
  shipping_amount?: string | number | null
  rental_amount?: string | number | null
  total: string | number
  deposit_required?: string | number | null
  payment_policy?: QuotePaymentPolicy | null
  valid_until?: string | null
  execution_duration?: string | null
  revision_count?: number | null
  revision_reason?: string | null
  notes?: string | null
  terms?: string | null
  delivery_terms?: string | null
  internal_notes?: string | null
  public_token_hint?: string | null
  sent_at?: string | null
  viewed_at?: string | null
  accepted_at?: string | null
  rejected_at?: string | null
  expired_at?: string | null
  items?: CommercialQuotationItem[]
  payment_summary?: CommercialQuotationPaymentSummary | null
  revisions?: CommercialQuotationRevisionSummary[]
  supersedes?: { id: number; reference?: string; revision?: number; status?: string } | null
  linkages?: {
    order_id?: number | null
    project_id?: number | null
    quote_request_id?: number | null
  } | null
  customer?: { id: number; name?: string | null; email?: string | null; phone?: string | null } | null
  quote_request?: {
    id: number
    reference?: string
    title?: string
    status?: string
    source_type?: string
    payload?: Record<string, unknown> | null
  } | null
  events?: Array<{
    id: number
    event_type: string
    actor_type?: string | null
    meta?: Record<string, unknown> | null
    created_at?: string | null
    actor?: { id: number; name?: string | null } | null
  }>
}

export type QuoteRequest = {
  id: number
  reference: string
  customer_id: number
  order_id?: number | null
  source_type: QuoteRequestSource | string
  source_id?: number | null
  title: string
  status: QuoteRequestStatus | string
  assigned_to?: number | null
  requested_at?: string | null
  required_date?: string | null
  budget_min?: string | number | null
  budget_max?: string | number | null
  city?: string | null
  customer_notes?: string | null
  internal_notes?: string | null
  information_request?: string | null
  payload?: Record<string, unknown> | null
  quotation_type?: string | null
  quotation_id?: number | null
  created_at?: string | null
  updated_at?: string | null
  customer?: { id: number; name?: string | null; email?: string | null; phone?: string | null } | null
  assignee?: { id: number; name?: string | null; email?: string | null } | null
  order?: { id: number; reference?: string; status?: string } | null
  files?: QuoteRequestFile[]
  events?: QuoteRequestEvent[]
  commercial_quotations?: CommercialQuotation[]
}

export type QuoteRequestListData = {
  items: QuoteRequest[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  summary?: QuoteRequestSummary
}

export type QuoteRequestSummary = {
  NEW: number
  UNDER_REVIEW: number
  NEEDS_INFORMATION: number
  QUOTED: number
  REVISION_REQUESTED: number
  waiting_customer: number
}

export type CreateQuoteRequestPayload = {
  source_type: QuoteRequestSource | string
  source_id?: number | null
  order_id?: number | null
  title: string
  required_date?: string | null
  budget_min?: number | string | null
  budget_max?: number | string | null
  city?: string | null
  customer_notes?: string | null
  payload?: Record<string, unknown> | null
  file_ids?: number[]
  idempotency_key?: string | null
}

export type UpdateCommercialQuotationPayload = {
  currency?: string
  valid_until?: string | null
  execution_duration?: string | null
  revision_count?: number | null
  payment_policy?: QuotePaymentPolicy
  deposit_required?: number | string | null
  discount_amount?: number | string | null
  tax_amount?: number | string | null
  shipping_amount?: number | string | null
  rental_amount?: number | string | null
  notes?: string | null
  terms?: string | null
  delivery_terms?: string | null
  internal_notes?: string | null
  items?: Array<{
    description: string
    quantity: number | string
    unit_price: number | string
    category?: string | null
    meta?: Record<string, unknown> | null
  }>
}

export type PublicCommercialQuotation = {
  reference: string
  revision: number
  status: string
  currency: string
  subtotal: string | number
  discount_amount?: string | number | null
  tax_amount?: string | number | null
  shipping_amount?: string | number | null
  rental_amount?: string | number | null
  total: string | number
  deposit_required?: string | number | null
  payment_policy?: string | null
  valid_until?: string | null
  execution_duration?: string | null
  revision_count?: number | null
  notes?: string | null
  terms?: string | null
  delivery_terms?: string | null
  items?: CommercialQuotationItem[]
  accepted_at?: string | null
  rejected_at?: string | null
  payment_summary?: CommercialQuotationPaymentSummary | null
  tracking_token?: string | null
  quote_request_reference?: string | null
  quote_request_title?: string | null
  customer?: { id?: number; name?: string | null } | null
  expires_in_days?: number | null
  can_accept?: boolean
  can_request_revision?: boolean
  can_reject?: boolean
  can_checkout?: boolean
  payment_required?: boolean
  paytabs_configured?: boolean
  card_unavailable_message?: string | null
  revisions?: CommercialQuotationRevisionSummary[]
  revision_diff?: Record<string, unknown> | null
  linkages?: {
    order_id?: number | null
    project_id?: number | null
    quote_request_id?: number | null
  } | null
  payment_cta_label?: string | null
  latest_payment_status?: string | null
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

/** Customer */

export function getCustomerQuoteRequests(filters: { page?: number; per_page?: number; status?: string } = {}) {
  return apiGet<QuoteRequestListData>(`/api/customer/quote-requests${listQuery(filters)}`)
}

export function getCustomerQuoteRequest(id: number) {
  return apiGet<QuoteRequest>(`/api/customer/quote-requests/${id}`)
}

export function createCustomerQuoteRequest(payload: CreateQuoteRequestPayload) {
  return apiPost<QuoteRequest>('/api/customer/quote-requests', payload)
}

export function respondCustomerQuoteRequest(id: number, payload: { message: string; file_ids?: number[] }) {
  return apiPost<QuoteRequest>(`/api/customer/quote-requests/${id}/respond`, payload)
}

/** Owner / operations */

export function getOwnerQuoteRequests(filters: {
  page?: number
  per_page?: number
  status?: string
  source?: string
  q?: string
} = {}) {
  return apiGet<QuoteRequestListData>(`/api/operations/quote-requests${listQuery(filters)}`)
}

export function getOwnerQuoteRequestSummary() {
  return apiGet<QuoteRequestSummary>('/api/operations/quote-requests/summary')
}

export function getOwnerQuoteRequest(id: number) {
  return apiGet<QuoteRequest>(`/api/operations/quote-requests/${id}`)
}

export function startQuoteRequestReview(id: number) {
  return apiPost<QuoteRequest>(`/api/operations/quote-requests/${id}/start-review`)
}

export function assignQuoteRequest(id: number, assigned_to: number) {
  return apiPost<QuoteRequest>(`/api/operations/quote-requests/${id}/assign`, { assigned_to })
}

export function requestQuoteInformation(id: number, message: string) {
  return apiPost<QuoteRequest>(`/api/operations/quote-requests/${id}/request-information`, { message })
}

export function cancelQuoteRequest(id: number, reason?: string | null) {
  return apiPost<QuoteRequest>(`/api/operations/quote-requests/${id}/cancel`, { reason: reason ?? null })
}

export function createCommercialQuotationFromRequest(
  quoteRequestId: number,
  payload: {
    items?: UpdateCommercialQuotationPayload['items']
    currency?: string
    notes?: string | null
    terms?: string | null
  } = {},
) {
  return apiPost<CommercialQuotation>(`/api/operations/quote-requests/${quoteRequestId}/quotations`, payload)
}

export function getCommercialQuotation(id: number) {
  return apiGet<CommercialQuotation>(`/api/operations/commercial-quotations/${id}`)
}

export function updateCommercialQuotation(id: number, payload: UpdateCommercialQuotationPayload) {
  return apiPatch<CommercialQuotation>(`/api/operations/commercial-quotations/${id}`, payload)
}

export function sendCommercialQuotation(id: number) {
  return apiPost<{
    quotation: CommercialQuotation
    public_token: string
    public_url: string
  }>(`/api/operations/commercial-quotations/${id}/send`)
}

export function reviseCommercialQuotation(id: number) {
  return apiPost<CommercialQuotation>(`/api/operations/commercial-quotations/${id}/revise`)
}

export function previewCommercialQuotation(id: number) {
  return apiGet<PublicCommercialQuotation>(`/api/operations/commercial-quotations/${id}/preview`)
}

export async function downloadCommercialQuotationPdf(id: number, reference = 'quotation') {
  await apiDownload(`/api/operations/commercial-quotations/${id}/pdf`, `${reference}.pdf`)
}

export async function downloadCustomerCommercialQuotationPdf(id: number, reference = 'quotation') {
  await apiDownload(`/api/customer/commercial-quotations/${id}/pdf`, `${reference}.pdf`)
}

/** Public */

export function getPublicCommercialQuotation(token: string) {
  return publicFetch<PublicCommercialQuotation>(
    `/api/public/commercial-quotations/${encodeURIComponent(token)}`,
  )
}

export function acceptPublicCommercialQuotation(token: string) {
  return publicFetch<PublicCommercialQuotation & { tracking_token?: string | null }>(
    `/api/public/commercial-quotations/${encodeURIComponent(token)}/accept`,
    { method: 'POST', body: JSON.stringify({}) },
  )
}

export function rejectPublicCommercialQuotation(token: string, reason?: string) {
  return publicFetch<PublicCommercialQuotation>(
    `/api/public/commercial-quotations/${encodeURIComponent(token)}/reject`,
    { method: 'POST', body: JSON.stringify(reason ? { reason } : {}) },
  )
}

export function requestPublicCommercialRevision(
  token: string,
  payload: { reason?: string; reason_code?: string } = {},
) {
  return publicFetch<PublicCommercialQuotation>(
    `/api/public/commercial-quotations/${encodeURIComponent(token)}/revision`,
    { method: 'POST', body: JSON.stringify(payload) },
  )
}

export function checkoutPublicCommercialQuotation(token: string) {
  return publicFetch<{
    payment_id: number
    checkout_url: string
    amount: string
    currency: string
  }>(`/api/public/commercial-quotations/${encodeURIComponent(token)}/checkout`, {
    method: 'POST',
    body: JSON.stringify({}),
  })
}

export async function downloadPublicCommercialQuotationPdf(token: string, reference = 'quotation') {
  const response = await fetch(
    `${API_BASE_URL}/api/public/commercial-quotations/${encodeURIComponent(token)}/pdf`,
    { headers: { Accept: '*/*' } },
  )

  if (!response.ok) {
    let message = 'تعذر تنزيل ملف PDF.'
    try {
      const raw = await response.text()
      const jsonStart = raw.indexOf('{')
      if (jsonStart >= 0) {
        const payload = JSON.parse(raw.slice(jsonStart)) as { message?: string }
        if (payload.message) message = payload.message
      }
    } catch {
      /* ignore parse errors */
    }
    throw new ApiRequestError(message, response.status, null)
  }

  const blob = await response.blob()
  const disposition = response.headers.get('content-disposition')
  let filename = `${reference}.pdf`
  const utfMatch = disposition?.match(/filename\*=UTF-8''([^;]+)/i)
  if (utfMatch?.[1]) {
    try {
      filename = decodeURIComponent(utfMatch[1])
    } catch {
      /* keep fallback */
    }
  } else {
    const basicMatch = disposition?.match(/filename="?([^"]+)"?/i)
    if (basicMatch?.[1]) filename = basicMatch[1]
  }

  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.rel = 'noopener'
  document.body.append(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}
