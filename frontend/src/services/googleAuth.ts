import { apiGet, apiPost, API_BASE_URL } from './api'
import type { AuthPayload } from '../types/api'

export type GoogleAuthIntent = 'login' | 'register' | 'supplier'

export type GoogleAuthStatus = {
  configured: boolean
  redirect_uri: string | null
}

export function getGoogleAuthStatus() {
  return apiGet<GoogleAuthStatus>('/api/auth/google/status')
}

export function googleAuthRedirectUrl(intent: GoogleAuthIntent = 'login', next?: string | null): string {
  const params = new URLSearchParams({ intent })
  if (next && next.startsWith('/') && !next.startsWith('//')) {
    params.set('next', next)
  }

  return `${API_BASE_URL}/api/auth/google/redirect?${params.toString()}`
}

export function exchangeGoogleAuthCode(code: string) {
  return apiPost<AuthPayload & { next?: string | null }>('/api/auth/google/exchange', { code })
}
