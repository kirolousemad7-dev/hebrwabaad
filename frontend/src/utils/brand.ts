/** Official full wordmark (Arabic + English + mark). */
export const BRAND_LOGO_SRC = '/brand/logo.png'

/** Geometric mark only — favicon, collapsed sidebar, watermark. */
export const BRAND_MARK_SRC = '/brand/mark.png'

export const BRAND_FAVICON_SRC = '/brand/favicon-32.png'
export const BRAND_APPLE_TOUCH_SRC = '/brand/apple-touch-icon.png'

export const BRAND_TAGLINE = 'نمنح أعمالك أبعادًا للنمو'

/** Exact values from Brand Guidelines PDF (Core Palette + Tints). */
export const BRAND_COLORS = {
  cobalt900: '#1733A3',
  cobalt700: '#2448D8',
  cobalt500: '#315CFF',
  cobalt300: '#7796FF',
  cobalt100: '#DCE5FF',
  ink900: '#111318',
  ink700: '#2A2E36',
  ink500: '#5F6673',
  ink300: '#A7ADB8',
  ink100: '#E7EAF0',
  paper: '#F7F5EF',
  success: '#138A5B',
  warning: '#D99300',
  error: '#D83A52',
  /** Aliases for older call sites */
  primary: '#315CFF',
  primaryHover: '#2448D8',
  black: '#111318',
  background: '#F7F5EF',
  surface: '#FFFFFF',
  border: '#E7EAF0',
  textMuted: '#5F6673',
} as const

/** Digital minimum sizes from Clear Space & Size. */
export const BRAND_LOGO_MIN_PX = 140
export const BRAND_MARK_MIN_PX = 24
