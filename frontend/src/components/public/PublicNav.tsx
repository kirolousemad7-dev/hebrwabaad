import { useEffect, useId, useState } from 'react'
import { Link, NavLink, useLocation } from 'react-router-dom'
import { BrandLogo } from '../brand/BrandLogo'
import { useAuth } from '../../context/AuthContext'
import { PUBLIC_NAV_ITEMS } from '../../utils/publicNav'
import { homePathForRole } from '../../utils/roles'

function navLinkClass(isActive: boolean) {
  return [
    'rounded-lg px-1.5 py-1.5 whitespace-nowrap focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900',
    isActive ? 'bg-slate-100 font-semibold text-slate-900' : 'text-slate-600 hover:text-slate-900',
  ].join(' ')
}

export function PublicNav() {
  const { isAuthenticated, user, logout } = useAuth()
  const location = useLocation()
  const [menuOpen, setMenuOpen] = useState(false)
  const menuId = useId()

  useEffect(() => {
    setMenuOpen(false)
  }, [location.pathname])

  useEffect(() => {
    function onKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        setMenuOpen(false)
      }
    }

    window.addEventListener('keydown', onKeyDown)
    return () => window.removeEventListener('keydown', onKeyDown)
  }, [])

  useEffect(() => {
    document.body.style.overflow = menuOpen ? 'hidden' : ''
    return () => {
      document.body.style.overflow = ''
    }
  }, [menuOpen])

  const catalogLinks = PUBLIC_NAV_ITEMS.map((item) => (
    <NavLink key={item.to} to={item.to} end={item.end} className={({ isActive }) => navLinkClass(isActive)}>
      {item.label}
    </NavLink>
  ))

  const authLinks = isAuthenticated ? (
    <>
      <Link
        to={homePathForRole(user?.role)}
        className="rounded-lg px-2 py-1.5 text-slate-600 hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
      >
        حسابي
      </Link>
      <button
        type="button"
        onClick={() => void logout()}
        className="rounded-lg px-2 py-1.5 text-slate-600 underline hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
      >
        خروج
      </button>
    </>
  ) : (
    <>
      <Link
        to="/login"
        className="rounded-lg px-2 py-1.5 text-slate-600 hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
      >
        تسجيل الدخول
      </Link>
      <Link
        to="/register"
        className="rounded-lg bg-amber-400 px-3 py-1.5 font-medium text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
      >
        إنشاء حساب
      </Link>
    </>
  )

  return (
    <>
      <header className="sticky top-0 z-40 border-b border-slate-200 bg-slate-50/95 shadow-sm backdrop-blur">
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-2.5 sm:px-6">
          <BrandLogo size="nav" />

          <nav
            aria-label="التنقل الرئيسي"
            className="hidden max-w-full min-w-0 items-center gap-0.5 overflow-x-auto text-sm lg:flex lg:flex-nowrap lg:justify-end"
          >
            {catalogLinks}
            <span className="mx-1 h-4 w-px bg-slate-200" aria-hidden="true" />
            {authLinks}
          </nav>

          <div className="flex items-center gap-2 lg:hidden">
            {isAuthenticated ? (
              <Link
                to={homePathForRole(user?.role)}
                className="rounded-lg px-2 py-1.5 text-sm text-slate-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
              >
                حسابي
              </Link>
            ) : (
              <Link
                to="/login"
                className="rounded-lg px-2 py-1.5 text-sm text-slate-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
              >
                تسجيل الدخول
              </Link>
            )}
            <button
              type="button"
              aria-expanded={menuOpen}
              aria-controls={menuId}
              onClick={() => setMenuOpen((open) => !open)}
            className="min-h-11 rounded-lg border border-slate-300 px-3 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
            >
              {menuOpen ? 'إغلاق' : 'القائمة'}
            </button>
          </div>
        </div>
      </header>

      {menuOpen ? (
        <div className="lg:hidden">
          <button
            type="button"
            aria-label="إغلاق القائمة"
            className="fixed inset-0 z-50 bg-slate-900/40"
            onClick={() => setMenuOpen(false)}
          />
          <nav
            id={menuId}
            aria-label="التنقل للجوال"
            aria-modal="true"
            role="dialog"
            className="fixed inset-y-0 start-0 z-[60] flex w-[min(20rem,88vw)] flex-col gap-1 overflow-y-auto border-e border-slate-200 bg-white px-5 py-6 text-sm shadow-xl"
          >
            <div className="mb-3">
              <BrandLogo size="nav" />
            </div>
            {catalogLinks}
            <span className="my-2 h-px bg-slate-100" aria-hidden="true" />
            {authLinks}
          </nav>
        </div>
      ) : null}
    </>
  )
}
