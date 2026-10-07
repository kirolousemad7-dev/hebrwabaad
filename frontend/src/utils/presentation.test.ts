import { describe, expect, it } from 'vitest'
import { buttonColorStyle, buttonTextColor } from './buttonColor'
import { formatEnglishAmount, formatMoney } from './catalog'
import { riyalGlyphSupported } from './riyalMark'

describe('presentation helpers', () => {
  it('formats prices with English digits and the riyal sign', () => {
    expect(formatEnglishAmount('1500.5')).toBe('1,500.5')
    expect(formatMoney(1500)).toContain('1,500')
    expect(formatMoney(1500)).toContain('⃁')
    expect(riyalGlyphSupported()).toBe(false)
  })

  it('picks readable button text and ignores invalid colors', () => {
    expect(buttonTextColor('#111111')).toBe('#ffffff')
    expect(buttonTextColor('#ffffff')).toBe('#111318')
    expect(buttonColorStyle('red')).toBeUndefined()
    expect(buttonColorStyle('#315CFF')?.backgroundColor).toBe('#315CFF')
  })
})
