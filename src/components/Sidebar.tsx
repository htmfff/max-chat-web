import { useState } from 'react';
import { GreenApiError } from '../api/greenApi';
import type { Chat, Credentials } from '../types';
import { formatChatTimestamp } from '../utils/format';

interface SidebarProps {
  chats: Chat[];
  activeChatId: string | null;
  instanceState: string | null;
  credentials: Credentials;
  onSelectChat: (chatId: string) => void;
  onCreateChat: (phone: string) => Promise<Chat>;
  onLogout: () => void;
}

const STATE_LABELS: Record<string, string> = {
  authorized: 'Авторизован',
  notAuthorized: 'Не авторизован',
  starting: 'Запускается',
  stopped: 'Остановлен',
  unknown: 'Нет связи',
};

export default function Sidebar({
  chats,
  activeChatId,
  instanceState,
  credentials,
  onSelectChat,
  onCreateChat,
  onLogout,
}: SidebarProps) {
  const [phone, setPhone] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const createChat = async (event: React.FormEvent) => {
    event.preventDefault();

    const value = phone.trim();
    if (!value) {
      return;
    }

    setPending(true);
    setError(null);

    try {
      await onCreateChat(value);
      setPhone('');
    } catch (createError) {
      setError(
        createError instanceof GreenApiError
          ? createError.message
          : 'Не удалось создать чат. Проверьте номер.',
      );
    } finally {
      setPending(false);
    }
  };

  return (
    <aside className="sidebar">
      <header className="sidebar__header">
        <div className="brand">
          <span className="brand__mark">MAX</span>
          <span className="brand__name">Чат</span>
        </div>
        <button className="button button--ghost" type="button" onClick={onLogout}>
          Выйти
        </button>
      </header>

      <div className="sidebar__status">
        <span className={`status status--${instanceState ?? 'unknown'}`} />
        {instanceState
          ? STATE_LABELS[instanceState] ?? instanceState
          : 'Определяем состояние…'}
        <span className="sidebar__instance">id {credentials.idInstance}</span>
      </div>

      <form className="new-chat" onSubmit={createChat}>
        <input
          className="new-chat__input"
          value={phone}
          onChange={(event) => setPhone(event.target.value)}
          placeholder="Номер телефона получателя"
          inputMode="tel"
          autoComplete="off"
          spellCheck={false}
        />
        <button className="button button--primary" type="submit" disabled={pending}>
          {pending ? '…' : 'Новый чат'}
        </button>
      </form>

      {error ? <p className="sidebar__error">{error}</p> : null}

      <nav className="chat-list">
        {chats.length === 0 ? (
          <p className="chat-list__empty">
            Здесь появятся чаты. Введите номер выше, чтобы начать переписку.
          </p>
        ) : (
          chats.map((chat) => (
            <button
              key={chat.chatId}
              type="button"
              className={`chat-item${chat.chatId === activeChatId ? ' chat-item--active' : ''}`}
              onClick={() => onSelectChat(chat.chatId)}
            >
              <span className="avatar" aria-hidden="true">
                {chat.title.slice(0, 2).toUpperCase()}
              </span>
              <span className="chat-item__body">
                <span className="chat-item__title">
                  {chat.title}
                  <span className="chat-item__time">{formatChatTimestamp(chat.updatedAt)}</span>
                </span>
                <span className="chat-item__subtitle">{chat.preview ?? chat.phone}</span>
              </span>
              {chat.unread > 0 ? (
                <span className="badge">{chat.unread}</span>
              ) : null}
            </button>
          ))
        )}
      </nav>
    </aside>
  );
}