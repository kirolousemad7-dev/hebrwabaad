import { describe, expect, it } from 'vitest'
import type { PublicMarketingPayload } from '../types/publicMarketing'
import {
  buildPublicMarketingIndex,
  resolveMarketingContent,
  resolveMarketingMedia,
  resolveMarketingVisual,
} from './publicMarketingAdapter'

const SAMPLE: PublicMarketingPayload = {
  sections: [
    {
      key: 'why-us',
      type: 'why-us',
      sort_order: 30,
      contents: [
        { key: 'eyebrow', text: 'CMS eyebrow', html: null, media: null, sort_order: 1 },
        { key: 'title', text: 'CMS title', html: null, media: null, sort_order: 2 },
        { key: 'item_1_title', text: 'CMS item', html: null, media: null, sort_order: 10 },
        { key: 'item_1_description', text: '  ', html: null, media: null, sort_order: 11 },
      ],
    },
    {
      key: 'about',
      type: 'about',
      sort_order: 90,
      contents: [
        {
          key: 'visual_image',
          text: null,
          html: null,
          media: {
            url: '/marketing/cms-about.jpg',
            alt: 'CMS alt',
            title: 'CMS title',
            width: 800,
            height: 600,
          },
          sort_order: 4,
        },
      ],
    },
    {
      key: 'hero',
      type: 'hero',
      sort_order: 10,
      contents: [
        {
          key: 'visual_image',
          text: null,
          html: null,
          media: null,
          sort_order: 6,
        },
      ],
    },
  ],
}

describe('buildPublicMarketingIndex', () => {
  it('indexes sections and content keys', () => {
    const index = buildPublicMarketingIndex(SAMPLE)
    expect(index.get('why-us')?.get('title')?.text).toBe('CMS title')
    expect(index.has('missing')).toBe(false)
  })

  it('tolerates null, undefined, and malformed payload', () => {
    expect(buildPublicMarketingIndex(null).size).toBe(0)
    expect(buildPublicMarketingIndex(undefined).size).toBe(0)
    expect(buildPublicMarketingIndex({ sections: null as unknown as [] }).size).toBe(0)
    expect(
      buildPublicMarketingIndex({
        sections: [{ key: '', type: 'x', sort_order: 0, contents: [] }],
      }).size,
    ).toBe(0)
  })
})

describe('resolveMarketingContent', () => {
  const index = buildPublicMarketingIndex(SAMPLE)

  it('uses CMS value when present', () => {
    expect(resolveMarketingContent(index, 'why-us', 'eyebrow', 'fallback')).toBe('CMS eyebrow')
  })

  it('uses fallback when API index is empty (failure)', () => {
    expect(resolveMarketingContent(new Map(), 'why-us', 'title', 'fallback')).toBe('fallback')
  })

  it('uses fallback when section is missing', () => {
    expect(resolveMarketingContent(index, 'process', 'title', 'fallback')).toBe('fallback')
  })

  it('uses fallback when content key is missing', () => {
    expect(resolveMarketingContent(index, 'why-us', 'item_9_title', 'fallback')).toBe('fallback')
  })

  it('uses fallback for blank CMS text (partial payload)', () => {
    expect(resolveMarketingContent(index, 'why-us', 'item_1_description', 'fallback body')).toBe(
      'fallback body',
    )
    expect(resolveMarketingContent(index, 'why-us', 'item_1_title', 'fallback')).toBe('CMS item')
  })

  it('uses fallback when index is null', () => {
    expect(resolveMarketingContent(null, 'why-us', 'title', 'safe')).toBe('safe')
  })
})

describe('resolveMarketingMedia', () => {
  const index = buildPublicMarketingIndex(SAMPLE)
  const fallback = {
    url: '/marketing/storefront.jpg',
    alt: 'واجهة',
    title: 'واجهة',
  }

  it('active CMS media URL → CMS URL', () => {
    expect(resolveMarketingMedia(index, 'about', 'visual_image', fallback)).toEqual({
      url: '/marketing/cms-about.jpg',
      alt: 'CMS alt',
      title: 'CMS title',
    })
  })

  it('missing media → fallback', () => {
    expect(resolveMarketingMedia(index, 'hero', 'visual_image', fallback)).toEqual(fallback)
  })

  it('inactive media → fallback (public API returns media: null)', () => {
    // Public MarketingSectionResource omits inactive media as null — same shape as missing.
    const inactivePayload: PublicMarketingPayload = {
      sections: [
        {
          key: 'hero',
          type: 'hero',
          sort_order: 10,
          contents: [
            {
              key: 'visual_image',
              text: null,
              html: null,
              media: null,
              sort_order: 6,
            },
          ],
        },
      ],
    }
    const inactiveIndex = buildPublicMarketingIndex(inactivePayload)
    expect(resolveMarketingMedia(inactiveIndex, 'hero', 'visual_image', fallback)).toEqual(fallback)
  })

  it('uses fallback when content key has no media', () => {
    expect(
      resolveMarketingMedia(index, 'why-us', 'title', {
        url: '/brand/mark.png',
        alt: 'mark',
        title: 'mark',
      }).url,
    ).toBe('/brand/mark.png')
  })
})

describe('resolveMarketingVisual', () => {
  const index = buildPublicMarketingIndex(SAMPLE)

  it('merges CMS image into MarketingVisual preserving accent', () => {
    const visual = resolveMarketingVisual(index, 'about', 'visual_image', {
      image: '/marketing/storefront.jpg',
      alt: 'من نحن',
      accentSvg: '/brand/mark.png',
    })
    expect(visual.image).toBe('/marketing/cms-about.jpg')
    expect(visual.accentSvg).toBe('/brand/mark.png')
    expect(visual.alt).toBe('CMS alt')
  })

  it('keeps empty geometric fallback when CMS media missing', () => {
    const fallback = {
      image: '',
      alt: 'الهوية والتصميم',
      accentSvg: '/printing/business-cards-luxury.svg',
    }
    expect(resolveMarketingVisual(index, 'services', 'visual_branding', fallback)).toEqual(fallback)
  })

  it('keeps photo fallback when CMS media missing', () => {
    const fallback = {
      image: '/marketing/storefront.jpg',
      alt: 'واجهة',
      accentSvg: '/brand/mark.png',
    }
    expect(resolveMarketingVisual(index, 'hero', 'visual_image', fallback)).toEqual(fallback)
  })
})

describe('content overrides', () => {
  const index = buildPublicMarketingIndex(SAMPLE)

  it('CMS content overrides fallback', () => {
    expect(resolveMarketingContent(index, 'why-us', 'title', 'fallback')).toBe('CMS title')
  })

  it('missing content uses fallback', () => {
    expect(resolveMarketingContent(index, 'about', 'quote', 'نحوّل احتياجات الأعمال')).toBe(
      'نحوّل احتياجات الأعمال',
    )
  })

  it('disabled/missing section does not blank — uses fallback', () => {
    expect(resolveMarketingContent(new Map(), 'why-us', 'eyebrow', 'لماذا نحن')).toBe('لماذا نحن')
  })
})
