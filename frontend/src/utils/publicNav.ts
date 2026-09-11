export type PublicNavItem = {
  to: string
  label: string
  end?: boolean
}

export type LandingSectionNavItem = {
  id: string
  label: string
}

/** Guest marketing anchors on "/" only. */
export const LANDING_SECTION_NAV: LandingSectionNavItem[] = [
  { id: 'home', label: 'الرئيسية' },
  { id: 'services', label: 'الخدمات' },
  { id: 'packages', label: 'الباقات' },
  { id: 'build-package', label: 'صمّم باقتك' },
  { id: 'portfolio', label: 'أعمالنا' },
  { id: 'suppliers', label: 'الموردين' },
  { id: 'about', label: 'من نحن' },
  { id: 'contact', label: 'تواصل معنا' },
]

/**
 * PDF §14 main navigation — maps to real catalog routes.
 * Primary discovery CTA stays `/consultant` (اكتشف احتياجك).
 */
export const PLATFORM_NAV_ITEMS: PublicNavItem[] = [
  { to: '/consultant', label: 'اكتشف احتياجك' },
  { to: '/services', label: 'الخدمات' },
  { to: '/packages', label: 'الباقات' },
  { to: '/build-package', label: 'صمّم باقتك' },
  { to: '/sectors', label: 'القطاعات' },
  { to: '/printing-packaging', label: 'الطباعة والتغليف' },
  { to: '/events', label: 'الفعاليات' },
  { to: '/portfolio', label: 'أعمالنا' },
  { to: '/suppliers', label: 'الموردين' },
  { to: '/dashboard/orders', label: 'طلباتي' },
  { to: '/dashboard/profile', label: 'حسابي' },
]

/**
 * Legacy marketing page destinations (still routed for SEO/deep links).
 * Not used as the authenticated platform menu.
 */
export const PUBLIC_NAV_ITEMS: PublicNavItem[] = [
  { to: '/', label: 'الرئيسية', end: true },
  { to: '/consultant', label: 'اكتشف احتياجك' },
  { to: '/services', label: 'الخدمات' },
  { to: '/packages', label: 'الباقات' },
  { to: '/build-package', label: 'صمّم باقتك' },
  { to: '/sectors', label: 'القطاعات' },
  { to: '/suppliers', label: 'الموردين' },
  { to: '/portfolio', label: 'أعمالنا' },
  { to: '/about', label: 'من نحن' },
  { to: '/contact', label: 'تواصل معنا' },
]

/** @deprecated Prefer PLATFORM_NAV_ITEMS — kept for any leftover imports. */
export const PUBLIC_CATALOG_LINKS: PublicNavItem[] = PLATFORM_NAV_ITEMS.filter((item) => item.to !== '/')

export function landingSectionHref(id: string): string {
  return `/#${id}`
}
