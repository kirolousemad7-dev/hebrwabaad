export const SERVICE_ICON_KEYS = ['warranty', 'speed', 'print', 'whatsapp', 'delivery', 'design'] as const

export type ServiceIconKey = (typeof SERVICE_ICON_KEYS)[number]

const PATHS: Record<ServiceIconKey, string> = {
  warranty: 'M12 3 5 6v5c0 4.2 2.8 7.4 7 9 4.2-1.6 7-4.8 7-9V6l-7-3Zm-1.2 10.2-2.3-2.3 1.1-1.1 1.2 1.2 3.1-3.1 1.1 1.1-4.2 4.2Z',
  speed: 'M12 5a7 7 0 1 0 7 7h-2a5 5 0 1 1-5-5V5Zm1 2v4.2l2.6 1.5-.9 1.6L11 12.2V7h2Z',
  print: 'M7 4h10v3H7V4Zm-2 5h14a2 2 0 0 1 2 2v5h-4v4H7v-4H3v-5a2 2 0 0 1 2-2Zm4 7h6v2H9v-2Z',
  whatsapp: 'M12 4a8 8 0 0 0-6.9 12l-.8 3.2 3.3-.8A8 8 0 1 0 12 4Zm4.2 10.3c-.2.5-1 .9-1.4 1-.4.1-.8.1-1.3-.1-.3-.1-.7-.2-1.2-.5-2.1-1.1-3.5-3.2-3.6-3.4-.1-.2-.9-1.2-.9-2.3s.6-1.6.8-1.8c.2-.2.4-.3.6-.3h.4c.1 0 .3 0 .5.4.2.5.6 1.6.7 1.7.1.1.1.3 0 .4l-.3.4c-.1.1-.2.3-.1.5.2.3.7 1.1 1.5 1.8.9.8 1.7 1 2 .1.1-.2.3-.3.5-.2l1.1.5c.2.1.4.2.4.3.1.2.1.7-.1 1.2Z',
  delivery: 'M3 7h11v8H3V7Zm11 2h3l3 3v3h-6V9ZM7 17a2 2 0 1 1 0 .01M17 17a2 2 0 1 1 0 .01',
  design: 'M4 16.5 14.8 5.7a2 2 0 0 1 2.8 0l.7.7a2 2 0 0 1 0 2.8L7.5 20H4v-3.5Zm2.1.4h1.2l8.8-8.8-1.2-1.2-8.8 8.8v1.2Z',
}

export function ServiceGlyph({ name, className = 'h-5 w-5' }: { name: ServiceIconKey; className?: string }) {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true" className={className} fill="currentColor">
      <path d={PATHS[name]} />
    </svg>
  )
}
