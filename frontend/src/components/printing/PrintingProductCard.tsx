import { PublicCta } from '../public/PublicCta'
import { formatMoney } from '../../utils/catalog'
import { printingCustomizePath, type PrintingProduct } from '../../utils/printingProducts'

type PrintingProductCardProps = {
  product: PrintingProduct
}

export function PrintingProductCard({ product }: PrintingProductCardProps) {
  return (
    <article className="flex min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <img
        src={product.image}
        alt={product.imageAlt}
        width={640}
        height={400}
        className="aspect-[16/10] w-full object-cover"
      />
      <div className="flex min-w-0 flex-1 flex-col gap-3 p-5">
        <div className="space-y-1">
          <h3 className="font-semibold">{product.name}</h3>
          <p className="text-sm text-slate-600">{product.summary}</p>
        </div>
        <p className="text-lg font-semibold">
          يبدأ من {formatMoney(product.startingPrice, product.currency)}
        </p>
        <p className="text-sm text-slate-600">
          <span className="font-medium text-slate-800">الأحجام: </span>
          {product.sizes.join('، ')}
        </p>
        <p className="text-sm text-slate-600">
          <span className="font-medium text-slate-800">الخامات: </span>
          {product.materials.join('، ')}
        </p>
        <div className="mt-auto">
          <PublicCta to={printingCustomizePath(product.slug)}>تخصيص المنتج</PublicCta>
        </div>
      </div>
    </article>
  )
}
