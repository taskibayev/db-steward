<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  createDatabaseAccess,
  createConnection,
  createManager,
  deleteDatabaseAccess,
  deleteTablePermission,
  getAuthProviders,
  getAuthState,
  getAvailableConnections,
  getConnections,
  getDatabaseAccesses,
  getUsers,
  logout,
  setConnectionActive,
  setUserActive,
  testConnection,
  updateDatabaseAccessMode,
  updateConnection,
  updateTablePermission,
  type AccessMode,
  type AuthState,
  type ClientConnection,
  type DatabaseAccess,
  type TablePermission,
  type User,
} from './api'
import DatabaseBrowser from './components/DatabaseBrowser.vue'
import AuditHistory from './components/AuditHistory.vue'
import SqlJobs from './components/SqlJobs.vue'

const { locale, t } = useI18n()
const auth = ref<AuthState>({ authenticated: false, user: null })
const providers = ref<string[]>([])
const users = ref<User[]>([])
const connections = ref<ClientConnection[]>([])
const availableConnections = ref<ClientConnection[]>([])
const accesses = ref<DatabaseAccess[]>([])
const managerEmail = ref('')
const mockEmail = ref('admin@example.com')
const activeSection = ref('databases')
const editingConnectionId = ref<string | null>(null)
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const connectionForm = reactive({
  name: '',
  host: '',
  port: 3306,
  database: '',
  username: '',
  password: '',
})
const accessForm = reactive({ userId: '', connectionId: '', mode: 'default_deny' as AccessMode })
const tableForm = reactive({
  accessId: '',
  table: '',
  select: '' as DecisionInput,
  insert: '' as DecisionInput,
  update: '' as DecisionInput,
  delete: '' as DecisionInput,
})
type DecisionInput = '' | 'allow' | 'deny'
const isAdmin = computed(() => auth.value.user?.role === 'administrator')
const managers = computed(() => users.value.filter((user) => user.role === 'manager'))
const navigation = computed(() =>
  isAdmin.value
    ? ['databases', 'users', 'access', 'history', 'jobs', 'notifications']
    : ['databases', 'history', 'jobs', 'notifications'],
)

onMounted(async () => {
  try {
    const [state, configuredProviders] = await Promise.all([getAuthState(), getAuthProviders()])
    auth.value = state
    providers.value = configuredProviders
    if (state.authenticated) availableConnections.value = await getAvailableConnections()
    if (isAdmin.value)
      [connections.value, users.value, accesses.value] = await Promise.all([
        getConnections(),
        getUsers(),
        getDatabaseAccesses(),
      ])
  } catch (caught) {
    setError(caught)
  } finally {
    loading.value = false
  }
})

function setError(caught: unknown): void {
  error.value = caught instanceof Error ? caught.message : 'unknown_error'
}

function replaceConnection(updated: ClientConnection): void {
  connections.value = connections.value.map((item) => (item.id === updated.id ? updated : item))
}

function resetConnectionForm(): void {
  Object.assign(connectionForm, {
    name: '',
    host: '',
    port: 3306,
    database: '',
    username: '',
    password: '',
  })
  editingConnectionId.value = null
}

function editConnection(connection: ClientConnection): void {
  Object.assign(connectionForm, {
    name: connection.name,
    host: connection.host,
    port: connection.port,
    database: connection.database,
    username: '',
    password: '',
  })
  editingConnectionId.value = connection.id
}

async function saveConnection(): Promise<void> {
  saving.value = true
  error.value = ''
  try {
    const input = {
      name: connectionForm.name,
      host: connectionForm.host,
      port: Number(connectionForm.port),
      database: connectionForm.database,
      username:
        editingConnectionId.value && !connectionForm.username ? null : connectionForm.username,
      password:
        editingConnectionId.value && !connectionForm.password ? null : connectionForm.password,
    }
    if (editingConnectionId.value)
      replaceConnection(await updateConnection(editingConnectionId.value, input))
    else connections.value.unshift(await createConnection(input))
    resetConnectionForm()
  } catch (caught) {
    setError(caught)
  } finally {
    saving.value = false
  }
}

async function checkConnection(connection: ClientConnection): Promise<void> {
  error.value = ''
  try {
    replaceConnection(await testConnection(connection.id))
  } catch (caught) {
    setError(caught)
    connections.value = await getConnections()
  }
}

