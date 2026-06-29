import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { apiPost } from '@/api/http'

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
  const isStaff    = computed(() => user.value?.role === 'staff' || user.value?.role === 'admin')

  async function login(email: string, password: string) {
    const res = await apiPost<LoginResponse>('/auth/login', { email, password })
    user.value  = res.user
    token.value = res.token
    localStorage.setItem('auth_token', res.token)
    localStorage.setItem('auth_user',  JSON.stringify(res.user))
  }

  async function logout() {
    if (token.value) {
      try { await apiPost('/auth/logout', {}, token.value) } catch {}
    }
    user.value  = null
    token.value = null
    localStorage.removeItem('auth_token')
    localStorage.removeItem('auth_user')
  }

  return { user, token, isLoggedIn, isStaff, login, logout }
})
