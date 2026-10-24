import { useEffect, useState } from 'react'
import { BRAND_MARK_SRC } from '../../utils/brand'

type PackageVisualProps = {
  src: string
  name: string
  featured?: boolean
  variant?: 'card' | 'banner'
  onOpen?: () => void
}

export function PackageVisual({ src, name, featured = false, variant = 'card', onOpen }: PackageVisualProps) {
  const [failed, setFailed] = useState(false)

  useEffect(() => {
    setFailed(false)
  }, [src])

  const showImage = src !== '' && !failed
  const badge = featured ? (
    <span className="absolute top-3 inset-inline-start-3 z-10 rounded-full bg-white/95 px-3 py-1 text-xs font-medium text-brand-ink-700">
      موصى بها
    </span>
  ) : null

  if (!showImage) {
    if (variant === 'banner') {
      return null
    }

    return (
      <div className="relative aspect-[4/3] w-full overflow-hidden bg-[#F7F5EF]">
        <div className="absolute inset-0 bg-[linear-gradient(145deg,#F7F5EF_0%,#DCE5FF_58%,#F7F5EF_100%)]" aria-hidden="true" />
        <img
          src={BRAND_MARK_SRC}
          alt=""
          className="absolute top-1/2 left-1/2 h-14 w-auto -translate-x-1/2 -translate-y-1/2 object-contain sm:h-16"
        />
        {badge}
      </div>
    )
  }

  const image = (
    <img
      src={src}
      alt={variant === 'card' ? '' : name}
      className={
        variant === 'banner'
          ? 'max-h-52 w-full max-w-full object-contain sm:max-h-72'
          : 'h-full w-full object-cover object-center'
      }
      loading="lazy"
      width={640}
      height={480}
      onError={() => setFailed(true)}
    />
  )

  if (variant === 'banner') {
    return (
      <div className="flex w-full items-center justify-center overflow-hidden bg-[#F7F5EF]">
        {image}
      </div>
    )
  }

  return (
    <div className="relative aspect-[4/3] w-full overflow-hidden bg-brand-ink-100">
      {onOpen ? (
        <button type="button" onClick={onOpen} className="block h-full w-full" aria-label={`صورة ${name}`}>
          {image}
        </button>
      ) : (
        image
      )}
      {badge}
    </div>
  )
}
