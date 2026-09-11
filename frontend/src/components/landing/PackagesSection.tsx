import { motion, useReducedMotion } from 'framer-motion'
import { LandingCta } from './LandingCta'

const PREVIEWS = [
  {
    name: 'الباقة الأساسية',
    body: 'انطلاقة واضحة للعلامات في بدايتها: هوية أولية، حضور رقمي، ومسار تنفيذ بسيط.',
    accent: 'أساسي',
  },
  {
    name: 'الباقة الاحترافية',
    body: 'توازن بين الاستراتيجية والتنفيذ للعلامات التي تحتاج حضوراً أقوى وحملات منظمة.',
    accent: 'موصى بها',
  },
  {
    name: 'الباقة المتكاملة',
    body: 'مسار شامل يجمع التقنية، الهوية، الإنتاج والتنفيذ تحت إدارة واحدة.',
    accent: 'متكامل',
  },
]

export function PackagesSection() {
  const reduceMotion = useReducedMotion()

  return (
    <section id="packages" className="scroll-mt-24 bg-brand-paper py-16 sm:py-20">
      <div className="mx-auto max-w-6xl px-4 sm:px-6">
        <header className="mb-10 max-w-2xl space-y-3">
          <p className="text-sm font-medium text-brand-primary">باقاتنا</p>
          <h2 className="text-3xl font-semibold text-brand-ink-900">باقات تناسب مرحلة مشروعك</h2>
          <p className="leading-8 text-slate-600">
            معاينة تسويقية لمفهوم الباقات. إدارة الباقات والطلبات تتم داخل المنصة بعد تسجيل الدخول.
          </p>
        </header>
        <ul className="grid gap-4 lg:grid-cols-3">
          {PREVIEWS.map((item, index) => (
            <motion.li
              key={item.name}
              initial={reduceMotion ? false : { opacity: 0, y: 12 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ delay: reduceMotion ? 0 : index * 0.05 }}
              className={`flex flex-col gap-4 rounded-3xl border p-6 shadow-sm ${
                index === 1
                  ? 'border-brand-primary bg-brand-ink-900 text-white'
                  : 'border-slate-200 bg-white text-slate-900'
              }`}
            >
              <span
                className={`w-fit rounded-full px-3 py-1 text-xs font-medium ${
                  index === 1 ? 'bg-brand-primary/20 text-brand-primary' : 'bg-brand-primary-soft text-brand-primary'
                }`}
              >
                {item.accent}
              </span>
              <h3 className="text-xl font-semibold">{item.name}</h3>
              <p className={`flex-1 text-sm leading-7 ${index === 1 ? 'text-white/75' : 'text-slate-600'}`}>
                {item.body}
              </p>
            </motion.li>
          ))}
        </ul>
        <div className="mt-8 flex flex-wrap gap-3">
          <LandingCta to="/packages" variant="primary">
            استكشف الباقات
          </LandingCta>
          <LandingCta to="/build-package" variant="secondary">
            صمم باقتك
          </LandingCta>
        </div>
      </div>
    </section>
  )
}
