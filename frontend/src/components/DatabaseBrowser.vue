<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  getDatabaseSchema,
  getTableRows,
  type ClientConnection,
  type RowPage,
  type SchemaTable,
} from '../api'

defineProps<{ connections: ClientConnection[] }>()
const { t } = useI18n()
const connection = ref<ClientConnection | null>(null)
const tables = ref<SchemaTable[]>([])
const selectedTable = ref<SchemaTable | null>(null)
const result = ref<RowPage | null>(null)
const loading = ref(false)
const error = ref('')
const pageSize = ref(25)
const sort = ref<string>()
const direction = ref<'asc' | 'desc'>('asc')
const filterColumn = ref('')
const filterValue = ref('')

async function openDatabase(selected: ClientConnection): Promise<void> {
  loading.value = true
  error.value = ''
  connection.value = selected
  selectedTable.value = null
  result.value = null
  try {
    tables.value = await getDatabaseSchema(selected.id)
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'unknown_error'
  } finally {
    loading.value = false
  }
}

async function openTable(table: SchemaTable, page = 1): Promise<void> {
  if (!connection.value) return
  loading.value = true
  error.value = ''
  selectedTable.value = table
  try {
    result.value = await getTableRows(connection.value.id, table.name, {
      page,
      pageSize: pageSize.value,
      sort: sort.value,
      direction: direction.value,
      filter:
        filterColumn.value && filterValue.value
          ? { [filterColumn.value]: filterValue.value }
          : undefined,
    })
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'unknown_error'
  } finally {
    loading.value = false
  }
}

async function sortRows(column: string): Promise<void> {
  if (!selectedTable.value) return
  if (sort.value === column) direction.value = direction.value === 'asc' ? 'desc' : 'asc'
  else {
    sort.value = column
    direction.value = 'asc'
  }
  await openTable(selectedTable.value)
}

function displayCell(value: unknown): string {
  if (value === null) return 'NULL'
  if (typeof value === 'object') return t('browser.binary')
  return String(value)
}
</script>

<template>
  <section class="panel database-browser">
    <div class="section-heading">
      <div>
        <h2>{{ t('browser.databases') }}</h2>
        <p>{{ t('browser.chooseDatabase') }}</p>
      </div>
      <span class="count-badge">{{ connections.length }}</span>
    </div>
    <div v-if="connections.length" class="database-cards">
      <button
        v-for="item in connections"
        :key="item.id"
        type="button"
        :class="{ active: connection?.id === item.id }"
        @click="openDatabase(item)"
      >
        <strong>{{ item.name }}</strong
        ><small>{{ item.database }}</small>
      </button>
    </div>
    <div v-else class="compact-empty">{{ t('browser.noDatabases') }}</div>
    <p v-if="error" class="alert error">{{ t(`errors.${error}`, error) }}</p>
    <div v-if="loading" class="browser-loading">
      <span class="spinner" />{{ t('common.loading') }}
    </div>
    <div v-else-if="connection" class="browser-layout">
      <aside class="schema-sidebar">
        <h3>{{ t('browser.tables') }}</h3>
        <button
          v-for="table in tables"
          :key="table.name"
          type="button"
          :class="{ active: selectedTable?.name === table.name }"
          @click="openTable(table)"
        >
          <span>{{ table.name }}</span
          ><small>{{ table.kind === 'view' ? t('browser.view') : t('browser.table') }}</small>
        </button>
        <p v-if="!tables.length">{{ t('browser.noTables') }}</p>
      </aside>
      <div class="data-area">
        <template v-if="selectedTable && result">
          <div class="browser-toolbar">
            <div>
              <strong>{{ selectedTable.name }}</strong
              ><small v-if="selectedTable.readOnly">{{ t('browser.readOnly') }}</small>
            </div>
            <form class="filter-form" @submit.prevent="openTable(selectedTable)">
              <select v-model="filterColumn">
                <option value="">{{ t('browser.filterColumn') }}</option>
                <option
                  v-for="column in selectedTable.columns"
                  :key="column.name"
                  :value="column.name"
                >
                  {{ column.name }}
                </option>
              </select>
              <input v-model="filterValue" :placeholder="t('browser.filterValue')" /><button
                class="button secondary"
                type="submit"
              >
                {{ t('browser.apply') }}
              </button>
            </form>
            <select v-model.number="pageSize" @change="openTable(selectedTable)">
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
          </div>
          <div class="table-scroll data-grid">
            <table>
              <thead>
                <tr>
                  <th v-for="column in selectedTable.columns" :key="column.name">
                    <button type="button" @click="sortRows(column.name)">
                      {{ column.name }}
                      <span v-if="sort === column.name">{{
                        direction === 'asc' ? '↑' : '↓'
                      }}</span></button
                    ><small>{{ column.type }}</small>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, index) in result.rows" :key="index">
                  <td
                    v-for="column in selectedTable.columns"
                    :key="column.name"
                    :class="{ 'null-cell': row[column.name] === null }"
                  >
                    {{ displayCell(row[column.name]) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="pagination">
            <span>{{ t('browser.total', { count: result.pagination.total }) }}</span
            ><button
              class="text-button"
              type="button"
              :disabled="result.pagination.page <= 1"
              @click="openTable(selectedTable, result.pagination.page - 1)"
            >
              ← {{ t('browser.previous') }}</button
            ><strong>{{ result.pagination.page }} / {{ result.pagination.pages }}</strong
            ><button
              class="text-button"
              type="button"
              :disabled="result.pagination.page >= result.pagination.pages"
              @click="openTable(selectedTable, result.pagination.page + 1)"
            >
              {{ t('browser.next') }} →
            </button>
          </div>
        </template>
        <div v-else class="compact-empty">{{ t('browser.chooseTable') }}</div>
      </div>
    </div>
  </section>
</template>
