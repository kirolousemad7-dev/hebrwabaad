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
          <h2 className="text-3xl font-semibold text-brand-ink-900">منصة حبر وأبعاد لخدمات الأعمال والنمو</h2>
          <p className="leading-8 text-slate-600">
            حبر وأبعاد منصة سعودية متكاملة لخدمات الأعمال والنمو. نساعد الشركات والمنشآت ورواد الأعمال على تشخيص احتياجاتهم، بناء علاماتهم، تطوير حضورهم الرقمي وتنفيذ مشاريعهم من خلال شبكة من المتخصصين والموردين في التسويق، المحتوى، التصوير، المتاجر الإلكترونية، الطباعة، التغليف، المعارض، الحفلات والافتتاحات.
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
              نمنح أعمالك أبعادًا للنمو
            </p>
            <p className="text-sm leading-7 text-white/65">تشخيص النشاط ← تحديد الأولويات ← اختيار الخدمة أو الباقة ← استلام العرض ← التنفيذ وإدارة المشروع ← قياس النتائج والمتابعة.</p>
          </div>
        </motion.div>
      </div>
    </section>
  )
}
