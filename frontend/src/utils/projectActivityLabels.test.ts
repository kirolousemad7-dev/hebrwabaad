import { describe, expect, it } from 'vitest'
import {
  projectActivityHeadline,
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
    expect(projectActivityLabel('task.completed')).toBe('تم إكمال المهمة')
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
      'تم تغيير ظهور الملف للعميل · أحمد · file #44',
    )
  })

  it('builds an Arabic headline from existing metadata instead of the stored English description', () => {
    expect(
      projectActivityHeadline({
        ...sample,
        action: 'task.status_changed',
        description: 'Task status changed',
        metadata: { title: 'تصميم اتجاه بصري أول', new_status: 'COMPLETED' },
      }),
    ).toBe('تم تغيير حالة مهمة "تصميم اتجاه بصري أول" إلى مكتملة')

    expect(
      projectActivityHeadline({
        ...sample,
        action: 'note.created',
        description: 'تمت إضافة ملاحظة: نص',
        entity_type: 'task',
        entity_id: 11,
        metadata: { task_id: 11 },
      }, { 11: 'مراجعة التصميم' }),
    ).toBe('أضاف أحمد ملاحظة على المهمة "مراجعة التصميم"')

    expect(
      projectActivityHeadline({
        ...sample,
        action: 'file.uploaded',
        description: 'File uploaded',
        metadata: { file_name: 'nova-demo-mark.png' },
      }),
    ).toBe('تم رفع ملف "nova-demo-mark.png" إلى المشروع')

    expect(projectActivityHeadline({ ...sample, action: 'project.created', description: 'Project created', metadata: null })).toBe(
      'تم إنشاء المشروع',
    )
  })
})
