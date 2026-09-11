import { SeoHead } from './components/seo/SeoHead'
import { AuthProvider } from './context/AuthContext'
import { PlatformSettingsProvider } from './context/PlatformSettingsContext'
import { ToastProvider } from './context/ToastContext'
import { AppRoutes } from './routes'

export default function App() {
  return (
    <AuthProvider>
      <PlatformSettingsProvider>
        <ToastProvider>
          <SeoHead />
          <AppRoutes />
        </ToastProvider>
      </PlatformSettingsProvider>
    </AuthProvider>
  )
}
