import { motion, useReducedMotion } from 'framer-motion'

const STEPS = [
  { n: '01', title: 'نفهم احتياجك' },
  { n: '02', title: 'نضع الخطة' },
  { n: '03', title: 'نبدأ التنفيذ' },
  { n: '04', title: 'نراجع التفاصيل' },
  { n: '05', title: 'نسلم المشروع' },
]

export function ProcessSection() {
  const reduceMotion = useReducedMotion()

  return (
    <section id="process" className="scroll-mt-24 bg-white py-16 sm:py-20">
      <div className="mx-auto max-w-6xl px-4 sm:px-6">
        <header className="mb-10 max-w-2xl space-y-3">
          <p className="text-sm font-medium text-brand-primary">مسار العمل</p>
          <h2 className="text-3xl font-semibold text-brand-ink-900">من الفكرة حتى التسليم</h2>
        </header>
        <ol className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
          {STEPS.map((step, index) => (
            <motion.li
              key={step.n}
              initial={reduceMotion ? false : { opacity: 0, y: 18 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true, amount: 0.4 }}
              transition={{ delay: reduceMotion ? 0 : index * 0.06 }}
              className="relative rounded-3xl border border-slate-200 bg-brand-paper p-5"
            >
              <p className="text-2xl font-semibold text-brand-primary">{step.n}</p>
              <h3 className="mt-3 text-base font-semibold text-brand-ink-900">{step.title}</h3>
            </motion.li>
          ))}
        </ol>
      </div>
    </section>
  )
}
