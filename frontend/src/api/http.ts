export const BASE = import.meta.env.VITE_API_URL as string

let _on401: (() => void) | null = null
let _handling401 = false

export function onUnauthorized(fn: () => void) { _on401 = fn }
export function resetUnauthorized() { _handling401 = false }

function handle401() {
  if (_handling401) return   // already redirecting — ignore all subsequent 401s
  _handling401 = true
  _on401?.()
}

export async function apiPost<T>(path: string, body: unknown, token?: string): Promise<T> {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'Accept':       'application/json',
  }
  if (token) headers['Authorization'] = `Bearer ${token}`

  const res = await fetch(`${BASE}${path}`, {
    method: 'POST',
    headers,
    body: JSON.stringify(body),
  })

  const data = await res.json()

  if (res.status === 401) { handle401(); throw new Error(data?.message ?? 'Unauthorized.') }
  if (!res.ok) {
    const firstError =
      data?.errors ? Object.values(data.errors as Record<string, string[]>)[0][0] : data?.message
    throw new Error(firstError ?? 'Something went wrong.')
  }

  return data as T
}

export async function apiGet<T>(path: string, token?: string): Promise<T> {
  const headers: Record<string, string> = { 'Accept': 'application/json' }
  if (token) headers['Authorization'] = `Bearer ${token}`

  const res = await fetch(`${BASE}${path}`, { headers })
  const data = await res.json()

  if (res.status === 401) { handle401(); throw new Error(data?.message ?? 'Unauthorized.') }
  if (!res.ok) throw new Error(data?.message ?? 'Request failed.')
  return data as T
}

export async function apiPatch<T>(path: string, body: unknown, token?: string): Promise<T> {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'Accept':       'application/json',
  }
  if (token) headers['Authorization'] = `Bearer ${token}`

  const res = await fetch(`${BASE}${path}`, {
    method: 'PATCH',
    headers,
    body: JSON.stringify(body),
  })

  const data = await res.json()

  if (res.status === 401) { handle401(); throw new Error(data?.message ?? 'Unauthorized.') }
  if (!res.ok) {
    const firstError =
      data?.errors ? Object.values(data.errors as Record<string, string[]>)[0][0] : data?.message
    throw new Error(firstError ?? 'Something went wrong.')
  }

  return data as T
}

export async function apiPut<T>(path: string, body: unknown, token?: string): Promise<T> {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'Accept':       'application/json',
  }
  if (token) headers['Authorization'] = `Bearer ${token}`

  const res = await fetch(`${BASE}${path}`, {
    method: 'PUT',
    headers,
    body: JSON.stringify(body),
  })

  const data = await res.json()

  if (res.status === 401) { handle401(); throw new Error(data?.message ?? 'Unauthorized.') }
  if (!res.ok) {
    const firstError =
      data?.errors ? Object.values(data.errors as Record<string, string[]>)[0][0] : data?.message
    throw new Error(firstError ?? 'Something went wrong.')
  }

  return data as T
}

export async function apiUpload<T>(path: string, formData: FormData, token?: string): Promise<T> {
  // Do NOT set Content-Type — the browser must set it with the multipart boundary
  const headers: Record<string, string> = { 'Accept': 'application/json' }
  if (token) headers['Authorization'] = `Bearer ${token}`

  const res = await fetch(`${BASE}${path}`, { method: 'POST', headers, body: formData })
  const data = await res.json()

  if (res.status === 401) { handle401(); throw new Error(data?.message ?? 'Unauthorized.') }
  if (!res.ok) {
    const firstError =
      data?.errors ? Object.values(data.errors as Record<string, string[]>)[0][0] : data?.message
    throw new Error(firstError ?? 'Something went wrong.')
  }

  return data as T
}

export async function apiDelete<T>(path: string, token?: string): Promise<T> {
  const headers: Record<string, string> = { 'Accept': 'application/json' }
  if (token) headers['Authorization'] = `Bearer ${token}`

  const res = await fetch(`${BASE}${path}`, { method: 'DELETE', headers })
  const data = await res.json()

  if (res.status === 401) { handle401(); throw new Error(data?.message ?? 'Unauthorized.') }
  if (!res.ok) throw new Error(data?.message ?? 'Request failed.')
  return data as T
}
