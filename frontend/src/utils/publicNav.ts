export type PublicNavItem = {
  to: string
  label: string
  end?: boolean
}

export const PUBLIC_NAV_ITEMS: PublicNavItem[] = [
  { to: '/', label: 'الرئيسية', end: true },
  { to: '/consultant', label: 'المستشار الذكي' },
  { to: '/services', label: 'الخدمات' },
  { to: '/packages', label: 'الباقات' },
  { to: '/marketing-packages', label: 'الباقات التسويقية' },
  { to: '/event-packages', label: 'الباقات للفعاليات' },
  { to: '/printing-packaging', label: 'الطباعة والتغليف' },
  { to: '/suppliers', label: 'الموردون' },
  { to: '/build-package', label: 'صمّم باقتك' },
]
