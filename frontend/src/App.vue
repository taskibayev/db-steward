<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  createManager,
  getAuthProviders,
  getAuthState,
  getUsers,
  logout,
  setUserActive,
  type AuthState,
  type User,
} from './api'

const { locale, t } = useI18n()
const auth = ref<AuthState>({ authenticated: false, user: null })
const providers = ref<string[]>([])
const users = ref<User[]>([])
const managerEmail = ref('')
const mockEmail = ref('admin@example.com')
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const isAdmin = computed(() => auth.value.user?.role === 'administrator')
const navigation = computed(() =>
  isAdmin.value
    ? ['databases', 'users', 'history', 'jobs', 'notifications']
    : ['databases', 'history', 'jobs', 'notifications'],
)

onMounted(async () => {
  try {
    const [state, configuredProviders] = await Promise.all([getAuthState(), getAuthProviders()])
    auth.value = state
    providers.value = configuredProviders
    if (isAdmin.value) users.value = await getUsers()
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'unknown_error'
  } finally {
    loading.value = false
  }
})

async function addManager(): Promise<void> {
  saving.value = true
  error.value = ''
  try {
    users.value.unshift(await createManager(managerEmail.value))
    managerEmail.value = ''
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'unknown_error'
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
    error.value = caught instanceof Error ? caught.message : 'unknown_error'
  }
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
          <input id="mock-email" v-model="mockEmail" type="email" required />
          <a
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
        <a
          v-for="(item, index) in navigation"
          :key="item"
          href="#"
          :class="{ active: item === 'users' || (index === 0 && !isAdmin) }"
          @click.prevent
          >{{ t(`navigation.${item}`) }}</a
        >
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
          <p class="eyebrow">{{ t('users.eyebrow') }}</p>
          <h1>{{ isAdmin ? t('users.title') : t('dashboard.title') }}</h1>
        </div>
        <select v-model="locale" :aria-label="t('common.language')">
          <option value="ru">Русский</option>
          <option value="kk">Қазақша</option>
          <option value="en">English</option>
        </select>
      </header>
      <p v-if="error" class="alert error">{{ t(`errors.${error}`, error) }}</p>

      <template v-if="isAdmin">
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
            />
            <button class="button primary" type="submit" :disabled="saving">
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

      <section v-else class="panel empty-state">
        <div class="database-glyph" aria-hidden="true"><span /><span /><span /></div>
        <h2>{{ t('databases.emptyTitle') }}</h2>
        <p>{{ t('databases.emptyDescription') }}</p>
      </section>
    </main>
  </div>
</template>
