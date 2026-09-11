import { Link, useLocation } from 'react-router-dom'
import { BrandLogo } from '../brand/BrandLogo'
import { useAuth } from '../../context/AuthContext'
import { usePlatformSettings } from '../../context/PlatformSettingsContext'
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

  return (
    <footer className="mt-auto border-t border-slate-200 bg-white">
      <div className="mx-auto flex max-w-6xl flex-col gap-8 px-4 py-10 sm:px-6">
        <div className="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
          <div className="space-y-2">
            <BrandLogo size="nav" />
            {footerDescription ? (
              <p className="max-w-sm text-sm leading-7 text-slate-600">{footerDescription}</p>
            ) : null}
            {settings.brand.tagline ? (
              <p className="text-xs text-slate-500">{settings.brand.tagline}</p>
            ) : null}
          </div>
          <nav aria-label="روابط تذييل الموقع" className="grid gap-6 text-sm sm:grid-cols-2">
            {showQuickLinks ? (
              <div>
                <p className="mb-2 font-semibold text-slate-900">
                  {useLandingSections ? 'الموقع' : 'المنصة'}
                </p>
                <ul className="flex flex-col gap-2 text-slate-600">
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
                            className="hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
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
                            className="hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                          >
                            {item.label}
                          </Link>
                        </li>
                      ))}
                </ul>
              </div>
            ) : null}
            <div>
              <p className="mb-2 font-semibold text-slate-900">الحساب</p>
              <ul className="flex flex-col gap-2 text-slate-600">
                <li>
                  <Link
                    to="/login"
                    className="hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                  >
                    تسجيل الدخول
                  </Link>
                </li>
                <li>
                  <Link
                    to="/register"
                    className="hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                  >
                    إنشاء حساب
                  </Link>
                </li>
              </ul>
              {showContact && (settings.contact.phone || settings.contact.email) ? (
                <div className="mt-4 space-y-1 text-slate-600">
                  <p className="font-semibold text-slate-900">تواصل</p>
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
                <ul className="mt-4 flex flex-wrap gap-3 text-slate-600">
                  {settings.social.map((item) => (
                    <li key={`${item.platform}-${item.url}`}>
                      <a
                        href={item.url}
                        target="_blank"
                        rel="noreferrer"
                        className="hover:text-slate-900"
                      >
                        {SOCIAL_LABELS[item.platform] || item.platform}
                      </a>
                    </li>
                  ))}
                </ul>
              ) : null}
            </div>
          </nav>
        </div>
        <p className="text-xs text-slate-500">
          {settings.website.copyright_text ||
            `© ${new Date().getFullYear()} ${brandName}. جميع الحقوق محفوظة.`}
        </p>
      </div>
    </footer>
  )
}
