import { BrandSectionAccent } from '../brand/BrandSectionAccent'
import { InteractivePackageCard } from '../marketing/InteractivePackageCard'
import { LandingCta } from './LandingCta'
import { marketingVisuals } from '../../utils/marketingVisuals'

const PREVIEWS = [
  {
    key: 'basic' as const,
    name: 'الباقة الأساسية',
    body: 'انطلاقة واضحة للعلامات في بدايتها: هوية أولية، حضور رقمي، ومسار تنفيذ بسيط.',
    accent: 'أساسي',
    features: ['هوية أولية واضحة', 'حضور رقمي منظم', 'مسار تنفيذ مختصر'],
    href: '/packages',
  },
  {
    key: 'professional' as const,
    name: 'الباقة الاحترافية',
    body: 'توازن بين الاستراتيجية والتنفيذ للعلامات التي تحتاج حضورًا أقوى وحملات منظمة.',
    accent: 'موصى بها',
    features: ['استراتيجية + تنفيذ', 'محتوى وحملات', 'متابعة وقياس'],
    href: '/packages',
    featured: true,
  },
  {
    key: 'integrated' as const,
    name: 'الباقة المتكاملة',
    body: 'مسار شامل يجمع التقنية، الهوية، الإنتاج والتنفيذ تحت إدارة واحدة.',
    accent: 'متكامل',
    features: ['إدارة موحّدة', 'هوية + إنتاج', 'تنفيذ متعدد التخصصات'],
    href: '/packages',
  },
]

export function PackagesSection() {
  return (
    <section id="packages" className="scroll-mt-24 bg-brand-paper py-16 sm:py-20">
      <div className="mx-auto max-w-6xl px-4 sm:px-6">
        <BrandSectionAccent
          className="mb-10"
          english="PACKAGES"
          title="باقات تناسب مرحلة مشروعك"
          description="معاينة لمفهوم الباقات. تفاصيل الأسعار والطلبات داخل المنصة بعد تسجيل الدخول."
        />
        <ul className="grid gap-5 lg:grid-cols-3">
          {PREVIEWS.map((item, index) => (
            <li key={item.key}>
              <InteractivePackageCard
                index={index}
                name={item.name}
                body={item.body}
                accent={item.accent}
                features={item.features}
                href={item.href}
                visual={marketingVisuals.packages[item.key]}
                featured={item.featured}
              />
            </li>
          ))}
        </ul>
        <div className="mt-10 flex flex-wrap gap-3">
          <LandingCta to="/packages" variant="primary">
            استكشف الباقات
          </LandingCta>
          <LandingCta to="/marketing-packages" variant="secondary">
            باقات التسويق
          </LandingCta>
          <LandingCta to="/build-package" variant="ghost">
            صمم باقتك
          </LandingCta>
        </div>
      </div>
    </section>
  )
}
