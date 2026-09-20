import { describe, expect, it } from 'vitest'
import { ApiRequestError } from '../services/api'
import { describeApiError } from './errors'

describe('describeApiError', () => {
  it('flattens validation bags', () => {
    const error = new ApiRequestError('invalid', 422, {
      message: 'invalid',
      errors: { title: ['العنوان مطلوب'], assigned_to: ['المسؤول مطلوب'] },
    })
    expect(describeApiError(error, 'فشل')).toContain('العنوان مطلوب')
    expect(describeApiError(error, 'فشل')).toContain('المسؤول مطلوب')
  })

  it('uses fallback for 500 and exception-like messages', () => {
    expect(describeApiError(new ApiRequestError('SQLSTATE[HY000]', 500, null), 'فشل عام')).toBe('فشل عام')
    expect(describeApiError(new ApiRequestError('RuntimeException: boom', 400, null), 'فشل عام')).toBe(
      'فشل عام',
    )
  })

  it('keeps readable client messages for 403/404', () => {
    expect(describeApiError(new ApiRequestError('غير مصرح', 403, null), 'فشل')).toBe('غير مصرح')
  })
})
