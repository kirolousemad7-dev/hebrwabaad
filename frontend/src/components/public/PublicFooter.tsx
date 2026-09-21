import { Link, useLocation } from 'react-router-dom'
import { BrandLogo } from '../brand/BrandLogo'
import { useAuth } from '../../context/AuthContext'
import { usePlatformSettings } from '../../context/PlatformSettingsContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import { listPublicCmsFooterPages } from '../../services/cmsPages'
import { groupFooterPages } from '../../utils/cmsPages'
import { LANDING_SECTION_NAV } from '../../utils/publicNav'

const SOCIAL_LABELS: Record<string, string> = {
  instagram: 'Instagram',
  facebook: 'Facebook',
  tiktok: 'TikTok',
  x: 'X',
  twitter: 'X',
  linkedin: 'LinkedIn',
  youtube: 'YouTube',
  snapchat: 'Snapchat',
  whatsapp: 'WhatsApp',
  behance: 'Behance',
  dribbble: 'Dribbble',
  pinterest: 'Pinterest',
}

export function PublicFooter() {
  const location = useLocation()
  const { isAuthenticated } = useAuth()
  const { settings, navigation } = usePlatformSettings()
  const useLandingSections = location.pathname === '/' && !isAuthenticated
  const brandName = settings.brand.name_ar || 'حبر وأبعاد'
  const footerDescription =
    settings.website.footer_description || settings.business.short_description || ''
  const showSocial = settings.website.show_social_in_footer && settings.social.length > 0
  const showContact = settings.website.show_contact_in_footer
  const showQuickLinks = settings.website.show_quick_links

  const { state: cmsFooterState } = useAsyncData(listPublicCmsFooterPages, [])
  const cmsGroups = cmsFooterState.status === 'ready' ? groupFooterPages(cmsFooterState.data) : []

  return (
    <footer className="mt-auto border-t border-brand-ink-100 bg-white">
      <div className="marketing-container flex flex-col gap-10 py-14">
        <div className="flex flex-col gap-8 lg:flex-row lg:items-start lg:justify-between">
          <div className="max-w-sm space-y-3">
            <BrandLogo size="nav" />
            {footerDescription ? (
              <p className="text-sm leading-7 text-brand-ink-500">{footerDescription}</p>
            ) : null}
            {settings.brand.tagline ? (
              <p className="text-xs text-brand-ink-300">{settings.brand.tagline}</p>
            ) : null}
          </div>
          <nav
            aria-label="روابط تذييل الموقع"
            className={`grid gap-8 text-sm ${
              cmsGroups.length > 0 ? 'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4' : 'sm:grid-cols-2'
            }`}
          >
            {showQuickLinks ? (
              <div>
                <p className="mb-3 text-xs font-semibold tracking-wide text-brand-ink-900">
                  {useLandingSections ? 'الموقع' : 'المنصة'}
                </p>
                <ul className="flex flex-col gap-2.5 text-brand-ink-500">
                  {useLandingSections
                    ? LANDING_SECTION_NAV.filter((item) => {
                        if (item.id === 'suppliers' && settings.website.features.show_suppliers === false) {
                          return false
                        }
                        if (item.id === 'services' && settings.website.features.show_services === false) {
                          return false
                        }
                        if (item.id === 'packages' && settings.website.features.show_packages === false) {
                          return false
                        }
                        if (
                          item.id === 'build-package' &&
                          settings.website.features.show_build_package === false
                        ) {
                          return false
                        }
                        if (item.id === 'portfolio' && settings.website.features.show_portfolio === false) {
                          return false
                        }
                        return true
                      }).map((item) => (
                        <li key={item.id}>
                          <a
                            href={`#${item.id}`}
                            className="transition hover:text-brand-ink-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500"
                            onClick={(event) => {
                              event.preventDefault()
                              document.getElementById(item.id)?.scrollIntoView({ behavior: 'smooth' })
                            }}
                          >
                            {item.label}
                          </a>
                        </li>
                      ))
                    : navigation.map((item) => (
                        <li key={item.id}>
                          <Link
                            to={item.path}
                            className="transition hover:text-brand-ink-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500"
                          >
                            {item.label}
                          </Link>
                        </li>
                      ))}
                </ul>
              </div>
            ) : null}
            {cmsGroups.map((group) => (
              <div key={group.group}>
                <p className="mb-3 text-xs font-semibold tracking-wide text-brand-ink-900">{group.group}</p>
                <ul className="flex flex-col gap-2.5 text-brand-ink-500">
                  {group.pages.map((page) => (
                    <li key={page.id}>
                      <Link
                        to={page.path || `/${page.slug}`}
                        className="transition hover:text-brand-ink-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500"
                      >
                        {page.title}
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
            <div>
              <p className="mb-3 text-xs font-semibold tracking-wide text-brand-ink-900">الحساب</p>
              <ul className="flex flex-col gap-2.5 text-brand-ink-500">
                <li>
                  <Link
                    to="/login"
                    className="transition hover:text-brand-ink-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500"
                  >
                    تسجيل الدخول
                  </Link>
                </li>
                <li>
                  <Link
                    to="/register"
                    className="transition hover:text-brand-ink-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cobalt-500"
                  >
                    إنشاء حساب
                  </Link>
                </li>
              </ul>
              {showContact && (settings.contact.phone || settings.contact.email) ? (
                <div className="mt-5 space-y-1.5 text-brand-ink-500">
                  <p className="text-xs font-semibold tracking-wide text-brand-ink-900">تواصل</p>
                  {settings.contact.phone ? <p>{settings.contact.phone}</p> : null}
                  {settings.contact.email ? <p>{settings.contact.email}</p> : null}
                  {settings.contact.whatsapp_url ? (
                    <a
                      href={settings.contact.whatsapp_url}
                      target="_blank"
                      rel="noreferrer"
                      className="text-brand-cobalt-700 hover:underline"
                    >
                      واتساب
                    </a>
                  ) : null}
                </div>
              ) : null}
              {showSocial ? (
                <ul className="mt-5 flex flex-wrap gap-3 text-brand-ink-500">
                  {settings.social.map((item) => (
                    <li key={`${item.platform}-${item.url}`}>
                      <a href={item.url} target="_blank" rel="noreferrer" className="hover:text-brand-ink-900">
                        {SOCIAL_LABELS[item.platform] || item.platform}
                      </a>
                    </li>
                  ))}
                </ul>
              ) : null}
            </div>
          </nav>
        </div>
        <div className="flex items-center justify-between gap-4 border-t border-brand-ink-100 pt-6">
          <p className="text-xs text-brand-ink-300">
            {settings.website.copyright_text ||
              `© ${new Date().getFullYear()} ${brandName}. جميع الحقوق محفوظة.`}
          </p>
          <span aria-hidden="true" className="hidden h-1.5 w-8 rounded-full bg-brand-cobalt-500 sm:block" />
        </div>
      </div>
    </footer>
  )
}
