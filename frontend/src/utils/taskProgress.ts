export function progressBarClass(percent: number): string {
  if (percent >= 100) {
    return 'bg-emerald-600'
  }
  if (percent >= 71) {
    return 'bg-sky-600'
  }
  if (percent >= 31) {
    return 'bg-amber-500'
  }
  return 'bg-red-600'
}

export function clampProgress(value: number): number {
  if (!Number.isFinite(value)) {
    return 0
  }
  return Math.max(0, Math.min(100, Math.round(value)))
}

export function formatProgressPercent(percent: number): string {
  return `${clampProgress(percent).toLocaleString('en-US')}%`
}
