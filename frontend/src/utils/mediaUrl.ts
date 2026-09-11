import { normalizeApiBaseUrl } from './apiBaseUrl'

/**
 * Resolve portfolio/media URLs for display.
 * Absolute http(s) URLs pass through. Frontend public assets (/brand/...) stay relative.
 * Backend storage/upload paths are prefixed with the API origin.
 */
export function resolveMediaUrl(url: string | null | undefined): string {
  if (!url) {
    return ''
  }

  const trimmed = url.trim()
  if (!trimmed) {
    return ''
  }

  if (/^(https?:|data:|blob:)/i.test(trimmed)) {
    return trimmed
  }

  if (
    trimmed.startsWith('/storage/') ||
    trimmed.startsWith('/uploads/') ||
    trimmed.startsWith('/media/')
  ) {
    const configured = import.meta.env.VITE_API_URL
    const apiBase = configured
      ? normalizeApiBaseUrl(configured)
      : import.meta.env.DEV
        ? 'http://127.0.0.1:8000'
        : ''

    return apiBase ? `${apiBase}${trimmed}` : trimmed
  }

  return trimmed
}
