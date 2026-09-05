import { BrandLogo } from '../components/brand/BrandLogo'
import { BrandWatermark } from '../components/brand/BrandWatermark'
import { PublicCta } from '../components/public/PublicCta'

export function NotFoundPage() {
  return (
    <div className="relative isolate min-h-screen bg-slate-50">
      <BrandWatermark />
      <section className="relative z-10 mx-auto max-w-md space-y-4 px-6 py-16 text-center">
        <BrandLogo size="mark" to={null} className="justify-center" />
        <h1 className="text-2xl font-semibold">الصفحة غير موجودة</h1>
        <p className="text-sm text-slate-600">قد يكون الرابط غير صحيح أو أن الصفحة نُقلت.</p>
        <PublicCta to="/">العودة للرئيسية</PublicCta>
      </section>
    </div>
  )
}
