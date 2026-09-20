import type { CustomerProjectActivity } from '../types/api'
import { projectActivityLabel } from './projectActivityLabels'

const FALLBACK_LABEL = 'تحديث على المشروع'

/**
 * Customer-safe primary line: prefer backend description; never expose metadata or raw action keys.
 */
export function customerProjectActivityPrimary(activity: CustomerProjectActivity): string {
  const description = activity.description?.trim()
  if (description) {
    return description
  }

  const mapped = projectActivityLabel(activity.action)
  if (mapped !== activity.action) {
    return mapped
  }

  return FALLBACK_LABEL
}

/**
 * Customer-safe secondary context: actor name only (no entity ids / metadata).
 */
export function customerProjectActivityActorLabel(activity: CustomerProjectActivity): string | null {
  const name = activity.actor?.name?.trim()
  return name ? name : null
}
