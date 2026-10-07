import { describe, expect, it } from 'vitest'
import { clampProgress, formatProgressPercent, progressBarClass } from './taskProgress'

describe('task progress', () => {
  it('colors the bar by percent bands', () => {
    expect(progressBarClass(0)).toBe('bg-red-600')
    expect(progressBarClass(30)).toBe('bg-red-600')
    expect(progressBarClass(31)).toBe('bg-amber-500')
    expect(progressBarClass(70)).toBe('bg-amber-500')
    expect(progressBarClass(71)).toBe('bg-sky-600')
    expect(progressBarClass(99)).toBe('bg-sky-600')
    expect(progressBarClass(100)).toBe('bg-emerald-600')
  })

  it('formats english digits', () => {
    expect(formatProgressPercent(65)).toBe('65%')
    expect(clampProgress(140)).toBe(100)
    expect(clampProgress(-4)).toBe(0)
  })
})