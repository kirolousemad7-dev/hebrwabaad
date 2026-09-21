import { useState } from 'react'
import { Link } from 'react-router-dom'
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion'
import { BrandSectionAccent } from '../brand/BrandSectionAccent'
import { BrandVisual } from '../marketing/BrandVisual'
import { marketingVisuals, type LandingServiceId } from '../../utils/marketingVisuals'
import { CATALOG_SECTIONS } from '../../utils/catalogRoutes'
import { fadeUp, imageReveal, motionOrReduced, staggerContainer } from '../../utils/marketingMotion'

type ServiceItem = {
  key: LandingServiceId
  title: string
  shortLabel: string
  body: string
  href: string
  features: string[]
}

const SERVICES: ServiceItem[] = [
  {
    key: 'strategy',
    title: CATALOG_SECTIONS['business-diagnosis-strategy'].title,
    shortLabel: 'استراتيجية',
    body: CATALOG_SECTIONS['business-diagnosis-strategy'].description,
    href: CATALOG_SECTIONS['business-diagnosis-strategy'].path,
    features: ['تحليل النشاط والسوق', 'تحديد الأولويات', 'خطة قابلة للقياس'],
  },
  {
    key: 'branding',
    title: CATALOG_SECTIONS['branding-design'].title,
    shortLabel: 'تصميم',
    body: CATALOG_SECTIONS['branding-design'].description,
    href: CATALOG_SECTIONS['branding-design'].path,
    features: ['شعار وهوية', 'دليل استخدام', 'تطبيقات بصرية'],
  },
  {
    key: 'digital',
    title: CATALOG_SECTIONS['content-writing'].title,
    shortLabel: 'محتوى',
    body: CATALOG_SECTIONS['content-writing'].description,
    href: CATALOG_SECTIONS['content-writing'].path,
    features: ['محتوى مقنع', 'نصوص حملات', 'رسائل العلامة'],
  },
  {
    key: 'ecommerce',
    title: CATALOG_SECTIONS['ecommerce-digital-experience'].title,
    shortLabel: 'متاجر',
    body: CATALOG_SECTIONS['ecommerce-digital-experience'].description,
    href: CATALOG_SECTIONS['ecommerce-digital-experience'].path,
    features: ['متاجر ومواقع', 'تجربة شراء', 'صفحات هبوط'],
  },
  {
    key: 'printing',
    title: 'الطباعة والتغليف',
    shortLabel: 'طباعة',
    body: 'حلول طباعة وتغليف احترافية للمنتجات، الهوية المكتبية، والمواد الدعائية بتشطيبات عالية الجودة.',
    href: '/printing-packaging',
    features: ['مطبوعات فاخرة', 'تغليف وباكدجنج', 'مواد دعائية'],
  },
  {
    key: 'events',
    title: CATALOG_SECTIONS['events-management'].title,
    shortLabel: 'فعاليات',
    body: CATALOG_SECTIONS['events-management'].description,
    href: CATALOG_SECTIONS['events-management'].path,
    features: ['افتتاحات ومعارض', 'هوية الفعالية', 'تشغيل وتغطية'],
  },
]

