const BASE = import.meta.env.VITE_API_URL as string

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

  if (!res.ok) throw new Error(data?.message ?? 'Request failed.')
  return data as T
}
