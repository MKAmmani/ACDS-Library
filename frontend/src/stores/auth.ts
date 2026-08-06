import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { BASE, apiPost, resetUnauthorized } from '@/api/http'

interface AuthUser {
  id: number
  name: string
  email: string
  role: 'admin' | 'staff' | 'user'
  is_active: boolean
  member_number?: string
}

interface LoginResponse {
  user: AuthUser
  token: string
}

export const useAuthStore = defineStore('auth', () => {
  const user  = ref<AuthUser | null>(JSON.parse(localStorage.getItem('auth_user') ?? 'null'))
  const token = ref<string | null>(localStorage.getItem('auth_token'))

  const isLoggedIn = computed(() => !!token.value)

  async function login(email: string, password: string) {
    const res = await apiPost<LoginResponse>('/auth/login', { email, password })
    resetUnauthorized()   // allow 401 handling again after a fresh login
    user.value  = res.user
    token.value = res.token
    localStorage.setItem('auth_token', res.token)
    localStorage.setItem('auth_user',  JSON.stringify(res.user))
  }

  async function logout() {
    // Use raw fetch — not apiPost — so a stale-token 401 on the logout endpoint
    // doesn't re-trigger handle401 and cause an infinite loop.
    if (token.value) {
      try {
        await fetch(`${BASE}/auth/logout`, {
          method: 'POST',
          headers: { 'Authorization': `Bearer ${token.value}`, 'Accept': 'application/json' },
        })
      } catch {}
    }
    user.value  = null
    token.value = null
    localStorage.removeItem('auth_token')
    localStorage.removeItem('auth_user')
  }

  return { user, token, isLoggedIn, login, logout }
})
