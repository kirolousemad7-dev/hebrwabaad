import { useEffect } from 'react'
import { useLocation, useParams } from 'react-router-dom'
import { CatalogEmptyState } from '../components/catalog/CatalogStatus'
import { PublicCta } from '../components/public/PublicCta'
import { PublicBreadcrumbs } from '../components/seo/PublicBreadcrumbs'
import { PRINTING_CATEGORIES } from '../utils/printing'
import { getPrintingProductBySlug, printingCustomizePath } from '../utils/printingProducts'

export function PrintingProductLandingPage() {
  const { slug: paramSlug = '' } = useParams()
  const location = useLocation()
  const slug = paramSlug || location.pathname.replace(/^\//, '')
  const product = getPrintingProductBySlug(slug)
  const categoryName = product
    ? PRINTING_CATEGORIES.find((category) => category.id === product.category)?.name
    : null

  useEffect(() => {
    if (!product) {
      return
    }

    document.title = `${product.name} | الطباعة والتغليف | حبر وأبعاد`
    document.querySelector('meta[name="description"]')?.setAttribute('content', product.summary)
  }, [product])

  if (!product) {
    return (
      <CatalogEmptyState
        title="لم نجد هذا المنتج في كتالوج الطباعة."
        description="يمكنك العودة إلى الطباعة والتغليف واختيار منتج متاح."
        actions={[{ to: '/printing-packaging', label: 'الطباعة والتغليف', variant: 'primary' }]}
      />
    )
  }

  return (
    <section className="space-y-6">
      <PublicBreadcrumbs
        items={[
          { name: 'الرئيسية', to: '/' },
          { name: 'الطباعة والتغليف', to: '/printing-packaging' },
          { name: product.name },
        ]}
      />
      <div className="grid gap-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
        <img src={product.image} alt={product.imageAlt} className="aspect-[16/10] w-full rounded-2xl object-cover" />
        <div className="space-y-4">
          {categoryName ? <p className="text-sm font-medium text-slate-500">{categoryName}</p> : null}
          <h1 className="text-2xl font-semibold">{product.name}</h1>
          <p className="leading-8 text-slate-600">{product.summary}</p>
          <p className="text-lg font-semibold">
            {product.requiresQuote || product.startingPrice <= 0
              ? 'السعر حسب الطلب بعد اعتماد المواصفات'
              : `يبدأ من ${product.startingPrice} ر.س`}
          </p>
          {product.sizes.length > 0 ? (
            <p className="text-sm text-slate-600">
              <span className="font-medium text-slate-800">المقاسات: </span>
              {product.sizes.join('، ')}
            </p>
          ) : null}
          {product.materials.length > 0 ? (
            <p className="text-sm text-slate-600">
              <span className="font-medium text-slate-800">الخامات: </span>
              {product.materials.join('، ')}
            </p>
          ) : null}
          <div className="flex flex-wrap gap-3">
            <PublicCta to={printingCustomizePath(product.slug)}>طلب عرض سعر</PublicCta>
            <PublicCta to="/printing-packaging" variant="secondary">
              كل منتجات الطباعة
            </PublicCta>
          </div>
        </div>
      </div>
    </section>
  )
}
