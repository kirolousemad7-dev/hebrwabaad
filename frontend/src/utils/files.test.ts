import { describe, expect, it } from 'vitest'
import { FILE_COPY, fileContextLabel, filesPathForRole, formatFileSize } from './files'
import type { ManagedFileItem } from '../types/api'

const sample: ManagedFileItem = {
  id: 4,
  original_name: 'Brand-Guidelines.pdf',
  mime_type: 'application/pdf',
  extension: 'pdf',
  size: 2_400_000,
  can_preview: true,
  created_at: '2026-09-03T00:00:00+00:00',
  project: { id: 12, title: 'هوية المطعم' },
  order: null,
  task: null,
}

describe('files', () => {
  it('formats size and context without exposing storage paths', () => {
    expect(formatFileSize(512)).toContain('بايت')
    expect(formatFileSize(2048)).toContain('ك.ب')
    expect(formatFileSize(sample.size)).toContain('م.ب')
    expect(fileContextLabel(sample)).toBe('هوية المطعم')
    expect(fileContextLabel({ ...sample, project: null, order: { id: 21, reference: 'HEBR-ORD-000021' } })).toBe(
      'HEBR-ORD-000021',
    )
    expect(FILE_COPY.empty).toBe('لا توجد ملفات بعد.')
    expect(FILE_COPY.error).toBe('تعذر تحميل الملفات.')
  })

  it('keeps file routes on live role destinations only', () => {
    expect(filesPathForRole('CUSTOMER')).toBe('/dashboard/files')
    expect(filesPathForRole('OWNER')).toBe('/owner/files')
    expect(filesPathForRole('WEB_DEVELOPER', true)).toBe('/workspace/files')
    expect(filesPathForRole('HR', false)).toBeNull()
  })
})
