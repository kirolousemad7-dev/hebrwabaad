import { apiGet, apiPost, publicFetch } from './api'

export type CustomerPortalQuote = {
  id?: number
  reference?: string
  status?: string
  total?: string | number | null
  currency?: string
  public_path?: string | null
  valid_until?: string | null
  product_name?: string | null
}

export type CustomerPortalPayment = {
  id?: number
  amount?: string | number | null
  currency?: string
  status?: string
  paid_at?: string | null
  method?: string | null
  quotation_reference?: string | null
}

export type CustomerPortalPrinting = {
  id?: number
  product_name?: string | null
  status_key?: string
  status_label?: string
  required_date?: string | null
  tracking_path?: string | null
}

export type CustomerPortalApproval = {
  id?: number
  type?: string
  status?: string
  title?: string | null
  action_path?: string | null
}

export type CustomerPortalSummaryCard = {
  key: string
  label: string
  count: number
  paid_total?: string | number | null
}

export type CustomerPortalTimelineEvent = {
  type?: string
  title?: string
  subtitle?: string | null
  at?: string | null
}

export type CustomerPortalDocument = {
  printing_request_id?: number
  name?: string | null
  kind?: string | null
}

export type CustomerPortalPayload = {
  customer?: { name?: string | null; email?: string | null }
  summary_cards?: CustomerPortalSummaryCard[]
  timeline?: CustomerPortalTimelineEvent[]
  documents?: CustomerPortalDocument[]
  quotes?: CustomerPortalQuote[]
  quotations?: CustomerPortalQuote[]
  payments?: CustomerPortalPayment[] | { items?: CustomerPortalPayment[]; summary?: { paid_total?: string } }
  printing?: CustomerPortalPrinting[]
  approvals?: CustomerPortalApproval[]
  files?: CustomerPortalDocument[]
}

export type PaymentReconciliationItem = {
  id: number
  amount: string | number
  currency: string
  status: string
  provider?: string | null
  failure_reason?: string | null
  reconciliation_note?: string | null
  attention_reason?: string
  category?: string
  order_id?: number | null
  printing_quotation_id?: number | null
  href?: string | null
  printing_quotation?: { id: number; reference?: string } | null
  order?: { id: number; reference?: string | null; title?: string | null } | null
  customer?: { id: number; name?: string | null } | null
  updated_at?: string | null
  net_paid?: string | number | null
  confirmed_refunds?: string | number | null
}

export type PrintingFunnelStage = {
  key: string
  label: string
  count: number
  avg_hours_from_previous?: number | null
}

export type PrintingFunnelData = {
  period_days: number
  from: string
  to: string
  stages: PrintingFunnelStage[]
}

export function getCustomerPortal(token: string) {
  return publicFetch<CustomerPortalPayload>(`/api/public/portal/${encodeURIComponent(token)}`)
}

export function getPaymentsReconciliation(limit = 50) {
  return apiGet<{
    items: PaymentReconciliationItem[]
    meta: { total: number; categories?: Record<string, number> }
  }>(`/api/operations/payments/reconciliation?limit=${limit}`)
}

export function reconcileOwnerPayment(paymentId: number) {
  return apiPost<unknown>(`/api/admin/payments/${paymentId}/reconcile`, {})
}

export function getPrintingFunnel(period: 7 | 30 | 90) {
  return apiGet<PrintingFunnelData>(`/api/operations/insights/printing-funnel?period=${period}`)
}
