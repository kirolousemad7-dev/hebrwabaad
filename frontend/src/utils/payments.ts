import type {
  CustomerOrder,
  CustomerPayment,
  CustomerPaymentSettings,
  PaymentMethod,
  PaymentStatus,
} from '../types/api'
import { formatMoney } from './catalog'

export const PAYMENT_COPY = {
  chooseMethod: 'اختر طريقة الدفع',
  card: 'الدفع بالبطاقة',
  instapay: 'إنستاباي',
  bankTransfer: 'التحويل البنكي',
  cardHint: 'ادفع ببطاقة Visa أو Mastercard عبر صفحة دفع آمنة. لا نحتفظ برقم البطاقة داخل حبر.',
  payNow: 'ادفع الآن',
  instapayHint: 'حوّل المبلغ ثم أدخل رقم العملية ليراجعه المالك.',
  bankTransferHint: 'حوّل المبلغ إلى الحساب البنكي ثم أدخل رقم الحوالة ليراجعه المالك.',
  awaitingQuote: 'هذه الباقة بحاجة إلى تسعير من الفريق. سنوافيك بالمبلغ المستحق قبل الدفع.',
  noMethods: 'لا توجد طريقة دفع متاحة حاليًا. برجاء المحاولة لاحقًا.',
  loading: 'جاري تحميل بيانات الدفع...',
  processing: 'جاري تحويلك إلى بوابة الدفع...',
  verifying: 'عملية الدفع قيد التحقق',
  success: 'نجحت عملية الدفع',
  failed: 'عملية الدفع لم تكتمل',
  cancelled: 'تم إلغاء عملية الدفع',
  pendingVerification: 'تم استلام بيانات التحويل وهي قيد التحقق.',
  rejected: 'رُفض التحويل.',
  emptyOwner: 'لا توجد مدفوعات بعد',
  emptyOwnerDescription: 'ستظهر هنا المدفوعات الحقيقية بعد إتمام العملاء للدفع.',
  amountDue: 'المبلغ المستحق',
  gatewayMissing: 'الدفع بالبطاقة غير متاح حاليًا، برجاء المحاولة لاحقًا.',
  notPayable: 'لا يوجد مبلغ مستحق على هذا الطلب.',
  isolation: 'لا يمكنك الوصول إلى دفعة عميل آخر.',
} as const

export const PAYMENT_STATUS_LABELS: Record<PaymentStatus, string> = {
  PENDING: 'بانتظار الدفع',
  PROCESSING: 'قيد المعالجة',
  PAID: 'مدفوع',
  FAILED: 'فشل الدفع',
  CANCELLED: 'ملغى',
  PENDING_VERIFICATION: 'بانتظار التحقق',
  REJECTED: 'مرفوض',
}

export const PAYMENT_METHOD_LABELS: Record<PaymentMethod, string> = {
  CARD: 'بطاقة بنكية',
  INSTAPAY: 'إنستاباي',
  BANK_TRANSFER: 'تحويل بنكي',
}

export type EnabledPaymentMethods = {
  card: boolean
  instapay: boolean
  bank_transfer: boolean
}

/**
 * Only methods the owner enabled and finished configuring are offered.
 * Card availability additionally depends on the server-side gateway config.
 */
export function enabledPaymentMethods(settings: CustomerPaymentSettings): EnabledPaymentMethods {
  return {
    card: settings.card.enabled && settings.card.configured,
    instapay: settings.instapay.enabled && settings.instapay.ready,
    bank_transfer: settings.bank_transfer.enabled && settings.bank_transfer.ready,
  }
}

export function hasAnyPaymentMethod(enabled: EnabledPaymentMethods): boolean {
  return enabled.card || enabled.instapay || enabled.bank_transfer
}

export function isManualPaymentMethod(method: string | null | undefined): boolean {
  return method === 'INSTAPAY' || method === 'BANK_TRANSFER'
}

/** Copy for an order that cannot be paid yet, based on the backend reason. */
export function payableUnavailableCopy(reason: string | null | undefined): string {
  return reason === 'awaiting_owner_quote' ? PAYMENT_COPY.awaitingQuote : PAYMENT_COPY.notPayable
}

export function canAccessOwnerPayments(role: string | undefined): boolean {
  return role === 'OWNER'
}

export function customerPayPath(orderId: number): string {
  return `/dashboard/orders/${orderId}/pay`
}

export function ownerPaymentPath(paymentId: number): string {
  return `/owner/payments/${paymentId}`
}

export function formatPaymentAmount(amount: string | number | null | undefined, currency = 'SAR'): string {
  if (amount === null || amount === undefined || amount === '') {
    return '—'
  }

  return formatMoney(amount, currency)
}

export function orderNeedsPayment(order: Pick<CustomerOrder, 'payable' | 'latest_payment'>): boolean {
  if (order.latest_payment?.status === 'PAID') {
    return false
  }

  return Boolean(order.payable?.available)
}

export function paymentViewState(
  payment: Pick<CustomerPayment, 'status'> | null | undefined,
): 'idle' | 'processing' | 'success' | 'failed' | 'pending_verification' | 'rejected' | 'cancelled' {
  const status = payment?.status

  if (status === 'PROCESSING') {
    return 'processing'
  }

  if (status === 'PAID') {
    return 'success'
  }

  if (status === 'FAILED') {
    return 'failed'
  }

  if (status === 'PENDING_VERIFICATION') {
    return 'pending_verification'
  }

  if (status === 'REJECTED') {
    return 'rejected'
  }

  if (status === 'CANCELLED') {
    return 'cancelled'
  }

  return 'idle'
}

export function selectedPaymentMethod(
  method: string | null | undefined,
  enabled: EnabledPaymentMethods,
): PaymentMethod | null {
  if (method === 'CARD' && enabled.card) {
    return 'CARD'
  }

  if (method === 'INSTAPAY' && enabled.instapay) {
    return 'INSTAPAY'
  }

  if (method === 'BANK_TRANSFER' && enabled.bank_transfer) {
    return 'BANK_TRANSFER'
  }

  return null
}

export type CheckoutReturnView =
  | 'idle'
  | 'processing'
  | 'verifying'
  | 'success'
  | 'failed'
  | 'pending_verification'
  | 'rejected'
  | 'cancelled'

export function checkoutReturnView(
  checkoutFlag: string | null | undefined,
  payment: Pick<CustomerPayment, 'status'> | null | undefined,
): CheckoutReturnView {
  const view = paymentViewState(payment)

  if (view === 'success') {
    return 'success'
  }

  if (view === 'failed' || view === 'rejected' || view === 'pending_verification') {
    return view
  }

  if (view === 'cancelled') {
    return 'cancelled'
  }

  if (checkoutFlag === 'success' || checkoutFlag === 'return') {
    return view === 'processing' || view === 'idle' ? 'verifying' : view
  }

  if (checkoutFlag === 'cancelled') {
    return view === 'processing' ? 'verifying' : 'cancelled'
  }

  return view
}

export function isSafeCheckoutRedirect(url: string | null | undefined): boolean {
  if (!url) {
    return false
  }

  try {
    const parsed = new URL(url)
    return parsed.protocol === 'https:' && parsed.hostname.includes('paytabs.com')
  } catch {
    return false
  }
}
