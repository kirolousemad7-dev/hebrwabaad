import { Link } from 'react-router-dom'
import { motion, useReducedMotion } from 'framer-motion'
import { BrandCornerAccent } from '../brand/BrandCornerAccent'
import { usePublicMarketing } from '../../context/PublicMarketingContext'
import { fadeUp, motionOrReduced, slideLeft, slideRight, staggerContainer } from '../../utils/marketingMotion'

const EXAMPLE_CHIPS = ['استراتيجية', 'تصميم', 'تصوير', 'ريلز', 'محتوى'] as const

export function BuildPackageSection() {
  const reduceMotion = useReducedMotion()
  const { resolveContent } = usePublicMarketing()
  const eyebrow = resolveContent('build-package', 'eyebrow', 'صمّم باقتك')
  const title = resolveContent('build-package', 'title', 'اختر ما تحتاجه — وابنِ الحل المناسب لك')
  const description = resolveContent(
    'build-package',
    'description',
    'اختار الخدمات اللي تناسب مشروعك من عدة فئات، حدد الكميات والإضافات، وشوف ملخص طلبك في مكان واحد.',
  )

  return (
    <section id="build-package" className="relative overflow-hidden bg-brand-ink-900 marketing-section text-white">
      <BrandCornerAccent position="bl" className="opacity-20 max-md:hidden" />
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_85%_15%,rgba(49,92,255,0.22),transparent_40%)]" />
      <div className="marketing-container relative grid items-center gap-12 lg:grid-cols-[1.1fr_0.9fr] lg:gap-16">
        <motion.div
          className="space-y-6"
          variants={motionOrReduced(reduceMotion, staggerContainer)}
          initial="hidden"
          whileInView="show"
          viewport={{ once: true, amount: 0.25 }}
        >
          <motion.p variants={motionOrReduced(reduceMotion, fadeUp)} className="brand-label text-brand-cobalt-300">
            {eyebrow}
          </motion.p>
          <motion.h2
            variants={motionOrReduced(reduceMotion, slideRight)}
            className="max-w-xl text-[clamp(1.85rem,1.3rem+1.8vw,3rem)] font-bold leading-tight"
          >
            {title}
          </motion.h2>
          <motion.p variants={motionOrReduced(reduceMotion, fadeUp)} className="max-w-xl text-base leading-8 text-white/70">
            {description}
          </motion.p>
          <motion.ul variants={motionOrReduced(reduceMotion, fadeUp)} className="flex flex-wrap gap-2" aria-hidden="true">
            {EXAMPLE_CHIPS.map((label) => (
              <li
                key={label}
                className="inline-flex items-center rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-sm text-white/80"
              >
                {label}
              </li>
            ))}
          </motion.ul>
          <motion.div variants={motionOrReduced(reduceMotion, fadeUp)}>
            <Link to="/build-package" className="brand-btn-primary">
              ابدأ تصميم باقتك
            </Link>
          </motion.div>
        </motion.div>

        <motion.div
          variants={motionOrReduced(reduceMotion, slideLeft)}
          initial="hidden"
          whileInView="show"
          viewport={{ once: true, amount: 0.3 }}
          className="rounded-[1.75rem] border border-white/10 bg-white/5 p-6 backdrop-blur-sm sm:p-8"
        >
          <p className="text-sm text-white/55">مثال توضيحي — ليس اختياراً فعلياً</p>
          <ol className="mt-5 space-y-3 text-sm">
            <li className="flex justify-between gap-3 rounded-xl bg-brand-cobalt-500/20 px-4 py-3">
              <span>الخدمات</span>
              <span className="font-semibold text-brand-cobalt-300">متعدد</span>
            </li>
            <li className="flex justify-between gap-3 rounded-xl bg-white/5 px-4 py-3 text-white/85">
              <span>التفاصيل</span>
              <span>كميات + إضافات</span>
            </li>
            <li className="flex justify-between gap-3 rounded-xl bg-white/5 px-4 py-3 text-white/85">
              <span>المراجعة</span>
              <span>ملخص واضح</span>
            </li>
          </ol>
        </motion.div>
      </div>
    </section>
  )
}
