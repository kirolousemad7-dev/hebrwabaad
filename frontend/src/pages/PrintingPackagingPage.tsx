import { Link, useSearchParams } from 'react-router-dom'
import { CatalogHero } from '../components/catalog/CatalogHero'
import { CatalogEmptyState } from '../components/catalog/CatalogStatus'
import { PrintingCategoryCard } from '../components/printing/PrintingCategoryCard'
import { PrintingProductCard } from '../components/printing/PrintingProductCard'
import { PublicCta } from '../components/public/PublicCta'
import { useAuth } from '../context/AuthContext'
import { isPrintingCategoryId, PRINTING_CATEGORIES, PRINTING_CUSTOM_PATH } from '../utils/printing'
import { getPrintingProducts } from '../utils/printingProducts'

export function PrintingPackagingPage() {
  const { user } = useAuth()
  const [params] = useSearchParams()
  const categoryParam = params.get('category')
  const selectedCategory = isPrintingCategoryId(categoryParam) ? categoryParam : null
  const selectedLabel = PRINTING_CATEGORIES.find((category) => category.id === selectedCategory)?.name
  const products = getPrintingProducts(selectedCategory)

  return (
    <div className="space-y-10">
      <CatalogHero
        tone="printing"
        eyebrow="الطباعة والتغليف"
        title="مواد مطبوعة وتغليف يعكس هوية علامتك"
        description="من الكروت والبوسترات إلى العلب والأكياس والتغليف المخصص. ننفّذ إنتاجاً تجارياً واضحاً يليق بعلامتك، دون تشتيت بين مطبعة وتصميم وتنفيذ."
        primaryCta="استعرض الفئات"
        secondaryCta="صمّم باقتك"
        secondaryTo="/build-package"
        packagesAnchor="printing-categories"
        packageCount={PRINTING_CATEGORIES.length}
        emptyCountLabel="لا توجد فئات طباعة حالياً."
        countLabel={(count) => `${count} فئات للطباعة والتغليف جاهزة للاختيار.`}
      />

      <section id="printing-categories" className="space-y-4 scroll-mt-24">
        <header className="space-y-1">
          <h2 className="text-xl font-semibold">فئات الطباعة والتغليف</h2>
          <p className="text-sm text-slate-600">اختر فئة لعرض منتجاتها وأسعارها الابتدائية والأحجام والخامات المتاحة.</p>
        </header>
        <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {PRINTING_CATEGORIES.map((category) => (
            <li key={category.id} className="min-w-0">
              <PrintingCategoryCard category={category} selected={selectedCategory === category.id} />
            </li>
          ))}
        </ul>
      </section>

      <section id="printing-products" className="space-y-4 scroll-mt-24">
        <header className="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
          <div className="space-y-1">
            <h2 className="text-xl font-semibold">المنتجات</h2>
            <p className="text-sm text-slate-600">
              {selectedLabel
                ? `منتجات فئة ${selectedLabel}. الأسعار ابتدائية للتقدير، والتخصيص التفصيلي يأتي لاحقاً.`
                : 'كل منتجات الطباعة والتغليف. اختر فئة أعلاه لتصفية القائمة.'}
            </p>
          </div>
          {selectedCategory ? (
            <Link
              to="/printing-packaging#printing-products"
              className="text-sm underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
            >
              عرض كل المنتجات
            </Link>
          ) : null}
        </header>

        {products.length === 0 ? (
          <CatalogEmptyState
            title="لا توجد منتجات متاحة في هذا القسم حاليًا."
            description="يمكنك اختيار فئة أخرى أو العودة لكل منتجات الطباعة."
            actions={[{ to: '/printing-packaging#printing-products', label: 'كل المنتجات', variant: 'primary' }]}
          />
        ) : (
          <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {products.map((product) => (
              <li key={product.id} className="min-w-0">
                <PrintingProductCard product={product} />
              </li>
            ))}
          </ul>
        )}
      </section>

      <section
        id="printing-suppliers"
        className="flex min-w-0 flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 sm:flex-row sm:items-center sm:justify-between"
      >
        <div className="space-y-1">
          <h2 className="text-lg font-semibold">شركاؤنا في الإنتاج</h2>
          <p className="text-sm text-slate-600">
            استكشف موردين للطباعة والتغليف واطّلع على تخصصاتهم وملفات أعمالهم. التسعير والتعيين يأتي لاحقاً.
          </p>
        </div>
        <PublicCta to="/suppliers">استكشف موردينا</PublicCta>
      </section>

      {user?.role === 'CUSTOMER' ? (
        <section className="flex min-w-0 flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 sm:flex-row sm:items-center sm:justify-between">
          <div className="space-y-1">
            <h2 className="text-lg font-semibold">طلباتك الحالية</h2>
            <p className="text-sm text-slate-600">تابع المراجعة والسعر التقديري أو عرض السعر بعد إرسال الطلب.</p>
          </div>
          <PublicCta to="/customer/printing-requests">متابعة طلبات الطباعة</PublicCta>
        </section>
      ) : null}

      <section
        id="custom-print"
        className="flex min-w-0 flex-col gap-3 rounded-2xl border border-slate-900 bg-slate-900 p-5 text-white sm:flex-row sm:items-center sm:justify-between"
      >
        <div className="space-y-1">
          <h2 className="text-lg font-semibold">منتج مخصص أو كمية خاصة؟</h2>
          <p className="text-sm text-white/75">
            إن لم تجد المنتج المناسب، يمكنك تسجيل رغبتك في حل طباعة مخصص. نموذج الطلب الفعلي سيُفعَّل لاحقاً، ولن يُنشأ طلب الآن.
          </p>
        </div>
        <PublicCta to={PRINTING_CUSTOM_PATH} variant="inverse">
          طلب طباعة مخصصة
        </PublicCta>
      </section>
    </div>
  )
}
