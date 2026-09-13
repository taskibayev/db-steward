import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import { describe, expect, it } from 'vitest'
import App from './App.vue'
import { messages } from './locales'
describe('App', () => {
  it('renders the workspace', () => {
    const wrapper = mount(App, {
      global: { plugins: [createI18n({ legacy: false, locale: 'ru', messages })] },
    })
    expect(wrapper.text()).toContain('DB Steward')
    expect(wrapper.text()).toContain('Клиентские базы данных')
  })
})
