import { motion, useReducedMotion } from 'framer-motion'
import { BrandLogo } from '../brand/BrandLogo'

export function AboutSection() {
  const reduceMotion = useReducedMotion()

  return (
    <section id="about" className="scroll-mt-24 bg-brand-paper py-16 sm:py-20">
      <div className="mx-auto grid max-w-6xl items-center gap-10 px-4 sm:px-6 lg:grid-cols-2">
        <motion.div
          initial={reduceMotion ? false : { opacity: 0, y: 16 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          className="space-y-4"
        >
          <p className="text-sm font-medium text-brand-primary">من نحن</p>
          <h2 className="text-3xl font-semibold text-brand-ink-900">نحوّل الأفكار إلى تجارب حقيقية</h2>
          <p className="leading-8 text-slate-600">
            في حبر وأبعاد نؤمن أن نجاح المشروع لا يبدأ من خدمة منفصلة، بل من رؤية متكاملة. لذلك نجمع التقنية، الإبداع
            والإنتاج في منظومة واحدة تساعد العلامات التجارية على الانتقال من الفكرة إلى التنفيذ بثقة ووضوح.
          </p>
        </motion.div>
        <motion.div
          initial={reduceMotion ? false : { opacity: 0, scale: 0.98 }}
          whileInView={{ opacity: 1, scale: 1 }}
          viewport={{ once: true }}
          className="relative overflow-hidden rounded-[2rem] border border-slate-200 bg-brand-ink-900 p-10 text-white"
        >
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_80%_20%,rgba(201,162,39,0.25),transparent_40%),radial-gradient(circle_at_10%_90%,rgba(42,154,163,0.2),transparent_45%)]" />
          <div className="relative space-y-6">
            <BrandLogo size="mark" to="/" className="brightness-110" />
            <p className="max-w-sm text-lg font-medium leading-8 text-white/90">
              برمجة • تسويق • هوية • طباعة • تغليف • فعاليات
            </p>
            <p className="text-sm leading-7 text-white/65">من الفكرة حتى التسليم — تحت إدارة واحدة.</p>
          </div>
        </motion.div>
      </div>
    </section>
  )
}
