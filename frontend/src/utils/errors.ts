import { ApiRequestError } from '../services/api'

/**
 * Turns an API failure into a single readable Arabic message,
 * flattening Laravel validation error bags when present.
 */
export function describeApiError(caught: unknown, fallback: string): string {
  if (!(caught instanceof ApiRequestError)) {
    return fallback
  }

  const details = caught.body?.errors

  return details ? Object.values(details).flat().join(' ') : caught.message
}
