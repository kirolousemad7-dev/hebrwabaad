import { describe, expect, it } from 'vitest'
import {
  printingPaymentPolicyLabel,
  printingPublicQuotePath,
  printingPublicTrackPath,
  printingQuotationStatusLabel,
} from './printingQuotations'

describe('printingQuotations', () => {
  it('maps quotation statuses to Arabic labels', () => {
    expect(printingQuotationStatusLabel('SENT')).toBe('مُرسل')
    expect(printingQuotationStatusLabel('ACCEPTED')).toBe('مقبول')
    expect(printingQuotationStatusLabel('UNKNOWN')).toBe('UNKNOWN')
  })

  it('maps payment policies to Arabic labels', () => {
    expect(printingPaymentPolicyLabel('DEPOSIT')).toBe('عربون')
    expect(printingPaymentPolicyLabel('FULL')).toBe('دفع كامل')
  })

  it('builds public quote and track paths', () => {
    expect(printingPublicQuotePath('abc')).toBe('/q/abc')
    expect(printingPublicTrackPath('xyz')).toBe('/track/xyz')
  })
})
