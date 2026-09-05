import { describe, expect, it } from 'vitest'
import type { CustomerPaymentSettings } from '../types/api'
import { ownerNavForRole } from './dashboardNav'
import {
  PAYMENT_COPY,
  PAYMENT_METHOD_LABELS,
  PAYMENT_STATUS_LABELS,
  canAccessOwnerPayments,
  checkoutReturnView,
  customerPayPath,
  enabledPaymentMethods,
  formatPaymentAmount,
  hasAnyPaymentMethod,
  isManualPaymentMethod,
  isSafeCheckoutRedirect,
  orderNeedsPayment,
  ownerPaymentPath,
  payableUnavailableCopy,
  paymentViewState,
  selectedPaymentMethod,
} from './payments'

describe('payments', () => {
  it('selects only enabled payment methods', () => {
    const all = { card: true, instapay: true, bank_transfer: true }

    expect(selectedPaymentMethod('CARD', all)).toBe('CARD')
    expect(selectedPaymentMethod('INSTAPAY', all)).toBe('INSTAPAY')
    expect(selectedPaymentMethod('BANK_TRANSFER', all)).toBe('BANK_TRANSFER')
    expect(selectedPaymentMethod('CARD', { ...all, card: false })).toBeNull()
    expect(selectedPaymentMethod('BANK_TRANSFER', { ...all, bank_transfer: false })).toBeNull()
    expect(selectedPaymentMethod('CRYPTO', all)).toBeNull()
  })

  it('offers a manual method only when the owner enabled and completed it', () => {
    const settings = (overrides: {
      instapay?: Partial<CustomerPaymentSettings['instapay']>
      bank?: Partial<CustomerPaymentSettings['bank_transfer']>
      card?: Partial<CustomerPaymentSettings['card']>
    }): CustomerPaymentSettings => ({
      card: { enabled: true, configured: true, ...overrides.card },
      instapay: {
        enabled: true,
        ready: true,
        account_name: 'حبر وأبعاد',
        bank_name: null,
        account_number: null,
        handle: 'hebr@instapay',
        phone: null,
        instructions: 'حوّل ثم أرسل رقم العملية.',
        notes: null,
        ...overrides.instapay,
      },
      bank_transfer: {
        enabled: true,
        ready: true,
        bank_name: 'بنك تجريبي',
        account_name: 'حبر وأبعاد',
        account_number: null,
        iban: 'SA0000000000000000000000',
        swift: null,
        branch: null,
        instructions: 'حوّل ثم أرسل رقم الحوالة.',
        notes: null,
        ...overrides.bank,
      },
    })

    expect(enabledPaymentMethods(settings({}))).toEqual({
      card: true,
      instapay: true,
      bank_transfer: true,
    })
    expect(enabledPaymentMethods(settings({ bank: { enabled: false } })).bank_transfer).toBe(false)
    expect(enabledPaymentMethods(settings({ bank: { ready: false } })).bank_transfer).toBe(false)
    expect(enabledPaymentMethods(settings({ instapay: { ready: false } })).instapay).toBe(false)
    expect(enabledPaymentMethods(settings({ card: { configured: false } })).card).toBe(false)
    expect(
      hasAnyPaymentMethod(
        enabledPaymentMethods(
          settings({ card: { enabled: false }, instapay: { enabled: false }, bank: { enabled: false } }),
        ),
      ),
    ).toBe(false)
    expect(PAYMENT_COPY.noMethods).toContain('لا توجد طريقة دفع متاحة')
  })

  it('treats InstaPay and bank transfer as owner-verified manual methods', () => {
    expect(isManualPaymentMethod('INSTAPAY')).toBe(true)
    expect(isManualPaymentMethod('BANK_TRANSFER')).toBe(true)
    expect(isManualPaymentMethod('CARD')).toBe(false)
    expect(PAYMENT_METHOD_LABELS.BANK_TRANSFER).toBe('تحويل بنكي')
  })

  it('explains an unpriced package instead of inventing an amount', () => {
    expect(payableUnavailableCopy('awaiting_owner_quote')).toBe(PAYMENT_COPY.awaitingQuote)
    expect(payableUnavailableCopy('order_has_no_catalog_price')).toBe(PAYMENT_COPY.notPayable)
    expect(payableUnavailableCopy(null)).toBe(PAYMENT_COPY.notPayable)
    expect(PAYMENT_COPY.awaitingQuote).not.toContain('0')
  })

  it('renders the authoritative amount with catalog currency', () => {
    expect(formatPaymentAmount('1500.00', 'SAR')).toContain('SAR')
    expect(formatPaymentAmount('1500.00', 'SAR')).toContain('١')
    expect(formatPaymentAmount(null)).toBe('—')
    expect(formatPaymentAmount(undefined, 'SAR')).toBe('—')
    expect(formatPaymentAmount(null)).not.toContain('0')
  })

  it('maps payment statuses to customer-facing view states', () => {
    expect(paymentViewState({ status: 'PROCESSING' })).toBe('processing')
    expect(paymentViewState({ status: 'PAID' })).toBe('success')
    expect(paymentViewState({ status: 'FAILED' })).toBe('failed')
    expect(paymentViewState({ status: 'PENDING_VERIFICATION' })).toBe('pending_verification')
    expect(paymentViewState({ status: 'REJECTED' })).toBe('rejected')
    expect(PAYMENT_STATUS_LABELS.PENDING_VERIFICATION).toBe('بانتظار التحقق')
    expect(PAYMENT_COPY.processing).toContain('بوابة')
    expect(PAYMENT_COPY.card).toBe('الدفع بالبطاقة')
    expect(PAYMENT_COPY.success).toBe('نجحت عملية الدفع')
    expect(PAYMENT_COPY.verifying).toBe('عملية الدفع قيد التحقق')
    expect(PAYMENT_COPY.failed).toBe('عملية الدفع لم تكتمل')
    expect(PAYMENT_COPY.cancelled).toBe('تم إلغاء عملية الدفع')
    expect(PAYMENT_COPY.gatewayMissing).toContain('غير متاح')
  })

  it('never treats a checkout query flag as success by itself', () => {
    expect(checkoutReturnView('success', { status: 'PROCESSING' })).toBe('verifying')
    expect(checkoutReturnView('return', { status: 'PROCESSING' })).toBe('verifying')
    expect(checkoutReturnView('success', { status: 'PAID' })).toBe('success')
    expect(checkoutReturnView('success', { status: 'FAILED' })).toBe('failed')
    expect(checkoutReturnView('cancelled', { status: 'PAID' })).toBe('success')
    expect(checkoutReturnView('cancelled', { status: 'PROCESSING' })).toBe('verifying')
    expect(checkoutReturnView('cancelled', null)).toBe('cancelled')
    expect(PAYMENT_COPY.success).not.toContain('query')
  })

  it('only follows PayTabs hosted checkout URLs', () => {
    expect(isSafeCheckoutRedirect('https://secure-egypt.paytabs.com/payment/page/abc')).toBe(true)
    expect(isSafeCheckoutRedirect('https://evil.example/phish')).toBe(false)
    expect(isSafeCheckoutRedirect('javascript:alert(1)')).toBe(false)
    expect(isSafeCheckoutRedirect(null)).toBe(false)
  })

  it('shows customer payment UI only when the order is actually payable', () => {
    expect(
      orderNeedsPayment({
        payable: { available: true, amount: '1500.00', currency: 'SAR' },
        latest_payment: null,
      }),
    ).toBe(true)
    expect(
      orderNeedsPayment({
        payable: { available: true, amount: '1500.00', currency: 'SAR' },
        latest_payment: { status: 'PAID' } as never,
      }),
    ).toBe(false)
    expect(orderNeedsPayment({ payable: { available: false, amount: null, currency: null }, latest_payment: null })).toBe(
      false,
    )
  })

  it('keeps owner payment administration owner-only', () => {
    expect(canAccessOwnerPayments('OWNER')).toBe(true)
    expect(canAccessOwnerPayments('CUSTOMER')).toBe(false)
    expect(canAccessOwnerPayments('ACCOUNT_MANAGER')).toBe(false)
    expect(canAccessOwnerPayments('HR')).toBe(false)
    expect(canAccessOwnerPayments('ADMIN_MANAGER')).toBe(false)
    expect(canAccessOwnerPayments('WEB_DEVELOPER')).toBe(false)
    expect(ownerNavForRole('OWNER').some((item) => item.to === '/owner/payments')).toBe(true)
    expect(ownerNavForRole('ADMIN_MANAGER').some((item) => item.to === '/owner/payments')).toBe(false)
  })

  it('scopes customer pay URLs to the current order id', () => {
    expect(customerPayPath(21)).toBe('/dashboard/orders/21/pay')
    expect(customerPayPath(21)).not.toContain('/22/')
    expect(ownerPaymentPath(9)).toBe('/owner/payments/9')
  })
})
