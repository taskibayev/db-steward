<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  getNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  type Notification,
  type NotificationPage,
} from '../api'

const props = defineProps<{ revision: number }>()
const emit = defineEmits<{ unread: [count: number] }>()
const { locale, t } = useI18n()
const page = ref<NotificationPage | null>(null)
const loading = ref(false)
const error = ref('')

async function load(pageNumber = page.value?.pagination.page ?? 1): Promise<void> {
  loading.value = true
  try {
    page.value = await getNotifications(pageNumber)
    emit('unread', page.value.unreadCount)
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'unknown_error'
  } finally {
    loading.value = false
  }
}

async function read(notification: Notification): Promise<void> {
  if (notification.readAt) return
  await markNotificationRead(notification.id)
  await load()
}

async function readAll(): Promise<void> {
  await markAllNotificationsRead()
  await load()
}

function message(notification: Notification): string {
  return t(`notifications.types.${notification.type}`, {
    database: notification.data.connectionName ?? '',
    operation: String(notification.data.operation ?? '').toUpperCase(),
    count: notification.data.affectedRows ?? 0,
  })
}

onMounted(() => load(1))
watch(
  () => props.revision,
  () => load(1),
)
</script>

<template>
  <section class="panel notifications-panel">
    <div class="section-heading">
      <div>
        <h2>{{ t('notifications.listTitle') }}</h2>
        <p>{{ t('notifications.description') }}</p>
      </div>
      <button v-if="page?.unreadCount" class="text-button" type="button" @click="readAll">
        {{ t('notifications.readAll') }}
      </button>
    </div>
    <p v-if="error" class="alert error">{{ t(`errors.${error}`, error) }}</p>
    <div v-if="page?.items.length" class="notification-list">
      <button
        v-for="notification in page.items"
        :key="notification.id"
        type="button"
        class="notification-item"
        :class="{ unread: !notification.readAt }"
        @click="read(notification)"
      >
        <span class="notification-dot" />
        <span>
          <strong>{{ message(notification) }}</strong>
          <small>{{ new Date(notification.createdAt).toLocaleString(locale) }}</small>
        </span>
      </button>
    </div>
    <div v-else class="compact-empty">
      {{ loading ? t('common.loading') : t('notifications.empty') }}
    </div>
  </section>
</template>
