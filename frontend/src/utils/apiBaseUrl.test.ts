import { describe, expect, it } from 'vitest'
import { MISSING_PRODUCTION_API_URL, normalizeApiBaseUrl } from './apiBaseUrl'

describe('apiBaseUrl', () => {
  it('trims and strips a trailing slash from the configured API origin', () => {
    expect(normalizeApiBaseUrl(' https://api.hebr.test/ ')).toBe('https://api.hebr.test')
    expect(normalizeApiBaseUrl('http://127.0.0.1:8000')).toBe('http://127.0.0.1:8000')
  })

  it('documents that production builds must set VITE_API_URL', () => {
    expect(MISSING_PRODUCTION_API_URL).toContain('VITE_API_URL')
  })
})
