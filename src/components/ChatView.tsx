import { useEffect, useMemo, useRef, useState } from 'react';
import type { Chat, Message } from '../types';
import { formatDateLabel, formatPhone } from '../utils/format';
import Composer from './Composer';
import MessageBubble from './MessageBubble';

interface ChatViewProps {
  chat: Chat | null;
  messages: Message[];
  error: string | null;
  onDismissError: () => void;
  onSend: (text: string) => void | Promise<void>;
}

export default function ChatView({
  chat,
  messages,
  error,
  onDismissError,
  onSend,
}: ChatViewProps) {
  const scrollRef = useRef<HTMLDivElement>(null);
  const [pinnedToBottom, setPinnedToBottom] = useState(true);

  // Группируем сообщения по календарным дням для разделителей.
  const items = useMemo(() => {
    const result: Array<{ key: string; type: 'date'; label: string } | {
      key: string;
      type: 'message';
      message: Message;
    }> = [];

    let currentDay = '';
    for (const message of messages) {
      const day = new Date(message.timestamp).toDateString();
      if (day !== currentDay) {
        currentDay = day;
        result.push({
          key: `date-${day}`,
          type: 'date',
          label: formatDateLabel(message.timestamp),
        });
      }
      result.push({ key: message.id, type: 'message', message });
    }

    return result;
  }, [messages]);

  useEffect(() => {
    if (pinnedToBottom) {
      const node = scrollRef.current;
      if (node) {
        node.scrollTop = node.scrollHeight;
      }
    }
  }, [items, pinnedToBottom]);

  if (!chat) {
    return (
      <main className="chat chat--empty">
        <div className="chat__placeholder">
          <span className="chat__placeholder-mark">MAX</span>
          <h2>Выберите чат</h2>
          <p>Введите номер телефона получателя в списке слева, чтобы создать новый чат.</p>
        </div>
      </main>
    );
  }

  return (
    <main className="chat">
      <header className="chat__header">
        <span className="avatar" aria-hidden="true">
          {chat.title.slice(0, 2).toUpperCase()}
        </span>
        <div className="chat__about">
          <h1 className="chat__title">{chat.title}</h1>
          <p className="chat__subtitle">{formatPhone(chat.phone)}</p>
        </div>
      </header>

      {error ? (
        <div className="chat__error" role="alert">
          <span>{error}</span>
          <button type="button" className="button button--ghost" onClick={onDismissError}>
            Скрыть
          </button>
        </div>
      ) : null}

      <div
        className="chat__scroll"
        ref={scrollRef}
        onScroll={(event) => {
          const node = event.currentTarget;
          setPinnedToBottom(node.scrollHeight - node.scrollTop - node.clientHeight < 80);
        }}
      >
        {messages.length === 0 ? (
          <p className="chat__empty-hint">
            Сообщений пока нет. Напишите первым — ответ появится здесь автоматически.
          </p>
        ) : (
          items.map((item) =>
            item.type === 'date' ? (
              <div className="chat__date" key={item.key}>
                {item.label}
              </div>
            ) : (
              <MessageBubble key={item.key} message={item.message} />
            ),
          )
        )}
      </div>

      <Composer disabled={false} onSend={onSend} />
    </main>
  );
}