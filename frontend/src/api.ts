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

export interface SchemaColumn {
  name: string
  type: string
  nullable: boolean
  autoincrement: boolean
  generated: boolean
  hasDefault: boolean
}

export interface SchemaTable {
  name: string
  kind: 'table' | 'view'
  columns: SchemaColumn[]
  primaryKey: string[]
  readOnly: boolean
  permissions: { select: boolean; insert: boolean; update: boolean; delete: boolean }
}

export interface RowPage {
  table: Omit<SchemaTable, 'permissions'>
  rows: Record<string, unknown>[]
  pagination: { page: number; pageSize: number; total: number; pages: number }
}

export interface TypedValue {
  type: string
  value: unknown
}

export interface AuditOperation {
  id: string
  action: 'insert' | 'update' | 'delete' | 'undo_insert' | 'undo_update' | 'undo_delete'
  status: 'succeeded' | 'failed' | 'conflict'
  table: string
  actor: { id: string; email: string }
  connection: { id: string; name: string; database: string }
  primaryKey: Record<string, TypedValue>
  affectedRows: number
  correlationId: string
  error: string | null
  before: Record<string, TypedValue> | null
  after: Record<string, TypedValue> | null
  diff: Record<string, { before: TypedValue | null; after: TypedValue | null }> | null
  createdAt: string
  undoesOperationId: string | null
  undoAvailable: boolean | null
}

export interface AuditPage {
  items: AuditOperation[]
  pagination: { page: number; pageSize: number; total: number; pages: number }
}

export type JobStatus =
  'queued' | 'running' | 'succeeded' | 'failed' | 'cancel_requested' | 'cancelled'

export interface SqlJob {
  id: string
  status: JobStatus
  actor: { id: string; email: string }
  connection: { id: string; name: string; database: string }
  operation: 'select' | 'insert' | 'update' | 'delete'
  sql: string
  tables: string[]
  affectedRows: number | null
  error: string | null
  correlationId: string
  createdAt: string
  startedAt: string | null
  completedAt: string | null
  result: {
    columns: string[]
    rows: Record<string, unknown>[]
    rowCount: number
    truncated: boolean
    expiresAt: string
  } | null
}

export interface JobPage {
  items: SqlJob[]
  pagination: { page: number; pageSize: number; total: number; pages: number }
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

export async function getAvailableConnections(): Promise<ClientConnection[]> {
  const response = await responseJson<{ items: ClientConnection[] }>(
    await fetch('/api/connections', { credentials: 'same-origin' }),
  )
  return response.items
}

export async function getDatabaseSchema(connectionId: string): Promise<SchemaTable[]> {
  const response = await responseJson<{ items: SchemaTable[] }>(
    await fetch(`/api/connections/${connectionId}/schema`, { credentials: 'same-origin' }),
  )
  return response.items
}

export async function getTableRows(
  connectionId: string,
  table: string,
  options: {
    page: number
    pageSize: number
    sort?: string
    direction?: 'asc' | 'desc'
    filter?: Record<string, string>
  },
): Promise<RowPage> {
  const parameters = new URLSearchParams({
    page: String(options.page),
    pageSize: String(options.pageSize),
    direction: options.direction ?? 'asc',
  })
  if (options.sort) parameters.set('sort', options.sort)
  for (const [column, value] of Object.entries(options.filter ?? {}))
    parameters.set(`filter[${column}]`, value)

  return responseJson<RowPage>(
    await fetch(
      `/api/connections/${connectionId}/tables/${encodeURIComponent(table)}/rows?${parameters}`,
      { credentials: 'same-origin' },
    ),
  )
}

export async function insertRow(
  connectionId: string,
  table: string,
  values: Record<string, unknown>,
): Promise<AuditOperation> {
  return (
    await mutate<{ operation: AuditOperation }>(
      `/api/connections/${connectionId}/tables/${encodeURIComponent(table)}/rows`,
      'POST',
      { values },
    )
  ).operation
}

export async function updateRow(
  connectionId: string,
  table: string,
  primaryKey: Record<string, unknown>,
  values: Record<string, unknown>,
): Promise<AuditOperation> {
  return (
    await mutate<{ operation: AuditOperation }>(
      `/api/connections/${connectionId}/tables/${encodeURIComponent(table)}/rows/one`,
      'PUT',
      { primaryKey, values },
    )
  ).operation
}

export async function deleteRow(
  connectionId: string,
  table: string,
  primaryKey: Record<string, unknown>,
): Promise<AuditOperation> {
  return (
    await mutate<{ operation: AuditOperation }>(
      `/api/connections/${connectionId}/tables/${encodeURIComponent(table)}/rows/one`,
      'DELETE',
      { primaryKey, confirmed: true },
    )
  ).operation
}

export async function getAuditHistory(
  connectionId: string,
  page = 1,
  pageSize = 25,
): Promise<AuditPage> {
  return responseJson<AuditPage>(
    await fetch(`/api/connections/${connectionId}/history?page=${page}&pageSize=${pageSize}`, {
      credentials: 'same-origin',
    }),
  )
}

export async function undoOperation(operationId: string): Promise<AuditOperation> {
  return (await mutate<{ operation: AuditOperation }>(`/api/audit/${operationId}/undo`, 'POST'))
    .operation
}

export async function createSqlJob(
  connectionId: string,
  sql: string,
  writeAcknowledged: boolean,
): Promise<SqlJob> {
  return (
    await mutate<{ job: SqlJob }>(`/api/connections/${connectionId}/sql-jobs`, 'POST', {
      sql,
      writeAcknowledged,
    })
  ).job
}

export async function getJobs(page = 1, pageSize = 25): Promise<JobPage> {
  return responseJson<JobPage>(
    await fetch(`/api/jobs?page=${page}&pageSize=${pageSize}`, { credentials: 'same-origin' }),
  )
}

export async function getJob(id: string): Promise<SqlJob> {
  return (
    await responseJson<{ job: SqlJob }>(
      await fetch(`/api/jobs/${id}`, { credentials: 'same-origin' }),
    )
  ).job
}

export async function cancelJob(id: string): Promise<SqlJob> {
  return (await mutate<{ job: SqlJob }>(`/api/jobs/${id}/cancel`, 'POST')).job
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
