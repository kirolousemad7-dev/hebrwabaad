import { useEffect, useId, useMemo, useRef, useState } from 'react'
import { Link, NavLink, useLocation } from 'react-router-dom'
import { BrandLogo } from '../brand/BrandLogo'
import { useAuth } from '../../context/AuthContext'
import { usePlatformSettings } from '../../context/PlatformSettingsContext'
import { LANDING_SECTION_NAV } from '../../utils/publicNav'
import { homePathForRole } from '../../utils/roles'

const PRIMARY_PLATFORM_PATHS = [
  '/consultant',
  '/services',
  '/packages',
  '/build-package',
  '/portfolio',
  '/suppliers',
]

function navLinkClass(isActive: boolean) {
  return [
    'rounded-lg px-1.5 py-1.5 whitespace-nowrap transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500',
    isActive
      ? 'font-semibold text-brand-ink-900 shadow-[inset_0_-2px_0_0_var(--color-brand-cobalt-500)]'
      : 'font-medium text-brand-ink-700 hover:text-brand-cobalt-700',
  ].join(' ')
}

function sectionLinkClass(active: boolean) {
  return [
    'rounded-lg px-1.5 py-1.5 whitespace-nowrap transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500',
    active
      ? 'font-semibold text-brand-ink-900 shadow-[inset_0_-2px_0_0_var(--color-brand-cobalt-500)]'
      : 'font-medium text-brand-ink-700 hover:text-brand-cobalt-700',
  ].join(' ')
}

