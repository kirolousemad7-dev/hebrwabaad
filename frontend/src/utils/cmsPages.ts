import type { CmsFooterPage } from '../services/cmsPages'

const PREFERRED_FOOTER_GROUPS = ['تعرف علينا', 'السياسات']

export type CmsFooterGroup = {
  group: string
  pages: CmsFooterPage[]
}

/**
 * Groups footer CMS pages by footer_group, sorted by preferred Arabic labels then name,
 * with pages ordered by footer_order then title.
 */
export function groupFooterPages(pages: CmsFooterPage[]): CmsFooterGroup[] {
  const map = new Map<string, CmsFooterPage[]>()

  for (const page of pages) {
    const group = (page.footer_group || '').trim() || 'روابط'
    const list = map.get(group) ?? []
    list.push(page)
    map.set(group, list)
  }

  const groups = Array.from(map.entries()).map(([group, groupPages]) => ({
    group,
    pages: [...groupPages].sort((a, b) => {
      if (a.footer_order !== b.footer_order) {
        return a.footer_order - b.footer_order
      }
      return a.title.localeCompare(b.title, 'ar')
    }),
  }))

  return groups.sort((a, b) => {
    const ai = PREFERRED_FOOTER_GROUPS.indexOf(a.group)
    const bi = PREFERRED_FOOTER_GROUPS.indexOf(b.group)
    if (ai !== -1 || bi !== -1) {
      if (ai === -1) {
        return 1
      }
      if (bi === -1) {
        return -1
      }
      return ai - bi
    }
    return a.group.localeCompare(b.group, 'ar')
  })
}
