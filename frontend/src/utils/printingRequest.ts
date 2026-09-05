export const PRINTING_SHAPES = ['RECTANGLE', 'SQUARE', 'CIRCLE', 'CUSTOM'] as const
export const PRINTING_UNITS = ['CM', 'MM'] as const
export const PRINTING_METHODS = ['DIGITAL', 'OFFSET', 'ON_DEMAND'] as const
export const PRINTING_FINISHINGS = ['NONE', 'GLOSS', 'MATTE', 'CUT', 'DIE_CUT', 'CUSTOM'] as const

export type PrintingShape = (typeof PRINTING_SHAPES)[number]
export type PrintingUnit = (typeof PRINTING_UNITS)[number]
export type PrintingMethod = (typeof PRINTING_METHODS)[number]
export type PrintingFinishing = (typeof PRINTING_FINISHINGS)[number]

export const PRINTING_SHAPE_LABELS: Record<PrintingShape, string> = {
  RECTANGLE: 'مستطيل',
  SQUARE: 'مربع',
  CIRCLE: 'دائري',
  CUSTOM: 'مخصص',
}

export const PRINTING_UNIT_LABELS: Record<PrintingUnit, string> = {
  CM: 'سم',
  MM: 'ملم',
}

export const PRINTING_METHOD_LABELS: Record<PrintingMethod, string> = {
  DIGITAL: 'طباعة رقمية',
  OFFSET: 'أوفست',
  ON_DEMAND: 'طباعة حسب الطلب',
}

export const PRINTING_FINISHING_LABELS: Record<PrintingFinishing, string> = {
  NONE: 'بدون تشطيب',
  GLOSS: 'تغليف لامع',
  MATTE: 'تغليف مطفي',
  CUT: 'قص',
  DIE_CUT: 'تكسير',
  CUSTOM: 'تشطيب مخصص',
}

export const PRINTING_FILE_ACCEPT = '.pdf,.jpg,.jpeg,.png,.webp,.svg,.zip'
export const PRINTING_FILE_MAX_BYTES = 10 * 1024 * 1024

const ALLOWED_EXTENSIONS = new Set(['pdf', 'jpg', 'jpeg', 'png', 'webp', 'svg', 'zip'])

export function riyadhTodayInputValue(): string {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Riyadh',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(new Date())

  const year = parts.find((part) => part.type === 'year')?.value
  const month = parts.find((part) => part.type === 'month')?.value
  const day = parts.find((part) => part.type === 'day')?.value

  return `${year}-${month}-${day}`
}

export function isAllowedPrintingFile(file: File): boolean {
  const extension = file.name.split('.').pop()?.toLowerCase()

  return Boolean(extension && ALLOWED_EXTENSIONS.has(extension))
}

export const PRINTING_REQUEST_FIELD_LABELS: Record<string, string> = {
  product_slug: 'المنتج',
  product_name: 'اسم المنتج',
  width: 'العرض',
  height: 'الارتفاع',
  dimension_unit: 'وحدة القياس',
  shape: 'الشكل',
  material: 'الخامة',
  quantity: 'الكمية',
  printing_method: 'طريقة الطباعة',
  finishing: 'التشطيب',
  file: 'ملف التصميم',
  required_date: 'تاريخ التسليم المطلوب',
  notes: 'ملاحظات إضافية',
  estimated_price: 'السعر التقديري',
  quoted_price: 'عرض السعر',
  pricing_notes: 'ملاحظات التسعير',
}

export const PRINTING_STATUS_LABELS: Record<string, string> = {
  PENDING: 'قيد المراجعة',
}

export const PRINTING_PRICING_LABELS: Record<string, string> = {
  ESTIMATED: 'سعر تقديري',
  QUOTE_REQUIRED: 'طلب عرض سعر',
  QUOTE_READY: 'عرض سعر جاهز',
}

export function formatPrintingDate(value: string | null | undefined): string {
  if (!value) {
    return '—'
  }

  const datePart = value.slice(0, 10)
  const [year, month, day] = datePart.split('-').map(Number)
  const date = new Date(year, (month ?? 1) - 1, day ?? 1)

  return date.toLocaleDateString('ar-SA', { year: 'numeric', month: 'long', day: 'numeric' })
}
