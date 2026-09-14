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
  })
})
