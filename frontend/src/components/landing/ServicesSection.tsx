import { Link } from 'react-router-dom'
import { motion, useReducedMotion } from 'framer-motion'
import { BrandSectionAccent } from '../brand/BrandSectionAccent'

const SERVICES = [
  {
    title: 'البرمجة وتطوير المواقع',
    body: 'تصميم وتطوير مواقع ومنصات رقمية تجمع بين الأداء، السرعة وتجربة المستخدم.',
  },
  {
    title: 'التسويق الرقمي',
    body: 'استراتيجيات ومحتوى وحملات إعلانية تساعد علامتك على الوصول للجمهور المناسب.',
  },
  {
    title: 'التصميم والهوية البصرية',
    body: 'بناء هوية بصرية متكاملة تعكس شخصية علامتك وتثبت حضورها في السوق.',
  },
  {
    title: 'الطباعة',
    body: 'حلول طباعة احترافية لمختلف احتياجات الشركات والعلامات التجارية.',
  },
  {
    title: 'التغليف والباكدجنج',
    body: 'تصميم وتنفيذ حلول تغليف تجمع بين الشكل العملي والهوية المميزة.',
  },
  {
    title: 'تنظيم الفعاليات',
    body: 'تخطيط وتنفيذ الفعاليات بداية من الفكرة وحتى اليوم النهائي للحدث.',
  },
]

export function ServicesSection() {
  const reduceMotion = useReducedMotion()

  return (
    <section id="services" className="scroll-mt-24 bg-brand-paper py-16 sm:py-20">
      <div className="mx-auto max-w-6xl px-4 sm:px-6">
        <BrandSectionAccent
          className="mb-10"
          english="SERVICES"
          title="كل ما تحتاجه علامتك في مكان واحد"
          description="حبر وأبعاد تجمع خدمات متعددة تحت مسار واحد: من البناء الرقمي والهوية حتى الإنتاج المادي وتنظيم الفعاليات."
        />
        <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {SERVICES.map((service, index) => (
            <motion.li
              key={service.title}
              initial={reduceMotion ? false : { opacity: 0, y: 16 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true, amount: 0.3 }}
              transition={{ delay: reduceMotion ? 0 : index * 0.05 }}
              className="brand-card group flex min-h-48 flex-col gap-3 rounded-3xl p-6 transition duration-300 hover:-translate-y-1"
            >
              <span className="brand-section-marker transition group-hover:w-14" aria-hidden="true" />
              <h3 className="text-lg font-semibold text-brand-ink-900">{service.title}</h3>
              <p className="flex-1 text-sm leading-7 text-brand-ink-500">{service.body}</p>
            </motion.li>
          ))}
        </ul>
        <div className="mt-8">
          <Link to="/build-package" className="brand-btn-primary">
            صمّم باقتك من عدة خدمات
          </Link>
        </div>
      </div>
    </section>
  )
}
