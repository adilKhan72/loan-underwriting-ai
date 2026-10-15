import { ApiClient } from './api/client'

// sessionStorage, not localStorage: the token disappears when the tab closes.
const KEY = 'underwriting.token'

export const apiUrl: string = import.meta.env.VITE_API_URL ?? 'http://localhost:8000'

export function getToken(): string | null {
  return sessionStorage.getItem(KEY)
}

export function setToken(token: string): void {
  sessionStorage.setItem(KEY, token)
}

export function clearToken(): void {
  sessionStorage.removeItem(KEY)
}

export function api(): ApiClient {
  const token = getToken()
  if (!token) throw new Error('Not signed in')
  return new ApiClient(apiUrl, token)
}
