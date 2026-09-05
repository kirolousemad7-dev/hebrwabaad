import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { BuilderAddonsStep } from '../components/builder/BuilderAddonsStep'
import { BuilderCategoryStep } from '../components/builder/BuilderCategoryStep'
import { BuilderEstimateBar } from '../components/builder/BuilderEstimateBar'
import { BuilderProgress } from '../components/builder/BuilderProgress'
import { BuilderQuantitiesStep } from '../components/builder/BuilderQuantitiesStep'
import { BuilderServicesStep } from '../components/builder/BuilderServicesStep'
import { BuilderSummaryStep } from '../components/builder/BuilderSummaryStep'
import type { Service, ServiceCategory } from '../types/api'
import { customPackagePath } from '../utils/orderIntent'
import {
  addonsSubtotalHalalas,
  BUILDER_MIN_QUANTITY,
  clampBuilderQuantity,
  estimatedDurationDays,
  servicesSubtotalHalalas,
  type BuilderStepId,
} from '../utils/builder'
import { formatDuration, formatHalalas } from '../utils/catalog'

export function BuildPackagePage() {
  const [step, setStep] = useState<BuilderStepId>(1)
  const [category, setCategory] = useState<ServiceCategory | null>(null)
  const [selectedServices, setSelectedServices] = useState<Service[]>([])
  const [quantities, setQuantities] = useState<Record<number, number>>({})
  const [addonIds, setAddonIds] = useState<string[]>([])

  const servicesSubtotal = useMemo(
    () => servicesSubtotalHalalas(selectedServices, quantities),
    [quantities, selectedServices],
  )
  const addonsSubtotal = useMemo(() => addonsSubtotalHalalas(addonIds), [addonIds])
  const estimatedTotal = servicesSubtotal + addonsSubtotal
  const durationDays = useMemo(() => estimatedDurationDays(selectedServices), [selectedServices])
  const durationLabel =
    selectedServices.length === 0 || durationDays === null
      ? 'لم يتم تحديد مدة التنفيذ بعد'
      : `مدة التنفيذ التقديرية: ${formatDuration(durationDays)}`

  function selectCategory(next: ServiceCategory) {
    if (category !== next) {
      setSelectedServices([])
      setQuantities({})
      setAddonIds([])
    }

    setCategory(next)
  }

  function toggleService(service: Service) {
    setSelectedServices((current) => {
      const exists = current.some((item) => item.id === service.id)

      if (exists) {
        setQuantities((quantitiesState) => {
          const next = { ...quantitiesState }
          delete next[service.id]
          return next
        })

        return current.filter((item) => item.id !== service.id)
      }

      setQuantities((quantitiesState) => ({
        ...quantitiesState,
        [service.id]: quantitiesState[service.id] ?? BUILDER_MIN_QUANTITY,
      }))

      return [...current, service]
    })
  }

  function changeQuantity(serviceId: number, quantity: number) {
    setQuantities((current) => ({
      ...current,
      [serviceId]: clampBuilderQuantity(quantity),
    }))
  }

  function removeService(serviceId: number) {
    setSelectedServices((current) => current.filter((service) => service.id !== serviceId))
    setQuantities((current) => {
      const next = { ...current }
      delete next[serviceId]
      return next
    })
  }

  function toggleAddon(addonId: string) {
    setAddonIds((current) =>
      current.includes(addonId) ? current.filter((id) => id !== addonId) : [...current, addonId],
    )
  }

  function canContinue(): boolean {
    if (step === 1) {
      return category !== null
    }

    if (step === 2 || step === 3) {
      return selectedServices.length > 0
    }

    return true
  }

  function goNext() {
    if (!canContinue()) {
      return
    }

    setStep((current) => (current === 5 ? current : ((current + 1) as BuilderStepId)))
  }

  function goBack() {
    setStep((current) => (current === 1 ? current : ((current - 1) as BuilderStepId)))
  }

  const nextLabel = step === 4 ? 'مراجعة الباقة' : 'التالي'

  function NavButtons() {
    return (
      <>
        <button
          type="button"
          onClick={goBack}
          disabled={step === 1}
          className="min-h-11 flex-1 rounded-lg border border-slate-300 bg-white px-4 text-sm disabled:opacity-40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
        >
          السابق
        </button>
        {step < 5 ? (
          <button
            type="button"
            onClick={goNext}
            disabled={!canContinue()}
            className="min-h-11 flex-1 rounded-lg bg-slate-900 px-4 text-sm font-medium text-white disabled:opacity-40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          >
            {nextLabel}
          </button>
        ) : null}
      </>
    )
  }

  return (
    <div className="space-y-8 pb-52 lg:pb-8">
      <header className="space-y-3">
        <h1 className="text-2xl font-semibold sm:text-3xl">صمّم باقتك بنفسك</h1>
        <p className="max-w-2xl text-slate-600">
          اختار الخدمات التي تحتاجها، حدد الكميات، وشاهد السعر ومدة التنفيذ بشكل مباشر.
        </p>
        <BuilderProgress current={step} />
      </header>

      <div className="hidden lg:block">
        <BuilderEstimateBar
          serviceCount={selectedServices.length}
          totalLabel={formatHalalas(estimatedTotal)}
          durationLabel={durationLabel}
        />
      </div>

      {step === 1 ? <BuilderCategoryStep selected={category} onSelect={selectCategory} /> : null}

      {step === 2 && category ? (
        <BuilderServicesStep
          key={category}
          category={category}
          selected={selectedServices}
          onToggle={toggleService}
          onChangeCategory={() => setStep(1)}
        />
      ) : null}

      {step === 3 ? (
        <BuilderQuantitiesStep
          services={selectedServices}
          quantities={quantities}
          onQuantityChange={changeQuantity}
          onRemove={removeService}
        />
      ) : null}

      {step === 4 ? <BuilderAddonsStep selectedIds={addonIds} onToggle={toggleAddon} /> : null}

      {step === 5 && category ? (
        <BuilderSummaryStep
          category={category}
          services={selectedServices}
          quantities={quantities}
          addonIds={addonIds}
          servicesSubtotal={servicesSubtotal}
          addonsSubtotal={addonsSubtotal}
          estimatedTotal={estimatedTotal}
          durationDays={durationDays}
          onEditStep={setStep}
        />
      ) : null}

      <div className="hidden flex-wrap gap-3 lg:flex">
        <NavButtons />
      </div>

      <div className="fixed inset-x-0 bottom-0 z-10 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur lg:hidden">
        <div className="mx-auto flex max-w-5xl flex-col gap-3">
          <BuilderEstimateBar
            serviceCount={selectedServices.length}
            totalLabel={formatHalalas(estimatedTotal)}
            durationLabel={durationLabel}
          />
          <div className="flex flex-wrap gap-3">
            <NavButtons />
            {step === 5 ? (
              <Link
                to={customPackagePath()}
                className="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg bg-slate-900 px-4 text-sm font-medium text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
              >
                اطلب هذه الباقة
              </Link>
            ) : null}
          </div>
        </div>
      </div>
    </div>
  )
}
