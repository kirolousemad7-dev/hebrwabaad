import { apiPost } from './api'

export type EventRequestInput = {
  event_type: string
  event_date?: string | null
  city: string
  attendance?: number | null
  venue?: string
  budget_range?: string
  buy_or_rent?: string
  notes?: string
}

export function submitEventRequest(input: EventRequestInput) {
  return apiPost<{ id: number; status: string }>('/api/event-requests', input)
}
