import type {
  PublicMarketingContent,
  PublicMarketingIndex,
  PublicMarketingMedia,
  PublicMarketingPayload,
  PublicMarketingSection,
} from '../types/publicMarketing'
import { resolveMediaUrl } from './mediaUrl'
import type { MarketingVisual } from './marketingVisuals'

/**
 * Build a section → content_key index from a public marketing payload.
 * Tolerates malformed/partial data without throwing.
 */
export function buildPublicMarketingIndex(
  payload: PublicMarketingPayload | null | undefined,
): PublicMarketingIndex {
  const index: PublicMarketingIndex = new Map()

  const sections = payload?.sections
  if (!Array.isArray(sections)) {
    return index
  }

  for (const section of sections) {
    if (!isPublicSection(section)) {
      continue
    }

    const byKey = new Map<string, PublicMarketingContent>()
    const contents = Array.isArray(section.contents) ? section.contents : []

    for (const content of contents) {
      if (!isPublicContent(content)) {
        continue
      }
      byKey.set(content.key, content)
    }

    index.set(section.key, byKey)
  }

  return index
}

export function resolveMarketingContent(
  index: PublicMarketingIndex | null | undefined,
  sectionKey: string,
  contentKey: string,
  fallback: string,
): string {
  try {
    const content = index?.get(sectionKey)?.get(contentKey)
    if (!content) {
      return fallback
    }

    const text = typeof content.text === 'string' ? content.text.trim() : ''
    if (text) {
      return text
    }

    return fallback
  } catch {
    return fallback
  }
}

export type ResolvedMarketingMedia = {
  url: string
  alt: string
  title: string
}

/**
 * Resolve active public media URL for a content key.
 * Falls back when media is missing, null, empty URL, or inactive (API returns null media).
 */
export function resolveMarketingMedia(
  index: PublicMarketingIndex | null | undefined,
  sectionKey: string,
  contentKey: string,
  fallback: ResolvedMarketingMedia,
): ResolvedMarketingMedia {
  try {
    const content = index?.get(sectionKey)?.get(contentKey)
    const media = content?.media
    if (!isUsableMedia(media)) {
      return fallback
    }

    const url = resolveMediaUrl(media.url)
    if (!url) {
      return fallback
    }

    return {
      url,
      alt: media.alt?.trim() || fallback.alt,
      title: media.title?.trim() || fallback.title,
    }
  } catch {
    return fallback
  }
}

/** Merge CMS image into a MarketingVisual while preserving accentSvg / empty geometric fallback. */
export function resolveMarketingVisual(
  index: PublicMarketingIndex | null | undefined,
  sectionKey: string,
  contentKey: string,
  fallback: MarketingVisual,
): MarketingVisual {
  try {
    const media = index?.get(sectionKey)?.get(contentKey)?.media
    if (!isUsableMedia(media)) {
      return fallback
    }

    const url = resolveMediaUrl(media.url)
    if (!url) {
      return fallback
    }

    return {
      ...fallback,
      image: url,
      alt: media.alt?.trim() || fallback.alt,
    }
  } catch {
    return fallback
  }
}

function isPublicSection(value: unknown): value is PublicMarketingSection {
  if (!value || typeof value !== 'object') {
    return false
  }
  const row = value as Record<string, unknown>
  return typeof row.key === 'string' && row.key.length > 0
}

function isPublicContent(value: unknown): value is PublicMarketingContent {
  if (!value || typeof value !== 'object') {
    return false
  }
  const row = value as Record<string, unknown>
  return typeof row.key === 'string' && row.key.length > 0
}

function isUsableMedia(media: PublicMarketingMedia | null | undefined): media is PublicMarketingMedia {
  if (!media || typeof media !== 'object') {
    return false
  }
  return typeof media.url === 'string' && media.url.trim().length > 0
}
