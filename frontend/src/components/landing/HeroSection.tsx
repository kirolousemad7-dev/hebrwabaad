import { motion, useReducedMotion } from 'framer-motion'
import { BrandCornerAccent } from '../brand/BrandCornerAccent'
import { BrandLogo } from '../brand/BrandLogo'
import { usePlatformSettings } from '../../context/PlatformSettingsContext'
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
      <BrandCornerAccent position="tr" className="opacity-95 max-lg:w-[min(14rem,48vw)] max-lg:h-[min(14rem,48vw)]" />
      <BrandCornerAccent position="bl" />
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_75%_15%,rgba(49,92,255,0.08),transparent_40%)]" />

      <div className="relative mx-auto grid min-h-[calc(100svh-4.5rem)] max-w-6xl items-center gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)] lg:gap-12 lg:py-16">
        <motion.div
          initial={reduceMotion ? false : { opacity: 0, y: 18 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.55 }}
          className="relative z-10 space-y-6"
        >
          <BrandLogo size="mark" to="/" />
          {settings.brand.tagline ? (
            <p className="brand-label text-brand-cobalt-700">{settings.brand.tagline}</p>
          ) : null}
          <h1 className="brand-heading-xl max-w-xl">
            {headingMain}
            {headingAccent ? (
              <span className="mt-2 block text-brand-cobalt-500">{headingAccent}</span>
            ) : null}
          </h1>
          {settings.homepage.hero_subheading ? (
            <p className="max-w-xl text-base leading-8 text-brand-ink-500 sm:text-lg">
              {settings.homepage.hero_subheading}
            </p>
          ) : null}
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
          initial={reduceMotion ? false : { opacity: 0, scale: 0.96 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ duration: 0.6, delay: 0.08 }}
          className="relative z-10 mx-auto flex w-full max-w-md items-center justify-center lg:max-w-none"
        >
          <div className="brand-image-mask relative aspect-square w-full max-w-md overflow-hidden bg-brand-ink-900 shadow-sm">
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_30%,rgba(49,92,255,0.42),transparent_55%)]" />
            <BrandLogo size="auth" to={null} className="absolute inset-0 m-auto brightness-0 invert [&_img]:h-40 sm:[&_img]:h-48" />
            <div className="absolute bottom-0 inset-inline-start-0 h-1/2 w-1/2 rounded-tr-[4rem] bg-brand-cobalt-500/90" aria-hidden="true" />
          </div>
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
