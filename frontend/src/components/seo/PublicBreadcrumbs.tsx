import { Link } from 'react-router-dom'

export type BreadcrumbItem = {
  name: string
  to?: string
}

export function PublicBreadcrumbs({ items }: { items: BreadcrumbItem[] }) {
  if (items.length < 2) {
    return null
  }

  return (
    <nav aria-label="مسار الصفحة" className="mb-4 text-sm text-slate-500">
      <ol className="flex flex-wrap items-center gap-1">
        {items.map((item, index) => {
          const isLast = index === items.length - 1
          return (
            <li key={`${item.name}-${index}`} className="flex items-center gap-1">
              {index > 0 ? <span aria-hidden="true">/</span> : null}
              {isLast || !item.to ? (
                <span className={isLast ? 'font-medium text-slate-800' : undefined} aria-current={isLast ? 'page' : undefined}>
                  {item.name}
                </span>
              ) : (
                <Link to={item.to} className="hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900">
                  {item.name}
                </Link>
              )}
            </li>
          )
        })}
      </ol>
    </nav>
  )
}
