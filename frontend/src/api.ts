export type UserRole = 'administrator' | 'manager'

export interface User {
  id: string
  email: string
  role: UserRole
  active: boolean
  createdAt: string
  lastLoginAt: string | null
}

export interface AuthState {
  authenticated: boolean
  user: User | null
}

export type ConnectionStatus = 'untested' | 'reachable' | 'unreachable'

export interface ClientConnection {
  id: string
  name: string
  host: string
  port: number
  database: string
  credentialsConfigured: boolean
  active: boolean
  status: ConnectionStatus
  serverVersion: string | null
  lastErrorCode: string | null
  lastCheckedAt: string | null
}

export interface ConnectionInput {
  name: string
  host: string
  port: number
  database: string
  username?: string | null
  password?: string | null
}

export type AccessMode = 'default_deny' | 'default_allow'

export interface TablePermission {
  id: string
  table: string
  select: boolean | null
  insert: boolean | null
  update: boolean | null
  delete: boolean | null
}

export interface DatabaseAccess {
  id: string
  mode: AccessMode
  user: User
  connection: ClientConnection
  tablePermissions: TablePermission[]
  createdAt: string
  updatedAt: string
}

async function responseJson<T>(response: Response): Promise<T> {
  if (!response.ok) {
    const payload = (await response.json().catch(() => ({}))) as { error?: string }
    throw new Error(payload.error ?? `http_${response.status}`)
  }
  return (await response.json()) as T
}

export async function getAuthState(): Promise<AuthState> {
  return responseJson<AuthState>(await fetch('/api/auth/me', { credentials: 'same-origin' }))
}

export async function getAuthProviders(): Promise<string[]> {
  const response = await responseJson<{ providers: string[] }>(
    await fetch('/api/auth/config', { credentials: 'same-origin' }),
  )
  return response.providers
}

async function getCsrfToken(): Promise<string> {
  const response = await responseJson<{ token: string }>(
    await fetch('/api/auth/csrf', { credentials: 'same-origin' }),
  )
  return response.token
}

async function mutate<T>(url: string, method: string, body?: object): Promise<T> {
  const csrfToken = await getCsrfToken()
  return responseJson<T>(
    await fetch(url, {
      method,
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: body ? JSON.stringify(body) : undefined,
    }),
  )
}

async function mutateVoid(url: string, method: string): Promise<void> {
  const csrfToken = await getCsrfToken()
  const response = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers: { 'X-CSRF-Token': csrfToken },
  })
  if (!response.ok) throw new Error(`http_${response.status}`)
}

export async function getUsers(): Promise<User[]> {
  const response = await responseJson<{ items: User[] }>(
    await fetch('/api/admin/users', { credentials: 'same-origin' }),
  )
  return response.items
}

export async function createManager(email: string): Promise<User> {
  return (await mutate<{ user: User }>('/api/admin/users', 'POST', { email })).user
}

export async function setUserActive(id: string, active: boolean): Promise<User> {
  return (await mutate<{ user: User }>(`/api/admin/users/${id}/status`, 'PATCH', { active })).user
}

export async function getConnections(): Promise<ClientConnection[]> {
  const response = await responseJson<{ items: ClientConnection[] }>(
    await fetch('/api/admin/connections', { credentials: 'same-origin' }),
  )
  return response.items
}

export async function createConnection(input: ConnectionInput): Promise<ClientConnection> {
  return (await mutate<{ connection: ClientConnection }>('/api/admin/connections', 'POST', input))
    .connection
}

export async function updateConnection(
  id: string,
  input: ConnectionInput,
): Promise<ClientConnection> {
  return (
    await mutate<{ connection: ClientConnection }>(`/api/admin/connections/${id}`, 'PUT', input)
  ).connection
}

export async function setConnectionActive(id: string, active: boolean): Promise<ClientConnection> {
  return (
    await mutate<{ connection: ClientConnection }>(`/api/admin/connections/${id}/status`, 'PATCH', {
      active,
    })
  ).connection
}

export async function testConnection(id: string): Promise<ClientConnection> {
  return (
    await mutate<{ connection: ClientConnection }>(`/api/admin/connections/${id}/test`, 'POST')
  ).connection
}

export async function getDatabaseAccesses(): Promise<DatabaseAccess[]> {
  const response = await responseJson<{ items: DatabaseAccess[] }>(
    await fetch('/api/admin/access', { credentials: 'same-origin' }),
  )
  return response.items
}

export async function createDatabaseAccess(
  userId: string,
  connectionId: string,
  mode: AccessMode,
): Promise<DatabaseAccess> {
  return (
    await mutate<{ access: DatabaseAccess }>('/api/admin/access', 'POST', {
      userId,
      connectionId,
      mode,
    })
  ).access
}

export async function updateDatabaseAccessMode(
  id: string,
  mode: AccessMode,
): Promise<DatabaseAccess> {
  return (await mutate<{ access: DatabaseAccess }>(`/api/admin/access/${id}`, 'PUT', { mode }))
    .access
}

export async function deleteDatabaseAccess(id: string): Promise<void> {
  await mutateVoid(`/api/admin/access/${id}`, 'DELETE')
}

export async function updateTablePermission(
  accessId: string,
  table: string,
  decisions: Omit<TablePermission, 'id' | 'table'>,
): Promise<TablePermission> {
  return (
    await mutate<{ tablePermission: TablePermission }>(
      `/api/admin/access/${accessId}/tables/${encodeURIComponent(table)}`,
      'PUT',
      decisions,
    )
  ).tablePermission
}

export async function deleteTablePermission(accessId: string, table: string): Promise<void> {
  await mutateVoid(`/api/admin/access/${accessId}/tables/${encodeURIComponent(table)}`, 'DELETE')
}

export async function logout(): Promise<void> {
  const csrfToken = await getCsrfToken()
  const response = await fetch('/api/auth/logout', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'X-CSRF-Token': csrfToken },
  })
  if (!response.ok) throw new Error(`http_${response.status}`)
}
