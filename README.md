# max-chat-web

Тестовое задание на должность «Frontend-разработчик React»: пользовательский интерфейс для отправки и получения текстовых сообщений в мессенджере MAX через сервис [GREEN-API](https://green-api.com/max).

**Демо:** https://max-chat-web.vercel.app

Интерфейс повторяет вид чата [web.max.ru](https://web.max.ru/): список чатов слева, переписка справа, фиолетовые исходящие сообщения. Ничего лишнего — только вход, создание чата, отправка и приём текста.

## Что умеет

- вход по учётным данным GREEN-API (`idInstance`, `apiTokenInstance`);
- создание чата по номеру телефона: номер резолвится в `chatId` методом `checkAccount`;
- отправка текстовых сообщений методом `SendMessage` с оптимистичным обновлением и статусами;
- приём входящих сообщений в реальном времени по HTTP API (`receiveNotification` + `deleteNotification`);
- статусы сообщений, счётчики непрочитанного, разделители по датам, автоскролл, чаты с непрочитанными;
- хранение учётных данных, чатов и истории в `localStorage` — при перезагрузке страницы всё на месте.

## Стек

React 18, TypeScript, Vite. Без UI-библиотек, без бэкенда и без state-менеджеров.

## Требования

- Node.js 18+ (проверено на 24);
- аккаунт GREEN-API с тарифом Developer (бесплатный);
- созданный инстанс MAX, авторизованный по QR-коду;
- в личном кабинете GREEN-API поле **Webhook URL должно быть пустым** — иначе `receiveNotification` отвечает ошибкой `Message cannot be received because custom webhook url is set`.

## Локальный запуск

```bash
git clone https://github.com/htmfff/max-chat-web.git
cd max-chat-web
npm install
npm run dev
```

Открыть http://localhost:5173 и ввести `idInstance` / `apiTokenInstance` из кабинета GREEN-API.

Сборка и предпросмотр продакшн-версии:

```bash
npm run build     # tsc --noEmit && vite build
npm run preview   # http://localhost:4173
```

Переменные окружения (необязательно) — скопируйте `.env.example` в `.env`:

| Переменная | По умолчанию | Назначение |
| --- | --- | --- |
| `VITE_GREEN_API_URL` | `https://api.green-api.com/v3` | Адрес GREEN-API для прокси |

## Используемые методы API

| Задача | Метод |
| --- | --- |
| Проверка учётных данных при входе | `GET getSettings` |
| Статус инстанса | `GET getStateInstance` |
| Номер телефона → `chatId` | `POST checkAccount` |
| Отправка сообщения | `POST sendMessage` |
| Получение уведомлений | `GET receiveNotification?receiveTimeout=25` |
| Подтверждение обработки уведомления | `DELETE deleteNotification/{receiptId}` |

## Почему запросы идут через `/api`

GREEN-API не отдаёт CORS-заголовки: на preflight-запрос отвечает `403`, поэтому напрямую из браузера API недоступен. Весь клиент ходит на относительный путь `/api/...`, а уже он проксируется на `https://api.green-api.com/v3`:

- локально — dev/preview-прокси в `vite.config.ts`;
- на Vercel — rewrite `/api/*` → `https://api.green-api.com/v3/*` в `vercel.json`.

Такой же приём использует [HTML5 SDK GREEN-API](https://green-api.com/v3/docs/sdk/html5/).

## Как устроен приём сообщений

`useNotificationPolling` выполняет long polling: запрос `receiveNotification` висит на стороне GREEN-API до 25 секунд, как только уведомление приходит — оно обрабатывается, подтверждается `deleteNotification`, и цикл повторяется. Поэтому ответ собеседника появляется в чате сразу, без перезагрузки страницы.

Обрабатывается только `incomingMessageReceived` с `textMessageData.textMessage` — по условию задачи нужны лишь текстовые сообщения. Остальные типы уведомлений подтверждаются, но не выводятся.

## Структура проекта

```
src/
├── api/greenApi.ts              клиент GREEN-API (fetch + разбор ошибок)
├── components/
│   ├── ChatView.tsx             переписка, заголовок чата, разделители дат
│   ├── Composer.tsx             поле ввода, Enter — отправить, Shift+Enter — перенос
│   ├── LoginScreen.tsx          форма входа
│   ├── MessageBubble.tsx        сообщение со временем и статусом
│   └── Sidebar.tsx              список чатов, создание чата, статус инстанса
├── hooks/
│   ├── useInstanceState.ts      опрос состояния инстанса
│   └── useNotificationPolling.ts long polling receiveNotification
├── utils/
│   ├── format.ts                даты, телефоны, нормализация номера
│   └── storage.ts               безопасная обёртка над localStorage
├── App.tsx                      состояние чатов и сообщений
└── styles.css                   оформление
```

## Ограничения

- только текстовые сообщения — по условию задачи;
- лимиты тарифа Developer: 3 чата, 100 проверок номера в сутки (счётчик `checkAccount`);
- максимум 20 000 символов в одном сообщении;
- чаты и сообщения хранятся в браузере и не синхронизируются между устройствами;
- `apiTokenInstance` передаётся в пути запроса — как требует сам GREEN-API.