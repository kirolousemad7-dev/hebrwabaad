import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'
import { fetchPublicMarketing } from '../services/publicMarketing'
import type { PublicMarketingIndex } from '../types/publicMarketing'
import {
  buildPublicMarketingIndex,
  resolveMarketingContent as resolveContent,
  resolveMarketingMedia as resolveMedia,
  resolveMarketingVisual as resolveVisual,
  type ResolvedMarketingMedia,
} from '../utils/publicMarketingAdapter'
import type { MarketingVisual } from '../utils/marketingVisuals'

type PublicMarketingContextValue = {
  /** True after the first fetch attempt finishes (success or failure). */
  isReady: boolean
  /** Indexed CMS payload; empty map when unavailable. */
  index: PublicMarketingIndex
  resolveContent: (sectionKey: string, contentKey: string, fallback: string) => string
  resolveMedia: (
    sectionKey: string,
    contentKey: string,
    fallback: ResolvedMarketingMedia,
  ) => ResolvedMarketingMedia
  resolveVisual: (
    sectionKey: string,
    contentKey: string,
    fallback: MarketingVisual,
  ) => MarketingVisual
}

const EMPTY_INDEX: PublicMarketingIndex = new Map()

const PublicMarketingContext = createContext<PublicMarketingContextValue | undefined>(undefined)

export function PublicMarketingProvider({ children }: { children: ReactNode }) {
  const [index, setIndex] = useState<PublicMarketingIndex>(EMPTY_INDEX)
  const [isReady, setIsReady] = useState(false)

  const load = useCallback(async () => {
    try {
      const payload = await fetchPublicMarketing()
      setIndex(buildPublicMarketingIndex(payload))
    } catch {
      setIndex(EMPTY_INDEX)
    } finally {
      setIsReady(true)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const value = useMemo<PublicMarketingContextValue>(() => {
    return {
      isReady,
      index,
      resolveContent: (sectionKey, contentKey, fallback) =>
        resolveContent(index, sectionKey, contentKey, fallback),
      resolveMedia: (sectionKey, contentKey, fallback) =>
        resolveMedia(index, sectionKey, contentKey, fallback),
      resolveVisual: (sectionKey, contentKey, fallback) =>
        resolveVisual(index, sectionKey, contentKey, fallback),
    }
  }, [index, isReady])

  return (
    <PublicMarketingContext.Provider value={value}>{children}</PublicMarketingContext.Provider>
  )
}

const FALLBACK_CONTEXT: PublicMarketingContextValue = {
  isReady: true,
  index: EMPTY_INDEX,
  resolveContent: (_sectionKey, _contentKey, fallback) => fallback,
  resolveMedia: (_sectionKey, _contentKey, fallback) => fallback,
  resolveVisual: (_sectionKey, _contentKey, fallback) => fallback,
}

/**
 * Public marketing adapter. Safe outside provider — returns fallbacks only.
 * One fetch is owned by PublicMarketingProvider (LandingLayout).
 */
export function usePublicMarketing(): PublicMarketingContextValue {
  return useContext(PublicMarketingContext) ?? FALLBACK_CONTEXT
}
