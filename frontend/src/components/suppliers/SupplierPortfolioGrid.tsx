import { useEffect, useId, useState } from 'react'
import type { SupplierPortfolioItem } from '../../types/api'

type SupplierPortfolioGridProps = {
  items: SupplierPortfolioItem[]
}

export function SupplierPortfolioGrid({ items }: SupplierPortfolioGridProps) {
  const [selected, setSelected] = useState<SupplierPortfolioItem | null>(null)
  const [category, setCategory] = useState<string | null>(null)
  const titleId = useId()
  const categories = [...new Set(items.map((item) => item.category))]
  const visible = category ? items.filter((item) => item.category === category) : items

  useEffect(() => {
    function onKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        setSelected(null)
      }
    }

    window.addEventListener('keydown', onKeyDown)
    return () => window.removeEventListener('keydown', onKeyDown)
  }, [])

  if (items.length === 0) {
    return <p className="text-sm text-slate-600">لا توجد أعمال في الملف حالياً.</p>
  }

  return (
    <section className="space-y-4" aria-labelledby="supplier-portfolio-heading">
      <div className="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h2 id="supplier-portfolio-heading" className="text-xl font-semibold">
            ملف الأعمال
          </h2>
          <p className="text-sm text-slate-600">{items.length.toLocaleString('ar-SA')} أعمال معروضة.</p>
        </div>
        {categories.length > 1 ? (
          <div className="flex gap-2 overflow-x-auto pb-1" role="toolbar" aria-label="تصفية الأعمال">
            <button
              type="button"
              aria-pressed={category === null}
              onClick={() => setCategory(null)}
              className={chipClass(category === null)}
            >
              الكل
            </button>
            {categories.map((value) => (
              <button
                key={value}
                type="button"
                aria-pressed={category === value}
                onClick={() => setCategory(value)}
                className={chipClass(category === value)}
              >
                {value}
              </button>
            ))}
          </div>
        ) : null}
      </div>

      {visible.length === 0 ? (
        <p className="text-sm text-slate-600">لا توجد أعمال في هذا التصنيف.</p>
      ) : (
        <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {visible.map((item) => (
            <li key={item.id} className="min-w-0">
              <button
                type="button"
                onClick={() => setSelected(item)}
                className="w-full min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white text-right focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
              >
                <img
                  src={item.image}
                  alt={item.title}
                  width={640}
                  height={400}
                  loading="lazy"
                  className="aspect-[16/10] w-full object-cover"
                />
                <span className="block space-y-1 p-4">
                  <span className="block font-medium">{item.title}</span>
                  <span className="block text-sm text-slate-600">{item.description}</span>
                  <span className="block text-xs text-slate-500">{item.category}</span>
                </span>
              </button>
            </li>
          ))}
        </ul>
      )}

      {selected ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 p-4" role="presentation">
          <button type="button" className="absolute inset-0" aria-label="إغلاق المعاينة" onClick={() => setSelected(null)} />
          <div
            role="dialog"
            aria-modal="true"
            aria-labelledby={titleId}
            className="relative z-10 max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-4"
          >
            <img src={selected.image} alt={selected.title} className="aspect-[16/10] w-full rounded-lg object-cover" />
            <h3 id={titleId} className="mt-4 text-lg font-semibold">
              {selected.title}
            </h3>
            <p className="mt-1 text-sm text-slate-600">{selected.description}</p>
            <p className="mt-1 text-xs text-slate-500">{selected.category}</p>
            <button
              type="button"
              onClick={() => setSelected(null)}
              className="mt-4 rounded-md bg-slate-900 px-4 py-2 text-sm text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
            >
              إغلاق
            </button>
          </div>
        </div>
      ) : null}
    </section>
  )
}

function chipClass(active: boolean): string {
  return [
    'shrink-0 rounded-full px-3 py-1.5 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900',
    active ? 'bg-slate-900 text-white' : 'border border-slate-300 bg-white text-slate-700',
  ].join(' ')
}
