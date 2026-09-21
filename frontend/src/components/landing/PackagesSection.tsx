import { Link } from 'react-router-dom'
import { motion, useReducedMotion } from 'framer-motion'
import { BrandSectionAccent } from '../brand/BrandSectionAccent'
import { BrandVisual } from '../marketing/BrandVisual'
import { LandingCta } from './LandingCta'
import { usePublicMarketing } from '../../context/PublicMarketingContext'
import { marketingVisuals, type LandingPackageId } from '../../utils/marketingVisuals'
import { fadeUp, motionOrReduced, staggerContainer } from '../../utils/marketingMotion'

type PackagePreview = {
  key: LandingPackageId
  name: string
  body: string
  accent: string
  features: string[]
  href: string
  featured?: boolean
}

const PREVIEWS: PackagePreview[] = [
  {
    key: 'basic',
    name: 'الباقة الأساسية',
    body: 'انطلاقة واضحة للعلامات في بدايتها: هوية أولية، حضور رقمي، ومسار تنفيذ بسيط.',
    accent: 'أساسي',
    features: ['هوية أولية واضحة', 'حضور رقمي منظم', 'مسار تنفيذ مختصر'],
    href: '/packages',
  },
  {
    key: 'professional',
    name: 'الباقة الاحترافية',
    body: 'توازن بين الاستراتيجية والتنفيذ للعلامات التي تحتاج حضورًا أقوى وحملات منظمة.',
    accent: 'موصى بها',
    features: ['استراتيجية + تنفيذ', 'محتوى وحملات', 'متابعة وقياس'],
    href: '/packages',
    featured: true,
  },
  {
    key: 'integrated',
    name: 'الباقة المتكاملة',
    body: 'مسار شامل يجمع التقنية، الهوية، الإنتاج والتنفيذ تحت إدارة واحدة.',
    accent: 'متكامل',
    features: ['إدارة موحّدة', 'هوية + إنتاج', 'تنفيذ متعدد التخصصات'],
    href: '/packages',
  },
]

const PACKAGE_VISUAL_KEYS: Record<LandingPackageId, string> = {
  basic: 'visual_basic',
  professional: 'visual_professional',
  integrated: 'visual_integrated',
}

export function PackagesSection() {
  const reduceMotion = useReducedMotion()
  const { resolveContent, resolveVisual } = usePublicMarketing()
  const eyebrow = resolveContent('packages', 'eyebrow', 'PACKAGES')
  const title = resolveContent('packages', 'title', 'باقات تناسب مرحلة مشروعك')
  const description = resolveContent(
    'packages',
    'description',
    'معاينة لمفهوم الباقات. التفاصيل والأسعار داخل المنصة بعد تسجيل الدخول.',
  )

  return (
    <section id="packages" className="marketing-section scroll-mt-24 bg-brand-paper">
      <div className="marketing-container">
        <BrandSectionAccent
          className="mb-12 max-w-2xl"
          english={eyebrow}
          title={title}
          description={description}
        />

        <motion.ul
          className="grid items-stretch gap-6 lg:grid-cols-3"
          variants={motionOrReduced(reduceMotion, staggerContainer)}
          initial="hidden"
          whileInView="show"
          viewport={{ once: true, amount: 0.2 }}
        >
          {PREVIEWS.map((item) => {
            const visual = resolveVisual(
              'packages',
              PACKAGE_VISUAL_KEYS[item.key],
              marketingVisuals.packages[item.key],
            )

            return (
              <motion.li key={item.key} variants={motionOrReduced(reduceMotion, fadeUp)} className="h-full">
                <article
                  className={[
                    'group relative flex h-full flex-col overflow-hidden rounded-[1.75rem] border bg-white transition duration-300',
                    'hover:-translate-y-2 hover:shadow-[0_28px_60px_-36px_rgba(17,19,24,0.45)]',
                    'focus-within:-translate-y-2 motion-reduce:hover:translate-y-0',
                    item.featured
                      ? 'border-brand-ink-900 shadow-card lg:scale-[1.03] lg:z-10'
                      : 'border-brand-ink-100',
                  ].join(' ')}
                >
                  {item.featured ? (
                    <span className="absolute top-0 inset-inline-start-0 h-full w-1 bg-brand-cobalt-500" aria-hidden="true" />
                  ) : null}
                  <div className="relative aspect-[16/10] overflow-hidden bg-brand-ink-900">
                    <BrandVisual visual={visual} zoomable />
                    <span className="absolute top-4 inset-inline-start-4 rounded-full bg-white/95 px-3 py-1 text-xs font-medium text-brand-ink-700">
                      {item.accent}
                    </span>
                  </div>
                  <div className="flex flex-1 flex-col gap-4 p-6 sm:p-7">
                    <h3 className="text-xl font-semibold text-brand-ink-900 sm:text-2xl">{item.name}</h3>
                    <p className="text-sm leading-7 text-brand-ink-500">{item.body}</p>
                    <ul className="mt-1 space-y-2.5 text-sm text-brand-ink-500">
                      {item.features.map((feature) => (
                        <li key={feature} className="flex gap-2">
                          <span className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-ink-300" aria-hidden="true" />
                          <span>{feature}</span>
                        </li>
                      ))}
                    </ul>
                    <Link
                      to={item.href}
                      className={[
                        'mt-auto inline-flex min-h-11 items-center justify-center rounded-xl px-4 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500',
                        item.featured
                          ? 'bg-brand-cobalt-500 text-white hover:bg-brand-cobalt-700'
                          : 'bg-brand-ink-900 text-white hover:bg-brand-ink-700',
                      ].join(' ')}
                    >
                      عرض الباقات
                    </Link>
                  </div>
                </article>
              </motion.li>
            )
          })}
        </motion.ul>

        <div className="mt-12 flex flex-wrap gap-3">
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
