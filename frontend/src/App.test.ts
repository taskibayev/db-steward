import { flushPromises, mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import { afterEach, describe, expect, it, vi } from 'vitest'
import App from './App.vue'
import { messages } from './locales'

function jsonResponse(payload: unknown): Response {
  return new Response(JSON.stringify(payload), {
    status: 200,
    headers: { 'Content-Type': 'application/json' },
  })
}

function mountApp() {
  return mount(App, {
    global: { plugins: [createI18n({ legacy: false, locale: 'ru', messages })] },
  })
}

afterEach(() => vi.unstubAllGlobals())

describe('App', () => {
  it('renders sign-in for an anonymous user', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn((url: string) =>
        Promise.resolve(
          jsonResponse(
            url.endsWith('/me')
              ? { authenticated: false, user: null }
              : { providers: ['google', 'mock'] },
          ),
        ),
      ),
    )
    const wrapper = mountApp()
    await flushPromises()

    expect(wrapper.text()).toContain('Вход в DB Steward')
    expect(wrapper.text()).toContain('Продолжить с Google')
    expect(wrapper.text()).toContain('Тестовый вход')
  })

  it('renders connection and user management for an administrator', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn((url: string) => {
        if (url.endsWith('/me'))
          return Promise.resolve(
            jsonResponse({
              authenticated: true,
              user: {
                id: '1',
                email: 'admin@example.com',
                role: 'administrator',
                active: true,
                createdAt: '',
                lastLoginAt: null,
              },
            }),
          )
        if (url.endsWith('/config'))
          return Promise.resolve(jsonResponse({ providers: ['google', 'mock'] }))
        if (url.endsWith('/connections')) return Promise.resolve(jsonResponse({ items: [] }))
        if (url.endsWith('/access')) return Promise.resolve(jsonResponse({ items: [] }))
        return Promise.resolve(
          jsonResponse({
            items: [
              {
                id: '1',
                email: 'admin@example.com',
                role: 'administrator',
                active: true,
                createdAt: '',
                lastLoginAt: null,
              },
            ],
          }),
        )
      }),
    )
    const wrapper = mountApp()
    await flushPromises()

    expect(wrapper.text()).toContain('Клиентские базы данных')
    expect(wrapper.text()).toContain('Новое подключение')
    await wrapper.get('nav button:nth-child(2)').trigger('click')
    expect(wrapper.text()).toContain('Пользователи')
    expect(wrapper.text()).toContain('admin@example.com')
    expect(wrapper.text()).toContain('Добавить менеджера')
    await wrapper.get('nav button:nth-child(3)').trigger('click')
    expect(wrapper.text()).toContain('Назначить базу менеджеру')
    expect(wrapper.text()).toContain('Правило для таблицы')
    await wrapper.get('nav button:nth-child(4)').trigger('click')
    expect(wrapper.text()).toContain('История базы данных')
  })

  it('lets a manager open an assigned table', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn((url: string) => {
        if (url.endsWith('/me'))
          return Promise.resolve(
            jsonResponse({
              authenticated: true,
              user: {
                id: '2',
                email: 'manager@example.com',
                role: 'manager',
                active: true,
                createdAt: '',
                lastLoginAt: null,
              },
            }),
          )
        if (url.endsWith('/config')) return Promise.resolve(jsonResponse({ providers: ['mock'] }))
        if (url.endsWith('/connections'))
          return Promise.resolve(
            jsonResponse({
              items: [
                {
                  id: 'db1',
                  name: 'Northwind Demo',
                  host: 'demo',
                  port: 3306,
                  database: 'northwind',
                  credentialsConfigured: true,
                  active: true,
                  status: 'reachable',
                  serverVersion: '8.4',
                  lastErrorCode: null,
                  lastCheckedAt: null,
                },
              ],
            }),
          )
        if (url.endsWith('/schema'))
          return Promise.resolve(
            jsonResponse({
              items: [
                {
                  name: 'orders',
                  kind: 'table',
                  primaryKey: ['id'],
                  readOnly: false,
                  permissions: { select: true, insert: true, update: true, delete: true },
                  columns: [
                    {
                      name: 'id',
                      type: 'int',
                      nullable: false,
                      autoincrement: true,
                      generated: false,
                      hasDefault: false,
                    },
                  ],
                },
              ],
            }),
          )
        return Promise.resolve(
          jsonResponse({
            table: {
              name: 'orders',
              kind: 'table',
              primaryKey: ['id'],
              readOnly: false,
              permissions: { select: true, insert: true, update: true, delete: true },
              columns: [
                {
                  name: 'id',
                  type: 'int',
                  nullable: false,
                  autoincrement: true,
                  generated: false,
                  hasDefault: false,
                },
              ],
            },
            rows: [{ id: 42 }],
            pagination: { page: 1, pageSize: 25, total: 1, pages: 1 },
          }),
        )
      }),
    )
    const wrapper = mountApp()
    await flushPromises()

    expect(wrapper.text()).toContain('Northwind Demo')
    await wrapper.get('.database-cards button').trigger('click')
    await flushPromises()
    await wrapper.get('.schema-sidebar button').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('42')
    expect(wrapper.text()).toContain('Всего строк: 1')
  })
})
