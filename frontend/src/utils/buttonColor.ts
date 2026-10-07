const HEX = /^#(?:[0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/

export function normalizeButtonColor(value: string | null | undefined): string | null {
  const trimmed = value?.trim() ?? ''
  if (!HEX.test(trimmed)) {
    return null
  }

  return trimmed
}

/** Readable label color for a custom button background. */
export function buttonTextColor(background: string): string {
  const hex = normalizeButtonColor(background)
  if (!hex) {
    return '#ffffff'
  }

  const full = hex.length === 4
    ? `#${hex[1]}${hex[1]}${hex[2]}${hex[2]}${hex[3]}${hex[3]}`
    : hex
  const red = Number.parseInt(full.slice(1, 3), 16)
  const green = Number.parseInt(full.slice(3, 5), 16)
  const blue = Number.parseInt(full.slice(5, 7), 16)
  const luminance = (0.299 * red + 0.587 * green + 0.114 * blue) / 255

  return luminance > 0.62 ? '#111318' : '#ffffff'
}

export function buttonColorStyle(value: string | null | undefined): { backgroundColor: string; color: string } | undefined {
  const background = normalizeButtonColor(value)
  if (!background) {
    return undefined
  }

  return {
    backgroundColor: background,
    color: buttonTextColor(background),
  }
}
