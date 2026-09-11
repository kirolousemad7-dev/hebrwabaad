import { motion, useReducedMotion } from 'framer-motion'

const VALUES = [
  {
    title: 'حلول متكاملة',
    body: 'كل خدمات مشروعك تحت إدارة واحدة.',
  },
  {
    title: 'فريق متخصص',
    body: 'كل جزء من المشروع يتم تنفيذه بواسطة المتخصص المناسب.',
  },
  {
    title: 'تنفيذ منظم',
    body: 'مراحل واضحة ومتابعة مستمرة من البداية حتى التسليم.',
  },
  {
    title: 'جودة تصنع الفرق',
    body: 'نهتم بالتفاصيل لأن قوة العلامة تبدأ من جودة التنفيذ.',
  },
]

export function WhyUsSection() {
  const reduceMotion = useReducedMotion()

  return (
    <section id="why-us" className="scroll-mt-24 bg-white py-16 sm:py-20">
      <div className="mx-auto max-w-6xl px-4 sm:px-6">
        <header className="mb-10 max-w-2xl space-y-3">
          <p className="text-sm font-medium text-brand-primary">لماذا نحن</p>
          <h2 className="text-3xl font-semibold text-brand-ink-900">لماذا حبر وأبعاد؟</h2>
        </header>
        <ul className="grid gap-4 md:grid-cols-2">
          {VALUES.map((item, index) => (
            <motion.li
              key={item.title}
              initial={reduceMotion ? false : { opacity: 0, y: 12 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ delay: reduceMotion ? 0 : index * 0.05 }}
              className="rounded-3xl border border-slate-200 bg-brand-paper p-6 transition hover:border-brand-primary/30"
            >
              <h3 className="text-lg font-semibold text-brand-ink-900">{item.title}</h3>
              <p className="mt-2 leading-8 text-slate-600">{item.body}</p>
            </motion.li>
          ))}
        </ul>
      </div>
    </section>
  )
}
