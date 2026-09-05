import { apiGet, apiPost, clearStoredToken, storeToken } from './api'
import type { AuthPayload, AuthUser } from '../types/api'

export function register(payload: {
  name: string
  email: string
  password: string
  password_confirmation: string
}) {
  return apiPost<AuthPayload>('/api/auth/register', payload)
}

export function login(payload: { email: string; password: string }) {
  return apiPost<AuthPayload>('/api/auth/login', payload)
}

export function logout() {
  return apiPost<null>('/api/auth/logout')
}

export function requestPasswordReset(email: string) {
  return apiPost<{ status: string }>('/api/auth/forgot-password', { email })
}

export function resetPassword(payload: {
  token: string
  email: string
  password: string
  password_confirmation: string
}) {
  return apiPost<{ status: string }>('/api/auth/reset-password', payload)
}

export function me() {
  return apiGet<AuthUser>('/api/auth/me')
}

export function persistSession(payload: AuthPayload): void {
  storeToken(payload.token)
}

export function clearSession(): void {
  clearStoredToken()
}
