import type { ProjectActivityListData } from '../types/api'
import { apiGet } from './api'

export type ProjectActivitiesQuery = {
  page?: number
  per_page?: number
}

export function workspaceProjectActivitiesPath(
  projectId: number,
  query: ProjectActivitiesQuery = {},
): string {
  const params = new URLSearchParams()
  const page = query.page ?? 1
  const perPage = query.per_page ?? 25
  params.set('page', String(page))
  params.set('per_page', String(perPage))

  return `/api/workspace/projects/${projectId}/activities?${params.toString()}`
}

/**
 * Staff / owner project activity feed (persistent project_activities).
 */
export function getProjectActivities(projectId: number, page = 1, perPage = 25) {
  return apiGet<ProjectActivityListData>(
    workspaceProjectActivitiesPath(projectId, { page, per_page: perPage }),
  )
}
