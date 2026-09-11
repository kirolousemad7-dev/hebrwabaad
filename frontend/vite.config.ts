import { defineConfig, loadEnv, type Plugin } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

const PUBLIC_PATHS = [
  '/',
  '/services',
  '/packages',
  '/marketing-packages',
  '/event-packages',
  '/printing-packaging',
  '/build-package',
  '/consultant',
  '/suppliers',
  '/portfolio',
  '/about',
  '/contact',
]

function sitemapXml(origin: string): string {
  const urls = PUBLIC_PATHS.map((path) => {
    const loc = `${origin}${path}`
    return `  <url>\n    <loc>${loc}</loc>\n    <changefreq>weekly</changefreq>\n  </url>`
  }).join('\n')

  return `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls}\n</urlset>\n`
}

function sitemapPlugin(origin: string, apiOrigin: string): Plugin {
  const fallbackXml = sitemapXml(origin)

  return {
    name: 'hebr-sitemap',
    configureServer(server) {
      server.middlewares.use((req, res, next) => {
        const requestUrl = 'url' in req ? String((req as { url?: string }).url ?? '') : ''
        if (requestUrl.split('?')[0] !== '/sitemap.xml') {
          next()
          return
        }

        void fetch(`${apiOrigin}/api/sitemap.xml`)
          .then(async (response) => {
            const body = response.ok ? await response.text() : fallbackXml
            res.setHeader('Content-Type', 'application/xml; charset=utf-8')
            res.end(body)
          })
          .catch(() => {
            res.setHeader('Content-Type', 'application/xml; charset=utf-8')
            res.end(fallbackXml)
          })
      })
    },
    async generateBundle() {
      let source = fallbackXml
      try {
        const response = await fetch(`${apiOrigin}/api/sitemap.xml`)
        if (response.ok) {
          source = await response.text()
        }
      } catch {
        // Keep static public paths when the API is unavailable at build time.
      }

      this.emitFile({
        type: 'asset',
        fileName: 'sitemap.xml',
        source,
      })
    },
  }
}

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, '.', 'VITE_')
  const origin = (env.VITE_PUBLIC_SITE_URL || 'http://localhost:5173').replace(/\/$/, '')
  const apiOrigin = (env.VITE_API_URL || 'http://127.0.0.1:8000').replace(/\/$/, '')

  return {
    plugins: [react(), tailwindcss(), sitemapPlugin(origin, apiOrigin)],
    server: {
      host: '127.0.0.1',
      port: 5173,
      strictPort: true,
      proxy: {
        '/api': {
          target: 'http://127.0.0.1:8000',
          changeOrigin: true,
        },
      },
    },
  }
})
