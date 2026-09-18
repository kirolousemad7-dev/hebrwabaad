import { WhyUsSection } from '../components/landing/WhyUsSection'
import { ProcessSection } from '../components/landing/ProcessSection'
import { FinalCta } from '../components/landing/FinalCta'
import { BrandLogo } from '../components/brand/BrandLogo'

export function AboutPage() {
  return (
    <div className="-mx-4 -my-8 sm:-mx-6 sm:-my-10">
      <section className="bg-slate-950 px-4 py-16 text-white sm:px-6">
        <div className="mx-auto max-w-3xl space-y-4">
          <BrandLogo size="mark" to="/" className="brightness-110" />
          <h1 className="text-4xl font-semibold">نمنح أعمالك أبعادًا للنمو</h1>
          <p className="text-lg leading-8 text-white/75">
            حبر وأبعاد منصة سعودية متكاملة لخدمات الأعمال والنمو. نساعد الشركات والمنشآت ورواد الأعمال على تشخيص احتياجاتهم، بناء علاماتهم، تطوير حضورهم الرقمي وتنفيذ مشاريعهم من خلال شبكة من المتخصصين والموردين في التسويق، المحتوى، التصوير، المتاجر الإلكترونية، الطباعة، التغليف، المعارض، الحفلات والافتتاحات.
          </p>
        </div>
      </section>
      <WhyUsSection />
      <ProcessSection />
      <FinalCta />
    </div>
  )
}
