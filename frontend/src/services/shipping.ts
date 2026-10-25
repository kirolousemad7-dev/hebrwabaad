import { apiGet, apiPost } from './api'

export type ShippingQuoteOption = {
  delivery_option_id: string
  name: string
  carrier: string
  price: string | null
  currency: string
  delivery_time: string
}

export type OwnerShipment = {
  status: string
  status_label: string
  carrier: string | null
  tracking_number: string | null
  tracking_url: string | null
  delivered_at: string | null
  oto_order_id: string | null
  shipment_id: string | null
  delivery_option_id: string | null
  shipping_cost: string | null
  currency: string
  awb_url: string | null
  error_code: string | null
  error_message: string | null
  last_synced_at: string | null
  recipient_name: string | null
  recipient_phone: string | null
  address: string | null
  city: string | null
  country: string | null
  quote_options: ShippingQuoteOption[]
  remote_order_created: boolean
}

export type ShippingIntegration = {
  enabled: boolean
  configured: boolean
  sandbox: boolean
  order_code_used: boolean
}

export type ShippingQueueItem = {
  id: number
  reference: string
  title: string
  customer_name: string | null
  shipment: OwnerShipment | null
}

export type ShippingQueue = {
  integration: ShippingIntegration
  items: ShippingQueueItem[]
  meta: { current_page: number; last_page: number; per_page: number; total: number; view: string }
}

export type ShippingDetail = {
  order: { id: number; reference: string; title: string; customer_name: string | null }
  block_reason: string | null
  shipment: OwnerShipment | null
  integration: ShippingIntegration
}

export type ShippingRecipient = {
  recipient_name: string
  recipient_phone: string
  address: string
  city: string
  country: string
  weight_kg?: number
}

export function getShippingQueue(view = 'awaiting') {
  return apiGet<ShippingQueue>(`/api/admin/shipping/oto?view=${encodeURIComponent(view)}`)
}

export function getShippingDetail(orderId: number) {
  return apiGet<ShippingDetail>(`/api/admin/orders/${orderId}/oto`)
}

export function quoteShipment(orderId: number, recipient: ShippingRecipient) {
  return apiPost<OwnerShipment>(`/api/admin/orders/${orderId}/oto/quote`, recipient)
}

export function createShippingOrder(orderId: number, recipient: ShippingRecipient) {
  return apiPost<OwnerShipment>(`/api/admin/orders/${orderId}/oto/order`, recipient)
}

export function createShipment(orderId: number, deliveryOptionId: string) {
  return apiPost<OwnerShipment>(`/api/admin/orders/${orderId}/oto/shipment`, {
    delivery_option_id: deliveryOptionId,
  })
}

export function syncShipment(orderId: number) {
  return apiPost<OwnerShipment>(`/api/admin/orders/${orderId}/oto/sync`)
}

export function retryShipment(orderId: number) {
  return apiPost<OwnerShipment>(`/api/admin/orders/${orderId}/oto/retry`)
}

export function getShippingLabel(orderId: number) {
  return apiGet<OwnerShipment>(`/api/admin/orders/${orderId}/oto/label`)
}
