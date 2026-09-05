export function normalizeApiBaseUrl(configured: string): string {
  return configured.trim().replace(/\/$/, '')
}

export const MISSING_PRODUCTION_API_URL = 'VITE_API_URL must be set for production builds.'
