import { useEffect } from 'react'
import { createPortal } from 'react-dom'

type MediaLightboxProps = {
  open: boolean
  title?: string
  imageSrc?: string | null
  videoSrc?: string | null
  onClose: () => void
}

function embedUrl(url: string): string | null {
  const youtube = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([\w-]{6,})/)
  if (youtube?.[1]) {
    return `https://www.youtube.com/embed/${youtube[1]}`
  }

  const vimeo = url.match(/vimeo\.com\/(?:video\/)?(\d+)/)
  if (vimeo?.[1]) {
    return `https://player.vimeo.com/video/${vimeo[1]}`
  }

  return null
}

export function MediaLightbox({ open, title, imageSrc, videoSrc, onClose }: MediaLightboxProps) {
  useEffect(() => {
    if (!open) {
      return
    }

    function onKey(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        onClose()
      }
    }

    const previous = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    window.addEventListener('keydown', onKey)

    return () => {
      document.body.style.overflow = previous
      window.removeEventListener('keydown', onKey)
    }
  }, [open, onClose])

  if (!open) {
    return null
  }

  const embed = videoSrc ? embedUrl(videoSrc) : null

  return createPortal(
    <div className="fixed inset-0 z-[80] flex items-center justify-center bg-black/85 p-4 pt-16" role="presentation" onClick={onClose}>
      <button
        type="button"
        onClick={onClose}
        className="fixed top-3 end-3 z-[81] inline-flex min-h-11 items-center rounded-full bg-white px-4 text-sm font-semibold text-slate-900 shadow-lg"
      >
        إغلاق
      </button>
      <div
        role="dialog"
        aria-modal="true"
        aria-label={title || 'عرض الوسائط'}
        className="relative max-h-[85vh] w-full max-w-5xl"
        onClick={(event) => event.stopPropagation()}
      >
        {embed ? (
          <iframe
            title={title || 'فيديو'}
            src={embed}
            className="aspect-video w-full rounded-2xl bg-black"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowFullScreen
          />
        ) : videoSrc ? (
          <video src={videoSrc} controls playsInline className="max-h-[85vh] w-full rounded-2xl bg-black" />
        ) : imageSrc ? (
          <img src={imageSrc} alt={title || ''} className="max-h-[85vh] w-full rounded-2xl object-contain" />
        ) : null}
      </div>
    </div>,
    document.body,
  )
}
