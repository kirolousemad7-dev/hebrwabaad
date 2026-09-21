import { describe, expect, it } from 'vitest'
import { resolveMediaUrl } from './mediaUrl'

describe('resolveMediaUrl', () => {
  it('passes through absolute and data URLs', () => {
    expect(resolveMediaUrl('https://cdn.example.com/a.jpg')).toBe('https://cdn.example.com/a.jpg')
    expect(resolveMediaUrl('data:image/png;base64,abc')).toBe('data:image/png;base64,abc')
  })

  it('keeps frontend public assets relative', () => {
    expect(resolveMediaUrl('/brand/logo.png')).toBe('/brand/logo.png')
  })

  it('prefixes backend storage paths with the API origin in development', () => {
    expect(resolveMediaUrl('/storage/portfolio/work.jpg')).toBe('http://127.0.0.1:8000/storage/portfolio/work.jpg')
    expect(resolveMediaUrl('/uploads/portfolio/work.jpg')).toBe('http://127.0.0.1:8000/uploads/portfolio/work.jpg')
    expect(resolveMediaUrl('/api/media/12/file')).toBe('http://127.0.0.1:8000/api/media/12/file')
  })

  it('returns empty string for blank values', () => {
    expect(resolveMediaUrl(null)).toBe('')
    expect(resolveMediaUrl('   ')).toBe('')
  })
})
