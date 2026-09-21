import { motion, useReducedMotion } from 'framer-motion'
import { BrandCornerAccent } from '../brand/BrandCornerAccent'
import { BrandLogo } from '../brand/BrandLogo'
import { usePlatformSettings } from '../../context/PlatformSettingsContext'
import { marketingVisuals } from '../../utils/marketingVisuals'
import { BrandVisual } from '../marketing/BrandVisual'
import { LandingCta } from './LandingCta'

function scrollToId(id: string) {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

export function HeroSection() {
  const reduceMotion = useReducedMotion()
  const { settings } = usePlatformSettings()
  const heading = settings.homepage.hero_heading || 'نمنح أعمالك أبعادًا للنمو'
  const [headingMain, headingAccent] = heading.includes('من الفكرة')
    ? [heading.replace(/\s*من الفكرة حتى التسليم\s*$/, ''), 'من الفكرة حتى التسليم']
    : [heading, null]
  const primaryPath = settings.homepage.hero_primary_cta_path || '/consultant'
  const secondaryPath = settings.homepage.hero_secondary_cta_path || '/services'

  return (
    <section id="home" className="relative overflow-hidden bg-brand-paper text-brand-ink-900">
      <BrandCornerAccent position="tr" className="opacity-95 max-lg:h-[min(14rem,48vw)] max-lg:w-[min(14rem,48vw)]" />
      <BrandCornerAccent position="bl" />
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_75%_15%,rgba(49,92,255,0.1),transparent_42%)]" />
      {!reduceMotion ? (
        <>
          <div className="hero-orb hero-orb-a pointer-events-none absolute start-[8%] top-[22%] h-24 w-24 rounded-full bg-brand-cobalt-300/25 blur-2xl" />
          <div className="hero-orb hero-orb-b pointer-events-none absolute end-[18%] top-[58%] h-16 w-16 rounded-full bg-brand-cobalt-500/20 blur-xl" />
        </>
      ) : null}

      <div className="relative mx-auto grid min-h-[calc(100svh-4.5rem)] max-w-6xl items-center gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)] lg:gap-14 lg:py-16">
        <motion.div
          initial={reduceMotion ? false : { opacity: 0, y: 22 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.55, ease: [0.22, 1, 0.36, 1] }}
          className="relative z-10 space-y-6"
        >
          <BrandLogo size="mark" to="/" />
          {settings.brand.tagline ? (
            <p className="brand-label text-brand-cobalt-700">{settings.brand.tagline}</p>
          ) : null}
          <h1 className="brand-heading-xl max-w-xl">
            {headingMain}
            {headingAccent ? <span className="mt-2 block text-brand-cobalt-500">{headingAccent}</span> : null}
          </h1>
          {settings.homepage.hero_subheading ? (
            <p className="max-w-xl text-base leading-8 text-brand-ink-500 sm:text-lg">
              {settings.homepage.hero_subheading}
            </p>
          ) : (
            <p className="max-w-xl text-base leading-8 text-brand-ink-500 sm:text-lg">
              منصة سعودية تجمع الاستراتيجية، الهوية، المحتوى، الطباعة، التغليف وتنظيم الفعاليات في مسار واحد واضح.
            </p>
          )}
          <div className="flex flex-col gap-3 sm:flex-row">
            <LandingCta to={primaryPath} variant="primary">
              {settings.homepage.hero_primary_cta_label || 'اكتشف احتياجك'}
            </LandingCta>
            <LandingCta to={secondaryPath} variant="secondary">
              {settings.homepage.hero_secondary_cta_label || 'تصفح الخدمات'}
            </LandingCta>
          </div>
        </motion.div>

        <motion.div
          initial={reduceMotion ? false : { opacity: 0, x: 28 }}
          animate={{ opacity: 1, x: 0 }}
          transition={{ duration: 0.65, delay: reduceMotion ? 0 : 0.1, ease: [0.22, 1, 0.36, 1] }}
          className="relative z-10 mx-auto w-full max-w-md lg:max-w-none"
        >
          <div className="brand-image-mask relative aspect-[4/5] w-full overflow-hidden shadow-card sm:aspect-square">
            <BrandVisual visual={marketingVisuals.hero} priority className="min-h-full" />
            <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-brand-ink-900/80 via-brand-ink-900/25 to-transparent p-6 text-white">
              <p className="text-sm font-medium text-brand-cobalt-300">حبر وأبعاد</p>
              <p className="mt-1 text-lg font-semibold leading-8">خدمات الأعمال والنمو — من الفكرة حتى التسليم</p>
            </div>
          </div>
          {!reduceMotion ? (
            <motion.div
              aria-hidden="true"
              animate={{ y: [0, -8, 0] }}
              transition={{ duration: 5.5, repeat: Infinity, ease: 'easeInOut' }}
              className="absolute -bottom-4 inset-inline-start-4 hidden rounded-2xl border border-brand-ink-100 bg-white px-4 py-3 text-xs font-medium text-brand-ink-700 shadow-card sm:block"
            >
              طباعة · هوية · تسويق · فعاليات
            </motion.div>
          ) : null}
        </motion.div>
      </div>

      <a
        href="#services"
        aria-label="انتقل إلى الخدمات"
        className="absolute bottom-5 left-1/2 z-10 -translate-x-1/2 text-xs text-brand-ink-500 transition hover:text-brand-ink-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500"
        onClick={(event) => {
          event.preventDefault()
          scrollToId('services')
        }}
      >
        <span className="hero-scroll-indicator mb-2 block h-8 w-px bg-brand-cobalt-500/50" aria-hidden="true" />
        اسحب للأسفل
      </a>
    </section>
  )
}
