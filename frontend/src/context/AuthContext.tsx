import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import { getStoredToken } from '../services/api'
import {
  clearSession,
  login as loginRequest,
  logout as logoutRequest,
  me,
  persistSession,
  register as registerRequest,
} from '../services/auth'
import type { AuthUser } from '../types/api'

type AuthContextValue = {
  user: AuthUser | null
  isAuthenticated: boolean
  isReady: boolean
  login: (email: string, password: string) => Promise<AuthUser>
  register: (payload: {
    name: string
    email: string
    password: string
    password_confirmation: string
  }) => Promise<AuthUser>
  logout: () => Promise<void>
  discardSession: () => void
  refreshUser: () => Promise<AuthUser | null>
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null)
  const [isReady, setIsReady] = useState(false)

  useEffect(() => {
    let cancelled = false

    if (!getStoredToken()) {
      setIsReady(true)
      return
    }

    me()
      .then((response) => {
        if (cancelled) {
          return
        }

        if (response.data.is_active === false) {
          clearSession()
          setUser(null)
          return
        }

        setUser(response.data)
      })
      .catch(() => {
        if (!cancelled) {
          clearSession()
          setUser(null)
        }
      })
      .finally(() => {
        if (!cancelled) {
          setIsReady(true)
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  const value = useMemo<AuthContextValue>(() => ({
    user,
    isAuthenticated: user !== null,
    isReady,
    async login(email, password) {
      const response = await loginRequest({ email, password })
      persistSession(response.data)
      setUser(response.data.user)
      return response.data.user
    },
    async register(payload) {
      const response = await registerRequest(payload)
      persistSession(response.data)
      setUser(response.data.user)
      return response.data.user
    },
    async logout() {
      try {
        await logoutRequest()
      } finally {
        clearSession()
        setUser(null)
      }
    },
    discardSession() {
      clearSession()
      setUser(null)
    },
    async refreshUser() {
      try {
        const response = await me()

        if (response.data.is_active === false) {
          clearSession()
          setUser(null)
          return null
        }

        setUser(response.data)
        return response.data
      } catch {
        clearSession()
        setUser(null)
        return null
      }
    },
  }), [user, isReady])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)

  if (!context) {
    throw new Error('useAuth must be used within AuthProvider')
  }

  return context
}
