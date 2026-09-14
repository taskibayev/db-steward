<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { getAuditHistory, type AuditPage, type ClientConnection } from '../api'

const { connections } = defineProps<{ connections: ClientConnection[] }>()
const { locale, t } = useI18n()
const connection = ref<ClientConnection | null>(null)
const history = ref<AuditPage | null>(null)
const loading = ref(false)
const error = ref('')
const expanded = ref<string | null>(null)
const connectionId = ref('')

async function selectConnection(): Promise<void> {
  const selected = connections.find((item) => item.id === connectionId.value)
  if (selected) await load(selected)
}

async function load(selected: ClientConnection, page = 1): Promise<void> {
  connection.value = selected
  loading.value = true
  error.value = ''
  try {
    history.value = await getAuditHistory(selected.id, page)
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'unknown_error'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <section class="panel table-panel audit-history">
    <div class="section-heading">
      <div>
        <h2>{{ t('history.chooseDatabase') }}</h2>
        <p>{{ t('history.description') }}</p>
      </div>
      <select v-model="connectionId" @change="selectConnection">
        <option value="" disabled>{{ t('history.database') }}</option>
        <option v-for="item in connections" :key="item.id" :value="item.id">{{ item.name }}</option>
      </select>
    </div>
    <p v-if="error" class="alert error">{{ t(`errors.${error}`, error) }}</p>
    <div v-if="loading" class="browser-loading">
      <span class="spinner" />{{ t('common.loading') }}
    </div>
    <div v-else-if="history?.items.length" class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>{{ t('history.time') }}</th>
            <th>{{ t('history.actor') }}</th>
            <th>{{ t('history.action') }}</th>
            <th>{{ t('history.table') }}</th>
            <th>{{ t('history.status') }}</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <template v-for="operation in history.items" :key="operation.id"
            ><tr>
              <td>{{ new Date(operation.createdAt).toLocaleString(locale) }}</td>
              <td>{{ operation.actor.email }}</td>
              <td>{{ operation.action.toUpperCase() }}</td>
              <td>
                <code>{{ operation.table }}</code>
              </td>
              <td>
                <span
                  class="status"
                  :class="operation.status === 'succeeded' ? 'active' : 'disabled'"
                  >{{ t(`history.statuses.${operation.status}`) }}</span
                >
              </td>
              <td>
                <button
                  class="text-button"
                  type="button"
                  @click="expanded = expanded === operation.id ? null : operation.id"
                >
                  {{ t('history.details') }}
                </button>
              </td>
            </tr>
            <tr v-if="expanded === operation.id" class="audit-details">
              <td colspan="6">
                <dl>
                  <div>
                    <dt>Primary key</dt>
                    <dd>
                      <code>{{ JSON.stringify(operation.primaryKey) }}</code>
                    </dd>
                  </div>
                  <div>
                    <dt>{{ t('history.diff') }}</dt>
                    <dd>
                      <code>{{ JSON.stringify(operation.diff) }}</code>
                    </dd>
                  </div>
                  <div>
                    <dt>Correlation ID</dt>
                    <dd>
                      <code>{{ operation.correlationId }}</code>
                    </dd>
                  </div>
                </dl>
              </td>
            </tr></template
          >
        </tbody>
      </table>
      <div class="pagination">
        <span>{{ t('browser.total', { count: history.pagination.total }) }}</span
        ><button
          class="text-button"
          type="button"
          :disabled="history.pagination.page <= 1"
          @click="connection && load(connection, history.pagination.page - 1)"
        >
          ← {{ t('browser.previous') }}</button
        ><strong>{{ history.pagination.page }} / {{ history.pagination.pages }}</strong
        ><button
          class="text-button"
          type="button"
          :disabled="history.pagination.page >= history.pagination.pages"
          @click="connection && load(connection, history.pagination.page + 1)"
        >
          {{ t('browser.next') }} →
        </button>
      </div>
    </div>
    <div v-else class="compact-empty">
      {{ connection ? t('history.empty') : t('history.selectPrompt') }}
    </div>
  </section>
</template>
