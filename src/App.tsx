import { useCallback, useEffect, useMemo, useState } from 'react';
import { checkAccount, getSettings, sendMessage } from './api/greenApi';
import ChatView from './components/ChatView';
import LoginScreen from './components/LoginScreen';
import Sidebar from './components/Sidebar';
import { useInstanceState } from './hooks/useInstanceState';
import { useNotificationPolling } from './hooks/useNotificationPolling';
import type { Chat, Credentials, Message, WebhookBody } from './types';
import { formatPhone, normalizePhone } from './utils/format';
import { readStorage, removeStorage, writeStorage } from './utils/storage';

const CREDENTIALS_KEY = 'max-chat.credentials';
const CHATS_KEY = 'max-chat.chats';
const MESSAGES_KEY = 'max-chat.messages';

const MAX_MESSAGE_LENGTH = 20000;

type MessagesByChat = Record<string, Message[]>;

function appendMessage(list: Message[], message: Message): Message[] {
  return [...list, message].sort((a, b) => a.timestamp - b.timestamp);
}

/** Добавляет сообщение, если такого id ещё нет (повторные уведомления игнорируем). */
function upsertMessage(list: Message[], message: Message): Message[] {
  return list.some((item) => item.id === message.id) ? list : appendMessage(list, message);
}

function patchMessage(list: Message[], id: string, patch: Partial<Message>): Message[] {
  return list.map((item) => (item.id === id ? { ...item, ...patch } : item));
}

