import { Link } from 'react-router-dom'
import { BrandSectionAccent } from '../brand/BrandSectionAccent'
import { InteractiveServiceCard } from '../marketing/InteractiveServiceCard'
import { marketingVisuals } from '../../utils/marketingVisuals'
import { CATALOG_SECTIONS } from '../../utils/catalogRoutes'

const SERVICES = [
  {
    key: 'strategy' as const,
    title: CATALOG_SECTIONS['business-diagnosis-strategy'].title,
    body: CATALOG_SECTIONS['business-diagnosis-strategy'].description,
    href: CATALOG_SECTIONS['business-diagnosis-strategy'].path,
    features: ['تحليل النشاط والسوق', 'تحديد الأولويات', 'خطة قابلة للقياس'],
  },
  {
    key: 'branding' as const,
    title: CATALOG_SECTIONS['branding-design'].title,
    body: CATALOG_SECTIONS['branding-design'].description,
    href: CATALOG_SECTIONS['branding-design'].path,
    features: ['شعار وهوية', 'دليل استخدام', 'تطبيقات بصرية'],
  },
  {
    key: 'digital' as const,
    title: CATALOG_SECTIONS['content-writing'].title,
    body: CATALOG_SECTIONS['content-writing'].description,
    href: CATALOG_SECTIONS['content-writing'].path,
    features: ['محتوى مقنع', 'نصوص حملات', 'رسائل العلامة'],
  },
  {
    key: 'ecommerce' as const,
    title: CATALOG_SECTIONS['ecommerce-digital-experience'].title,
    body: CATALOG_SECTIONS['ecommerce-digital-experience'].description,
    href: CATALOG_SECTIONS['ecommerce-digital-experience'].path,
    features: ['متاجر ومواقع', 'تجربة شراء', 'صفحات هبوط'],
  },
  {
    key: 'printing' as const,
    title: 'الطباعة والتغليف',
    body: 'حلول طباعة وتغليف احترافية للمنتجات، الهوية المكتبية، والمواد الدعائية بتشطيبات عالية الجودة.',
    href: '/printing-packaging',
    features: ['مطبوعات فاخرة', 'تغليف وباكدجنج', 'مواد دعائية'],
  },
  {
    key: 'events' as const,
    title: CATALOG_SECTIONS['events-management'].title,
    body: CATALOG_SECTIONS['events-management'].description,
    href: CATALOG_SECTIONS['events-management'].path,
    features: ['افتتاحات ومعارض', 'هوية الفعالية', 'تشغيل وتغطية'],
  },
]

export function ServicesSection() {
  return (
    <section id="services" className="scroll-mt-24 bg-brand-paper py-16 sm:py-20">
      <div className="mx-auto max-w-6xl px-4 sm:px-6">
        <BrandSectionAccent
          className="mb-10"
          english="SERVICES"
          title="كل ما تحتاجه علامتك في مكان واحد"
          description="حبر وأبعاد تجمع خدمات متعددة تحت مسار واحد: من البناء الرقمي والهوية حتى الإنتاج المادي وتنظيم الفعاليات."
        />
        <ul className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
          {SERVICES.map((service, index) => (
            <li key={service.key}>
              <InteractiveServiceCard
                index={index}
                title={service.title}
                body={service.body}
                href={service.href}
                visual={marketingVisuals.services[service.key]}
                features={service.features}
              />
            </li>
          ))}
        </ul>
        <div className="mt-10 flex flex-wrap gap-3">
          <Link to="/services" className="brand-btn-secondary">
            كل الخدمات
          </Link>
          <Link to="/build-package" className="brand-btn-primary">
            صمّم باقتك من عدة خدمات
          </Link>
        </div>
      </div>
    </section>
  )
}
