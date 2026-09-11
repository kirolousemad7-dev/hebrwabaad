import { describe, expect, it } from 'vitest'
import { ownerNavForRole } from './dashboardNav'
import {
  DEFAULT_PAGE_SEO,
  PUBLIC_SITEMAP_PATHS,
  absoluteAssetUrl,
  descriptionLengthHint,
  isPrivateSeoPath,
  seoKeyFromPath,
  titleLengthHint,
} from './seo'
import { LANDING_SECTION_NAV, PLATFORM_NAV_ITEMS, PUBLIC_NAV_ITEMS } from './publicNav'

describe('seo helpers', () => {
  it('maps public marketing paths to SEO keys', () => {
    expect(seoKeyFromPath('/')).toBe('home')
    expect(seoKeyFromPath('/services')).toBe('services')
    expect(seoKeyFromPath('/packages')).toBe('packages')
    expect(seoKeyFromPath('/marketing-packages')).toBe('marketing-packages')
    expect(seoKeyFromPath('/event-packages')).toBe('event-packages')
    expect(seoKeyFromPath('/printing-packaging')).toBe('printing-packaging')
    expect(seoKeyFromPath('/consultant')).toBe('consultant')
    expect(seoKeyFromPath('/build-package')).toBe('build-package')
    expect(seoKeyFromPath('/portfolio')).toBe('portfolio')
    expect(seoKeyFromPath('/about')).toBe('about')
    expect(seoKeyFromPath('/contact')).toBe('contact')
    expect(seoKeyFromPath('/suppliers')).toBe('suppliers')
    expect(seoKeyFromPath('/suppliers/demo')).toBeNull()
  })

  it('does not expose private platform routes as public SEO pages', () => {
    expect(seoKeyFromPath('/dashboard')).toBeNull()
    expect(seoKeyFromPath('/owner')).toBeNull()
    expect(seoKeyFromPath('/owner/seo')).toBeNull()
    expect(seoKeyFromPath('/workspace')).toBeNull()
    expect(seoKeyFromPath('/login')).toBeNull()
    expect(isPrivateSeoPath('/dashboard')).toBe(true)
    expect(isPrivateSeoPath('/owner/orders')).toBe(true)
    expect(isPrivateSeoPath('/crm')).toBe(true)
    expect(isPrivateSeoPath('/crm/leads')).toBe(true)
    expect(isPrivateSeoPath('/printing/customize/x')).toBe(true)
    expect(isPrivateSeoPath('/login')).toBe(true)
    expect(isPrivateSeoPath('/services')).toBe(false)
  })

  it('provides unique default titles for major public pages', () => {
    const titles = Object.values(DEFAULT_PAGE_SEO).map((item) => item.title)
    expect(new Set(titles).size).toBe(titles.length)
    expect(DEFAULT_PAGE_SEO.home.title).toContain('حبر وأبعاد')
    expect(DEFAULT_PAGE_SEO.services.title).toContain('خدمات')
    expect(DEFAULT_PAGE_SEO['printing-packaging'].title).toContain('الطباعة')
  })

  it('lists indexable sitemap paths without private areas', () => {
    expect(PUBLIC_SITEMAP_PATHS).toContain('/printing-packaging')
    expect(PUBLIC_SITEMAP_PATHS).toContain('/marketing-packages')
    expect(PUBLIC_SITEMAP_PATHS.some((path) => path.startsWith('/dashboard'))).toBe(false)
  })

  it('warns when title or description exceed search preview lengths', () => {
    expect(titleLengthHint('حبر وأبعاد').tone).toBe('ok')
    expect(titleLengthHint('x'.repeat(61)).tone).toBe('warn')
    expect(descriptionLengthHint('y'.repeat(161)).tone).toBe('warn')
  })

  it('builds absolute asset URLs', () => {
    expect(absoluteAssetUrl('/brand/logo.png', 'https://example.com')).toBe('https://example.com/brand/logo.png')
    expect(absoluteAssetUrl('https://cdn.example/x.png', 'https://example.com')).toBe('https://cdn.example/x.png')
  })
})

describe('public navigation', () => {
  it('landing navbar uses section anchors only', () => {
    expect(LANDING_SECTION_NAV.map((item) => item.id)).toEqual([
      'home',
      'services',
      'packages',
      'build-package',
      'portfolio',
      'suppliers',
      'about',
      'contact',
    ])
  })

  it('exposes PDF §14 customer catalog navigation', () => {
    expect(PLATFORM_NAV_ITEMS.map((item) => item.to)).toEqual([
      '/consultant',
      '/services',
      '/packages',
      '/build-package',
      '/sectors',
      '/printing-packaging',
      '/events',
      '/portfolio',
      '/suppliers',
      '/dashboard/orders',
      '/dashboard/profile',
    ])
    expect(PLATFORM_NAV_ITEMS[0]?.label).toBe('اكتشف احتياجك')
    expect(PLATFORM_NAV_ITEMS.some((item) => item.label === 'صمّم باقتك')).toBe(true)
    expect(PLATFORM_NAV_ITEMS.some((item) => item.label === 'الموردين')).toBe(true)
  })

  it('keeps legacy public marketing destinations available for deep links', () => {
    const paths = PUBLIC_NAV_ITEMS.map((item) => item.to)

    expect(paths).toEqual([
      '/',
      '/consultant',
      '/services',
      '/packages',
      '/build-package',
      '/sectors',
      '/suppliers',
      '/portfolio',
      '/about',
      '/contact',
    ])
    expect(paths.some((path) => path.startsWith('/owner') || path.startsWith('/dashboard') || path.startsWith('/workspace'))).toBe(
      false,
    )
  })

  it('keeps SEO management in the owner area only', () => {
    expect(ownerNavForRole('OWNER').some((item) => item.to === '/owner/seo')).toBe(true)
    expect(ownerNavForRole('ADMIN_MANAGER').some((item) => item.to === '/owner/seo')).toBe(true)
    expect(ownerNavForRole('OWNER').some((item) => item.to === '/owner/marketing')).toBe(true)
    expect(ownerNavForRole('OWNER').some((item) => item.to === '/owner/work-reviews')).toBe(true)
    expect(ownerNavForRole('OWNER').some((item) => item.to === '/owner/suppliers')).toBe(true)
  })
})
