import { describe, expect, it } from 'vitest'
import type { CmsFooterPage } from '../services/cmsPages'
import { groupFooterPages } from './cmsPages'

function page(partial: Partial<CmsFooterPage> & Pick<CmsFooterPage, 'id' | 'title'>): CmsFooterPage {
  return {
    slug: partial.slug || `slug-${partial.id}`,
    page_type: 'POLICY',
    path: partial.path || `/${partial.slug || `slug-${partial.id}`}`,
    show_in_footer: true,
    footer_group: partial.footer_group ?? null,
    footer_order: partial.footer_order ?? 0,
    updated_at: null,
    ...partial,
  }
}

describe('groupFooterPages', () => {
  it('groups by footer_group and sorts preferred Arabic groups first', () => {
    const groups = groupFooterPages([
      page({ id: 1, title: 'الخصوصية', footer_group: 'السياسات', footer_order: 2 }),
      page({ id: 2, title: 'من نحن', footer_group: 'تعرف علينا', footer_order: 1 }),
      page({ id: 3, title: 'الشروط', footer_group: 'السياسات', footer_order: 1 }),
      page({ id: 4, title: 'أخرى', footer_group: 'روابط إضافية', footer_order: 0 }),
    ])

    expect(groups.map((group) => group.group)).toEqual(['تعرف علينا', 'السياسات', 'روابط إضافية'])
    expect(groups[1].pages.map((item) => item.title)).toEqual(['الشروط', 'الخصوصية'])
  })

  it('falls back to روابط when footer_group is empty', () => {
    const groups = groupFooterPages([page({ id: 9, title: 'بدون مجموعة', footer_group: '  ' })])
    expect(groups).toHaveLength(1)
    expect(groups[0].group).toBe('روابط')
  })
})
