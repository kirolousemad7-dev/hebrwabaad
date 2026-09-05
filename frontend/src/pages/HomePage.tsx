import { PublicCta } from '../components/public/PublicCta'
import { BRAND_TAGLINE } from '../utils/brand'

const DISCOVERY_PATHS = [
  {
    to: '/services',
    title: 'الخدمات',
    description: 'خدمات منفردة للتسويق، الإنتاج، والمتاجر مع الأسعار ومدة التنفيذ.',
    cta: 'تصفح الخدمات',
  },
  {
    to: '/packages',
    title: 'الباقات',
    description: 'باقات جاهزة تجمع أكثر من خدمة بسعر واحد يمكنك مقارنتها بسرعة.',
    cta: 'تصفح الباقات',
  },
  {
    to: '/marketing-packages',
    title: 'الباقات التسويقية',
    description: 'حلول متكاملة للنمو الرقمي: استراتيجية، محتوى، وحملات.',
    cta: 'استكشف باقات التسويق',
  },
  {
    to: '/event-packages',
    title: 'الباقات للفعاليات',
    description: 'تخطيط، إنتاج، وتنفيذ يحول مناسبتك إلى تجربة متكاملة.',
    cta: 'استكشف باقات الفعاليات',
  },
  {
    to: '/printing-packaging',
    title: 'الطباعة والتغليف',
    description: 'كروت، فلايرز، علب، أكياس، بوسترات، ومنتجات مخصصة.',
    cta: 'استكشف الطباعة والتغليف',
  },
] as const

export function HomePage() {
  return (
    <section className="space-y-8">
      <header className="overflow-hidden rounded-3xl border border-slate-200 bg-slate-900 px-5 py-8 text-white shadow-sm sm:px-8 sm:py-10">
        <p className="text-sm font-medium text-amber-300">{BRAND_TAGLINE}</p>
        <h1 className="mt-3 max-w-3xl text-3xl font-semibold leading-snug sm:text-4xl">حبر وأبعاد</h1>
        <p className="mt-3 max-w-2xl text-sm leading-7 text-white/75 sm:text-base">
          شريكك في البرمجة، التسويق، الطباعة، التغليف، وتنظيم الفعاليات. ابدأ بخدمة منفردة، قارن باقة جاهزة، أو صمّم باقة تناسب احتياجك.
        </p>
        <div className="mt-6 flex min-w-0 flex-col gap-3 lg:flex-row lg:flex-wrap">
          <PublicCta to="/consultant" variant="inverse">
            احصل على توصية مخصصة
          </PublicCta>
          <PublicCta to="/build-package" variant="secondary">
            صمّم باقتك
          </PublicCta>
          <PublicCta to="/services" variant="secondary">
            تصفح الخدمات
          </PublicCta>
        </div>
      </header>

      <div className="grid gap-4 lg:grid-cols-2">
        <article className="flex min-w-0 flex-col gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
          <div className="space-y-1">
            <h2 className="text-lg font-semibold">مستشار حبر الذكي</h2>
            <p className="text-sm leading-7 text-slate-700">
              نفهم نشاطك وأهدافك ثم نرشّح الخدمة أو الباقة المناسبة من المنصة — ليس دردشة عامة.
            </p>
          </div>
          <PublicCta to="/consultant">ابدأ الاستشارة</PublicCta>
        </article>

        <article className="flex min-w-0 flex-col gap-3 rounded-2xl border border-slate-900 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
          <div className="space-y-1">
            <h2 className="text-lg font-semibold">صمّم باقتك بنفسك</h2>
            <p className="text-sm leading-7 text-slate-600">اختار الخدمات والكميات وشاهد السعر التقديري ومدة التنفيذ مباشرة.</p>
          </div>
          <PublicCta to="/build-package">ابدأ التصميم</PublicCta>
        </article>
      </div>

      <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {DISCOVERY_PATHS.map((item) => (
          <li key={item.to} className="flex min-w-0 flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-lg font-semibold">{item.title}</h2>
            <p className="flex-1 text-sm leading-7 text-slate-600">{item.description}</p>
            <PublicCta to={item.to} variant="secondary">
              {item.cta}
            </PublicCta>
          </li>
        ))}
      </ul>
    </section>
  )
}
