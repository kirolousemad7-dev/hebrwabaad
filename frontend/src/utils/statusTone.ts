export type StatusTone =
  | 'neutral'
  | 'pending'
  | 'progress'
  | 'review'
  | 'revision'
  | 'success'
  | 'danger'
  | 'warning'
  | 'unavailable'

const STATUS_TONES: Record<string, StatusTone> = {
  TODO: 'pending',
  PENDING: 'pending',
  RECEIVED: 'pending',
  CONFIRMED: 'pending',
  IN_PROGRESS: 'progress',
  REVIEW: 'review',
  REVISION: 'revision',
  COMPLETED: 'success',
  DELIVERED: 'success',
  RESOLVED: 'success',
  CLOSED: 'neutral',
  CANCELLED: 'neutral',
  CANCELED: 'neutral',
  ACTIVE: 'success',
  INACTIVE: 'neutral',
  UNREAD: 'warning',
  READ: 'neutral',
  AVAILABLE: 'success',
  UNAVAILABLE: 'unavailable',
  OVERDUE: 'danger',
  OPEN: 'progress',
  PROCESSING: 'progress',
  PAID: 'success',
  FAILED: 'danger',
  PENDING_VERIFICATION: 'warning',
  REJECTED: 'danger',
}

export function toneForStatus(status: string | null | undefined): StatusTone {
  if (!status) {
    return 'neutral'
  }

  return STATUS_TONES[status] ?? 'neutral'
}
