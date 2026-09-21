import { motion, useReducedMotion } from 'framer-motion'
import { BrandLogo } from '../brand/BrandLogo'
import { usePlatformSettings } from '../../context/PlatformSettingsContext'
import { marketingVisuals } from '../../utils/marketingVisuals'
import {
  fadeUp,
  imageReveal,
  motionOrReduced,
  staggerContainer,
} from '../../utils/marketingMotion'
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
  const sub =
    settings.homepage.hero_subheading ||
    'منصة سعودية تجمع الاستراتيجية، الهوية، المحتوى، الطباعة، التغليف وتنظيم الفعاليات في مسار واحد واضح.'

  const brandName = settings.brand.name_ar || 'حبر وأبعاد'
  const rawTagline = settings.brand.tagline?.trim() || ''
  const taglineDuplicatesHeading =
    Boolean(rawTagline) &&
    (rawTagline === heading ||
      rawTagline === headingMain ||
      heading.includes(rawTagline) ||
      rawTagline.includes(headingMain))
  const eyebrow = taglineDuplicatesHeading ? `${brandName} للطباعة والتصميم` : rawTagline || null

  return (
    <section id="home" className="relative overflow-hidden bg-brand-paper text-brand-ink-900">
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_90%_8%,rgba(49,92,255,0.04),transparent_34%)]" />

      <div className="marketing-container relative grid items-center gap-8 py-10 sm:gap-10 sm:py-14 lg:min-h-[calc(100svh-4.5rem)] lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)] lg:gap-14 lg:py-20">
        <motion.div
          className="relative z-10 space-y-5 sm:space-y-6"
          variants={motionOrReduced(reduceMotion, staggerContainer)}
          initial="hidden"
          animate="show"
        >
          <motion.div variants={motionOrReduced(reduceMotion, fadeUp)} className="hidden sm:block">
            <BrandLogo size="mark" to="/" />
          </motion.div>
          {eyebrow ? (
            <motion.p
              variants={motionOrReduced(reduceMotion, fadeUp)}
              className="brand-label tracking-wide text-brand-ink-500"
            >
              {eyebrow}
            </motion.p>
          ) : null}
          <motion.h1
            variants={motionOrReduced(reduceMotion, fadeUp)}
            className="max-w-[18ch] text-balance text-[clamp(2.5rem,1.85rem+2.6vw,4rem)] font-bold leading-[1.2] tracking-tight text-brand-ink-900"
          >
            {headingMain}
            {headingAccent ? (
              <span className="mt-2 block text-[0.72em] font-semibold text-brand-ink-500">{headingAccent}</span>
            ) : null}
          </motion.h1>
          <motion.p
            variants={motionOrReduced(reduceMotion, fadeUp)}
            className="max-w-xl text-base leading-8 text-brand-ink-500 sm:text-lg sm:leading-9"
          >
            {sub}
          </motion.p>
          <motion.div variants={motionOrReduced(reduceMotion, fadeUp)} className="flex flex-col gap-3 sm:flex-row">
            <LandingCta to={primaryPath} variant="primary">
              {settings.homepage.hero_primary_cta_label || 'اكتشف احتياجك'}
            </LandingCta>
            <LandingCta to={secondaryPath} variant="secondary">
              {settings.homepage.hero_secondary_cta_label || 'تصفح الخدمات'}
            </LandingCta>
          </motion.div>
        </motion.div>

        <motion.div
          className="relative z-10 w-full max-w-md justify-self-center lg:max-w-xl lg:justify-self-stretch"
          variants={motionOrReduced(reduceMotion, imageReveal)}
          initial="hidden"
          animate="show"
        >
          <div className="group relative">
            <div className="relative aspect-[16/11] overflow-hidden rounded-[1.5rem] border border-brand-ink-100 shadow-[0_24px_60px_-40px_rgba(17,19,24,0.45)] sm:aspect-[5/4] lg:aspect-[4/5]">
              <BrandVisual visual={marketingVisuals.hero} priority zoomable />
            </div>
            {!reduceMotion ? (
              <motion.div
                aria-hidden="true"
                animate={{ y: [0, -8, 0] }}
                transition={{ duration: 6, repeat: Infinity, ease: 'easeInOut' }}
                className="absolute -bottom-4 inset-inline-start-3 hidden max-w-[13rem] rounded-2xl border border-brand-ink-100 bg-white/95 px-3.5 py-2.5 text-xs leading-6 text-brand-ink-700 shadow-card backdrop-blur sm:block"
              >
                <span className="mb-0.5 block text-[0.65rem] font-medium text-brand-ink-500">فرع المنصة</span>
                طباعة · هوية · تسويق · فعاليات
              </motion.div>
            ) : null}
          </div>
        </motion.div>
      </div>

      <a
        href="#services"
        aria-label="انتقل إلى الخدمات"
        className="absolute bottom-4 left-1/2 z-10 hidden -translate-x-1/2 text-xs text-brand-ink-500 transition hover:text-brand-ink-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500 lg:block"
        onClick={(event) => {
          event.preventDefault()
          scrollToId('services')
        }}
      >
        <span className="hero-scroll-indicator mb-2 block h-8 w-px bg-brand-ink-300" aria-hidden="true" />
        استكشف
      </a>
    </section>
  )
}
