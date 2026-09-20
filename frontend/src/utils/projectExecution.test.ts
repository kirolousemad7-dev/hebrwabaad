import { describe, expect, it } from 'vitest'
import {
  attentionKindLabel,
  customerPayloadHasInternalKeys,
  groupAttentionItems,
  isClosureReady,
  milestoneCompletionWarning,
  milestoneOpenTaskCount,
  type ProjectAttentionItem,
} from './projectExecution'

describe('projectExecution helpers', () => {
  const items: ProjectAttentionItem[] = [
    {
      kind: 'overdue',
      related_type: 'task',
      related_id: 1,
      title: 'متأخرة',
      due_date: '2026-01-01',
      assignee_name: null,
    },
    {
      kind: 'due_soon',
      related_type: 'milestone',
      related_id: 2,
      title: 'قريبة',
      due_date: '2026-09-22',
      assignee_name: 'أحمد',
    },
  ]

  it('groups attention items and labels kinds', () => {
    const groups = groupAttentionItems(items)
    expect(groups.overdue).toHaveLength(1)
    expect(groups.due_soon).toHaveLength(1)
    expect(groups.unassigned).toHaveLength(0)
    expect(attentionKindLabel('overdue')).toBe('متأخر')
  })

  it('handles empty attention state', () => {
    expect(groupAttentionItems([])).toEqual({
      overdue: [],
      due_soon: [],
      unassigned: [],
      waiting: [],
    })
  })

  it('computes milestone open task warning', () => {
    expect(milestoneOpenTaskCount({ open_tasks: 3 })).toBe(3)
    expect(milestoneOpenTaskCount({ progress: { total: 5, completed: 2 } })).toBe(3)
    expect(milestoneCompletionWarning(0)).toBeNull()
    expect(milestoneCompletionWarning(2)).toMatch(/[2٢]/)
  })

  it('detects closure readiness', () => {
    expect(isClosureReady({ state: 'ready', label: 'جاهز', issues: [] })).toBe(true)
    expect(
      isClosureReady({
        state: 'attention',
        label: 'متابعة',
        issues: [{ key: 'open_tasks', label: 'مهام', count: 1 }],
      }),
    ).toBe(false)
  })

  it('flags internal keys that must stay off customer payloads', () => {
    expect(
      customerPayloadHasInternalKeys({
        id: 1,
        title: 'مشروع',
        team_stats: [],
        health: { status: 'ok' },
      }),
    ).toEqual(['team_stats', 'health'])
  })
})