export function ServicesSection() {
  const reduceMotion = useReducedMotion()
  const [active, setActive] = useState(0)
  const current = SERVICES[active] ?? SERVICES[0]

  function go(delta: number) {
    setActive((index) => (index + delta + SERVICES.length) % SERVICES.length)
  }

  return (
    <section id="services" className="marketing-section scroll-mt-24 bg-white">
      <div className="marketing-container">
        <BrandSectionAccent
          className="mb-12 max-w-2xl"
          english="SERVICES"
          title="خدمات تبني حضور علامتك"
          description="اختر محورًا واستكشف كيف ننفّذه — من التشخيص والهوية إلى الطباعة والفعاليات."
        />

        <div className="grid items-stretch gap-10 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)] lg:gap-14">
          <div className="relative">
            <AnimatePresence mode="wait">
              <motion.div
                key={current.key}
                className="group relative overflow-hidden rounded-[1.75rem] border border-brand-ink-100 bg-brand-paper shadow-card"
                variants={motionOrReduced(reduceMotion, imageReveal)}
                initial="hidden"
                animate="show"
                exit={{ opacity: 0, scale: 0.98, transition: { duration: 0.2 } }}
              >
                <div className="aspect-[16/11] sm:aspect-[16/10]">
                  <BrandVisual visual={marketingVisuals.services[current.key]} zoomable />
                </div>
                <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-brand-ink-900/85 via-brand-ink-900/35 to-transparent p-6 sm:p-8">
                  <p className="text-xs font-medium tracking-wide text-white/70">
                    {String(active + 1).padStart(2, '0')} / {String(SERVICES.length).padStart(2, '0')}
                  </p>
                  <h3 className="mt-2 text-2xl font-semibold text-white sm:text-3xl">{current.title}</h3>
                </div>
              </motion.div>
            </AnimatePresence>
          </div>

          <motion.div
            className="flex flex-col justify-center gap-6"
            variants={motionOrReduced(reduceMotion, staggerContainer)}
            initial="hidden"
            whileInView="show"
            viewport={{ once: true, amount: 0.3 }}
          >
            <AnimatePresence mode="wait">
              <motion.div
                key={`copy-${current.key}`}
                variants={motionOrReduced(reduceMotion, fadeUp)}
                initial="hidden"
                animate="show"
                exit={{ opacity: 0, y: 12, transition: { duration: 0.18 } }}
                className="space-y-4"
              >
                <h3 className="text-2xl font-semibold text-brand-ink-900 sm:text-3xl">{current.title}</h3>
                <p className="max-w-md text-base leading-8 text-brand-ink-500">{current.body}</p>
                <ul className="space-y-2 text-sm text-brand-ink-500">
                  {current.features.map((feature) => (
                    <li key={feature} className="flex gap-2">
                      <span className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-ink-300" aria-hidden="true" />
                      <span>{feature}</span>
                    </li>
                  ))}
                </ul>
                <Link to={current.href} className="brand-btn-primary w-fit">
                  استكشف الخدمة
                </Link>
              </motion.div>
            </AnimatePresence>

            <div className="border-t border-brand-ink-100 pt-6">
              <div className="mb-3 flex items-center justify-between gap-3">
                <button
                  type="button"
                  onClick={() => go(-1)}
                  className="inline-flex min-h-10 items-center rounded-xl border border-brand-ink-100 bg-white px-3 text-sm text-brand-ink-700 transition hover:border-brand-ink-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500"
                >
                  السابق
                </button>
                <button
                  type="button"
                  onClick={() => go(1)}
                  className="inline-flex min-h-10 items-center rounded-xl border border-brand-ink-100 bg-white px-3 text-sm text-brand-ink-700 transition hover:border-brand-ink-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500"
                >
                  التالي
                </button>
              </div>
              <div
                role="tablist"
                aria-label="اختيار الخدمة"
                className="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 sm:flex-wrap sm:overflow-visible"
              >
                {SERVICES.map((service, index) => {
                  const selected = index === active
                  return (
                    <button
                      key={service.key}
                      type="button"
                      role="tab"
                      aria-selected={selected}
                      aria-label={service.title}
                      onClick={() => setActive(index)}
                      className={[
                        'inline-flex min-h-11 shrink-0 flex-col items-start justify-center rounded-xl px-3 py-1.5 text-start transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500',
                        selected
                          ? 'bg-brand-ink-900 text-white'
                          : 'bg-brand-paper text-brand-ink-700 hover:bg-brand-ink-100',
                      ].join(' ')}
                    >
                      <span className={`text-[0.65rem] font-medium ${selected ? 'text-white/70' : 'text-brand-ink-300'}`}>
                        {String(index + 1).padStart(2, '0')}
                      </span>
                      <span className="text-xs font-semibold sm:text-sm">{service.shortLabel}</span>
                    </button>
                  )
                })}
              </div>
            </div>
          </motion.div>
        </div>

        <div className="mt-12 flex flex-wrap gap-3">
          <Link to="/services" className="brand-btn-secondary">
            كل الخدمات
          </Link>
          <Link to="/build-package" className="brand-btn-ghost">
            صمّم باقتك
          </Link>
        </div>
      </div>
    </section>
  )
}
