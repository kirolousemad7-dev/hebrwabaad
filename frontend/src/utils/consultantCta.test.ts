import { describe, expect, it } from 'vitest'
import { consultantCtaEventName, isLiveConsultantCta } from './consultantCta'

describe('consultant CTA guards', () => {
  it('accepts only safe internal paths and known CTA types', () => {
    expect(
      isLiveConsultantCta({
        type: 'choose_package',
        label: 'اختر',
        path: '/customer?intent=order&package=foundation-package',
      }),
    ).toBe(true)
    expect(isLiveConsultantCta({ type: 'plan_event', label: 'فعالية', path: '/event-packages' })).toBe(true)
    expect(isLiveConsultantCta({ type: 'choose_package', label: 'x', path: 'https://evil.test' })).toBe(false)
    expect(isLiveConsultantCta({ type: 'invented', label: 'x', path: '/packages' })).toBe(false)
  })

  it('maps CTA types to analytics events', () => {
    expect(consultantCtaEventName('choose_package')).toBe('package_clicked')
    expect(consultantCtaEventName('request_service')).toBe('service_clicked')
    expect(consultantCtaEventName('request_quote')).toBe('quote_requested')
  })
})
