import { useCallback, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import { useNavigate } from 'react-router-dom'
import { api, setAuthToken, setUnauthorizedHandler } from '../lib/api'
import { AuthContext } from './authContext'
import type { AuthResponse, User } from '../types'

const TOKEN_KEY = 'supportdesk.token'
const USER_KEY = 'supportdesk.user'

function readStoredUser(): User | null {
  try {
    const raw = localStorage.getItem(USER_KEY)
    return raw ? (JSON.parse(raw) as User) : null
  } catch {
    return null
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [token, setToken] = useState<string | null>(() => localStorage.getItem(TOKEN_KEY))
  const [user, setUser] = useState<User | null>(readStoredUser)
  const navigate = useNavigate()

  const logout = useCallback(() => {
    setToken(null)
    setUser(null)
    setAuthToken(null)
    localStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(USER_KEY)
  }, [])

  const applyAuth = useCallback((auth: AuthResponse) => {
    setToken(auth.token)
    setUser(auth.user)
    setAuthToken(auth.token)
    localStorage.setItem(TOKEN_KEY, auth.token)
    localStorage.setItem(USER_KEY, JSON.stringify(auth.user))
  }, [])

  const login = useCallback(
    async (email: string, password: string) => {
      const auth = await api<AuthResponse>('/auth/login', {
        method: 'POST',
        body: { email, password },
      })
      applyAuth(auth)
    },
    [applyAuth],
  )

  const register = useCallback(
    async (name: string, email: string, password: string) => {
      const auth = await api<AuthResponse>('/auth/register', {
        method: 'POST',
        body: { name, email, password, password_confirmation: password },
      })
      applyAuth(auth)
    },
    [applyAuth],
  )

  useEffect(() => {
    if (token) setAuthToken(token)
    setUnauthorizedHandler(() => {
      logout()
      navigate('/login', { replace: true })
    })
    return () => setUnauthorizedHandler(null)
  }, [token, logout, navigate])

  const value = useMemo(
    () => ({ token, user, login, register, logout }),
    [token, user, login, register, logout],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
