import { createContext } from 'react'
import type { User } from '../types'

export interface AuthContextValue {
  token: string | null
  user: User | null
  login: (email: string, password: string) => Promise<void>
  register: (name: string, email: string, password: string) => Promise<void>
  logout: () => void
}

export const AuthContext = createContext<AuthContextValue | null>(null)