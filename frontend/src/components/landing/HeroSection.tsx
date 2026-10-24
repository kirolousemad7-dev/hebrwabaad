import { useState } from 'react'
import { Link } from 'react-router-dom'
import { motion, useReducedMotion } from 'framer-motion'
import { BrandLogo } from '../brand/BrandLogo'
import { MediaLightbox } from '../ui/MediaLightbox'
import { usePlatformSettings } from '../../context/PlatformSettingsContext'
import { usePublicMarketing } from '../../context/PublicMarketingContext'
import { marketingVisuals } from '../../utils/marketingVisuals'
import {
  fadeUp,
  imageReveal,
  motionOrReduced,
  staggerContainer,
} from '../../utils/marketingMotion'
import { LandingCta } from './LandingCta'

function scrollToId(id: string) {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

export function HeroSection() {
  const reduceMotion = useReducedMotion()
  const { settings } = usePlatformSettings()
  const { resolveVisual, resolveContent } = usePublicMarketing()
  const [lightboxOpen, setLightboxOpen] = useState(false)
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
  const heroVisual = resolveVisual('hero', 'visual_image', marketingVisuals.hero)
  const heroVideo = resolveContent('hero', 'visual_video', '').trim()
  const discoveryLabel = settings.homepage.hero_primary_cta_label || 'اكتشف احتياجك'
  const staticHeroImage = marketingVisuals.hero.image
  const customImage =
    heroVisual.image && !heroVisual.image.endsWith(staticHeroImage) ? heroVisual.image : ''
  const hasMedia = Boolean(heroVideo || customImage)

  return (
    <section id="home" className="relative overflow-hidden bg-brand-paper text-brand-ink-900">
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_90%_8%,rgba(49,92,255,0.04),transparent_34%)]" />

      <div className="marketing-container relative grid items-center gap-8 py-10 sm:gap-10 sm:py-14 lg:grid-cols-[minmax(0,1.15fr)_minmax(16rem,0.72fr)] lg:gap-12 lg:py-16">
        <motion.div
          className="relative z-10 space-y-5 sm:space-y-6"
          variants={motionOrReduced(reduceMotion, staggerContainer)}
          initial="hidden"
          animate="show"
        >
          <motion.div variants={motionOrReduced(reduceMotion, fadeUp)} className="hidden sm:block">
            <BrandLogo size="hero" to="/" className="!p-0" />
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
          className="relative z-10 w-full max-w-[18.5rem] justify-self-center lg:max-w-[20rem] lg:justify-self-end"
          variants={motionOrReduced(reduceMotion, imageReveal)}
          initial="hidden"
          animate="show"
        >
          {hasMedia ? (
            <button
              type="button"
              onClick={() => setLightboxOpen(true)}
              className="block w-full rounded-[1.5rem] border border-brand-ink-100 bg-white p-3 text-start shadow-[0_24px_60px_-40px_rgba(17,19,24,0.45)]"
              aria-label="فتح صورة اكتشف احتياجك"
            >
              <div className="flex min-h-40 items-center justify-center overflow-hidden rounded-2xl bg-brand-paper">
                {heroVideo && !heroVideo.includes('youtube') && !heroVideo.includes('youtu.be') && !heroVideo.includes('vimeo') ? (
                  <video src={heroVideo} className="max-h-64 w-full object-contain sm:max-h-72" muted playsInline loop autoPlay />
                ) : (
                  <img
                    src={customImage}
                    alt={heroVisual.alt || discoveryLabel}
                    className="max-h-64 w-full object-contain sm:max-h-72"
                    decoding="async"
                  />
                )}
              </div>
              <span className="mt-3 block px-1 text-sm font-semibold text-brand-ink-900">{discoveryLabel}</span>
              <span className="mt-1 block px-1 text-xs leading-6 text-brand-ink-500">طباعة · هوية · تسويق · فعاليات</span>
            </button>
          ) : (
            <Link
              to={primaryPath}
              className="block rounded-[1.5rem] border border-brand-ink-100 bg-white p-5 shadow-[0_24px_60px_-40px_rgba(17,19,24,0.45)] transition hover:-translate-y-0.5"
            >
              <p className="text-xs font-medium text-brand-ink-500">فرع المنصة</p>
              <p className="mt-2 text-2xl font-bold leading-snug text-brand-ink-900">{discoveryLabel}</p>
              <p className="mt-3 line-clamp-4 text-sm leading-7 text-brand-ink-500">{sub}</p>
              <p className="mt-4 text-sm text-brand-ink-700">طباعة · هوية · تسويق · فعاليات</p>
              <span className="mt-5 inline-flex min-h-10 items-center rounded-xl bg-brand-ink-900 px-4 text-sm font-medium text-white">
                {discoveryLabel}
              </span>
            </Link>
          )}
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
      <MediaLightbox
        open={lightboxOpen}
        title="اكتشف احتياجك"
        imageSrc={heroVideo ? null : customImage || null}
        videoSrc={heroVideo || null}
        onClose={() => setLightboxOpen(false)}
      />
    </section>
  )
}
