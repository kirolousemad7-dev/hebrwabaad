import { Link } from 'react-router-dom'
import { BrandLogo } from '../brand/BrandLogo'
import { BRAND_TAGLINE } from '../../utils/brand'
import { PUBLIC_NAV_ITEMS } from '../../utils/publicNav'

export function PublicFooter() {
  return (
    <footer className="mt-auto border-t border-slate-200 bg-white">
      <div className="mx-auto flex max-w-6xl flex-col gap-5 px-4 py-8 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <div className="space-y-2">
          <BrandLogo size="nav" />
          <p className="text-sm text-slate-600">{BRAND_TAGLINE}</p>
        </div>
        <nav aria-label="روابط تذييل الموقع">
          <ul className="flex flex-wrap gap-x-4 gap-y-2 text-sm text-slate-600">
            {PUBLIC_NAV_ITEMS.map((item) => (
              <li key={item.to}>
                <Link
                  to={item.to}
                  className="hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                >
                  {item.label}
                </Link>
              </li>
            ))}
          </ul>
        </nav>
      </div>
    </footer>
  )
}
