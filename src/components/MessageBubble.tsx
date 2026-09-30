import type { Message } from '../types';
import { formatTime } from '../utils/format';

interface MessageBubbleProps {
  message: Message;
}

const STATUS_LABELS: Record<Message['status'], string> = {
  sending: 'Отправляется…',
  sent: 'Отправлено',
  failed: 'Не отправлено',
};

export default function MessageBubble({ message }: MessageBubbleProps) {
  const isOutgoing = message.direction === 'out';

  return (
    <div className={`bubble-row${isOutgoing ? ' bubble-row--out' : ''}`}>
      <div className={`bubble${isOutgoing ? ' bubble--out' : ''}`}>
        <p className="bubble__text">{message.text}</p>
        <span className="bubble__meta">
          {formatTime(message.timestamp)}
          {isOutgoing ? (
            <span className={`bubble__status bubble__status--${message.status}`}>
              {message.status === 'sending' ? '•' : message.status === 'failed' ? '!' : '✓'}
            </span>
          ) : null}
          <span className="visually-hidden">{STATUS_LABELS[message.status]}</span>
        </span>
      </div>
    </div>
  );
}