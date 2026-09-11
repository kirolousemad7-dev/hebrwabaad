import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'
import { createCustomerPackageOrder } from '../../services/orders'
import { describeApiError } from '../../utils/errors'
import {
  PACKAGE_ORDER_COPY,
  packageOrderAction,
  packageOrderPath,
} from '../../utils/orderIntent'
import { customerPayPath } from '../../utils/payments'
import { buildRequestQuotePath } from '../../utils/quoteRequests'

type PackageOrderCtaProps = {
  slug: string
  /** Selected package level; omitted for a package-level order. */
  tierSlug?: string | null
  label?: string
  variant?: 'primary' | 'secondary'
  /** When true, opens RFQ flow instead of creating a payable order. */
  requiresQuote?: boolean
  packageId?: number | null
  packageName?: string | null
  tierId?: number | null
  pricingMode?: string | null
}

const variantClasses: Record<'primary' | 'secondary', string> = {
  primary: 'bg-slate-900 text-white focus-visible:outline-slate-900',
  secondary: 'border border-slate-300 text-slate-900 focus-visible:outline-slate-900',
}

export function PackageOrderCta({
  slug,
  tierSlug = null,
  label = PACKAGE_ORDER_COPY.order,
  variant = 'primary',
  requiresQuote = false,
  packageId = null,
  packageName = null,
  tierId = null,
  pricingMode = null,
}: PackageOrderCtaProps) {
  const { isAuthenticated, isReady, user } = useAuth()
  const navigate = useNavigate()
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const quoteMode = requiresQuote || pricingMode === 'QUOTE'

  async function orderPackage() {
    const action = packageOrderAction({
      busy,
      isReady,
      isAuthenticated,
      role: user?.role,
    })

    if (action === 'wait') {
      return
    }

    const quotePath = buildRequestQuotePath({
      source_type: tierId ? 'PACKAGE_TIER' : 'PACKAGE',
      source_id: tierId ?? packageId ?? undefined,
      title: packageName || label,
      payload: {
        package_slug: slug,
        tier_slug: tierSlug,
      },
    })

    if (action === 'login') {
      navigate('/login', { state: { from: quoteMode ? quotePath : packageOrderPath(slug, tierSlug) } })
      return
    }

    if (action === 'forbidden') {
      setError(PACKAGE_ORDER_COPY.customerOnly)
      return
    }

    if (quoteMode) {
      navigate(quotePath)
      return
    }

    setBusy(true)
    setError(null)

    try {
      const response = await createCustomerPackageOrder(slug, tierSlug)
      navigate(customerPayPath(response.data.id))
    } catch (caught) {
      setError(describeApiError(caught, PACKAGE_ORDER_COPY.createError))
      setBusy(false)
    }
  }

  return (
    <div className="flex-1 space-y-2">
      <button
        type="button"
        disabled={busy || !isReady}
        onClick={() => void orderPackage()}
        className={[
          'inline-flex min-h-11 w-full items-center justify-center rounded-lg px-4 text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-wait disabled:opacity-60',
          variantClasses[variant],
        ].join(' ')}
      >
        {busy ? PACKAGE_ORDER_COPY.creating : label}
      </button>
      {error ? (
        <p className="text-xs leading-6 text-red-700" role="alert">
          {error}
        </p>
      ) : null}
    </div>
  )
}
