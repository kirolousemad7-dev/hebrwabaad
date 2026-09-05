import { apiDownload, apiGet, apiPatch, apiPostForm } from './api'
import type { PrintingRequest } from '../types/api'

export type PrintingRequestInput = {
  productSlug: string
  productName: string
  width: string
  height: string
  dimensionUnit: string
  shape: string
  material: string
  quantity: string
  printingMethod: string
  finishing: string[]
  file: File
  requiredDate: string
  notes: string
}

export type AdminPrintingRequestFilters = {
  status?: string
  pricing_type?: string
  q?: string
}

export function createPrintingRequest(input: PrintingRequestInput) {
  const body = new FormData()
  body.append('product_slug', input.productSlug)
  body.append('product_name', input.productName)
  body.append('width', input.width)
  body.append('height', input.height)
  body.append('dimension_unit', input.dimensionUnit)
  body.append('shape', input.shape)
  body.append('material', input.material)
  body.append('quantity', input.quantity)
  body.append('printing_method', input.printingMethod)
  body.append('finishing', JSON.stringify(input.finishing))
  body.append('file', input.file)
  body.append('required_date', input.requiredDate)

  if (input.notes.trim() !== '') {
    body.append('notes', input.notes.trim())
  }

  return apiPostForm<PrintingRequest>('/api/printing-requests', body)
}

export function getCustomerPrintingRequests() {
  return apiGet<PrintingRequest[]>('/api/printing-requests')
}

export function getCustomerPrintingRequest(id: number) {
  return apiGet<PrintingRequest>(`/api/printing-requests/${id}`)
}

export function downloadCustomerPrintingRequestFile(id: number, filename: string) {
  return apiDownload(`/api/printing-requests/${id}/file`, filename)
}

function adminQuery(filters: AdminPrintingRequestFilters = {}): string {
  const params = new URLSearchParams()

  if (filters.status) {
    params.set('status', filters.status)
  }

  if (filters.pricing_type) {
    params.set('pricing_type', filters.pricing_type)
  }

  if (filters.q?.trim()) {
    params.set('q', filters.q.trim())
  }

  const query = params.toString()
  return query === '' ? '' : `?${query}`
}

export function getAdminPrintingRequests(filters: AdminPrintingRequestFilters = {}) {
  return apiGet<PrintingRequest[]>(`/api/admin/printing-requests${adminQuery(filters)}`)
}

export function getAdminPrintingRequest(id: number) {
  return apiGet<PrintingRequest>(`/api/admin/printing-requests/${id}`)
}

export function downloadAdminPrintingRequestFile(id: number, filename: string) {
  return apiDownload(`/api/admin/printing-requests/${id}/file`, filename)
}

export function setPrintingRequestEstimate(id: number, estimatedPrice: string, pricingNotes: string) {
  return apiPatch<PrintingRequest>(`/api/admin/printing-requests/${id}/pricing`, {
    estimated_price: estimatedPrice,
    pricing_notes: pricingNotes.trim() === '' ? null : pricingNotes.trim(),
  })
}

export function markPrintingRequestQuoteRequired(id: number, pricingNotes: string) {
  return apiPatch<PrintingRequest>(`/api/admin/printing-requests/${id}/request-quote`, {
    pricing_notes: pricingNotes.trim() === '' ? null : pricingNotes.trim(),
  })
}

export function providePrintingRequestQuote(id: number, quotedPrice: string, pricingNotes: string) {
  return apiPatch<PrintingRequest>(`/api/admin/printing-requests/${id}/quote`, {
    quoted_price: quotedPrice,
    pricing_notes: pricingNotes.trim() === '' ? null : pricingNotes.trim(),
  })
}
