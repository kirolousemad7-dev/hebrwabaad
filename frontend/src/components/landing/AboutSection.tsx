import { Link } from 'react-router-dom'
import { BrandLogo } from '../brand/BrandLogo'
import { AnimatedSection } from '../marketing/AnimatedSection'
import { BrandVisual } from '../marketing/BrandVisual'
import { marketingVisuals } from '../../utils/marketingVisuals'

export function AboutSection() {
  return (
    <section id="about" className="scroll-mt-24 bg-brand-paper py-16 sm:py-20">
      <div className="mx-auto grid max-w-6xl items-center gap-10 px-4 sm:px-6 lg:grid-cols-2">
        <AnimatedSection className="space-y-4">
          <p className="brand-label text-brand-cobalt-700">من نحن</p>
          <h2 className="brand-heading-lg">منصة حبر وأبعاد لخدمات الأعمال والنمو</h2>
          <p className="leading-8 text-brand-ink-500">
            حبر وأبعاد منصة سعودية متكاملة لخدمات الأعمال والنمو. نساعد الشركات والمنشآت ورواد الأعمال على تشخيص
            احتياجاتهم، بناء علاماتهم، تطوير حضورهم الرقمي وتنفيذ مشاريعهم من خلال شبكة من المتخصصين والموردين.
          </p>
          <Link to="/about" className="brand-btn-secondary">
            اقرأ المزيد
          </Link>
        </AnimatedSection>
        <AnimatedSection delay={0.08} className="relative overflow-hidden rounded-[2rem] border border-brand-ink-100">
          <div className="aspect-[4/3]">
            <BrandVisual visual={marketingVisuals.about} />
          </div>
          <div className="absolute inset-0 bg-gradient-to-t from-brand-ink-900 via-brand-ink-900/40 to-transparent" />
          <div className="absolute inset-x-0 bottom-0 space-y-3 p-8 text-white">
            <BrandLogo size="mark" to="/" className="brightness-110" />
            <p className="max-w-sm text-lg font-medium leading-8 text-white/90">نمنح أعمالك أبعادًا للنمو</p>
            <p className="text-sm leading-7 text-white/65">
              تشخيص ← أولويات ← خدمة أو باقة ← عرض ← تنفيذ ← قياس ومتابعة
            </p>
          </div>
        </AnimatedSection>
      </div>
    </section>
  )
}