export default function App() {
  const [credentials, setCredentials] = useState<Credentials | null>(() =>
    readStorage<Credentials | null>(CREDENTIALS_KEY, null),
  );
  const [chats, setChats] = useState<Chat[]>(() => readStorage<Chat[]>(CHATS_KEY, []));
  const [messages, setMessages] = useState<MessagesByChat>(() =>
    readStorage<MessagesByChat>(MESSAGES_KEY, {}),
  );
  const [activeChatId, setActiveChatId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => writeStorage(CHATS_KEY, chats), [chats]);
  useEffect(() => writeStorage(MESSAGES_KEY, messages), [messages]);

  // После перезагрузки страницы сразу открываем последний активный чат.
  useEffect(() => {
    if (activeChatId || chats.length === 0) {
      return;
    }
    const newest = [...chats].sort((a, b) => b.updatedAt - a.updatedAt)[0];
    if (newest) {
      setActiveChatId(newest.chatId);
    }
  }, [activeChatId, chats]);

  const instanceState = useInstanceState(credentials);

  const handleLogin = useCallback(async (next: Credentials) => {
    await getSettings(next);
    writeStorage(CREDENTIALS_KEY, next);
    setCredentials(next);
    setError(null);
  }, []);

  const handleLogout = useCallback(() => {
    removeStorage(CREDENTIALS_KEY);
    setCredentials(null);
    setActiveChatId(null);
    setError(null);
  }, []);

  const handleSelectChat = useCallback((chatId: string) => {
    setActiveChatId(chatId);
    setChats((prev) => prev.map((chat) => (chat.chatId === chatId ? { ...chat, unread: 0 } : chat)));
  }, []);

  const handleCreateChat = useCallback(
    async (phone: string) => {
      if (!credentials) {
        throw new Error('Нет подключения к GREEN-API');
      }

      const result = await checkAccount(credentials, phone);

      if (!result?.chatId) {
        throw new Error(
          'GREEN-API не вернул chatId: номер не найден в MAX или инстанс не авторизован.',
        );
      }

      const chat: Chat = {
        chatId: result.chatId,
        phone: normalizePhone(phone) || phone,
        title: result.name?.trim() || result.contactName?.trim() || formatPhone(phone),
        updatedAt: Date.now(),
        unread: 0,
      };

      setChats((prev) => [chat, ...prev.filter((item) => item.chatId !== chat.chatId)]);
      setActiveChatId(chat.chatId);

      return chat;
    },
    [credentials],
  );

  const handleSend = useCallback(
    async (rawText: string) => {
      const text = rawText.trim().slice(0, MAX_MESSAGE_LENGTH);

      if (!text || !credentials || !activeChatId) {
        return;
      }

      const localId = `local-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
      const optimistic: Message = {
        id: localId,
        chatId: activeChatId,
        text,
        direction: 'out',
        timestamp: Date.now(),
        status: 'sending',
      };

      setMessages((prev) => ({
        ...prev,
        [activeChatId]: appendMessage(prev[activeChatId] ?? [], optimistic),
      }));
      setChats((prev) =>
        prev.map((chat) =>
          chat.chatId === activeChatId
            ? { ...chat, preview: text, updatedAt: optimistic.timestamp }
            : chat,
        ),
      );

      try {
        const { idMessage } = await sendMessage(credentials, activeChatId, text);

        setMessages((prev) => {
          const list = (prev[activeChatId] ?? []).filter(
            (message) => message.id !== localId && message.id !== idMessage,
          );

          return {
            ...prev,
            [activeChatId]: appendMessage(list, { ...optimistic, id: idMessage, status: 'sent' }),
          };
        });
      } catch (sendError) {
        setMessages((prev) => ({
          ...prev,
          [activeChatId]: patchMessage(prev[activeChatId] ?? [], localId, { status: 'failed' }),
        }));
        setError((sendError as Error).message);
      }
    },
    [activeChatId, credentials],
  );

  const handleNotification = useCallback(
    (body: WebhookBody) => {
      const chatId = body.senderData?.chatId;
      if (!chatId) {
        return;
      }

      const sender = body.senderData;
      const text = body.messageData?.textMessageData?.textMessage
        ?? body.messageData?.textMessageData?.text;

      if (body.typeWebhook === 'incomingMessageReceived') {
        if (!text) {
          return;
        }

        const message: Message = {
          id: body.idMessage ?? `in-${chatId}-${Date.now()}`,
          chatId,
          text,
          direction: 'in',
          timestamp: body.timestamp ? body.timestamp * 1000 : Date.now(),
          status: 'sent',
        };

        setMessages((prev) => ({
          ...prev,
          [chatId]: upsertMessage(prev[chatId] ?? [], message),
        }));

        const isActive = chatId === activeChatId;
        const receivedAt = message.timestamp;
        const title = sender?.senderName?.trim() || sender?.chatName?.trim() || chatId;
        const phone = sender?.senderPhoneNumber ? String(sender.senderPhoneNumber) : chatId;

        setChats((prev) => {
          const existing = prev.find((chat) => chat.chatId === chatId);

          if (!existing) {
            return [
              {
                chatId,
                phone,
                title,
                preview: text,
                updatedAt: receivedAt,
                unread: 1,
              },
              ...prev,
            ];
          }

          return prev.map((chat) =>
            chat.chatId === chatId
              ? {
                  ...chat,
                  preview: text,
                  updatedAt: receivedAt,
                  unread: isActive ? 0 : chat.unread + 1,
                }
              : chat,
          );
        });

        return;
      }

      // Исходящие подтверждают доставку сообщения, которое мы уже показали.
      if (
        body.typeWebhook === 'outgoingApiMessage' ||
        body.typeWebhook === 'outgoingMessageReceived' ||
        body.typeWebhook === 'outgoingMessageStatus'
      ) {
        const idMessage = body.idMessage ?? body.messageData?.idMessage;
        if (!idMessage) {
          return;
        }

        setMessages((prev) => {
          const list = prev[chatId];

          if (!list?.some((message) => message.id === idMessage)) {
            return prev;
          }

          return {
            ...prev,
            [chatId]: patchMessage(list, idMessage, { status: 'sent' }),
          };
        });
      }
    },
    [activeChatId],
  );

  const handlePollingError = useCallback((pollingError: Error) => {
    // Поллинг повторяет запрос при ошибке — не спамим одинаковым текстом.
    setError((prev) => (prev === pollingError.message ? prev : pollingError.message));
  }, []);

  useNotificationPolling(credentials, handleNotification, handlePollingError);

  const sortedChats = useMemo(
    () => [...chats].sort((a, b) => b.updatedAt - a.updatedAt),
    [chats],
  );

  const activeChat = useMemo(
    () => sortedChats.find((chat) => chat.chatId === activeChatId) ?? null,
    [activeChatId, sortedChats],
  );

  const activeMessages = useMemo(
    () => (activeChatId ? messages[activeChatId] ?? [] : []),
    [activeChatId, messages],
  );

  if (!credentials) {
    return <LoginScreen onLogin={handleLogin} />;
  }

  return (
    <div className="app">
      <Sidebar
        chats={sortedChats}
        activeChatId={activeChatId}
        instanceState={instanceState}
        credentials={credentials}
        onSelectChat={handleSelectChat}
        onCreateChat={handleCreateChat}
        onLogout={handleLogout}
      />
      <ChatView
        chat={activeChat}
        messages={activeMessages}
        error={error}
        onDismissError={() => setError(null)}
        onSend={handleSend}
      />
    </div>
  );
}