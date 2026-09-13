<script setup lang="ts">
import { useI18n } from 'vue-i18n'
const { locale, t } = useI18n()
const navigation = ['databases', 'history', 'jobs', 'notifications'] as const
</script>

<template>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand"><span class="brand-mark">DS</span><span>DB Steward</span></div>
      <nav aria-label="Основная навигация">
        <a
          v-for="(item, index) in navigation"
          :key="item"
          href="#"
          :class="{ active: index === 0 }"
          @click.prevent
          >{{ t(`navigation.${item}`) }}</a
        >
      </nav>
      <div class="profile">
        <span class="avatar">А</span>
        <div>
          <strong>{{ t('profile.name') }}</strong
          ><small>{{ t('profile.role') }}</small>
        </div>
      </div>
    </aside>
    <main>
      <header class="topbar">
        <div>
          <p class="eyebrow">DB Steward</p>
          <h1>{{ t('dashboard.title') }}</h1>
        </div>
        <select v-model="locale" aria-label="Язык интерфейса">
          <option value="ru">Русский</option>
          <option value="kk">Қазақша</option>
          <option value="en">English</option>
        </select>
      </header>
      <section class="notice">
        <span class="notice-dot" />
        <div>
          <strong>{{ t('dashboard.stageTitle') }}</strong>
          <p>{{ t('dashboard.stageDescription') }}</p>
        </div>
      </section>
      <section>
        <div class="section-heading">
          <div>
            <h2>{{ t('databases.title') }}</h2>
            <p>{{ t('databases.description') }}</p>
          </div>
          <button type="button" disabled>
            {{ t('databases.add') }}
          </button>
        </div>
        <div class="empty-state">
          <div class="database-glyph" aria-hidden="true"><span /><span /><span /></div>
          <h3>{{ t('databases.emptyTitle') }}</h3>
          <p>{{ t('databases.emptyDescription') }}</p>
        </div>
      </section>
    </main>
  </div>
</template>
