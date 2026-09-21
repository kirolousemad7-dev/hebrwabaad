import { publicFetch } from './api'
import type { PublicMarketingPayload } from '../types/publicMarketing'

export function fetchPublicMarketing(): Promise<PublicMarketingPayload> {
  return publicFetch<PublicMarketingPayload>('/api/public/marketing')
}
