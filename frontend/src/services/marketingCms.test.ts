import { describe, expect, it } from 'vitest'
import {
  deleteOwnerMarketingMedia,
  getOwnerMarketingSection,
  listOwnerMarketingMedia,
  listOwnerMarketingMediaOrphans,
  listOwnerMarketingSections,
  replaceOwnerMarketingMedia,
  updateOwnerMarketingMedia,
  updateOwnerMarketingSection,
  uploadOwnerMarketingMedia,
} from './marketingCms'
import { labelForContentKey, sectionDisplayName } from '../utils/marketingCmsLabels'
import { moduleForPath } from '../utils/dashboardAccess'

describe('marketingCms service exports', () => {
  it('exposes owner marketing API helpers', () => {
    expect(typeof listOwnerMarketingSections).toBe('function')
    expect(typeof getOwnerMarketingSection).toBe('function')
    expect(typeof updateOwnerMarketingSection).toBe('function')
    expect(typeof listOwnerMarketingMedia).toBe('function')
    expect(typeof listOwnerMarketingMediaOrphans).toBe('function')
    expect(typeof uploadOwnerMarketingMedia).toBe('function')
    expect(typeof updateOwnerMarketingMedia).toBe('function')
    expect(typeof replaceOwnerMarketingMedia).toBe('function')
    expect(typeof deleteOwnerMarketingMedia).toBe('function')
  })
})

describe('marketingCmsLabels', () => {
  it('maps technical keys to Arabic owner labels', () => {
    expect(labelForContentKey('hero', 'visual_image').label).toContain('صورة')
    expect(labelForContentKey('hero', 'heading').kind).toBe('text')
    expect(labelForContentKey('why-us', 'item_1_title').label).toContain('عنصر 1')
    expect(labelForContentKey('process', 'step_2_title').label).toContain('الخطوة 2')
    expect(labelForContentKey('final-cta', 'cta_primary_url').kind).toBe('url')
    expect(labelForContentKey('about', 'quote').label).toContain('اقتباس')
    expect(labelForContentKey('about', 'cta_label').kind).toBe('text')
    expect(labelForContentKey('services', 'visual_strategy').kind).toBe('media')
    expect(labelForContentKey('packages', 'visual_basic').kind).toBe('media')
  })

  it('prefers admin title for section display name', () => {
    expect(sectionDisplayName({ admin_title: 'Hero', key: 'hero', type_label: 'البطل' })).toBe('Hero')
  })
})

describe('dashboard access for website CMS', () => {
  it('maps /owner/website paths to content module', () => {
    expect(moduleForPath('/owner/website')).toBe('content')
    expect(moduleForPath('/owner/website/media')).toBe('content')
    expect(moduleForPath('/owner/website/hero')).toBe('content')
  })
})
