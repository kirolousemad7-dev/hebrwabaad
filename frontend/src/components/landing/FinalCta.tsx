import { motion, useReducedMotion } from 'framer-motion'
import { usePublicMarketing } from '../../context/PublicMarketingContext'
import { LandingCta } from './LandingCta'
import { fadeUp, motionOrReduced, staggerContainer } from '../../utils/marketingMotion'

function scrollToId(id: string) {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

export function FinalCta() {
  const reduceMotion = useReducedMotion()
  const { resolveContent } = usePublicMarketing()

  const eyebrow = resolveContent('final-cta', 'eyebrow', 'جاهز نبدأ؟')
  const title = resolveContent('final-cta', 'title', 'لنبنِ شيئًا يستحق التذكّر.')
  const body = resolveContent(
    'final-cta',
    'description',
    'احكِ لنا عن فكرتك، ودع فريق حبر وأبعاد يحوّلها إلى تجربة متكاملة من الفكرة حتى التسليم.',
  )
  const primaryLabel = resolveContent('final-cta', 'cta_primary_label', 'ابدأ مشروعك')
  const secondaryLabel = resolveContent('final-cta', 'cta_secondary_label', 'تواصل معنا')

  return (
    <section id="final-cta" className="relative overflow-hidden bg-brand-ink-900 py-24 text-white sm:py-28">
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_70%_20%,rgba(49,92,255,0.28),transparent_42%)]" />
      {!reduceMotion ? (
        <>
          <div className="hero-orb hero-orb-a pointer-events-none absolute -start-10 top-10 h-40 w-40 rounded-full bg-brand-cobalt-500/20 blur-3xl" />
          <div className="hero-orb hero-orb-b pointer-events-none absolute -end-8 bottom-8 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
        </>
      ) : null}
      <motion.div
        className="marketing-container relative z-10 max-w-3xl text-center"
        variants={motionOrReduced(reduceMotion, staggerContainer)}
        initial="hidden"
        whileInView="show"
        viewport={{ once: true, amount: 0.35 }}
      >
        <motion.p variants={motionOrReduced(reduceMotion, fadeUp)} className="brand-label text-brand-cobalt-300">
          {eyebrow}
        </motion.p>
        <motion.h2
          variants={motionOrReduced(reduceMotion, fadeUp)}
          className="mt-4 text-[clamp(2rem,1.4rem+2.2vw,3.5rem)] font-bold leading-tight"
        >
          {title}
        </motion.h2>
        <motion.p variants={motionOrReduced(reduceMotion, fadeUp)} className="mx-auto mt-5 max-w-xl leading-8 text-white/70">
          {body}
        </motion.p>
        <motion.div
          variants={motionOrReduced(reduceMotion, fadeUp)}
          className="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row"
        >
          <LandingCta
            href="#contact"
            onClick={(event) => {
              event.preventDefault()
              scrollToId('contact')
            }}
          >
            {primaryLabel}
          </LandingCta>
          <LandingCta
            href="#contact"
            variant="light"
            onClick={(event) => {
              event.preventDefault()
              scrollToId('contact')
            }}
          >
            {secondaryLabel}
          </LandingCta>
        </motion.div>
      </motion.div>
    </section>
  )
}
