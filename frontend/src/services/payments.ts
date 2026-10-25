import type {
  CustomerPayment,
  CustomerPaymentSettings,
  OwnerPayment,
  OwnerPaymentAccount,
  OwnerPaymentListData,
  OwnerPaymentSettings,
  PaymentMethod,
  PaymentRevenueSummary,
} from '../types/api'
import { apiGet, apiGetBlob, apiPatch, apiPost, apiPostForm } from './api'

export type OwnerPaymentListFilters = {
  q?: string
  status?: string
  payment_method?: string
  page?: number
}

export function getCustomerPaymentSettings() {
  return apiGet<CustomerPaymentSettings>('/api/customer/payments/settings')
}

export function getCustomerPayments() {
  return apiGet<CustomerPayment[]>('/api/customer/payments')
}

export function getCustomerPayment(id: number) {
  return apiGet<CustomerPayment>(`/api/customer/payments/${id}`)
}

export function createCustomerPayment(payload: { order_id: number; method: PaymentMethod }) {
  return apiPost<CustomerPayment>('/api/customer/payments', payload)
}

export function startCustomerCardPayment(id: number) {
  return apiPost<CustomerPayment>(`/api/customer/payments/${id}/card`)
}

/** Customer-declared InstaPay / bank transfer details plus a required receipt image. */
export function submitCustomerManualTransfer(
  id: number,
  payload: { reference_number: string; payer_name?: string; notes?: string; payment_account_id?: number; proof: File },
) {
  const body = new FormData()
  body.append('reference_number', payload.reference_number)
  if (payload.payer_name) body.append('payer_name', payload.payer_name)
  if (payload.notes) body.append('notes', payload.notes)
  if (payload.payment_account_id) body.append('payment_account_id', String(payload.payment_account_id))
  body.append('proof', payload.proof)

  return apiPostForm<CustomerPayment>(`/api/customer/payments/${id}/manual-transfer`, body)
}

export function getCustomerPaymentProof(id: number) {
  return apiGetBlob(`/api/customer/payments/${id}/proof`)
}

export function getOwnerPaymentProof(id: number) {
  return apiGetBlob(`/api/admin/payments/${id}/proof`)
}

export function getOwnerPaymentAccounts() {
  return apiGet<OwnerPaymentAccount[]>('/api/admin/payment-accounts')
}

export function createOwnerPaymentAccount(payload: Partial<OwnerPaymentAccount> & { method: string; account_holder_name: string; instructions: string }) {
  return apiPost<OwnerPaymentAccount>('/api/admin/payment-accounts', payload)
}

export function updateOwnerPaymentAccount(id: number, payload: Partial<OwnerPaymentAccount>) {
  return apiPatch<OwnerPaymentAccount>(`/api/admin/payment-accounts/${id}`, payload)
}

export function makeOwnerPaymentAccountDefault(id: number) {
  return apiPost<OwnerPaymentAccount>(`/api/admin/payment-accounts/${id}/default`)
}

export function archiveOwnerPaymentAccount(id: number) {
  return apiPost<OwnerPaymentAccount>(`/api/admin/payment-accounts/${id}/archive`)
}

export function getOwnerPayments(filters: OwnerPaymentListFilters = {}) {
  const params = new URLSearchParams()

  if (filters.q) {
    params.set('q', filters.q)
  }
  if (filters.status) {
    params.set('status', filters.status)
  }
  if (filters.payment_method) {
    params.set('payment_method', filters.payment_method)
  }
  if (filters.page && filters.page > 1) {
    params.set('page', String(filters.page))
  }

  const query = params.toString()

  return apiGet<OwnerPaymentListData>(`/api/admin/payments${query ? `?${query}` : ''}`)
}

export function getOwnerPayment(id: number) {
  return apiGet<OwnerPayment>(`/api/admin/payments/${id}`)
}

export function getOwnerPaymentRevenue() {
  return apiGet<PaymentRevenueSummary>('/api/admin/payments/revenue')
}

export function verifyOwnerPayment(id: number) {
  return apiPost<OwnerPayment>(`/api/admin/payments/${id}/verify`)
}

export function rejectOwnerPayment(id: number, reason: string) {
  return apiPost<OwnerPayment>(`/api/admin/payments/${id}/reject`, { reason })
}

export function getOwnerPaymentSettings() {
  return apiGet<OwnerPaymentSettings>('/api/admin/payments/settings')
}

export function updateOwnerPaymentSettings(payload: Partial<OwnerPaymentSettings>) {
  return apiPatch<OwnerPaymentSettings>('/api/admin/payments/settings', payload)
}

export type PaymentRefundRow = {
  id: number
  payment_id: number
  amount: string | number
  currency: string
  status: string
  reason?: string | null
  is_manual?: boolean
  requested_at?: string | null
  processed_at?: string | null
}

export function getOwnerPaymentRefunds(paymentId: number) {
  return apiGet<{
    items: PaymentRefundRow[]
    refundable_amount: string
    net_paid: string
    payment_amount: string | number
  }>(`/api/admin/payments/${paymentId}/refunds`)
}

export function requestOwnerPaymentRefund(
  paymentId: number,
  payload: { amount: string; reason?: string; manual?: boolean },
) {
  return apiPost<{
    refund: PaymentRefundRow
    payment_amount: string | number
    net_paid: string
  }>(`/api/admin/payments/${paymentId}/refunds`, payload)
}
