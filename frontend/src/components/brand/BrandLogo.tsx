import { Link } from 'react-router-dom'
import { APP_NAME } from '../../utils/constants'
import { BRAND_LOGO_SRC } from '../../utils/brand'

const sizeClass = {
  nav: 'h-12',
  sidebar: 'h-16',
  auth: 'h-28 sm:h-32',
  mark: 'h-20',
} as const

type BrandLogoProps = {
  size?: keyof typeof sizeClass
  to?: string | null
  className?: string
}

export function BrandLogo({ size = 'nav', to = '/', className = '' }: BrandLogoProps) {
  const image = (
    <img
      src={BRAND_LOGO_SRC}
      alt={APP_NAME}
      width={447}
      height={440}
      className={`w-auto object-contain object-center ${sizeClass[size]}`}
    />
  )

  if (!to) {
    return <span className={`inline-flex items-center ${className}`}>{image}</span>
  }

  return (
    <Link
      to={to}
      className={`inline-flex shrink-0 items-center focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 ${className}`}
    >
      {image}
    </Link>
  )
}
