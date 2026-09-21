/**
 * Human-readable Arabic labels for marketing CMS content keys.
 * Keep technical keys in the API; show these labels to OWNER.
 */

export type ContentFieldKind = 'text' | 'textarea' | 'html' | 'media' | 'url' | 'readonly_note'

export type ContentFieldMeta = {
  label: string
  kind: ContentFieldKind
  help?: string
  group?: string
}

const DEFAULT_META: ContentFieldMeta = {
  label: '',
  kind: 'textarea',
}

const GLOBAL_KEY_LABELS: Record<string, ContentFieldMeta> = {
  eyebrow: { label: 'التسمية العلوية', kind: 'text' },
  title: { label: 'العنوان', kind: 'text' },
  heading: { label: 'العنوان الرئيسي', kind: 'text' },
  description: { label: 'الوصف', kind: 'textarea' },
  body: { label: 'النص', kind: 'textarea' },
  tagline: { label: 'الشعار النصي', kind: 'text' },
  visual_image: { label: 'الصورة الرئيسية', kind: 'media' },
  note: { label: 'ملاحظة داخلية', kind: 'readonly_note', help: 'للمرجعية الإدارية فقط — لا تظهر للجمهور عادةً.' },
  cta_primary: { label: 'نص الزر الرئيسي', kind: 'text' },
  cta_secondary: { label: 'نص الزر الثانوي', kind: 'text' },
  cta_primary_label: { label: 'نص الزر الرئيسي', kind: 'text' },
  cta_primary_url: { label: 'رابط الزر الرئيسي', kind: 'url' },
  cta_secondary_label: { label: 'نص الزر الثانوي', kind: 'text' },
  cta_secondary_url: { label: 'رابط الزر الثانوي', kind: 'url' },
  eyebrow_fallback: {
    label: 'التسمية البديلة',
    kind: 'text',
    help: 'تُستخدم إذا تطابقت تسمية العلامة مع العنوان.',
  },
}

const SECTION_OVERRIDES: Record<string, Record<string, ContentFieldMeta>> = {
  hero: {
    heading: {
      label: 'العنوان الرئيسي (محتوى الموقع)',
      kind: 'text',
      help: 'نسخة Marketing CMS. الموقع العام حاليًا يعرض عنوان Hero من إعدادات المنصة حتى Phase 3.',
      group: 'marketing',
    },
    description: {
      label: 'الوصف (محتوى الموقع)',
      kind: 'textarea',
      help: 'نسخة Marketing CMS. الموقع العام يستخدم حاليًا الوصف من إعدادات المنصة.',
      group: 'marketing',
    },
    cta_primary: {
      label: 'نص الزر الرئيسي (محتوى الموقع)',
      kind: 'text',
      group: 'marketing',
    },
    cta_secondary: {
      label: 'نص الزر الثانوي (محتوى الموقع)',
      kind: 'text',
      group: 'marketing',
    },
    visual_image: {
      label: 'صورة الـHero',
      kind: 'media',
      help: 'ستُربط بالواجهة العامة في Phase 3. حاليًا الواجهة تستخدم /marketing/storefront.jpg.',
      group: 'marketing',
    },
  },
  about: {
    eyebrow: { label: 'التسمية', kind: 'text', help: 'محتوى مختصر يظهر في الصفحة الرئيسية.' },
    body: { label: 'الوصف المختصر', kind: 'textarea', help: 'محتوى مختصر يظهر في الصفحة الرئيسية — ليس صفحة من نحن الكاملة.' },
    visual_image: { label: 'صورة المقطع', kind: 'media' },
  },
}

/** Platform-settings fields shown as informational cards on Hero editor (not editable here). */
export const HERO_PLATFORM_SETTINGS_FIELDS = [
  {
    key: 'homepage.hero_heading',
    label: 'العنوان الرئيسي',
    source: 'إعدادات المنصة',
    settingsPath: '/owner/settings?tab=website',
  },
  {
    key: 'homepage.hero_subheading',
    label: 'الوصف',
    source: 'إعدادات المنصة',
    settingsPath: '/owner/settings?tab=website',
  },
  {
    key: 'homepage.hero_primary_cta_label',
    label: 'نص الزر الرئيسي',
    source: 'إعدادات المنصة',
    settingsPath: '/owner/settings?tab=website',
  },
  {
    key: 'homepage.hero_secondary_cta_label',
    label: 'نص الزر الثانوي',
    source: 'إعدادات المنصة',
    settingsPath: '/owner/settings?tab=website',
  },
  {
    key: 'brand.tagline',
    label: 'تسمية العلامة (Tagline)',
    source: 'إعدادات المنصة',
    settingsPath: '/owner/settings?tab=brand',
  },
] as const

export const DOMAIN_SECTION_LINKS: Record<
  string,
  { title: string; description: string; href: string; cta: string }
> = {
  services: {
    title: 'الخدمات',
    description: 'إدارة أسماء الخدمات والأوصاف والصور تتم من كتالوج الخدمات.',
    href: '/owner/services',
    cta: 'إدارة الخدمات',
  },
  packages: {
    title: 'الباقات',
    description: 'إدارة الباقات والأسعار تتم من كتالوج الباقات.',
    href: '/owner/packages',
    cta: 'إدارة الباقات',
  },
  portfolio: {
    title: 'الأعمال',
    description: 'معرض الأعمال يُدار من المعرض التسويقي الحالي.',
    href: '/owner/marketing',
    cta: 'إدارة الأعمال',
  },
  suppliers: {
    title: 'الموردين',
    description: 'إدارة الموردين والشركاء تتم من وحدة الموردين.',
    href: '/owner/suppliers',
    cta: 'إدارة الموردين',
  },
}

export function labelForContentKey(sectionKey: string, contentKey: string): ContentFieldMeta {
  const override = SECTION_OVERRIDES[sectionKey]?.[contentKey]
  if (override) {
    return { ...override, label: override.label || contentKey }
  }

  const itemMatch = /^item_(\d+)_(title|description|icon|image)$/.exec(contentKey)
  if (itemMatch) {
    const n = itemMatch[1]
    const part = itemMatch[2]
    const partLabel =
      part === 'title' ? 'العنوان' : part === 'description' ? 'الوصف' : part === 'icon' ? 'الأيقونة' : 'الصورة'
    return {
      label: `عنصر ${n} — ${partLabel}`,
      kind: part === 'image' || part === 'icon' ? 'media' : part === 'description' ? 'textarea' : 'text',
      group: `item_${n}`,
    }
  }

  const stepMatch = /^step_(\d+)_(title|description|image|icon)$/.exec(contentKey)
  if (stepMatch) {
    const n = stepMatch[1]
    const part = stepMatch[2]
    const partLabel =
      part === 'title' ? 'العنوان' : part === 'description' ? 'الوصف' : part === 'icon' ? 'الأيقونة' : 'الصورة'
    return {
      label: `الخطوة ${n} — ${partLabel}`,
      kind: part === 'image' || part === 'icon' ? 'media' : part === 'description' ? 'textarea' : 'text',
      group: `step_${n}`,
    }
  }

  const global = GLOBAL_KEY_LABELS[contentKey]
  if (global) {
    return { ...global, label: global.label || contentKey }
  }

  return { ...DEFAULT_META, label: contentKey }
}

export function sectionDisplayName(section: { admin_title?: string; key: string; type_label?: string | null }): string {
  return section.admin_title || section.type_label || section.key
}

export function formatBytes(size: number): string {
  if (size < 1024) return `${size} B`
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`
  return `${(size / (1024 * 1024)).toFixed(1)} MB`
}
