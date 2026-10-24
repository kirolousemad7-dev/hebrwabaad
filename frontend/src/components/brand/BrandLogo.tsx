import { Link } from 'react-router-dom'
import { usePlatformSettings } from '../../context/PlatformSettingsContext'
import {
  BRAND_LOGO_MIN_PX,
  BRAND_LOGO_ON_DARK_SRC,
  BRAND_LOGO_SRC,
  BRAND_LOGO_TRANSPARENT_SRC,
  BRAND_MARK_MIN_PX,
  BRAND_MARK_SRC,
} from '../../utils/brand'
import { resolveMediaUrl } from '../../utils/mediaUrl'

const sizeClass = {
  nav: 'h-14 w-auto sm:h-16',
  hero: 'h-20 w-auto sm:h-24',
  footer: 'h-20 w-auto',
  sidebar: 'h-14 min-w-[8.75rem]',
  auth: 'h-24 sm:h-28 min-w-[8.75rem]',
  mark: 'h-16 sm:h-20',
  symbol: 'h-9 w-9 min-h-6 min-w-6',
} as const

type BrandLogoProps = {
  size?: keyof typeof sizeClass
  /** Use geometric mark only (collapsed sidebar / tight spaces). */
  markOnly?: boolean
  /** Light wordmark for the dark footer. Stock logo only; custom uploads stay as provided. */
  tone?: 'default' | 'on-dark'
  to?: string | null
  className?: string
}

function isStockWordmark(url: string): boolean {
  return url === BRAND_LOGO_SRC || url.endsWith('/brand/logo.png')
}

export function BrandLogo({
  size = 'nav',
  markOnly = false,
  tone = 'default',
  to = '/',
  className = '',
}: BrandLogoProps) {
  const { settings } = usePlatformSettings()
  const fallbackLogo = tone === 'on-dark' ? BRAND_LOGO_ON_DARK_SRC : BRAND_LOGO_TRANSPARENT_SRC
  const fallbackMark = BRAND_MARK_SRC
  const configuredLogo = resolveMediaUrl(settings.brand.logo_url) || BRAND_LOGO_SRC
  const configuredMark = resolveMediaUrl(settings.brand.mark_url) || fallbackMark
  const wordmark = isStockWordmark(configuredLogo)
    ? tone === 'on-dark'
      ? BRAND_LOGO_ON_DARK_SRC
      : BRAND_LOGO_TRANSPARENT_SRC
    : configuredLogo
  const src = markOnly || size === 'symbol' ? configuredMark : wordmark
  const resolvedSize = markOnly && size === 'nav' ? 'symbol' : size
  const isMark = markOnly || size === 'symbol'
  const altName = settings.brand.name_ar || 'حبر وأبعاد'

  const image = (
    <img
      src={src}
      alt={isMark ? altName : `شعار ${altName}`}
      width={isMark ? 371 : 1024}
      height={isMark ? 347 : 1024}
      className={`w-auto object-contain object-center ${sizeClass[resolvedSize]}`}
      style={
        isMark
          ? { minWidth: BRAND_MARK_MIN_PX, minHeight: BRAND_MARK_MIN_PX }
          : size === 'sidebar' || size === 'auth'
            ? { minWidth: Math.min(BRAND_LOGO_MIN_PX, 140) }
            : undefined
      }
      decoding="async"
      onError={(event) => {
        const target = event.currentTarget
        const fallback = isMark ? fallbackMark : fallbackLogo
        if (target.src.endsWith(fallback) || target.getAttribute('data-fallback') === '1') {
          return
        }
        target.setAttribute('data-fallback', '1')
        target.src = fallback
      }}
    />
  )

  const shellClass = `brand-logo-clearspace inline-flex shrink-0 items-center ${className}`

  if (!to) {
    return <span className={shellClass}>{image}</span>
  }

  return (
    <Link
      to={to}
      className={`${shellClass} focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary`}
    >
      {image}
    </Link>
  )
}
