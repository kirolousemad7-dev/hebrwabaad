import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { MediaLightbox } from '../ui/MediaLightbox'
import { SarAmount } from '../ui/SarAmount'
import { usePublicMarketing } from '../../context/PublicMarketingContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getPublicPackages } from '../../services/catalog'
import { getPublicSectors } from '../../services/sectors'
import type { Package } from '../../types/api'
import { buttonColorStyle } from '../../utils/buttonColor'
import { packageHasDiscount } from '../../utils/catalog'
import { resolveMediaUrl } from '../../utils/mediaUrl'

const FALLBACKS = [
  {
    name: 'الباقة الأساسية',
    body: 'هوية أولية وحضور رقمي ومسار تنفيذ مختصر.',
    included: ['هوية أولية', 'حضور رقمي', 'تنفيذ مختصر'],
    excluded: ['حملات مدفوعة', 'تغطية فعاليات'],
  },
  {
    name: 'الباقة الاحترافية',
    body: 'استراتيجية وتنفيذ ومتابعة للعلامات التي تحتاج حضورًا أقوى.',
    included: ['استراتيجية', 'محتوى', 'متابعة'],
    excluded: ['تغطية فعاليات'],
  },
  {
    name: 'الباقة المتكاملة',
    body: 'هوية وإنتاج وتنفيذ متعدد التخصصات تحت إدارة واحدة.',
    included: ['هوية', 'إنتاج', 'تنفيذ متعدد'],
    excluded: [],
  },
]

function durationLabel(days: number | null): string | null {
  if (!days || days <= 0) {
    return null
  }

  return `${days.toLocaleString('en-US')} يوم`
}

export function PackagesSection() {
  const { resolveContent } = usePublicMarketing()
  const packagesState = useAsyncData(getPublicPackages)
  const sectorsState = useAsyncData(getPublicSectors)
  const [sectorId, setSectorId] = useState<number | 'all'>('all')
  const [preview, setPreview] = useState<string | null>(null)
  const packages = useMemo(
    () => (packagesState.state.status === 'ready' ? packagesState.state.data : []),
    [packagesState.state],
  )
  const sectors = sectorsState.state.status === 'ready' ? sectorsState.state.data : []
  const visible = useMemo(() => {
    const rows = sectorId === 'all'
      ? packages
      : packages.filter((pkg) => (pkg.sector_ids ?? []).includes(sectorId))
    return [...rows].sort((left, right) => (left.sort_order ?? 0) - (right.sort_order ?? 0))
  }, [packages, sectorId])

  const rawTitle = resolveContent('packages', 'title', 'باقات تناسب مشروعك')
  const title = rawTitle === 'باقات تناسب مرحلة مشروعك' ? 'باقات تناسب مشروعك' : rawTitle
  const description = resolveContent('packages', 'description', 'اختر نوع المشروع لعرض الباقات الأقرب لاحتياجك.')
  const compareNames = useMemo(() => {
    const names = new Set<string>()
    visible.forEach((pkg) => {
      pkg.items.forEach((item) => {
        if (item.service?.name) {
          names.add(item.service.name)
        }
      })
      ;(pkg.excluded_service_names ?? []).forEach((name) => names.add(name))
    })
    return [...names].slice(0, 8)
  }, [visible])

  return (
    <section id="packages" className="marketing-section scroll-mt-24 bg-brand-paper">
      <div className="marketing-container">
        <header className="mb-5 max-w-3xl space-y-2">
          <p className="brand-label text-brand-ink-500">نوع المشروع</p>
          <h2 className="text-2xl font-bold text-brand-ink-900 sm:text-3xl">{title}</h2>
          <p className="text-sm leading-7 text-brand-ink-500">{description}</p>
          {sectors.length > 0 ? (
            <div className="flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="نوع المشروع">
              <button
                type="button"
                onClick={() => setSectorId('all')}
                className={`min-h-10 shrink-0 rounded-full px-3 text-sm ${sectorId === 'all' ? 'bg-brand-ink-900 text-white' : 'bg-white text-brand-ink-700'}`}
              >
                الكل
              </button>
              {sectors.map((sector) => (
                <button
                  key={sector.id}
                  type="button"
                  onClick={() => setSectorId(sector.id)}
                  className={`min-h-10 shrink-0 rounded-full px-3 text-sm ${sectorId === sector.id ? 'bg-brand-ink-900 text-white' : 'bg-white text-brand-ink-700'}`}
                >
                  {sector.name_ar}
                </button>
              ))}
            </div>
          ) : null}
        </header>

        {visible.length > 0 ? (
          <ul className="flex snap-x gap-3 overflow-x-auto pb-2 lg:grid lg:grid-cols-3 lg:overflow-visible">
            {visible.map((pkg) => (
              <PackageCompareCard
                key={pkg.id}
                pkg={pkg}
                names={compareNames}
                onPreview={(src) => setPreview(src)}
              />
            ))}
          </ul>
        ) : packages.length === 0 ? (
          <ul className="flex snap-x gap-3 overflow-x-auto pb-2 lg:grid lg:grid-cols-3 lg:overflow-visible">
            {FALLBACKS.map((pkg) => (
              <li key={pkg.name} className="flex min-w-[16.5rem] snap-start flex-col rounded-2xl border border-brand-ink-100 bg-white p-4 lg:min-w-0">
                <h3 className="text-center text-lg font-semibold text-brand-ink-900">{pkg.name}</h3>
                <p className="mt-2 text-center text-sm leading-6 text-brand-ink-500">{pkg.body}</p>
                <p className="mt-3 text-center text-sm text-brand-ink-500">السعر عند الطلب</p>
                <ul className="mt-3 space-y-1 text-sm">
                  {pkg.included.map((name) => (
                    <li key={name} className="flex items-center justify-between gap-2">
                      <span>{name}</span>
                      <span className="text-emerald-700">✓</span>
                    </li>
                  ))}
                  {pkg.excluded.map((name) => (
                    <li key={name} className="flex items-center justify-between gap-2 text-brand-ink-400">
                      <span>{name}</span>
                      <span className="text-red-600">✕</span>
                    </li>
                  ))}
                </ul>
                <Link to="/packages" className="brand-btn-primary mt-4 w-full justify-center">
                  تفاصيل الباقة
                </Link>
              </li>
            ))}
          </ul>
        ) : (
          <p className="text-sm text-brand-ink-500">لا توجد باقات لهذا النوع حالياً.</p>
        )}
      </div>
      <MediaLightbox open={preview !== null} imageSrc={preview} title="صورة الباقة" onClose={() => setPreview(null)} />
    </section>
  )
}

