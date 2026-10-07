import { usePublicMarketing } from '../../context/PublicMarketingContext'
import { ServiceGlyph, type ServiceIconKey } from '../ui/ServiceGlyph'

const STEPS: Array<{ n: string; titleKey: string; bodyKey: string; title: string; body: string; icon: ServiceIconKey }> = [
  { n: '01', titleKey: 'step_1_title', bodyKey: 'step_1_description', title: 'تشخيص النشاط', body: 'نفهم وضعك الحالي.', icon: 'design' },
  { n: '02', titleKey: 'step_2_title', bodyKey: 'step_2_description', title: 'تحديد الأولويات', body: 'نرتب ما يُنفَّذ أولًا.', icon: 'speed' },
  { n: '03', titleKey: 'step_3_title', bodyKey: 'step_3_description', title: 'اختيار المسار', body: 'خدمة أو باقة مناسبة.', icon: 'print' },
  { n: '04', titleKey: 'step_4_title', bodyKey: 'step_4_description', title: 'العرض والتنفيذ', body: 'عرض واضح ثم بدء العمل.', icon: 'delivery' },
  { n: '05', titleKey: 'step_5_title', bodyKey: 'step_5_description', title: 'التسليم والمتابعة', body: 'تسليم وقياس مختصر.', icon: 'warranty' },
]

export function ProcessSection() {
  const { resolveContent } = usePublicMarketing()
  const title = resolveContent('process', 'title', 'رحلة العميل')

  return (
    <section id="process" className="marketing-section scroll-mt-24 bg-white">
      <div className="marketing-container">
        <h2 className="mb-4 text-2xl font-bold text-brand-ink-900 sm:text-3xl">{title}</h2>
        <ol className="flex snap-x gap-3 overflow-x-auto rounded-3xl border border-brand-ink-100 bg-brand-paper p-3 lg:grid lg:grid-cols-5 lg:overflow-visible">
          {STEPS.map((step) => (
            <li key={step.n} className="min-w-[12.5rem] snap-start rounded-2xl bg-white p-3 lg:min-w-0">
              <div className="flex items-center justify-between gap-2 text-brand-cobalt-700">
                <span className="text-sm font-semibold" dir="ltr">{step.n}</span>
                <ServiceGlyph name={step.icon} />
              </div>
              <h3 className="mt-2 text-sm font-semibold text-brand-ink-900">
                {resolveContent('process', step.titleKey, step.title)}
              </h3>
              <p className="mt-1 text-xs leading-5 text-brand-ink-500">
                {resolveContent('process', step.bodyKey, step.body)}
              </p>
            </li>
          ))}
        </ol>
      </div>
    </section>
  )
}
