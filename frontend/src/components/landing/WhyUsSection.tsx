import { motion, useReducedMotion } from 'framer-motion'
import { usePublicMarketing } from '../../context/PublicMarketingContext'
import { fadeUp, motionOrReduced, staggerContainer } from '../../utils/marketingMotion'

const FALLBACK_VALUES = [
  {
    titleKey: 'item_1_title',
    bodyKey: 'item_1_description',
    title: 'حلول متكاملة',
    body: 'كل خدمات مشروعك تحت إدارة واحدة.',
  },
  {
    titleKey: 'item_2_title',
    bodyKey: 'item_2_description',
    title: 'فريق متخصص',
    body: 'كل جزء من المشروع يتم تنفيذه بواسطة المتخصص المناسب.',
  },
  {
    titleKey: 'item_3_title',
    bodyKey: 'item_3_description',
    title: 'تنفيذ منظم',
    body: 'مراحل واضحة ومتابعة مستمرة من البداية حتى التسليم.',
  },
  {
    titleKey: 'item_4_title',
    bodyKey: 'item_4_description',
    title: 'جودة تصنع الفرق',
    body: 'نهتم بالتفاصيل لأن قوة العلامة تبدأ من جودة التنفيذ.',
  },
] as const

export function WhyUsSection() {
  const reduceMotion = useReducedMotion()
  const { resolveContent } = usePublicMarketing()

  const eyebrow = resolveContent('why-us', 'eyebrow', 'لماذا نحن')
  const title = resolveContent('why-us', 'title', 'لماذا حبر وأبعاد؟')
  const values = FALLBACK_VALUES.map((item) => ({
    title: resolveContent('why-us', item.titleKey, item.title),
    body: resolveContent('why-us', item.bodyKey, item.body),
  }))

  return (
    <section id="why-us" className="marketing-section scroll-mt-24 bg-brand-paper">
      <div className="marketing-container">
        <header className="mb-4 max-w-2xl space-y-2">
          <p className="brand-label text-brand-ink-500">{eyebrow}</p>
          <h2 className="text-2xl font-bold leading-tight text-brand-ink-900 sm:text-3xl">{title}</h2>
        </header>
        <motion.ul
          className="grid gap-3 md:grid-cols-2"
          variants={motionOrReduced(reduceMotion, staggerContainer)}
          initial="hidden"
          whileInView="show"
          viewport={{ once: true, amount: 0.2 }}
        >
          {values.map((item, index) => (
            <motion.li
              key={`why-us-${index}`}
              variants={motionOrReduced(reduceMotion, fadeUp)}
              className="relative overflow-hidden rounded-2xl border border-brand-ink-100 bg-white p-4"
            >
              <span className="mb-1 block text-xs font-semibold tracking-wider text-brand-cobalt-700" dir="ltr">
                {String(index + 1).padStart(2, '0')}
              </span>
              <h3 className="text-base font-semibold text-brand-ink-900">{item.title}</h3>
              <p className="mt-1 text-sm leading-6 text-brand-ink-500">{item.body}</p>
            </motion.li>
          ))}
        </motion.ul>
      </div>
    </section>
  )
}
