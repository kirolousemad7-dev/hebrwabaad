import { Link } from 'react-router-dom'
import type { PrintingCategory } from '../../utils/printing'
import { PrintingCategoryIcon } from './PrintingCategoryIcon'

type PrintingCategoryCardProps = {
  category: PrintingCategory
  selected?: boolean
}

export function PrintingCategoryCard({ category, selected = false }: PrintingCategoryCardProps) {
  return (
    <Link
      to={category.href}
      id={category.id}
      aria-current={selected ? 'true' : undefined}
      className={[
        'flex h-full min-w-0 scroll-mt-24 flex-col gap-3 rounded-xl border bg-white p-5 text-right transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900',
        selected
          ? 'border-slate-900 ring-2 ring-slate-900'
          : 'border-slate-200 hover:border-slate-900 hover:bg-slate-50',
      ].join(' ')}
    >
      <span className="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100">
        <PrintingCategoryIcon id={category.id} />
      </span>
      <span className="font-semibold">{category.name}</span>
      <span className="flex-1 text-sm leading-6 text-slate-600">{category.description}</span>
      <span className="text-sm font-medium text-slate-900">{selected ? 'محددة' : 'عرض المنتجات'}</span>
    </Link>
  )
}
