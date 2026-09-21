/**
 * Public Marketing CMS payload types.
 * Mirrors GET /api/public/marketing — owner-only fields are intentionally omitted.
 */

export type PublicMarketingMedia = {
  url: string | null
  alt: string
  title: string
  width: number | null
  height: number | null
}

export type PublicMarketingContent = {
  key: string
  text: string | null
  html: string | null
  media: PublicMarketingMedia | null
  sort_order: number
}

export type PublicMarketingSection = {
  key: string
  type: string
  sort_order: number
  contents: PublicMarketingContent[]
}

export type PublicMarketingPayload = {
  sections: PublicMarketingSection[]
}

/** Indexed lookup: sectionKey → contentKey → content */
export type PublicMarketingIndex = Map<string, Map<string, PublicMarketingContent>>
