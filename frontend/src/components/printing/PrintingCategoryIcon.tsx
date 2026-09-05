import type { PrintingCategoryId } from '../../utils/printing'

type PrintingCategoryIconProps = {
  id: PrintingCategoryId
}

const iconClass = 'h-6 w-6 shrink-0 text-slate-800'

export function PrintingCategoryIcon({ id }: PrintingCategoryIconProps) {
  switch (id) {
    case 'business-cards':
      return (
        <svg viewBox="0 0 24 24" className={iconClass} aria-hidden="true">
          <rect x="3" y="7" width="18" height="11" rx="1.5" fill="none" stroke="currentColor" strokeWidth="1.5" />
          <path d="M7 11h6M7 14h4" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
        </svg>
      )
    case 'flyers':
      return (
        <svg viewBox="0 0 24 24" className={iconClass} aria-hidden="true">
          <path d="M7 4h10v16H7z" fill="none" stroke="currentColor" strokeWidth="1.5" />
          <path d="M10 8h4M10 12h4M10 16h2" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
        </svg>
      )
    case 'stickers':
      return (
        <svg viewBox="0 0 24 24" className={iconClass} aria-hidden="true">
          <circle cx="12" cy="12" r="7.5" fill="none" stroke="currentColor" strokeWidth="1.5" />
          <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" strokeWidth="1.5" />
        </svg>
      )
    case 'boxes':
      return (
        <svg viewBox="0 0 24 24" className={iconClass} aria-hidden="true">
          <path d="M4 8h16v11H4zM4 8l8-4 8 4M12 4v15" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinejoin="round" />
        </svg>
      )
    case 'bags':
      return (
        <svg viewBox="0 0 24 24" className={iconClass} aria-hidden="true">
          <path d="M6 8h12l-1 12H7z" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinejoin="round" />
          <path d="M9 8V7a3 3 0 0 1 6 0v1" fill="none" stroke="currentColor" strokeWidth="1.5" />
        </svg>
      )
    case 'packaging':
      return (
        <svg viewBox="0 0 24 24" className={iconClass} aria-hidden="true">
          <path d="M5 7h14v12H5z" fill="none" stroke="currentColor" strokeWidth="1.5" />
          <path d="M5 11h14M12 7v12" fill="none" stroke="currentColor" strokeWidth="1.5" />
        </svg>
      )
    case 'posters':
      return (
        <svg viewBox="0 0 24 24" className={iconClass} aria-hidden="true">
          <path d="M6 3h12v18H6z" fill="none" stroke="currentColor" strokeWidth="1.5" />
          <path d="M9 8h6M9 12h6M9 16h3" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
        </svg>
      )
    case 'custom-products':
      return (
        <svg viewBox="0 0 24 24" className={iconClass} aria-hidden="true">
          <path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
        </svg>
      )
  }
}
