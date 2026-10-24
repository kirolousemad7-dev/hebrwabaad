import { Link } from 'react-router-dom'
import { BrandLogo } from '../brand/BrandLogo'
import { usePlatformSettings } from '../../context/PlatformSettingsContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import { listPublicCmsFooterPages } from '../../services/cmsPages'
import { groupFooterPages } from '../../utils/cmsPages'
import { WHATSAPP_CHAT_URL, WHATSAPP_DISPLAY } from '../../utils/whatsapp'

const IDENTITY_LINE = 'نصنع حضور علامتك من الفكرة حتى التسليم'

const MAIN_LINKS = [
  { to: '/consultant', label: 'اكتشف احتياجك', feature: 'show_consultant' },
  { to: '/services', label: 'الخدمات', feature: 'show_services' },
  { to: '/packages', label: 'الباقات', feature: 'show_packages' },
  { to: '/build-package', label: 'صمّم باقتك', feature: 'show_build_package' },
  { to: '/portfolio', label: 'أعمالنا', feature: 'show_portfolio' },
  { to: '/suppliers', label: 'الموردين', feature: 'show_suppliers' },
  { to: '/about', label: 'من نحن' },
] as const

const LEGAL_LINKS = [
  { to: '/contact', label: 'تواصل معنا' },
  { to: '/golden-warranty', label: 'الضمان الذهبي' },
  { to: '/terms-and-conditions', label: 'الشروط والأحكام' },
  { to: '/privacy-policy', label: 'سياسة الخصوصية' },
  { to: '/shipping-policy', label: 'سياسة الشحن والتوصيل' },
  { to: '/returns-and-refunds', label: 'سياسة الاستبدال والاسترجاع والإلغاء' },
  { to: '/login', label: 'تسجيل الدخول' },
] as const

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

function WhatsAppIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-5 w-5" aria-hidden="true" fill="currentColor">
      <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z" />
    </svg>
  )
}

export function PublicFooter() {
  const { settings } = usePlatformSettings()
  const brandName = settings.brand.name_ar || 'حبر وأبعاد'
  const features = settings.website.features
  const showSocial = settings.website.show_social_in_footer && settings.social.length > 0

  const { state: cmsFooterState } = useAsyncData(listPublicCmsFooterPages, [])
  const cmsPages = cmsFooterState.status === 'ready' ? groupFooterPages(cmsFooterState.data).flatMap((group) => group.pages) : []
  const knownPaths = new Set<string>([...MAIN_LINKS.map((item) => item.to), ...LEGAL_LINKS.map((item) => item.to)])
  const extraPages = cmsPages.filter((page) => {
    const path = page.path || `/${page.slug}`
    return !knownPaths.has(path)
  })

  const mainLinks = MAIN_LINKS.filter((item) => {
    if (!('feature' in item) || !item.feature) {
      return true
    }
    return features[item.feature] !== false
  })

  return (
    <footer className="mt-auto bg-brand-ink-900 text-white">
      <div className="mx-auto w-full max-w-[1180px] px-4 py-10 sm:px-6 sm:py-12 lg:px-8">
        <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-12 lg:gap-8">
          <div className="space-y-4 sm:col-span-2 lg:col-span-4">
            <BrandLogo size="footer" tone="on-dark" className="!p-0" />
            <p className="max-w-xs text-sm leading-7 text-white/75">{IDENTITY_LINE}</p>
          </div>

          <nav aria-label="روابط رئيسية" className="lg:col-span-3">
            <h2 className="text-sm font-semibold text-white">روابط رئيسية</h2>
            <ul className="mt-4 space-y-2.5 text-sm text-white/75">
              {mainLinks.map((item) => (
                <li key={item.to}>
                  <Link to={item.to} className="transition hover:text-white">
                    {item.label}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>

          <nav aria-label="روابط قانونية وسياسات" className="lg:col-span-3">
            <h2 className="text-sm font-semibold text-white">سياسات</h2>
            <ul className="mt-4 space-y-2.5 text-sm text-white/75">
              {LEGAL_LINKS.map((item) => (
                <li key={item.to}>
                  <Link to={item.to} className="transition hover:text-white">
                    {item.label}
                  </Link>
                </li>
              ))}
              {extraPages.map((page) => (
                <li key={page.id}>
                  <Link to={page.path || `/${page.slug}`} className="transition hover:text-white">
                    {page.title}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>

          <div className="lg:col-span-2">
            <h2 className="text-sm font-semibold text-white">تواصل</h2>
            <a
              href={WHATSAPP_CHAT_URL}
              target="_blank"
              rel="noreferrer"
              className="mt-4 inline-flex w-full items-center gap-3 rounded-2xl border border-white/15 bg-white/5 px-3 py-2.5 text-white transition hover:border-white/30 hover:bg-white/10"
            >
              <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#25D366] text-white">
                <WhatsAppIcon />
              </span>
              <span className="min-w-0 text-start">
                <span className="block text-xs text-white/60">واتساب</span>
                <span dir="ltr" className="block text-sm font-semibold tracking-wide">
                  {WHATSAPP_DISPLAY}
                </span>
              </span>
            </a>
            {settings.contact.email ? (
              <a href={`mailto:${settings.contact.email}`} className="mt-3 block text-sm text-white/70 hover:text-white">
                {settings.contact.email}
              </a>
            ) : null}
            {showSocial ? (
              <ul className="mt-4 flex flex-wrap gap-x-3 gap-y-2 text-xs text-white/60">
                {settings.social.map((item) => (
                  <li key={`${item.platform}-${item.url}`}>
                    <a href={item.url} target="_blank" rel="noreferrer" className="hover:text-white">
                      {SOCIAL_LABELS[item.platform] || item.platform}
                    </a>
                  </li>
                ))}
              </ul>
            ) : null}
          </div>
        </div>

        {(settings.business.commercial_register || settings.business.tax_number) ? (
          <p className="mt-8 flex flex-wrap items-center gap-2 text-xs text-white/55">
            <span aria-label="علم السعودية">🇸🇦</span>
            {settings.business.commercial_register ? (
              <span dir="ltr">CR {settings.business.commercial_register}</span>
            ) : null}
            {settings.business.tax_number ? <span dir="ltr">VAT {settings.business.tax_number}</span> : null}
          </p>
        ) : null}

        <p className="mt-6 border-t border-white/10 pt-4 text-xs text-white/45">
          {settings.website.copyright_text || `© ${new Date().getFullYear()} ${brandName}. جميع الحقوق محفوظة.`}
        </p>
      </div>
    </footer>
  )
}
