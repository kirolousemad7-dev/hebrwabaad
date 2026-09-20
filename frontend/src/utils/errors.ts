import { ApiRequestError } from '../services/api'

/**
 * Turns an API failure into a single readable Arabic message,
 * flattening Laravel validation error bags when present.
 * Avoids surfacing raw exception / infrastructure text.
 */
export function describeApiError(caught: unknown, fallback: string): string {
  if (!(caught instanceof ApiRequestError)) {
    return fallback
  }

  const details = caught.body?.errors
  if (details) {
    return Object.values(details).flat().join(' ')
  }

  if (caught.status >= 500) {
    return fallback
  }

  const message = caught.message?.trim() ?? ''
  if (!message || /exception|sqlstate|stack trace|error:/i.test(message)) {
    return fallback
  }

  return message
}
