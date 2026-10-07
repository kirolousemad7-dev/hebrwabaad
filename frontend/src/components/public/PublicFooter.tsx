import { Link, useLocation } from 'react-router-dom'
import { BrandLogo } from '../brand/BrandLogo'
import { useAuth } from '../../context/AuthContext'
import { usePlatformSettings } from '../../context/PlatformSettingsContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import { listPublicCmsFooterPages } from '../../services/cmsPages'
import { groupFooterPages } from '../../utils/cmsPages'
import { LANDING_SECTION_NAV } from '../../utils/publicNav'
import { WHATSAPP_CHAT_URL, WHATSAPP_DISPLAY } from '../../utils/whatsapp'

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
  const showQuickLinks = settings.website.show_quick_links

  const { state: cmsFooterState } = useAsyncData(listPublicCmsFooterPages, [])
  const cmsGroups = cmsFooterState.status === 'ready' ? groupFooterPages(cmsFooterState.data) : []

  return (
    <footer className="mt-auto bg-brand-ink-900 text-white">
      <div className="marketing-container flex flex-col items-center gap-4 py-6 text-center">
        <BrandLogo size="nav" className="brightness-0 invert" />
        {footerDescription ? <p className="max-w-xl text-sm leading-7 text-white/70">{footerDescription}</p> : null}
        <div className="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-sm" dir="ltr">
          <a
            href={WHATSAPP_CHAT_URL}
            target="_blank"
            rel="noreferrer"
            className="font-semibold text-white"
          >
            WhatsApp {WHATSAPP_DISPLAY}
          </a>
          {settings.contact.email ? <span className="text-white/70">{settings.contact.email}</span> : null}
        </div>
        {(settings.business.commercial_register || settings.business.tax_number) ? (
          <p className="flex flex-wrap items-center justify-center gap-2 text-xs text-white/70">
            <span aria-label="علم السعودية">🇸🇦</span>
            {settings.business.commercial_register ? (
              <span dir="ltr">CR {settings.business.commercial_register}</span>
            ) : null}
            {settings.business.tax_number ? <span dir="ltr">VAT {settings.business.tax_number}</span> : null}
          </p>
        ) : null}
        {showSocial ? (
          <ul className="flex flex-wrap justify-center gap-3 text-sm text-white/75">
            {settings.social.map((item) => (
              <li key={`${item.platform}-${item.url}`}>
                <a href={item.url} target="_blank" rel="noreferrer" className="hover:text-white">
                  {SOCIAL_LABELS[item.platform] || item.platform}
                </a>
              </li>
            ))}
          </ul>
        ) : null}
        <nav aria-label="روابط تذييل الموقع" className="flex max-w-3xl flex-wrap justify-center gap-x-4 gap-y-2 text-sm text-white/70">
          {showQuickLinks
            ? (useLandingSections
                ? LANDING_SECTION_NAV.filter((item) => {
                    if (item.id === 'suppliers' && settings.website.features.show_suppliers === false) return false
                    if (item.id === 'services' && settings.website.features.show_services === false) return false
                    if (item.id === 'packages' && settings.website.features.show_packages === false) return false
                    if (item.id === 'build-package' && settings.website.features.show_build_package === false) return false
                    if (item.id === 'portfolio' && settings.website.features.show_portfolio === false) return false
                    return true
                  }).map((item) => (
                    <a
                      key={item.id}
                      href={`#${item.id}`}
                      onClick={(event) => {
                        event.preventDefault()
                        document.getElementById(item.id)?.scrollIntoView({ behavior: 'smooth' })
                      }}
                    >
                      {item.label}
                    </a>
                  ))
                : navigation.map((item) => (
                    <Link key={item.id} to={item.path}>
                      {item.label}
                    </Link>
                  )))
            : null}
          {cmsGroups.flatMap((group) =>
            group.pages.map((page) => (
              <Link key={page.id} to={page.path || `/${page.slug}`}>
                {page.title}
              </Link>
            )),
          )}
          <Link to="/login">تسجيل الدخول</Link>
        </nav>
        <p className="text-xs text-white/45">
          {settings.website.copyright_text || `© ${new Date().getFullYear()} ${brandName}. جميع الحقوق محفوظة.`}
        </p>
      </div>
    </footer>
  )
}
