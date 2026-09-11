import { apiGet } from './api'
import type { BuilderAddon } from '../utils/builder'
import { parseSarToHalalas } from '../utils/catalog'

type ApiAddon = {
  id: number
  slug: string
  name: string
  summary?: string | null
  description?: string | null
  pricing_mode?: string
  price?: string | number | null
  is_available?: boolean
  capacity_available?: boolean
  requires_capacity?: boolean
  is_urgent?: boolean
  service_ids?: number[]
}

export async function getPublicAddons(): Promise<{ data: BuilderAddon[] }> {
  const response = await apiGet<ApiAddon[]>('/api/addons')

  return {
    data: response.data.map((row) => {
      const price = row.price == null || row.price === '' ? null : Number(row.price)
      const available =
        row.is_available !== false &&
        !(row.requires_capacity && row.capacity_available === false)

      return {
        id: row.slug,
        name: row.name,
        description: row.summary || row.description || '',
        pricing_mode: (row.pricing_mode as BuilderAddon['pricing_mode']) || 'QUOTE',
        price_halalas: price != null && price > 0 ? parseSarToHalalas(price) : null,
        is_available: available,
        service_ids: row.service_ids ?? [],
      }
    }),
  }
}
