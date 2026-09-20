import { describe, expect, it } from 'vitest'
import {
  customerProjectActivitiesPath,
} from '../services/customerDashboard'
import {
  fileClientVisibilityBody,
  fileClientVisibilityPath,
  managedFilesBasePath,
} from '../services/files'
import { workspaceProjectActivitiesPath } from '../services/projectActivities'
import type {
  CustomerProjectActivity,
  CustomerProjectActivityListData,
  ManagedFileItem,
  ProjectActivity,
  ProjectActivityListData,
} from '../types/api'

describe('project activity API paths', () => {
  it('builds the workspace activities endpoint with pagination', () => {
    expect(workspaceProjectActivitiesPath(42)).toBe(
      '/api/workspace/projects/42/activities?page=1&per_page=25',
    )
    expect(workspaceProjectActivitiesPath(7, { page: 3, per_page: 10 })).toBe(
      '/api/workspace/projects/7/activities?page=3&per_page=10',
    )
  })

  it('builds the customer activities endpoint with pagination', () => {
    expect(customerProjectActivitiesPath(15)).toBe(
      '/api/customer/projects/15/activities?page=1&per_page=25',
    )
    expect(customerProjectActivitiesPath(15, { page: 2 })).toBe(
      '/api/customer/projects/15/activities?page=2&per_page=25',
    )
  })

  it('types staff list responses with optional metadata and meta pagination', () => {
    const payload: ProjectActivityListData = {
      items: [
        {
          id: 1,
          action: 'task.status_changed',
          description: 'Task status changed',
          actor: { type: 'user', id: 9, name: 'Ahmed' },
          entity_type: 'task',
          entity_id: 22,
          metadata: { old_status: 'TODO', new_status: 'IN_PROGRESS' },
          is_client_visible: false,
          created_at: '2026-09-20T10:00:00+00:00',
        } satisfies ProjectActivity,
      ],
      meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
    }

    expect(payload.meta.per_page).toBe(25)
    expect(payload.items[0].metadata).toEqual({
      old_status: 'TODO',
      new_status: 'IN_PROGRESS',
    })
  })

  it('types customer list responses without requiring metadata', () => {
    const payload: CustomerProjectActivityListData = {
      items: [
        {
          id: 3,
          action: 'project.status_changed',
          description: 'Project status updated',
          actor: { type: 'staff', id: null, name: 'Hebr & Ab3ad team' },
          entity_type: 'project',
          entity_id: 5,
          created_at: '2026-09-20T11:00:00+00:00',
        } satisfies CustomerProjectActivity,
      ],
      meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
    }

    expect(payload.items[0]).not.toHaveProperty('metadata')
    expect(payload.items[0].actor?.id).toBeNull()
  })
})

describe('file client visibility API', () => {
  it('builds the PATCH path and body for client visibility', () => {
    expect(fileClientVisibilityPath(88)).toBe('/api/workspace/files/88/client-visibility')
    expect(fileClientVisibilityBody(true)).toEqual({ is_client_visible: true })
    expect(fileClientVisibilityBody(false)).toEqual({ is_client_visible: false })
  })

  it('keeps customer file lists on the customer scope endpoint', () => {
    expect(managedFilesBasePath('customer')).toBe('/api/customer/files')
    expect(managedFilesBasePath('workspace')).toBe('/api/workspace/files')
  })

  it('requires is_client_visible on ManagedFileItem', () => {
    const file: ManagedFileItem = {
      id: 1,
      original_name: 'brief.pdf',
      mime_type: 'application/pdf',
      extension: 'pdf',
      size: 100,
      can_preview: true,
      is_client_visible: true,
      created_at: null,
    }

    expect(file.is_client_visible).toBe(true)
  })
})
