import { useEffect, useMemo, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { BuilderAddonsStep } from '../components/builder/BuilderAddonsStep'
import { BuilderEstimateBar } from '../components/builder/BuilderEstimateBar'
import { BuilderProgress } from '../components/builder/BuilderProgress'
import { BuilderQuantitiesStep } from '../components/builder/BuilderQuantitiesStep'
import { BuilderServicesStep } from '../components/builder/BuilderServicesStep'
import { BuilderSummaryStep } from '../components/builder/BuilderSummaryStep'
import { useAuth } from '../context/AuthContext'
import { getPublicAddons } from '../services/addons'
import { getPublicServices } from '../services/catalog'
import { createCustomerCustomPackageOrder } from '../services/orders'
import type { Service } from '../types/api'
import { describeApiError } from '../utils/errors'
import {
  BUILDER_MIN_QUANTITY,
  addonsSubtotalHalalas,
  clampBuilderQuantity,
  estimatedDurationDays,
  packageHasIncompletePricing,
  packageLevelAddons,
  selectedServicesSubtotalHalalas,
  type BuilderAddon,
  type BuilderStepId,
  type SelectedServiceConfig,
} from '../utils/builder'
import { formatDuration, formatHalalas } from '../utils/catalog'
import { buildRequestQuotePath } from '../utils/quoteRequests'

export function BuildPackagePage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { user } = useAuth()
  const [step, setStep] = useState<BuilderStepId>(1)
  const [configs, setConfigs] = useState<SelectedServiceConfig[]>([])
  const [packageAddonIds, setPackageAddonIds] = useState<string[]>([])
  const [addons, setAddons] = useState<BuilderAddon[]>([])
  const [addonsLoading, setAddonsLoading] = useState(true)
  const [prefillDone, setPrefillDone] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false

    void getPublicAddons()
      .then((response) => {
        if (!cancelled) {
          setAddons(response.data)
        }
      })
      .catch(() => {
        if (!cancelled) {
          setAddons([])
        }
      })
      .finally(() => {
        if (!cancelled) {
          setAddonsLoading(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    if (prefillDone) {
      return
    }

    const raw = searchParams.get('services')
    if (!raw) {
      setPrefillDone(true)
      return
    }

    const slugs = raw
      .split(',')
      .map((value) => value.trim())
      .filter(Boolean)

    if (slugs.length === 0) {
      setPrefillDone(true)
      return
    }

    let cancelled = false

    void getPublicServices()
      .then((response) => {
        if (cancelled) {
          return
        }

        const matched = response.data.filter((service) => slugs.includes(service.slug))
        if (matched.length > 0) {
          setConfigs(
            matched.map((service) => ({
              service,
              quantity: BUILDER_MIN_QUANTITY,
              addonIds: [],
              notes: '',
            })),
          )
          setStep(2)
        }
      })
      .finally(() => {
        if (!cancelled) {
          setPrefillDone(true)
        }
      })

    return () => {
      cancelled = true
    }
  }, [prefillDone, searchParams])

  const servicesSubtotal = useMemo(() => selectedServicesSubtotalHalalas(configs), [configs])
  const lineAddonIds = useMemo(() => configs.flatMap((row) => row.addonIds), [configs])
  const addonsSubtotal = useMemo(
    () => addonsSubtotalHalalas(addons, [...packageAddonIds, ...lineAddonIds]),
    [addons, lineAddonIds, packageAddonIds],
  )
  const estimatedTotal = servicesSubtotal + addonsSubtotal
  const incomplete = packageHasIncompletePricing(configs, addons, packageAddonIds)
  const durationDays = useMemo(
    () => estimatedDurationDays(configs.map((row) => row.service)),
    [configs],
  )
  const durationLabel =
    configs.length === 0 || durationDays === null
      ? 'مدة الباقة النهائية يتم تأكيدها بعد المراجعة'
      : `مدة التنفيذ التقديرية (أطول مسار): ${formatDuration(durationDays)}`

  const packageAddons = useMemo(() => packageLevelAddons(addons), [addons])

  function toggleService(service: Service) {
    setConfigs((current) => {
      const exists = current.some((row) => row.service.id === service.id)
      if (exists) {
        return current.filter((row) => row.service.id !== service.id)
      }

      return [
        ...current,
        {
          service,
          quantity: BUILDER_MIN_QUANTITY,
          addonIds: [],
          notes: '',
        },
      ]
    })
  }

  function removeService(serviceId: number) {
    setConfigs((current) => current.filter((row) => row.service.id !== serviceId))
  }

  function changeQuantity(serviceId: number, quantity: number) {
    setConfigs((current) =>
      current.map((row) =>
        row.service.id === serviceId ? { ...row, quantity: clampBuilderQuantity(quantity) } : row,
      ),
    )
  }

  function toggleServiceAddon(serviceId: number, addonId: string) {
    setConfigs((current) =>
      current.map((row) => {
        if (row.service.id !== serviceId) {
          return row
        }

        const next = row.addonIds.includes(addonId)
          ? row.addonIds.filter((id) => id !== addonId)
          : [...row.addonIds, addonId]

        return { ...row, addonIds: next }
      }),
    )
  }

  function togglePackageAddon(addonId: string) {
    setPackageAddonIds((current) =>
      current.includes(addonId) ? current.filter((id) => id !== addonId) : [...current, addonId],
    )
  }

  function canContinue(): boolean {
    if (step === 1 || step === 2) {
      return configs.length > 0
    }

    return true
  }

  function goNext() {
    if (!canContinue()) {
      return
    }

    setStep((current) => (current === 4 ? current : ((current + 1) as BuilderStepId)))
  }

  function goBack() {
    setStep((current) => (current === 1 ? current : ((current - 1) as BuilderStepId)))
  }

  async function submitOrder() {
    setSubmitError(null)

    if (!user) {
      const slugs = configs.map((row) => row.service.slug).join(',')
      navigate(`/login?next=${encodeURIComponent(`/build-package?services=${slugs}`)}`)
      return
    }

    setSubmitting(true)

    try {
      if (incomplete) {
        navigate(
          buildRequestQuotePath({
            source_type: 'CUSTOM_PACKAGE',
            title: 'صمّم باقتك',
            payload: {
              line_items: configs.map((row) => ({
                description: row.service.name,
                quantity: row.quantity,
                unit_price: '0.00',
                category: 'CREATIVE',
                service_id: row.service.id,
                service_slug: row.service.slug,
                addon_ids: row.addonIds,
                notes: row.notes || null,
              })),
              package_addon_ids: packageAddonIds,
            },
          }),
        )
        return
      }

      const response = await createCustomerCustomPackageOrder({
        items: configs.map((row) => ({
          service_id: row.service.id,
          quantity: row.quantity,
          addon_slugs: row.addonIds,
          notes: row.notes || null,
        })),
        package_addon_slugs: packageAddonIds,
      })

      const order = response.data
      if (order.requires_quote || !order.payable?.available) {
        navigate(
          buildRequestQuotePath({
            source_type: 'ORDER',
            source_id: order.id,
            title: `طلب #${order.id}`,
            payload: { order_id: order.id },
          }),
        )
        return
      }

      navigate(`/dashboard/orders/${order.id}/pay`)
    } catch (error) {
      setSubmitError(describeApiError(error, 'تعذر إرسال الطلب.'))
    } finally {
      setSubmitting(false)
    }
  }

  function NavButtons() {
    return (
      <>
        <button
          type="button"
          onClick={goBack}
          disabled={step === 1}
          className="min-h-11 flex-1 rounded-full border border-brand-border bg-white px-4 text-sm disabled:opacity-40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary"
        >
          السابق
        </button>
        {step < 4 ? (
          <button
            type="button"
            onClick={goNext}
            disabled={!canContinue()}
            className="brand-btn-primary flex-1 disabled:opacity-40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500"
          >
            التالي
          </button>
        ) : null}
      </>
    )
  }

  const totalLabel = incomplete
    ? `القيمة المعروفة: ${formatHalalas(estimatedTotal)} + تسعير`
    : formatHalalas(estimatedTotal)

  return (
    <div className="relative space-y-8 pb-52 lg:pb-8">
      <header className="space-y-3">
        <h1 className="text-3xl font-extrabold text-brand-black sm:text-4xl">صمّم باقتك بنفسك</h1>
        <p className="max-w-2xl text-brand-text-muted">
          اختر عدة خدمات، ثم حدّد الكميات والإضافات. الأسعار تظهر فقط للعناصر المعتمدة من المالك.
        </p>
        <BuilderProgress current={step} />
      </header>

      <div className="hidden lg:block">
        <BuilderEstimateBar
          serviceCount={configs.length}
          totalLabel={totalLabel}
          durationLabel={durationLabel}
        />
      </div>

      {step === 1 ? (
        <BuilderServicesStep
          selected={configs.map((row) => row.service)}
          onToggle={toggleService}
          onRemove={removeService}
        />
      ) : null}

      {step === 2 ? (
        <BuilderQuantitiesStep
          configs={configs}
          addons={addons}
          onQuantityChange={changeQuantity}
          onToggleServiceAddon={toggleServiceAddon}
          onRemove={removeService}
        />
      ) : null}

      {step === 3 ? (
        <BuilderAddonsStep
          addons={packageAddons}
          selectedIds={packageAddonIds}
          onToggle={togglePackageAddon}
          loading={addonsLoading}
        />
      ) : null}

      {step === 4 ? (
        <BuilderSummaryStep
          configs={configs}
          addons={addons}
          packageAddonIds={packageAddonIds}
          servicesSubtotal={servicesSubtotal}
          addonsSubtotal={addonsSubtotal}
          estimatedTotal={estimatedTotal}
          incomplete={incomplete}
          durationDays={durationDays}
          submitting={submitting}
          submitError={submitError}
          onEditStep={setStep}
          onSubmit={() => void submitOrder()}
        />
      ) : null}

      <div className="hidden flex-wrap gap-3 lg:flex">
        <NavButtons />
      </div>

      <div className="fixed inset-x-0 bottom-0 z-10 border-t border-brand-border bg-white/95 px-4 py-3 backdrop-blur lg:hidden">
        <div className="mx-auto flex max-w-5xl flex-col gap-3">
          <BuilderEstimateBar
            serviceCount={configs.length}
            totalLabel={totalLabel}
            durationLabel={durationLabel}
          />
          <div className="flex flex-wrap gap-3">
            <NavButtons />
          </div>
        </div>
      </div>
    </div>
  )
}
