import { useHealth } from '../hooks/useHealth'
import { API_BASE_URL } from '../services/api'

export function HealthStatus() {
  const health = useHealth()

  if (health.status === 'loading') {
    return <p className="text-sm text-slate-500">جاري التحقق من اتصال الواجهة الخلفية...</p>
  }

  if (health.status === 'error') {
    return (
      <p className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        فشل الاتصال بـ {API_BASE_URL}/api/health: {health.message}
      </p>
    )
  }

  return (
    <p className="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
      API متصل: {health.data.service} ({health.data.status})
    </p>
  )
}
