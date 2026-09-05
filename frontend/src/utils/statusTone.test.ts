import { describe, expect, it } from 'vitest'
import { toneForStatus } from './statusTone'

describe('statusTone', () => {
  it('maps live platform statuses to a consistent visual tone', () => {
    expect(toneForStatus('TODO')).toBe('pending')
    expect(toneForStatus('IN_PROGRESS')).toBe('progress')
    expect(toneForStatus('REVIEW')).toBe('review')
    expect(toneForStatus('REVISION')).toBe('revision')
    expect(toneForStatus('COMPLETED')).toBe('success')
    expect(toneForStatus('INACTIVE')).toBe('neutral')
    expect(toneForStatus('UNAVAILABLE')).toBe('unavailable')
    expect(toneForStatus('OVERDUE')).toBe('danger')
    expect(toneForStatus('PROCESSING')).toBe('progress')
    expect(toneForStatus('PAID')).toBe('success')
    expect(toneForStatus('FAILED')).toBe('danger')
    expect(toneForStatus('PENDING_VERIFICATION')).toBe('warning')
    expect(toneForStatus('REJECTED')).toBe('danger')
    expect(toneForStatus('UNKNOWN')).toBe('neutral')
  })
})
