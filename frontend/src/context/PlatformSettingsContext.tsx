import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'
import { fetchPublicPlatformSettings } from '../services/platformSettings'
import {
  PLATFORM_SETTINGS_DEFAULTS,
  filterPublicNavigation,
  type PlatformSettingsPublic,
} from '../types/platformSettings'

type PlatformSettingsContextValue = {
  settings: PlatformSettingsPublic
  isReady: boolean
  refresh: () => Promise<void>
  navigation: ReturnType<typeof filterPublicNavigation>
}

const PlatformSettingsContext = createContext<PlatformSettingsContextValue | undefined>(undefined)

export function PlatformSettingsProvider({ children }: { children: ReactNode }) {
  const [settings, setSettings] = useState<PlatformSettingsPublic>(PLATFORM_SETTINGS_DEFAULTS)
  const [isReady, setIsReady] = useState(false)

  const refresh = useCallback(async () => {
    try {
      const payload = await fetchPublicPlatformSettings()
      setSettings({
        ...PLATFORM_SETTINGS_DEFAULTS,
        ...payload,
        brand: { ...PLATFORM_SETTINGS_DEFAULTS.brand, ...payload.brand },
        business: { ...PLATFORM_SETTINGS_DEFAULTS.business, ...payload.business },
        contact: { ...PLATFORM_SETTINGS_DEFAULTS.contact, ...payload.contact },
        website: {
          ...PLATFORM_SETTINGS_DEFAULTS.website,
          ...payload.website,
          features: {
            ...PLATFORM_SETTINGS_DEFAULTS.website.features,
            ...(payload.website?.features ?? {}),
          },
          navigation: payload.website?.navigation?.length
            ? payload.website.navigation
            : PLATFORM_SETTINGS_DEFAULTS.website.navigation,
        },
        homepage: {
          ...PLATFORM_SETTINGS_DEFAULTS.homepage,
          ...payload.homepage,
          section_titles: {
            ...PLATFORM_SETTINGS_DEFAULTS.homepage.section_titles,
            ...(payload.homepage?.section_titles ?? {}),
          },
          section_descriptions: {
            ...PLATFORM_SETTINGS_DEFAULTS.homepage.section_descriptions,
            ...(payload.homepage?.section_descriptions ?? {}),
          },
        },
        customer: { ...PLATFORM_SETTINGS_DEFAULTS.customer, ...payload.customer },
        seo: { ...PLATFORM_SETTINGS_DEFAULTS.seo, ...payload.seo },
        social: payload.social ?? [],
        cta: payload.cta?.length ? payload.cta : PLATFORM_SETTINGS_DEFAULTS.cta,
      })
    } catch {
      setSettings(PLATFORM_SETTINGS_DEFAULTS)
    } finally {
      setIsReady(true)
    }
  }, [])

  useEffect(() => {
    void refresh()
  }, [refresh])

  const navigation = useMemo(() => filterPublicNavigation(settings), [settings])

  const value = useMemo(
    () => ({ settings, isReady, refresh, navigation }),
    [settings, isReady, refresh, navigation],
  )

  return (
    <PlatformSettingsContext.Provider value={value}>{children}</PlatformSettingsContext.Provider>
  )
}

export function usePlatformSettings(): PlatformSettingsContextValue {
  const ctx = useContext(PlatformSettingsContext)
  if (!ctx) {
    return {
      settings: PLATFORM_SETTINGS_DEFAULTS,
      isReady: true,
      refresh: async () => undefined,
      navigation: filterPublicNavigation(PLATFORM_SETTINGS_DEFAULTS),
    }
  }
  return ctx
}
