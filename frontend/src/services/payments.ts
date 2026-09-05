import type {
  CustomerPayment,
  CustomerPaymentSettings,
  OwnerPayment,
  OwnerPaymentListData,
  OwnerPaymentSettings,
  PaymentMethod,
  PaymentRevenueSummary,
} from '../types/api'
import { apiGet, apiPatch, apiPost } from './api'

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

/** Customer-declared InstaPay / bank transfer details, pending owner verification. */
export function submitCustomerManualTransfer(
  id: number,
  payload: { reference_number: string; payer_name?: string; notes?: string },
) {
  return apiPost<CustomerPayment>(`/api/customer/payments/${id}/manual-transfer`, payload)
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