async function toggleConnection(connection: ClientConnection): Promise<void> {
  error.value = ''
  try {
    replaceConnection(await setConnectionActive(connection.id, !connection.active))
  } catch (caught) {
    setError(caught)
  }
}

async function addManager(): Promise<void> {
  saving.value = true
  error.value = ''
  try {
    users.value.unshift(await createManager(managerEmail.value))
    managerEmail.value = ''
  } catch (caught) {
    setError(caught)
  } finally {
    saving.value = false
  }
}

async function toggleUser(user: User): Promise<void> {
  error.value = ''
  try {
    const updated = await setUserActive(user.id, !user.active)
    users.value = users.value.map((item) => (item.id === updated.id ? updated : item))
  } catch (caught) {
    setError(caught)
  }
}

function replaceAccess(updated: DatabaseAccess): void {
  accesses.value = accesses.value.map((item) => (item.id === updated.id ? updated : item))
}

async function addAccess(): Promise<void> {
  saving.value = true
  error.value = ''
  try {
    accesses.value.unshift(
      await createDatabaseAccess(accessForm.userId, accessForm.connectionId, accessForm.mode),
    )
    Object.assign(accessForm, { userId: '', connectionId: '', mode: 'default_deny' })
  } catch (caught) {
    setError(caught)
  } finally {
    saving.value = false
  }
}

async function changeAccessMode(access: DatabaseAccess): Promise<void> {
  try {
    replaceAccess(await updateDatabaseAccessMode(access.id, access.mode))
  } catch (caught) {
    setError(caught)
  }
}

async function revokeAccess(access: DatabaseAccess): Promise<void> {
  try {
    await deleteDatabaseAccess(access.id)
    accesses.value = accesses.value.filter((item) => item.id !== access.id)
  } catch (caught) {
    setError(caught)
  }
}

function decision(value: DecisionInput): boolean | null {
  return value === '' ? null : value === 'allow'
}

async function saveTableRule(): Promise<void> {
  saving.value = true
  error.value = ''
  try {
    const permission = await updateTablePermission(tableForm.accessId, tableForm.table, {
      select: decision(tableForm.select),
      insert: decision(tableForm.insert),
      update: decision(tableForm.update),
      delete: decision(tableForm.delete),
    })
    const access = accesses.value.find((item) => item.id === tableForm.accessId)
    if (access) {
      const permissions = access.tablePermissions.filter((item) => item.table !== permission.table)
      replaceAccess({ ...access, tablePermissions: [...permissions, permission] })
    }
    Object.assign(tableForm, {
      accessId: '',
      table: '',
      select: '',
      insert: '',
      update: '',
      delete: '',
    })
  } catch (caught) {
    setError(caught)
  } finally {
    saving.value = false
  }
}

async function removeTableRule(access: DatabaseAccess, permission: TablePermission): Promise<void> {
  try {
    await deleteTablePermission(access.id, permission.table)
    replaceAccess({
      ...access,
      tablePermissions: access.tablePermissions.filter((item) => item.id !== permission.id),
    })
  } catch (caught) {
    setError(caught)
  }
}

function sectionTitle(kind: 'eyebrow' | 'title'): string {
  if (activeSection.value === 'users') return t(`users.${kind}`)
  if (activeSection.value === 'access') return t(`access.${kind}`)
  if (activeSection.value === 'history') return t(`history.${kind}`)
  if (activeSection.value === 'jobs') return t(`jobs.${kind}`)
  return t(`connections.${kind}`)
}

async function signOut(): Promise<void> {
  await logout()
  globalThis.location.assign('/')
}
</script>

