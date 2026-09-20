import { describe, expect, it } from 'vitest'
import {
  projectActivityLabel,
  projectActivityMatchesFilter,
  projectActivitySecondaryLine,
} from './projectActivityLabels'
import type { ProjectActivity } from '../types/api'

const sample: ProjectActivity = {
  id: 9,
  action: 'file.client_visibility_changed',
  description: 'File client visibility changed',
  actor: { type: 'user', id: 3, name: 'أحمد' },
  entity_type: 'file',
  entity_id: 44,
  metadata: { old_is_client_visible: false, new_is_client_visible: true },
  created_at: '2026-09-20T12:00:00+00:00',
}

describe('projectActivityLabels', () => {
  it('maps known actions to Arabic labels and keeps unknown actions raw', () => {
    expect(projectActivityLabel('task.completed')).toBe('إكمال مهمة')
    expect(projectActivityLabel('custom.future_action')).toBe('custom.future_action')
  })

  it('filters activity rows by existing Owner workspace chips', () => {
    expect(projectActivityMatchesFilter(sample, 'files')).toBe(true)
    expect(projectActivityMatchesFilter(sample, 'tasks')).toBe(false)
    expect(projectActivityMatchesFilter({ ...sample, action: 'task.created' }, 'tasks')).toBe(true)
    expect(projectActivityMatchesFilter(sample, 'all')).toBe(true)
  })

  it('builds a concise secondary line without dumping metadata', () => {
    expect(projectActivitySecondaryLine(sample)).toBe(
      'تغيير ظهور الملف للعميل · أحمد · file #44',
    )
  })
})
