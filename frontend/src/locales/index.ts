export const messages = {
  ru: {
    navigation: {
      databases: 'Базы данных',
      history: 'История',
      jobs: 'Задания',
      notifications: 'Уведомления',
    },
    profile: { name: 'Администратор', role: 'Системный доступ' },
    dashboard: {
      title: 'Рабочее пространство',
      stageTitle: 'Подготовка окружения завершена',
      stageDescription: 'Подключения к клиентским базам появятся на следующем этапе.',
    },
    databases: {
      title: 'Клиентские базы данных',
      description: 'Контролируемый доступ к данным без передачи реквизитов MySQL.',
      add: 'Добавить подключение',
      emptyTitle: 'Подключений пока нет',
      emptyDescription: 'На следующем этапе здесь появятся доступные базы данных.',
    },
  },
  kk: {
    navigation: {
      databases: 'Дерекқорлар',
      history: 'Тарих',
      jobs: 'Тапсырмалар',
      notifications: 'Хабарландырулар',
    },
    profile: { name: 'Әкімші', role: 'Жүйелік қолжетімділік' },
    dashboard: {
      title: 'Жұмыс кеңістігі',
      stageTitle: 'Ортаны дайындау аяқталды',
      stageDescription: 'Клиенттік дерекқор қосылымдары келесі кезеңде пайда болады.',
    },
    databases: {
      title: 'Клиенттік дерекқорлар',
      description: 'MySQL деректемелерін бермей басқарылатын қолжетімділік.',
      add: 'Қосылым қосу',
      emptyTitle: 'Қосылымдар әзірге жоқ',
      emptyDescription: 'Қолжетімді дерекқорлар келесі кезеңде осында пайда болады.',
    },
  },
  en: {
    navigation: {
      databases: 'Databases',
      history: 'History',
      jobs: 'Jobs',
      notifications: 'Notifications',
    },
    profile: { name: 'Administrator', role: 'System access' },
    dashboard: {
      title: 'Workspace',
      stageTitle: 'Environment setup is complete',
      stageDescription: 'Client database connections will arrive in the next stage.',
    },
    databases: {
      title: 'Client databases',
      description: 'Controlled access without sharing MySQL credentials.',
      add: 'Add connection',
      emptyTitle: 'No connections yet',
      emptyDescription: 'Available databases will appear here during the next stage.',
    },
  },
} as const
