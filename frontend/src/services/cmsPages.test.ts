import { describe, expect, it } from 'vitest'
import {
  CMS_PAGE_TYPE_OPTIONS,
  createOwnerCmsPage,
  deleteOwnerCmsPage,
  getOwnerCmsPage,
  getPublicCmsPage,
  listOwnerCmsPages,
  listPublicCmsFooterPages,
  publishOwnerCmsPage,
  updateOwnerCmsPage,
  updateOwnerCmsPageFooter,
} from './cmsPages'

describe('cmsPages service', () => {
  it('exports CMS page type options', () => {
    expect(CMS_PAGE_TYPE_OPTIONS.some((option) => option.value === 'ABOUT')).toBe(true)
    expect(CMS_PAGE_TYPE_OPTIONS.some((option) => option.value === 'POLICY')).toBe(true)
  })

  it('exports public and owner API helpers', () => {
    expect(typeof getPublicCmsPage).toBe('function')
    expect(typeof listPublicCmsFooterPages).toBe('function')
    expect(typeof listOwnerCmsPages).toBe('function')
    expect(typeof getOwnerCmsPage).toBe('function')
    expect(typeof createOwnerCmsPage).toBe('function')
    expect(typeof updateOwnerCmsPage).toBe('function')
    expect(typeof deleteOwnerCmsPage).toBe('function')
    expect(typeof publishOwnerCmsPage).toBe('function')
    expect(typeof updateOwnerCmsPageFooter).toBe('function')
  })
})
