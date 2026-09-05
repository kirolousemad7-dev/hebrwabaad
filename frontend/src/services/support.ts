import type {
  ManagedSupportConversation,
  ManagedSupportListData,
  SupportConversation,
} from '../types/api'
import { apiGet, apiPatch, apiPost } from './api'

export function getCustomerConversations() {
  return apiGet<SupportConversation[]>('/api/customer/conversations')
}

export function getCustomerConversation(id: number, page?: number) {
  const query = page ? `?page=${page}` : ''
  return apiGet<SupportConversation>(`/api/customer/conversations/${id}${query}`)
}

export function createCustomerConversation(payload: {
  subject?: string
  message?: string
  order_id?: number
  project_id?: number
}) {
  return apiPost<SupportConversation>('/api/customer/conversations', payload)
}

export function sendCustomerMessage(id: number, message: string) {
  return apiPost<SupportConversation>(`/api/customer/conversations/${id}/messages`, { message })
}

export function getSupportConversations(query = '') {
  return apiGet<ManagedSupportListData>(`/api/support/conversations${query}`)
}

export function getSupportConversation(id: number, page?: number) {
  const suffix = page ? `?page=${page}` : ''
  return apiGet<ManagedSupportConversation>(`/api/support/conversations/${id}${suffix}`)
}

export function sendSupportMessage(id: number, message: string) {
  return apiPost<ManagedSupportConversation>(`/api/support/conversations/${id}/messages`, { message })
}

export function updateSupportConversationStatus(id: number, status: string) {
  return apiPatch<ManagedSupportConversation>(`/api/support/conversations/${id}/status`, { status })
}
