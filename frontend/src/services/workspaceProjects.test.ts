import { describe, expect, it } from 'vitest'
import {
  buildCreateWorkspaceProjectPayload,
} from '../services/workspaceProjects'

describe('buildCreateWorkspaceProjectPayload', () => {
  it('builds the Owner payload with required account_manager_id', () => {
    expect(
      buildCreateWorkspaceProjectPayload({
        title: '  حملة هوية  ',
        description: '  وصف المشروع  ',
        customerId: '12',
        accountManagerId: '7',
        status: 'IN_PROGRESS',
        startedAt: '2026-09-20',
        deadline: '2026-10-01',
      }),
    ).toEqual({
      title: 'حملة هوية',
      description: 'وصف المشروع',
      customer_id: 12,
      account_manager_id: 7,
      status: 'IN_PROGRESS',
      started_at: '2026-09-20',
      deadline: '2026-10-01',
    })
  })

  it('keeps Account Manager payloads compatible without account_manager_id', () => {
    expect(
      buildCreateWorkspaceProjectPayload({
        title: 'AM project',
        customerId: '3',
        description: '',
        accountManagerId: '',
        status: '',
        startedAt: '',
        deadline: '2026-11-01',
      }),
    ).toEqual({
      title: 'AM project',
      customer_id: 3,
      deadline: '2026-11-01',
    })
  })

  it('omits empty optional fields', () => {
    const payload = buildCreateWorkspaceProjectPayload({
      title: 'Minimal',
      customerId: '9',
      accountManagerId: '4',
    })

    expect(payload).toEqual({
      title: 'Minimal',
      customer_id: 9,
      account_manager_id: 4,
    })
    expect(payload).not.toHaveProperty('description')
    expect(payload).not.toHaveProperty('status')
    expect(payload).not.toHaveProperty('started_at')
    expect(payload).not.toHaveProperty('deadline')
  })
})
