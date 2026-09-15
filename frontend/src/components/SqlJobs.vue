<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  cancelJob,
  createSqlJob,
  getJob,
  getJobs,
  type ClientConnection,
  type JobPage,
  type SqlJob,
} from '../api'

const { connections } = defineProps<{ connections: ClientConnection[] }>()
const { locale, t } = useI18n()
const connectionId = ref('')
const sql = ref('SELECT * FROM customers LIMIT 25')
const acknowledged = ref(false)
const jobs = ref<JobPage | null>(null)
const selected = ref<SqlJob | null>(null)
const loading = ref(false)
const submitting = ref(false)
const error = ref('')
let polling: number | undefined

async function load(page = jobs.value?.pagination.page ?? 1): Promise<void> {
  loading.value = true
  try {
    jobs.value = await getJobs(page)
    if (selected.value) selected.value = await getJob(selected.value.id)
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'unknown_error'
  } finally {
    loading.value = false
  }
}

async function submit(): Promise<void> {
  if (!connectionId.value) return
  submitting.value = true
  error.value = ''
  try {
    selected.value = await createSqlJob(connectionId.value, sql.value, acknowledged.value)
    acknowledged.value = false
    await load(1)
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'unknown_error'
  } finally {
    submitting.value = false
  }
}

async function inspect(job: SqlJob): Promise<void> {
  error.value = ''
  try {
    selected.value = await getJob(job.id)
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'unknown_error'
  }
}

async function cancel(job: SqlJob): Promise<void> {
  try {
    selected.value = await cancelJob(job.id)
    await load()
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'unknown_error'
  }
}

onMounted(async () => {
  await load(1)
  polling = globalThis.setInterval(() => {
    if (
      jobs.value?.items.some((job) =>
        ['queued', 'running', 'cancel_requested'].includes(job.status),
      )
    )
      void load()
  }, 3000)
})
onUnmounted(() => globalThis.clearInterval(polling))
</script>

<template>
  <section class="panel sql-editor">
    <div class="section-heading">
      <div>
        <h2>{{ t('jobs.newTitle') }}</h2>
        <p>{{ t('jobs.description') }}</p>
      </div>
    </div>
    <p v-if="error" class="alert error">{{ t(`errors.${error}`, error) }}</p>
    <form @submit.prevent="submit">
      <label
        ><span>{{ t('jobs.database') }}</span
        ><select v-model="connectionId" required>
          <option value="" disabled>{{ t('jobs.chooseDatabase') }}</option>
          <option v-for="connection in connections" :key="connection.id" :value="connection.id">
            {{ connection.name }} — {{ connection.database }}
          </option>
        </select></label
      >
      <label
        ><span>SQL</span><textarea v-model="sql" required maxlength="100000" spellcheck="false" />
      </label>
      <label class="risk-check"
        ><input v-model="acknowledged" type="checkbox" />
        <span>{{ t('jobs.writeRisk') }}</span></label
      >
      <button class="button primary" type="submit" :disabled="submitting || !connectionId">
        {{ submitting ? t('jobs.queuing') : t('jobs.enqueue') }}
      </button>
    </form>
  </section>

  <section class="panel table-panel jobs-panel">
    <div class="section-heading">
      <div>
        <h2>{{ t('jobs.listTitle') }}</h2>
        <p>{{ t('jobs.listDescription') }}</p>
      </div>
      <button class="text-button" type="button" :disabled="loading" @click="load()">
        {{ t('jobs.refresh') }}
      </button>
    </div>
    <div v-if="jobs?.items.length" class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>{{ t('jobs.time') }}</th>
            <th>{{ t('jobs.actor') }}</th>
            <th>{{ t('jobs.database') }}</th>
            <th>{{ t('jobs.operation') }}</th>
            <th>{{ t('jobs.status') }}</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="job in jobs.items" :key="job.id">
            <td>{{ new Date(job.createdAt).toLocaleString(locale) }}</td>
            <td>{{ job.actor.email }}</td>
            <td>{{ job.connection.name }}</td>
            <td>{{ job.operation.toUpperCase() }}</td>
            <td>
              <span class="status" :class="job.status">{{ t(`jobs.statuses.${job.status}`) }}</span>
            </td>
            <td class="actions">
              <button class="text-button" type="button" @click="inspect(job)">
                {{ t('jobs.details') }}
              </button>
              <button
                v-if="['queued', 'running'].includes(job.status)"
                class="text-button danger"
                type="button"
                @click="cancel(job)"
              >
                {{ t('common.cancel') }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-else class="compact-empty">{{ loading ? t('common.loading') : t('jobs.empty') }}</div>
  </section>

  <section v-if="selected" class="panel job-details">
    <div class="section-heading">
      <div>
        <h2>{{ t('jobs.details') }}</h2>
        <p>{{ selected.connection.name }} · {{ selected.id }}</p>
      </div>
      <span class="status" :class="selected.status">{{
        t(`jobs.statuses.${selected.status}`)
      }}</span>
    </div>
    <pre><code>{{ selected.sql }}</code></pre>
    <p v-if="selected.error" class="alert error">
      {{ t(`errors.${selected.error}`, selected.error) }}
    </p>
    <p v-if="selected.affectedRows !== null">
      <strong>{{ t('jobs.affectedRows', { count: selected.affectedRows }) }}</strong>
    </p>
    <template v-if="selected.result"
      ><p>
        {{
          t('jobs.resultRetention', {
            time: new Date(selected.result.expiresAt).toLocaleString(locale),
          })
        }}
      </p>
      <p v-if="selected.result.truncated" class="alert">{{ t('jobs.truncated') }}</p>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th v-for="column in selected.result.columns" :key="column">{{ column }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, index) in selected.result.rows" :key="index">
              <td v-for="column in selected.result.columns" :key="column">
                <code>{{ JSON.stringify(row[column]) }}</code>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </section>
</template>
