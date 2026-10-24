import { useEffect, useState } from 'react'

/**
 * Distance the floating actions should rise so they sit above the footer
 * instead of covering it. Zero while the footer is still below the viewport.
 */
export function useFooterClearance(): number {
  const [clearance, setClearance] = useState(0)

  useEffect(() => {
    const footer = document.querySelector('footer')
    if (!footer) {
      return
    }

    let frame = 0

    const update = () => {
      cancelAnimationFrame(frame)
      frame = requestAnimationFrame(() => {
        const top = footer.getBoundingClientRect().top
        const overlap = window.innerHeight - top
        setClearance(overlap > 12 ? Math.ceil(overlap) : 0)
      })
    }

    update()
    window.addEventListener('scroll', update, { passive: true })
    window.addEventListener('resize', update)

    return () => {
      cancelAnimationFrame(frame)
      window.removeEventListener('scroll', update)
      window.removeEventListener('resize', update)
    }
  }, [])

  return clearance
}
