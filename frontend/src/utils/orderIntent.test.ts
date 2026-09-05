import { describe, expect, it } from 'vitest'
import {
  PACKAGE_ORDER_COPY,
  isSafeInternalPath,
  packageOrderAction,
  packageOrderPath,
} from './orderIntent'
import { customerPayPath } from './payments'

describe('package order flow', () => {
  it('routes the package CTA to the real protected order flow', () => {
    expect(packageOrderPath('foundation-package')).toBe(
      '/dashboard/packages/foundation-package/order',
    )
    expect(packageOrderPath('باقة خاصة')).toContain(encodeURIComponent('باقة خاصة'))
    expect(packageOrderPath('foundation-package')).not.toContain('intent=order')
    expect(customerPayPath(42)).toBe('/dashboard/orders/42/pay')
  })

  it('carries the chosen package level to the order flow', () => {
    expect(packageOrderPath('foundation-package', 'professional')).toBe(
      '/dashboard/packages/foundation-package/order?tier=professional',
    )
    expect(packageOrderPath('foundation-package', null)).not.toContain('tier=')
    expect(isSafeInternalPath(packageOrderPath('foundation-package', 'advanced'))).toBe(true)
    expect(PACKAGE_ORDER_COPY.requestQuote).toBe('اطلب تسعير الباقة')
  })

  it('allows only a ready authenticated customer to create an order', () => {
    expect(
      packageOrderAction({
        busy: false,
        isReady: true,
        isAuthenticated: true,
        role: 'CUSTOMER',
      }),
    ).toBe('submit')
    expect(
      packageOrderAction({
        busy: false,
        isReady: true,
        isAuthenticated: false,
      }),
    ).toBe('login')
    expect(
      packageOrderAction({
        busy: false,
        isReady: true,
        isAuthenticated: true,
        role: 'OWNER',
      }),
    ).toBe('forbidden')
  })

  it('blocks duplicate submission while the CTA is loading', () => {
    expect(
      packageOrderAction({
        busy: true,
        isReady: true,
        isAuthenticated: true,
        role: 'CUSTOMER',
      }),
    ).toBe('wait')
    expect(
      packageOrderAction({
        busy: false,
        isReady: false,
        isAuthenticated: true,
        role: 'CUSTOMER',
      }),
    ).toBe('wait')
  })

  it('uses honest Arabic unavailable and API error states', () => {
    expect(PACKAGE_ORDER_COPY.creating).toBe('جاري إنشاء الطلب...')
    expect(PACKAGE_ORDER_COPY.createError).toBe(
      'تعذر إنشاء الطلب حاليًا. برجاء المحاولة مرة أخرى.',
    )
    expect(PACKAGE_ORDER_COPY.unavailable).toBe('هذه الباقة غير متاحة حاليًا.')
  })

  it('preserves only safe internal login return paths', () => {
    expect(isSafeInternalPath(packageOrderPath('foundation-package'))).toBe(true)
    expect(isSafeInternalPath('//evil.example')).toBe(false)
    expect(isSafeInternalPath('https://evil.example')).toBe(false)
  })
})