function PackageCompareCard({
  pkg,
  names,
  onPreview,
}: {
  pkg: Package
  names: string[]
  onPreview: (src: string) => void
}) {
  const included = new Set(pkg.items.map((item) => item.service?.name).filter((name): name is string => Boolean(name)))
  const excluded = new Set(pkg.excluded_service_names ?? [])
  const rows = names.length > 0 ? names : [...included]
  const discounted = packageHasDiscount(pkg)
  const duration = durationLabel(pkg.duration_days)
  const image = resolveMediaUrl(pkg.image_url)
  const style = buttonColorStyle(pkg.button_color)
  const quote = pkg.pricing_mode === 'QUOTE' || Number.parseFloat(pkg.final_price) <= 0

  return (
    <li className="flex min-w-[16.5rem] snap-start flex-col rounded-2xl border border-brand-ink-100 bg-white p-4 lg:min-w-0">
      {image ? (
        <button type="button" onClick={() => onPreview(image)} className="mb-3 block w-full">
          <img src={image} alt="" className="aspect-[4/3] w-full rounded-xl object-cover" loading="lazy" width={640} height={480} />
        </button>
      ) : null}
      <h3 className="text-center text-lg font-semibold text-brand-ink-900">{pkg.name}</h3>
      <div className="mt-3 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-sm">
        {quote ? (
          <span>السعر عند الطلب</span>
        ) : (
          <SarAmount amount={pkg.final_price} className="text-lg font-bold text-brand-ink-900" />
        )}
        {discounted && !quote ? <SarAmount amount={pkg.price} struck /> : null}
        {discounted ? (
          <span className="text-red-600">
            خصم <SarAmount amount={pkg.discount_amount} className="text-red-600" />
          </span>
        ) : null}
        {duration ? <span className="text-brand-ink-500">{duration}</span> : null}
      </div>
      <ul className="mt-3 space-y-1 text-sm">
        {rows.map((name) => {
          const on = included.has(name) && !excluded.has(name)
          return (
            <li key={name} className="flex items-center justify-between gap-2">
              <span className={on ? 'text-brand-ink-800' : 'text-brand-ink-400'}>{name}</span>
              <span className={on ? 'text-emerald-700' : 'text-red-600'}>{on ? '✓' : '✕'}</span>
            </li>
          )
        })}
      </ul>
      <Link to="/packages" className="brand-btn-primary mt-4 w-full justify-center" style={style}>
        تفاصيل الباقة
      </Link>
    </li>
  )
}