<template>
  <div v-if="loading" class="screen-center" aria-live="polite">
    <span class="spinner" />{{ t('common.loading') }}
  </div>
  <main v-else-if="!auth.authenticated" class="login-page">
    <section class="login-card">
      <div class="login-brand"><span class="brand-mark">DS</span><strong>DB Steward</strong></div>
      <p class="eyebrow">{{ t('auth.secureAccess') }}</p>
      <h1>{{ t('auth.title') }}</h1>
      <p>{{ t('auth.description') }}</p>
      <p v-if="error" class="alert error">{{ t(`errors.${error}`, error) }}</p>
      <a
        v-if="providers.includes('google')"
        class="button google"
        href="/api/auth/oauth/google/start"
        ><span class="google-g">G</span>{{ t('auth.google') }}</a
      >
      <form v-if="providers.includes('mock')" class="mock-login" @submit.prevent>
        <label for="mock-email">{{ t('auth.developmentEmail') }}</label>
        <div class="inline-form">
          <input id="mock-email" v-model="mockEmail" type="email" required /><a
            class="button secondary"
            :href="`/api/auth/oauth/mock/start?email=${encodeURIComponent(mockEmail)}`"
            >{{ t('auth.developmentLogin') }}</a
          >
        </div>
      </form>
      <div class="language-switcher">
        <button
          v-for="code in ['ru', 'kk', 'en']"
          :key="code"
          type="button"
          :class="{ active: locale === code }"
          @click="locale = code"
        >
          {{ code.toUpperCase() }}
        </button>
      </div>
    </section>
  </main>

  <div v-else class="app-shell">
    <aside class="sidebar">
      <div class="brand"><span class="brand-mark">DS</span><span>DB Steward</span></div>
      <nav :aria-label="t('navigation.label')">
        <button
          v-for="item in navigation"
          :key="item"
          type="button"
          :class="{ active: activeSection === item }"
          @click="activeSection = item"
        >
          {{ t(`navigation.${item}`) }}
        </button>
      </nav>
      <div class="profile">
        <span class="avatar">{{ auth.user?.email.slice(0, 1).toUpperCase() }}</span>
        <div class="profile-copy">
          <strong>{{ auth.user?.email }}</strong
          ><small>{{ t(`roles.${auth.user?.role}`) }}</small>
        </div>
        <button class="icon-button" type="button" :title="t('auth.logout')" @click="signOut">
          ↪
        </button>
      </div>
    </aside>

    <main class="workspace">
      <header class="topbar">
        <div>
          <p class="eyebrow">
            {{ sectionTitle('eyebrow') }}
          </p>
          <h1>{{ sectionTitle('title') }}</h1>
        </div>
        <select v-model="locale" :aria-label="t('common.language')">
          <option value="ru">Русский</option>
          <option value="kk">Қазақша</option>
          <option value="en">English</option>
        </select>
      </header>
      <p v-if="error" class="alert error">{{ t(`errors.${error}`, error) }}</p>

      <template v-if="isAdmin && activeSection === 'databases'">
        <section class="panel connection-editor">
          <div class="section-heading">
            <div>
              <h2>
                {{ editingConnectionId ? t('connections.editTitle') : t('connections.addTitle') }}
              </h2>
              <p>{{ t('connections.secretNote') }}</p>
            </div>
            <button
              v-if="editingConnectionId"
              class="text-button"
              type="button"
              @click="resetConnectionForm"
            >
              {{ t('common.cancel') }}
            </button>
          </div>
          <form class="connection-form" @submit.prevent="saveConnection">
            <label
              ><span>{{ t('connections.name') }}</span
              ><input v-model="connectionForm.name" required maxlength="100"
            /></label>
            <label
              ><span>{{ t('connections.host') }}</span
              ><input v-model="connectionForm.host" required maxlength="255"
            /></label>
            <label class="port-field"
              ><span>{{ t('connections.port') }}</span
              ><input
                v-model.number="connectionForm.port"
                type="number"
                min="1"
                max="65535"
                required
            /></label>
            <label
              ><span>{{ t('connections.database') }}</span
              ><input v-model="connectionForm.database" required maxlength="64"
            /></label>
            <label
              ><span>{{ t('connections.username') }}</span
              ><input
                v-model="connectionForm.username"
                :required="!editingConnectionId"
                autocomplete="off"
            /></label>
            <label
              ><span>{{ t('connections.password') }}</span
              ><input v-model="connectionForm.password" type="password" autocomplete="new-password"
            /></label>
            <button class="button primary" type="submit" :disabled="saving">
              {{ saving ? t('connections.testing') : t('connections.saveAndTest') }}
            </button>
          </form>
        </section>
        <section class="panel table-panel">
          <div class="section-heading">
            <div>
              <h2>{{ t('connections.listTitle') }}</h2>
              <p>{{ t('connections.listDescription') }}</p>
            </div>
            <span class="count-badge">{{ connections.length }}</span>
          </div>
          <div v-if="connections.length" class="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>{{ t('connections.name') }}</th>
                  <th>{{ t('connections.endpoint') }}</th>
                  <th>{{ t('connections.version') }}</th>
                  <th>{{ t('connections.status') }}</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                <tr v-for="connection in connections" :key="connection.id">
                  <td>
                    <strong>{{ connection.name }}</strong
                    ><small>{{ connection.database }}</small>
                  </td>
                  <td>{{ connection.host }}:{{ connection.port }}</td>
                  <td>{{ connection.serverVersion ?? '—' }}</td>
                  <td>
                    <span class="status" :class="connection.status">{{
                      t(`connections.statuses.${connection.status}`)
                    }}</span
                    ><small v-if="!connection.active">{{ t('connections.disabled') }}</small>
                  </td>
                  <td class="actions">
                    <button class="text-button" type="button" @click="checkConnection(connection)">
                      {{ t('connections.test') }}</button
                    ><button class="text-button" type="button" @click="editConnection(connection)">
                      {{ t('common.edit') }}</button
                    ><button
                      class="text-button"
                      type="button"
                      @click="toggleConnection(connection)"
                    >
                      {{ connection.active ? t('common.disable') : t('common.enable') }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="compact-empty">{{ t('connections.empty') }}</div>
        </section>
        <DatabaseBrowser :connections="availableConnections" />
      </template>

      <template v-else-if="isAdmin && activeSection === 'users'">
        <section class="panel create-user">
          <div>
            <h2>{{ t('users.addTitle') }}</h2>
            <p>{{ t('users.addDescription') }}</p>
          </div>
          <form class="inline-form" @submit.prevent="addManager">
            <input
              v-model="managerEmail"
              type="email"
              :placeholder="t('users.emailPlaceholder')"
              required
              maxlength="254"
            /><button class="button primary" type="submit" :disabled="saving">
              {{ saving ? t('common.saving') : t('users.add') }}
            </button>
          </form>
        </section>
        <section class="panel table-panel">
          <div class="section-heading">
            <div>
              <h2>{{ t('users.listTitle') }}</h2>
              <p>{{ t('users.listDescription') }}</p>
            </div>
            <span class="count-badge">{{ users.length }}</span>
          </div>
          <div class="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>{{ t('users.email') }}</th>
                  <th>{{ t('users.role') }}</th>
                  <th>{{ t('users.lastLogin') }}</th>
                  <th>{{ t('users.status') }}</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                <tr v-for="user in users" :key="user.id">
                  <td>
                    <strong>{{ user.email }}</strong>
                  </td>
                  <td>{{ t(`roles.${user.role}`) }}</td>
                  <td>
                    {{ user.lastLoginAt ? new Date(user.lastLoginAt).toLocaleString(locale) : '—' }}
                  </td>
                  <td>
                    <span class="status" :class="user.active ? 'active' : 'disabled'">{{
                      user.active ? t('users.active') : t('users.disabled')
                    }}</span>
                  </td>
                  <td class="actions">
                    <button
                      v-if="user.id !== auth.user?.id"
                      type="button"
                      class="text-button"
                      @click="toggleUser(user)"
                    >
                      {{ user.active ? t('users.disable') : t('users.enable') }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </template>

      <template v-else-if="isAdmin && activeSection === 'access'">
        <section class="panel access-editor">
          <div class="section-heading">
            <div>
              <h2>{{ t('access.assignTitle') }}</h2>
              <p>{{ t('access.assignDescription') }}</p>
            </div>
          </div>
          <form class="access-form" @submit.prevent="addAccess">
            <label
              ><span>{{ t('access.manager') }}</span
              ><select v-model="accessForm.userId" required>
                <option value="" disabled>{{ t('access.chooseManager') }}</option>
                <option v-for="manager in managers" :key="manager.id" :value="manager.id">
                  {{ manager.email }}
                </option>
              </select></label
            >
            <label
              ><span>{{ t('access.database') }}</span
              ><select v-model="accessForm.connectionId" required>
                <option value="" disabled>{{ t('access.chooseDatabase') }}</option>
                <option
                  v-for="connection in connections"
                  :key="connection.id"
                  :value="connection.id"
                >
                  {{ connection.name }}
                </option>
              </select></label
            >
            <label
              ><span>{{ t('access.defaultMode') }}</span
              ><select v-model="accessForm.mode">
                <option value="default_deny">{{ t('access.modes.default_deny') }}</option>
                <option value="default_allow">{{ t('access.modes.default_allow') }}</option>
              </select></label
            >
            <button class="button primary" type="submit" :disabled="saving">
              {{ t('access.assign') }}
            </button>
          </form>
        </section>

        <section class="panel table-panel">
          <div class="section-heading">
            <div>
              <h2>{{ t('access.listTitle') }}</h2>
              <p>{{ t('access.listDescription') }}</p>
            </div>
            <span class="count-badge">{{ accesses.length }}</span>
          </div>
          <div v-if="accesses.length" class="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>{{ t('access.manager') }}</th>
                  <th>{{ t('access.database') }}</th>
                  <th>{{ t('access.defaultMode') }}</th>
                  <th>{{ t('access.exceptions') }}</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                <tr v-for="access in accesses" :key="access.id">
                  <td>
                    <strong>{{ access.user.email }}</strong>
                  </td>
                  <td>
                    <strong>{{ access.connection.name }}</strong
                    ><small>{{ access.connection.database }}</small>
                  </td>
                  <td>
                    <select v-model="access.mode" @change="changeAccessMode(access)">
                      <option value="default_deny">{{ t('access.modes.default_deny') }}</option>
                      <option value="default_allow">{{ t('access.modes.default_allow') }}</option>
                    </select>
                  </td>
                  <td>
                    <div v-if="access.tablePermissions.length" class="rule-list">
                      <div
                        v-for="permission in access.tablePermissions"
                        :key="permission.id"
                        class="rule-row"
                      >
                        <code>{{ permission.table }}</code
                        ><span
                          v-for="operation in ['select', 'insert', 'update', 'delete'] as const"
                          :key="operation"
                          :class="[
                            'decision',
                            permission[operation] === true
                              ? 'allow'
                              : permission[operation] === false
                                ? 'deny'
                                : 'inherit',
                          ]"
                          >{{ operation.toUpperCase() }}:
                          {{
                            permission[operation] === null
                              ? t('access.inherit')
                              : permission[operation]
                                ? t('access.allow')
                                : t('access.deny')
                          }}</span
                        ><button
                          class="text-button"
                          type="button"
                          @click="removeTableRule(access, permission)"
                        >
                          ×
                        </button>
                      </div>
                    </div>
                    <span v-else>—</span>
                  </td>
                  <td class="actions">
                    <button class="text-button danger" type="button" @click="revokeAccess(access)">
                      {{ t('access.revoke') }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="compact-empty">{{ t('access.empty') }}</div>
        </section>

        <section class="panel access-editor">
          <div class="section-heading">
            <div>
              <h2>{{ t('access.ruleTitle') }}</h2>
              <p>{{ t('access.ruleDescription') }}</p>
            </div>
          </div>
          <form class="rule-form" @submit.prevent="saveTableRule">
            <label
              ><span>{{ t('access.assignment') }}</span
              ><select v-model="tableForm.accessId" required>
                <option value="" disabled>{{ t('access.chooseAssignment') }}</option>
                <option v-for="access in accesses" :key="access.id" :value="access.id">
                  {{ access.user.email }} — {{ access.connection.name }}
                </option>
              </select></label
            >
            <label
              ><span>{{ t('access.table') }}</span
              ><input v-model="tableForm.table" required maxlength="64" pattern="[A-Za-z0-9_$-]+"
            /></label>
            <label
              v-for="operation in ['select', 'insert', 'update', 'delete'] as const"
              :key="operation"
              ><span>{{ operation.toUpperCase() }}</span
              ><select v-model="tableForm[operation]">
                <option value="">{{ t('access.inherit') }}</option>
                <option value="allow">{{ t('access.allow') }}</option>
                <option value="deny">{{ t('access.deny') }}</option>
              </select></label
            >
            <button class="button primary" type="submit" :disabled="saving">
              {{ t('access.saveRule') }}
            </button>
          </form>
        </section>
      </template>

      <template v-else-if="activeSection === 'databases'">
        <DatabaseBrowser :connections="availableConnections" />
      </template>

      <template v-else-if="activeSection === 'history'">
        <AuditHistory :connections="availableConnections" />
      </template>

      <template v-else-if="activeSection === 'jobs'">
        <SqlJobs :connections="availableConnections" />
      </template>

      <section v-else class="panel empty-state">
        <div class="database-glyph" aria-hidden="true"><span /><span /><span /></div>
        <h2>{{ t('databases.emptyTitle') }}</h2>
        <p>{{ t('databases.emptyDescription') }}</p>
      </section>
    </main>
  </div>
</template>
