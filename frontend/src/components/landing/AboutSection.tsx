import { Link } from 'react-router-dom'
import { motion, useReducedMotion } from 'framer-motion'
import { BrandLogo } from '../brand/BrandLogo'
import { BrandVisual } from '../marketing/BrandVisual'
import { marketingVisuals } from '../../utils/marketingVisuals'
import { fadeUp, imageReveal, motionOrReduced, staggerContainer } from '../../utils/marketingMotion'

export function AboutSection() {
  const reduceMotion = useReducedMotion()

  return (
    <section id="about" className="marketing-section scroll-mt-24 bg-white">
      <div className="marketing-container grid items-center gap-12 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)] lg:gap-16">
        <motion.div
          className="space-y-6"
          variants={motionOrReduced(reduceMotion, staggerContainer)}
          initial="hidden"
          whileInView="show"
          viewport={{ once: true, amount: 0.25 }}
        >
          <motion.p variants={motionOrReduced(reduceMotion, fadeUp)} className="brand-label text-brand-ink-500">
            من نحن
          </motion.p>
          <motion.blockquote
            variants={motionOrReduced(reduceMotion, fadeUp)}
            className="max-w-[18ch] text-[clamp(1.85rem,1.2rem+2vw,3rem)] font-bold leading-[1.25] text-brand-ink-900"
          >
            نحوّل احتياجات الأعمال إلى حلول واضحة قابلة للتنفيذ.
          </motion.blockquote>
          <motion.p variants={motionOrReduced(reduceMotion, fadeUp)} className="max-w-xl text-base leading-9 text-brand-ink-500">
            حبر وأبعاد منصة سعودية متكاملة لخدمات الأعمال والنمو. نساعد الشركات والمنشآت ورواد الأعمال على تشخيص
            احتياجاتهم، بناء علاماتهم، وتطوير حضورهم من خلال شبكة متخصصين وموردين.
          </motion.p>
          <motion.div variants={motionOrReduced(reduceMotion, fadeUp)}>
            <Link to="/about" className="brand-btn-secondary">
              اقرأ القصة كاملة
            </Link>
          </motion.div>
        </motion.div>

        <motion.div
          className="relative"
          variants={motionOrReduced(reduceMotion, imageReveal)}
          initial="hidden"
          whileInView="show"
          viewport={{ once: true, amount: 0.3 }}
        >
          <div className="group relative overflow-hidden rounded-[1.75rem] border border-brand-ink-100 shadow-card">
            <div className="aspect-[4/5] sm:aspect-[5/6]">
              <BrandVisual visual={marketingVisuals.about} zoomable />
            </div>
            <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-brand-ink-900 via-brand-ink-900/50 to-transparent p-7 text-white">
              <BrandLogo size="mark" to="/" className="brightness-110" />
              <p className="mt-4 max-w-xs text-lg font-medium leading-8 text-white/95">نمنح أعمالك أبعادًا للنمو</p>
              <p className="mt-2 text-sm leading-7 text-white/65">
                تشخيص ← أولويات ← خدمة أو باقة ← عرض ← تنفيذ ← متابعة
              </p>
            </div>
            <div aria-hidden="true" className="absolute top-0 inset-inline-end-0 h-full w-1.5 bg-brand-cobalt-500" />
          </div>
        </motion.div>
      </div>
    </section>
  )
}
