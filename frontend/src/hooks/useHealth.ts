import { useEffect, useState } from 'react'
import { ApiRequestError } from '../services/api'
import { getHealth } from '../services/health'
import type { HealthData } from '../types/api'

type HealthState =
  | { status: 'loading' }
  | { status: 'ok'; data: HealthData }
  | { status: 'error'; message: string }

export function useHealth(): HealthState {
  const [state, setState] = useState<HealthState>({ status: 'loading' })

  useEffect(() => {
    let cancelled = false

    getHealth()
      .then((response) => {
        if (!cancelled) {
          setState({ status: 'ok', data: response.data })
        }
      })
      .catch((error: unknown) => {
        if (cancelled) {
          return
        }

        const message = error instanceof ApiRequestError
          ? error.message
          : 'تعذر الاتصال بالخادم.'

        setState({ status: 'error', message })
      })

    return () => {
      cancelled = true
    }
  }, [])

  return state
}
