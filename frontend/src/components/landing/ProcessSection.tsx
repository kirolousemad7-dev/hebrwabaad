import { motion, useReducedMotion } from 'framer-motion'
import { usePublicMarketing } from '../../context/PublicMarketingContext'
import { fadeUp, motionOrReduced, staggerContainer } from '../../utils/marketingMotion'

const FALLBACK_STEPS = [
  { n: '01', contentKey: 'step_1_title', title: 'تشخيص النشاط' },
  { n: '02', contentKey: 'step_2_title', title: 'تحديد الأولويات' },
  { n: '03', contentKey: 'step_3_title', title: 'اختيار الخدمة أو الباقة' },
  { n: '04', contentKey: 'step_4_title', title: 'استلام العرض' },
  { n: '05', contentKey: 'step_5_title', title: 'التنفيذ وإدارة المشروع' },
  { n: '06', contentKey: 'step_6_title', title: 'قياس النتائج والمتابعة' },
] as const

export function ProcessSection() {
  const reduceMotion = useReducedMotion()
  const { resolveContent } = usePublicMarketing()

  const eyebrow = resolveContent('process', 'eyebrow', 'مسار العمل')
  const title = resolveContent('process', 'title', 'رحلة العميل')
  const steps = FALLBACK_STEPS.map((step) => ({
    n: step.n,
    title: resolveContent('process', step.contentKey, step.title),
  }))

  return (
    <section id="process" className="marketing-section scroll-mt-24 bg-white">
      <div className="marketing-container">
        <header className="mb-12 max-w-2xl space-y-3">
          <p className="brand-label text-brand-ink-500">{eyebrow}</p>
          <h2 className="text-[clamp(1.75rem,1.3rem+1.4vw,2.75rem)] font-bold leading-tight text-brand-ink-900">
            {title}
          </h2>
        </header>
        <motion.ol
          className="relative grid gap-0 sm:grid-cols-2 lg:grid-cols-3"
          variants={motionOrReduced(reduceMotion, staggerContainer)}
          initial="hidden"
          whileInView="show"
          viewport={{ once: true, amount: 0.2 }}
        >
          {steps.map((step) => (
            <motion.li
              key={step.n}
              variants={motionOrReduced(reduceMotion, fadeUp)}
              className="relative border-b border-brand-ink-100 px-1 py-7 sm:border-e sm:px-6 sm:odd:ps-0 lg:[&:nth-child(3n)]:border-e-0"
            >
              <p className="text-sm font-semibold tracking-wider text-brand-cobalt-700">{step.n}</p>
              <h3 className="mt-3 text-lg font-semibold text-brand-ink-900">{step.title}</h3>
            </motion.li>
          ))}
        </motion.ol>
      </div>
    </section>
  )
}
