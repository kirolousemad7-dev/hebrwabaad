import { Link } from 'react-router-dom'
import { usePlatformSettings } from '../../context/PlatformSettingsContext'
import { BRAND_LOGO_MIN_PX, BRAND_LOGO_SRC, BRAND_MARK_MIN_PX, BRAND_MARK_SRC } from '../../utils/brand'
import { resolveMediaUrl } from '../../utils/mediaUrl'

const sizeClass = {
  nav: 'h-11 sm:h-12 min-w-[8.75rem]',
  sidebar: 'h-14 min-w-[8.75rem]',
  auth: 'h-24 sm:h-28 min-w-[8.75rem]',
  mark: 'h-16 sm:h-20',
  symbol: 'h-9 w-9 min-h-6 min-w-6',
} as const

type BrandLogoProps = {
  size?: keyof typeof sizeClass
  /** Use geometric mark only (collapsed sidebar / tight spaces). */
  markOnly?: boolean
  to?: string | null
  className?: string
}

export function BrandLogo({
  size = 'nav',
  markOnly = false,
  to = '/',
  className = '',
}: BrandLogoProps) {
  const { settings } = usePlatformSettings()
  const fallbackLogo = BRAND_LOGO_SRC
  const fallbackMark = BRAND_MARK_SRC
  const configuredLogo = resolveMediaUrl(settings.brand.logo_url) || fallbackLogo
  const configuredMark = resolveMediaUrl(settings.brand.mark_url) || fallbackMark
  const src = markOnly || size === 'symbol' ? configuredMark : configuredLogo
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
          : size === 'nav' || size === 'sidebar' || size === 'auth'
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