function scrollToSection(id: string) {
  const target = document.getElementById(id)
  if (!target) return
  target.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

export function PublicNav() {
  const { isAuthenticated, user, logout } = useAuth()
  const { settings, navigation } = usePlatformSettings()
  const location = useLocation()
  const [menuOpen, setMenuOpen] = useState(false)
  const [moreOpen, setMoreOpen] = useState(false)
  const [activeSection, setActiveSection] = useState('home')
  const [scrolled, setScrolled] = useState(false)
  const menuId = useId()
  const moreId = useId()
  const moreRef = useRef<HTMLDivElement>(null)

  const useLandingSections = location.pathname === '/' && !isAuthenticated

  useEffect(() => {
    function onScroll() {
      setScrolled(window.scrollY > 12)
    }
    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  const landingItems = useMemo(() => {
    const features = settings.website.features
    return LANDING_SECTION_NAV.filter((item) => {
      if (item.id === 'services' && features.show_services === false) return false
      if (item.id === 'packages' && features.show_packages === false) return false
      if (item.id === 'build-package' && features.show_build_package === false) return false
      if (item.id === 'suppliers' && features.show_suppliers === false) return false
      if (item.id === 'portfolio' && features.show_portfolio === false) return false
      return true
    })
  }, [settings.website.features])

  useEffect(() => {
    setMenuOpen(false)
    setMoreOpen(false)
  }, [location.pathname, location.hash])

  useEffect(() => {
    function onKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        setMenuOpen(false)
        setMoreOpen(false)
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

  useEffect(() => {
    if (!moreOpen) return
    function onPointerDown(event: MouseEvent) {
      if (moreRef.current && !moreRef.current.contains(event.target as Node)) {
        setMoreOpen(false)
      }
    }
    window.addEventListener('mousedown', onPointerDown)
    return () => window.removeEventListener('mousedown', onPointerDown)
  }, [moreOpen])

  useEffect(() => {
    if (!useLandingSections) return

    const nodes = landingItems
      .map((item) => document.getElementById(item.id))
      .filter((node): node is HTMLElement => node !== null)

    if (nodes.length === 0) return

    const observer = new IntersectionObserver(
      (entries) => {
        const visible = entries
          .filter((entry) => entry.isIntersecting)
          .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0]
        if (visible?.target.id) {
          setActiveSection(visible.target.id)
        }
      },
      { rootMargin: '-35% 0px -50% 0px', threshold: [0.15, 0.35, 0.6] },
    )

    nodes.forEach((node) => observer.observe(node))
    return () => observer.disconnect()
  }, [useLandingSections, landingItems])

  const landingLinks = landingItems.map((item) => {
    const active = activeSection === item.id
    return (
      <a
        key={item.id}
        href={`#${item.id}`}
        className={sectionLinkClass(active)}
        aria-current={active ? 'true' : undefined}
        onClick={(event) => {
          event.preventDefault()
          setMenuOpen(false)
          scrollToSection(item.id)
          window.history.replaceState(null, '', `#${item.id}`)
        }}
      >
        {item.label}
      </a>
    )
  })

  const { primaryNav, secondaryNav } = useMemo(() => {
    const primary: typeof navigation = []
    const secondary: typeof navigation = []
    for (const item of navigation) {
      if (PRIMARY_PLATFORM_PATHS.includes(item.path)) {
        primary.push(item)
      } else {
        secondary.push(item)
      }
    }
    primary.sort(
      (a, b) => PRIMARY_PLATFORM_PATHS.indexOf(a.path) - PRIMARY_PLATFORM_PATHS.indexOf(b.path),
    )
    return { primaryNav: primary, secondaryNav: secondary }
  }, [navigation])

  function renderPlatformLink(item: (typeof navigation)[number], onNavigate?: () => void) {
    if (item.path === '/' && location.pathname === '/') {
      return (
        <Link key={item.id} to="/" className={navLinkClass(true)} onClick={onNavigate}>
          {item.label}
        </Link>
      )
    }

    return (
      <NavLink
        key={item.id}
        to={item.path}
        className={({ isActive }) => navLinkClass(isActive)}
        onClick={onNavigate}
      >
        {item.label}
      </NavLink>
    )
  }

  const platformPrimaryLinks = primaryNav.map((item) => renderPlatformLink(item, () => setMenuOpen(false)))

  const moreActive = secondaryNav.some(
    (item) => location.pathname === item.path || location.pathname.startsWith(`${item.path}/`),
  )

  const desktopPlatformLinks = (
    <>
      {platformPrimaryLinks}
      {secondaryNav.length > 0 ? (
        <div className="relative" ref={moreRef}>
          <button
            type="button"
            id={moreId}
            aria-expanded={moreOpen}
            aria-haspopup="menu"
            onClick={() => setMoreOpen((open) => !open)}
            className={navLinkClass(moreActive || moreOpen)}
          >
            المزيد
          </button>
          {moreOpen ? (
            <div
              role="menu"
              aria-labelledby={moreId}
              className="absolute top-full inset-inline-end-0 z-50 mt-2 min-w-[12rem] rounded-xl border border-brand-ink-100 bg-white p-2 shadow-card"
            >
              {secondaryNav.map((item) => (
                <NavLink
                  key={item.id}
                  role="menuitem"
                  to={item.path}
                  className={({ isActive }) =>
                    [
                      'block rounded-lg px-3 py-2 text-sm transition',
                      isActive
                        ? 'bg-brand-paper font-semibold text-brand-ink-900'
                        : 'font-medium text-brand-ink-700 hover:bg-brand-paper hover:text-brand-cobalt-700',
                    ].join(' ')
                  }
                  onClick={() => {
                    setMoreOpen(false)
                    setMenuOpen(false)
                  }}
                >
                  {item.label}
                </NavLink>
              ))}
            </div>
          ) : null}
        </div>
      ) : null}
    </>
  )

  const mobilePlatformLinks = (
    <>
      {platformPrimaryLinks}
      {secondaryNav.map((item) => renderPlatformLink(item, () => setMenuOpen(false)))}
    </>
  )

  const desktopPrimaryLinks = useLandingSections ? landingLinks : desktopPlatformLinks
  const mobilePrimaryLinks = useLandingSections ? landingLinks : mobilePlatformLinks

  const authLinks = isAuthenticated ? (
    <>
      <Link
        to={homePathForRole(user?.role)}
        className="rounded-xl bg-brand-ink-900 px-3 py-1.5 font-medium text-white hover:bg-brand-ink-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500"
      >
        حسابي
      </Link>
      <button
        type="button"
        onClick={() => void logout()}
        className="rounded-lg px-2 py-1.5 font-medium text-brand-ink-700 underline hover:text-brand-ink-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-ink-900"
      >
        خروج
      </button>
    </>
  ) : (
    <>
      <Link
        to="/login"
        className="rounded-lg px-2 py-1.5 font-medium text-brand-ink-700 hover:text-brand-cobalt-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500"
      >
        تسجيل الدخول
      </Link>
      <Link
        to="/register"
        className="brand-btn-primary !min-h-9 rounded-xl px-3 py-1.5 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500"
      >
        إنشاء حساب
      </Link>
    </>
  )

  return (
    <>
      <header
        className={[
          'sticky top-0 z-40 border-b transition-[background-color,box-shadow,border-color,backdrop-filter] duration-300',
          scrolled
            ? 'border-brand-ink-100/90 bg-brand-paper/95 shadow-sm backdrop-blur-md'
            : 'border-transparent bg-brand-paper/80 shadow-none backdrop-blur-[2px]',
        ].join(' ')}
      >
        <div className="marketing-container flex items-center justify-between gap-4 py-2.5">
          <BrandLogo size="nav" />

          <nav
            aria-label="التنقل الرئيسي"
            className="hidden min-w-0 flex-1 items-center justify-end gap-0.5 text-sm lg:flex lg:flex-nowrap"
          >
            {desktopPrimaryLinks}
            <span className="mx-1.5 h-4 w-px shrink-0 bg-brand-ink-100" aria-hidden="true" />
            {authLinks}
          </nav>

          <div className="flex items-center gap-2 lg:hidden">
            {isAuthenticated ? (
              <Link
                to={homePathForRole(user?.role)}
                className="rounded-xl bg-brand-ink-900 px-2.5 py-1.5 text-sm text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500"
              >
                حسابي
              </Link>
            ) : (
              <Link
                to="/login"
                className="rounded-lg px-2 py-1.5 text-sm font-medium text-brand-ink-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500"
              >
                تسجيل الدخول
              </Link>
            )}
            <button
              type="button"
              aria-expanded={menuOpen}
              aria-controls={menuId}
              onClick={() => setMenuOpen((open) => !open)}
              className="min-h-11 rounded-xl border border-brand-ink-100 bg-white px-3 text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500"
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
            className="fixed inset-0 z-50 bg-brand-ink-900/40"
            onClick={() => setMenuOpen(false)}
          />
          <nav
            id={menuId}
            aria-label="التنقل للجوال"
            aria-modal="true"
            role="dialog"
            className="fixed inset-y-0 start-0 z-[60] flex w-[min(20rem,88vw)] flex-col gap-1 overflow-y-auto border-e border-brand-ink-100 bg-white px-5 py-6 text-sm shadow-xl"
          >
            <div className="mb-3">
              <BrandLogo size="nav" />
            </div>
            {mobilePrimaryLinks}
            <span className="my-2 h-px bg-brand-ink-100" aria-hidden="true" />
            {authLinks}
            {settings.social.length > 0 ? (
              <div className="mt-4 flex flex-wrap gap-2 text-xs text-brand-ink-500">
                {settings.social.map((item) => (
                  <a key={`${item.platform}-${item.url}`} href={item.url} target="_blank" rel="noreferrer">
                    {item.platform}
                  </a>
                ))}
              </div>
            ) : null}
          </nav>
        </div>
      ) : null}
    </>
  )
}
