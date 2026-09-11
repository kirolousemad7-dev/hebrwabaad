import { Link } from 'react-router-dom'
import { BrandCornerAccent } from '../brand/BrandCornerAccent'

const EXAMPLE_CHIPS = ['استراتيجية', 'تصميم', 'تصوير', 'ريلز', 'محتوى'] as const

export function BuildPackageSection() {
  return (
    <section id="build-package" className="relative overflow-hidden bg-brand-paper py-16 sm:py-20">
      <BrandCornerAccent position="bl" className="opacity-[0.08]" />
      <div className="relative mx-auto grid max-w-6xl items-center gap-10 px-4 sm:px-6 lg:grid-cols-[1.1fr_0.9fr]">
        <div className="space-y-5">
          <span className="brand-section-marker" aria-hidden="true" />
          <p className="brand-label font-latin uppercase tracking-[0.14em] text-brand-cobalt-700">BUILD YOUR PACKAGE</p>
          <h2 className="brand-heading-xl max-w-xl">صمّم باقتك بنفسك</h2>
          <p className="max-w-xl text-base leading-8 text-brand-ink-500">
            اختار الخدمات اللي تناسب مشروعك من عدة فئات، حدد الكميات والإضافات، وشوف ملخص طلبك في مكان واحد.
          </p>
          <ul className="flex flex-wrap gap-2" aria-hidden="true">
            {EXAMPLE_CHIPS.map((label) => (
              <li
                key={label}
                className="inline-flex items-center gap-1.5 rounded-xl border border-brand-cobalt-300/40 bg-white px-3 py-1.5 text-sm text-brand-ink-900"
              >
                <span className="text-brand-cobalt-500">✓</span>
                {label}
              </li>
            ))}
          </ul>
          <p className="text-sm font-medium text-brand-ink-900">صمم الحل المناسب لك</p>
          <Link to="/build-package" className="brand-btn-primary">
            ابدأ تصميم باقتك
          </Link>
        </div>
        <div className="brand-card rounded-[2rem] p-6 sm:p-8">
          <p className="text-sm text-brand-ink-500">مثال توضيحي — ليس اختياراً فعلياً</p>
          <ol className="mt-4 space-y-3 text-sm text-brand-ink-900">
            <li className="flex justify-between gap-3 rounded-xl bg-brand-cobalt-100/60 px-3 py-2">
              <span>الخدمات</span>
              <span className="font-semibold text-brand-primary">متعدد</span>
            </li>
            <li className="flex justify-between gap-3 rounded-xl bg-slate-50 px-3 py-2">
              <span>التفاصيل</span>
              <span>كميات + إضافات</span>
            </li>
            <li className="flex justify-between gap-3 rounded-xl bg-slate-50 px-3 py-2">
              <span>المراجعة</span>
              <span>ملخص واضح</span>
            </li>
          </ol>
        </div>
      </div>
    </section>
  )
}
