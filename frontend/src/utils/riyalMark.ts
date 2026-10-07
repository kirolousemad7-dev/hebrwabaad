let cached: boolean | null = null

/**
 * True when the browser paints U+20C1 as its own glyph.
 * Identical pixels to the replacement character means the font has no riyal mark.
 */
export function riyalGlyphSupported(): boolean {
  if (cached !== null) {
    return cached
  }

  if (typeof document === 'undefined') {
    cached = false
    return false
  }

  try {
    const canvas = document.createElement('canvas')
    const context = canvas.getContext('2d')
    if (!context) {
      cached = false
      return false
    }

    canvas.width = 64
    canvas.height = 32
    context.font = '24px sans-serif'
    context.textBaseline = 'middle'
    context.fillStyle = '#000'

    const paint = (text: string): Uint8ClampedArray => {
      context.clearRect(0, 0, canvas.width, canvas.height)
      context.fillText(text, 4, 16)
      return context.getImageData(0, 0, canvas.width, canvas.height).data
    }

    const glyph = paint('\u20C1')
    const missing = paint('\uFFFD')
    let painted = false
    let different = false

    for (let index = 3; index < glyph.length; index += 4) {
      if (glyph[index] > 0) {
        painted = true
      }
      if (glyph[index] !== missing[index]) {
        different = true
      }
      if (painted && different) {
        break
      }
    }

    cached = painted && different
  } catch {
    cached = false
  }

  return cached
}
