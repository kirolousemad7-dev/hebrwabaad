import { PublicCta } from '../public/PublicCta'
import { SarAmount } from '../ui/SarAmount'
import { MediaLightbox } from '../ui/MediaLightbox'
import { useState } from 'react'
import { buttonColorStyle } from '../../utils/buttonColor'
import type { PrintingProduct } from '../../utils/printingProducts'

type PrintingProductCardProps = {
  product: PrintingProduct
}

export function PrintingProductCard({ product }: PrintingProductCardProps) {
  const [open, setOpen] = useState(false)
  const style = buttonColorStyle(product.buttonColor)
  const hasOld = (product.compareAtPrice ?? 0) > (product.startingPrice ?? 0) && product.startingPrice > 0

  return (
    <article className="flex min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <button type="button" className="block w-full" onClick={() => setOpen(true)}>
        <img
          src={product.image}
          alt={product.imageAlt}
          width={640}
          height={400}
          loading="lazy"
          className="aspect-[4/3] w-full object-cover"
        />
      </button>
      <div className="flex min-w-0 flex-1 flex-col gap-3 p-4">
        <div className="space-y-1">
          <h3 className="font-semibold">{product.name}</h3>
          <p className="text-sm text-slate-600">{product.summary}</p>
        </div>
        <div className="flex flex-wrap items-center gap-2 text-sm">
          {product.requiresQuote || product.startingPrice <= 0 ? (
            <p className="font-semibold">السعر حسب الطلب بعد اعتماد المواصفات</p>
          ) : (
            <>
              <SarAmount amount={product.startingPrice} className="text-lg font-semibold" />
              {hasOld ? <SarAmount amount={product.compareAtPrice ?? 0} struck /> : null}
            </>
          )}
        </div>
        <div className="mt-auto">
          <PublicCta to={`/${product.slug}`} style={style}>عرض المنتج</PublicCta>
        </div>
      </div>
      <MediaLightbox open={open} imageSrc={product.image} title={product.name} onClose={() => setOpen(false)} />
    </article>
  )
}
