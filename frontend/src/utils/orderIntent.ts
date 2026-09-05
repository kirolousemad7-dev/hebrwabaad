export const PACKAGE_ORDER_COPY = {
  creating: 'جاري إنشاء الطلب...',
  createError: 'تعذر إنشاء الطلب حاليًا. برجاء المحاولة مرة أخرى.',
  unavailable: 'هذه الباقة غير متاحة حاليًا.',
  customerOnly: 'هذه العملية متاحة لحسابات العملاء فقط.',
  order: 'اطلب الباقة',
  requestQuote: 'اطلب تسعير الباقة',
  tierPending: 'بانتظار تحديد التفاصيل من الفريق.',
} as const

export function packageOrderPath(slug: string, tierSlug?: string | null): string {
  const base = `/dashboard/packages/${encodeURIComponent(slug)}/order`

  return tierSlug ? `${base}?tier=${encodeURIComponent(tierSlug)}` : base
}

export function packageOrderAction(input: {
  busy: boolean
  isReady: boolean
  isAuthenticated: boolean
  role?: string
}): 'wait' | 'login' | 'forbidden' | 'submit' {
  if (input.busy || !input.isReady) {
    return 'wait'
  }

  if (!input.isAuthenticated) {
    return 'login'
  }

  return input.role === 'CUSTOMER' ? 'submit' : 'forbidden'
}

/**
 * Placeholder after the Task 09 estimator. Does not persist builder
 * selections or create an order — that belongs to later checkout work.
 */
export function customPackagePath(): string {
  return '/customer?intent=custom'
}

/** Placeholder until the custom printing request form exists. Does not create an order. */
export function printingCustomPath(): string {
  return '/customer?intent=printing-custom'
}

export function isSafeInternalPath(path: string | null | undefined): boolean {
  return typeof path === 'string' && path.startsWith('/') && !path.startsWith('//')
}
