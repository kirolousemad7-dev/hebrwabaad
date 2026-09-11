import { lazy, Suspense, useEffect, useState } from 'react'
import { BRAND_LOGO_SRC } from '../../utils/brand'

const HeroThreeScene = lazy(() => import('./HeroThreeScene'))

function canUseWebGl(): boolean {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return false
  }

  if (window.matchMedia('(max-width: 1023px)').matches || window.matchMedia('(hover: none)').matches) {
    return false
  }

  try {
    const canvas = document.createElement('canvas')
    return Boolean(canvas.getContext('webgl') || canvas.getContext('experimental-webgl'))
  } catch {
    return false
  }
}

export function HeroVisual() {
  const [mode, setMode] = useState<'css' | 'webgl'>('css')

  useEffect(() => {
    setMode(canUseWebGl() ? 'webgl' : 'css')
  }, [])

  return (
    <div className="relative isolate h-[min(28rem,70vw)] w-full max-w-xl" aria-hidden="true">
      <div className="hero-orb hero-orb-gold" />
      <div className="hero-orb hero-orb-teal" />
      <div className="hero-orb hero-orb-navy" />
      {mode === 'webgl' ? (
        <Suspense fallback={null}>
          <HeroThreeScene />
        </Suspense>
      ) : (
        <img
          src={BRAND_LOGO_SRC}
          alt=""
          className="hero-logo-float absolute left-1/2 top-1/2 w-[min(16rem,62%)] -translate-x-1/2 -translate-y-1/2 object-contain drop-shadow-2xl"
        />
      )}
    </div>
  )
}
