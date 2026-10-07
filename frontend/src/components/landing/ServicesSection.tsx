import { useState } from 'react'
import { Link } from 'react-router-dom'
import { MediaLightbox } from '../ui/MediaLightbox'
import { ServiceGlyph, type ServiceIconKey } from '../ui/ServiceGlyph'
import { BrandVisual } from '../marketing/BrandVisual'
import { usePublicMarketing } from '../../context/PublicMarketingContext'
import { marketingVisuals, type LandingServiceId } from '../../utils/marketingVisuals'
import { CATALOG_SECTIONS } from '../../utils/catalogRoutes'
import { buttonColorStyle } from '../../utils/buttonColor'

type ServiceItem = {
  key: LandingServiceId
  title: string
  body: string
  href: string
  icon: ServiceIconKey
}

const SERVICES: ServiceItem[] = [
  {
    key: 'strategy',
    title: CATALOG_SECTIONS['business-diagnosis-strategy'].title,
    body: 'تحليل النشاط وتحديد الأولويات.',
    href: CATALOG_SECTIONS['business-diagnosis-strategy'].path,
    icon: 'design',
  },
  {
    key: 'branding',
    title: CATALOG_SECTIONS['branding-design'].title,
    body: 'هوية بصرية وتطبيقات واضحة.',
    href: CATALOG_SECTIONS['branding-design'].path,
    icon: 'design',
  },
  {
    key: 'digital',
    title: CATALOG_SECTIONS['content-writing'].title,
    body: 'محتوى يشرح القيمة ويبني الثقة.',
    href: CATALOG_SECTIONS['content-writing'].path,
    icon: 'speed',
  },
  {
    key: 'ecommerce',
    title: CATALOG_SECTIONS['ecommerce-digital-experience'].title,
    body: 'متاجر وصفحات هبوط جاهزة للبيع.',
    href: CATALOG_SECTIONS['ecommerce-digital-experience'].path,
    icon: 'delivery',
  },
  {
    key: 'printing',
    title: 'الطباعة والتغليف',
    body: 'مطبوعات وتغليف بجودة تشطيب عالية.',
    href: '/printing-packaging',
    icon: 'print',
  },
  {
    key: 'events',
    title: CATALOG_SECTIONS['events-management'].title,
    body: 'تنظيم الفعاليات من الهوية حتى التشغيل.',
    href: CATALOG_SECTIONS['events-management'].path,
    icon: 'warranty',
  },
]

const VISUAL_KEYS: Record<LandingServiceId, string> = {
  strategy: 'visual_strategy',
  branding: 'visual_branding',
  digital: 'visual_digital',
  ecommerce: 'visual_ecommerce',
  printing: 'visual_printing',
  events: 'visual_events',
}

const ICON_BY_KEY: Record<string, ServiceIconKey> = {
  warranty: 'warranty',
  speed: 'speed',
  print: 'print',
  whatsapp: 'whatsapp',
  delivery: 'delivery',
  design: 'design',
}

export function ServicesSection() {
  const { resolveContent, resolveVisual } = usePublicMarketing()
  const [preview, setPreview] = useState<{ src: string; title: string } | null>(null)
  const title = resolveContent('services', 'title', 'خدمات تبني حضور علامتك')
  const description = resolveContent('services', 'description', 'كروت مختصرة، والتفاصيل تحت كل خدمة.')
  const buttonStyle = buttonColorStyle(resolveContent('services', 'button_color', ''))

  return (
    <section id="services" className="marketing-section scroll-mt-24 bg-white">
      <div className="marketing-container">
        <header className="mb-4 max-w-2xl space-y-2">
          <h2 className="text-2xl font-bold text-brand-ink-900 sm:text-3xl">{title}</h2>
          <p className="text-sm leading-7 text-brand-ink-500">{description}</p>
        </header>
        <ul className="flex snap-x snap-mandatory gap-3 overflow-x-auto pb-2">
          {SERVICES.map((service) => {
            const visual = resolveVisual('services', VISUAL_KEYS[service.key], marketingVisuals.services[service.key])
            const iconKey = resolveContent('services', `icon_${service.key}`, service.icon)
            const icon = ICON_BY_KEY[iconKey] ?? service.icon
            return (
              <li key={service.key} className="flex w-[15rem] shrink-0 snap-start flex-col rounded-2xl border border-brand-ink-100 bg-brand-paper p-3 sm:w-[16.25rem]">
                <button
                  type="button"
                  className="block w-full overflow-hidden rounded-xl"
                  onClick={() => {
                    if (visual.image) {
                      setPreview({ src: visual.image, title: service.title })
                    }
                  }}
                >
                  <div className="aspect-[4/3]">
                    <BrandVisual visual={visual} />
                  </div>
                </button>
                <div className="mt-3 flex items-center gap-2 text-brand-ink-900">
                  <ServiceGlyph name={icon} />
                  <h3 className="text-base font-semibold">{service.title}</h3>
                </div>
                <p className="mt-1 text-sm leading-6 text-brand-ink-500">{service.body}</p>
                <Link to={service.href} className="brand-btn-primary mt-3 w-full justify-center" style={buttonStyle}>
                  تفاصيل الخدمة
                </Link>
              </li>
            )
          })}
        </ul>
      </div>
      <MediaLightbox
        open={preview !== null}
        imageSrc={preview?.src}
        title={preview?.title}
        onClose={() => setPreview(null)}
      />
    </section>
  )
}
