import { LandingCta } from './LandingCta'

export function ConsultantCta() {
  return (
    <section className="bg-amber-50 py-16 sm:py-20">
      <div className="mx-auto flex max-w-6xl flex-col gap-6 px-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <div className="max-w-2xl space-y-3">
          <h2 className="text-3xl font-semibold">مش عارف تبدأ منين؟</h2>
          <p className="leading-8 text-slate-700">
            خلّي مستشار حبر وأبعاد يساعدك تعرف أنسب حل لبيزنسك من الخدمات والباقات الموجودة فعلياً في المنصة.
          </p>
        </div>
        <LandingCta to="/consultant" variant="navy">
          ابدأ مع المستشار الذكي
        </LandingCta>
      </div>
    </section>
  )
}
