import type { ConsultantConfig, ConsultantSession } from '../types/api'
import { apiGet, apiPost } from './api'

const TOKEN_KEY = 'hebr_consultant_token'

export function getStoredConsultationToken(): string | null {
  return sessionStorage.getItem(TOKEN_KEY)
}

export function storeConsultationToken(token: string): void {
  sessionStorage.setItem(TOKEN_KEY, token)
}

export function clearConsultationToken(): void {
  sessionStorage.removeItem(TOKEN_KEY)
}

export function getConsultantConfig() {
  return apiGet<ConsultantConfig>('/api/consultations/config')
}

export function startConsultation() {
  return apiPost<ConsultantSession>('/api/consultations')
}

export function getConsultation(token: string) {
  return apiGet<ConsultantSession>(`/api/consultations/${token}`)
}

export function answerConsultation(token: string, questionId: string, value: unknown) {
  return apiPost<ConsultantSession>(`/api/consultations/${token}/answers`, {
    question_id: questionId,
    value,
  })
}

export function messageConsultation(token: string, message: string) {
  return apiPost<ConsultantSession>(`/api/consultations/${token}/messages`, { message })
}

export function resetConsultation(token: string) {
  return apiPost<ConsultantSession>(`/api/consultations/${token}/reset`)
}

export function captureConsultationLead(
  token: string,
  payload: {
    name: string
    email: string
    phone?: string
    business_name?: string
    contact_method?: 'email' | 'phone' | 'whatsapp'
  },
) {
  return apiPost<{ lead_captured: boolean; id: number }>(`/api/consultations/${token}/lead`, payload)
}

export function recordConsultationEvent(token: string, name: string, payload?: Record<string, unknown>) {
  return apiPost<{ recorded: boolean }>(`/api/consultations/${token}/events`, { name, payload })
}
