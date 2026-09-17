import { apiGet, apiPost } from './api'
import type { AuthPayload, AuthUser } from '../types/api'
import type { AdminSupplier } from './supplierWorkspace'

export type SupplierAuthPayload = AuthPayload & {
  supplier?: AdminSupplier
  message?: string
  status?: string
}

export function registerSupplier(payload: Record<string, unknown>) {
  return apiPost<SupplierAuthPayload>('/api/supplier/register', payload)
}

export function loginSupplier(payload: { email: string; password: string }) {
  return apiPost<SupplierAuthPayload>('/api/supplier/login', payload)
}

export function requestSupplierOtp(email: string) {
  return apiPost<{ status: string }>('/api/supplier/otp/request', { email })
}

export function verifySupplierOtp(payload: { email: string; code: string }) {
  return apiPost<SupplierAuthPayload>('/api/supplier/otp/verify', payload)
}

export function getSupplierDashboard() {
  return apiGet<{
    supplier: AdminSupplier
    completion: { percent: number; sections: Record<string, { complete: boolean; missing: string[] }>; missing: string[] }
    counts: Record<string, number>
    access: { is_active_supplier: boolean; status: string; owner_change_request: string | null; locked_fields: string[] }
    modules: Record<string, { available: boolean; items: unknown[] }>
  }>('/api/supplier/dashboard')
}

export function getSupplierCompletion() {
  return apiGet<{ percent: number; sections: Record<string, { complete: boolean; missing: string[] }>; missing: string[] }>(
    '/api/supplier/completion',
  )
}

export function getSupplierPortalServices() {
  return apiGet<{ items: Array<Record<string, unknown>> }>('/api/supplier/services')
}

export function createSupplierPortalService(payload: Record<string, unknown>) {
  return apiPost<Record<string, unknown>>('/api/supplier/services', payload)
}

export function getSupplierPortalContacts() {
  return apiGet<{ items: Array<Record<string, unknown>> }>('/api/supplier/contacts')
}

export function createSupplierPortalContact(payload: Record<string, unknown>) {
  return apiPost<Record<string, unknown>>('/api/supplier/contacts', payload)
}

export function getSupplierPortalDocuments() {
  return apiGet<{ items: Array<Record<string, unknown>> }>('/api/supplier/documents')
}

export function createSupplierPortalDocument(payload: Record<string, unknown>) {
  return apiPost<Record<string, unknown>>('/api/supplier/documents', payload)
}

export function getSupplierPortalSettings() {
  return apiGet<Record<string, unknown>>('/api/supplier/settings')
}

export type EmailVerificationStatus = {
  status: 'verified' | 'pending' | 'expired'
  verified: boolean
  expires_at: string | null
  sent_at: string | null
}

export function getSupplierEmailStatus() {
  return apiGet<EmailVerificationStatus>('/api/supplier/email/status')
}

export function resendSupplierEmailVerification() {
  return apiPost<EmailVerificationStatus>('/api/supplier/email/resend')
}

export function verifySupplierEmail(params: {
  id: string
  hash: string
  expires: string
  signature: string
}) {
  const query = new URLSearchParams(params).toString()
  return apiGet<{ status: string; user: AuthUser }>(`/api/supplier/email/verify?${query}`)
}

export function requestSupplierPhoneOtp(phone?: string) {
  return apiPost<{ status: string }>('/api/supplier/phone/otp/request', phone ? { phone } : {})
}

export function verifySupplierPhoneOtp(payload: { code: string; phone?: string }) {
  return apiPost<{ status: string; supplier: AdminSupplier }>('/api/supplier/phone/otp/verify', payload)
}

export type { AuthUser }
