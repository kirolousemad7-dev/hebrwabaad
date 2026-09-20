import { describe, expect, it } from 'vitest'
import type { CustomerProjectActivity } from '../types/api'
import {
  customerProjectActivityActorLabel,
  customerProjectActivityPrimary,
} from './customerProjectActivity'

const base: CustomerProjectActivity = {
  id: 1,
  action: 'task.completed',
  description: null,
  actor: { type: 'staff', id: null, name: 'فريق حبر وأبعاد' },
  entity_type: 'task',
  entity_id: 99,
  created_at: '2026-09-20T12:00:00+00:00',
}

describe('customerProjectActivity', () => {
  it('prefers backend description and never reads metadata', () => {
    const activity = {
      ...base,
      description: '  تم إكمال مرحلة التصميم  ',
    }

    expect(customerProjectActivityPrimary(activity)).toBe('تم إكمال مرحلة التصميم')
    expect(activity).not.toHaveProperty('metadata')
  })

  it('falls back to Arabic action labels instead of raw action keys', () => {
    expect(customerProjectActivityPrimary({ ...base, description: null })).toBe('إكمال مهمة')
    expect(customerProjectActivityPrimary({ ...base, description: '   ' })).toBe('إكمال مهمة')
  })

  it('uses a generic Arabic fallback for unknown actions without description', () => {
    expect(
      customerProjectActivityPrimary({
        ...base,
        action: 'internal.debug.dump',
        description: null,
      }),
    ).toBe('تحديث على المشروع')
  })

  it('exposes only the safe actor name', () => {
    expect(customerProjectActivityActorLabel(base)).toBe('فريق حبر وأبعاد')
    expect(
      customerProjectActivityActorLabel({
        ...base,
        actor: { type: 'staff', id: null, name: '  ' },
      }),
    ).toBeNull()
    expect(customerProjectActivityActorLabel({ ...base, actor: null })).toBeNull()
  })
})
