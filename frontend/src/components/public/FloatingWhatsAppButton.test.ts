import { describe, expect, it } from 'vitest'
import { WHATSAPP_CHAT_URL } from './FloatingWhatsAppButton'

describe('FloatingWhatsAppButton', () => {
  it('uses the approved WhatsApp chat URL and Saudi number only', () => {
    expect(WHATSAPP_CHAT_URL).toBe('https://wa.me/966579434114')
    expect(WHATSAPP_CHAT_URL).toContain('966579434114')
  })
})
