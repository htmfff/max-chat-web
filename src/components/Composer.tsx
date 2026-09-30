import { useEffect, useRef, useState } from 'react';

interface ComposerProps {
  disabled: boolean;
  onSend: (text: string) => void | Promise<void>;
}

const MAX_LENGTH = 20000;

export default function Composer({ disabled, onSend }: ComposerProps) {
  const [value, setValue] = useState('');
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  useEffect(() => {
    const textarea = textareaRef.current;
    if (!textarea) {
      return;
    }
    textarea.style.height = 'auto';
    textarea.style.height = `${Math.min(textarea.scrollHeight, 160)}px`;
  }, [value]);

  const submit = async () => {
    const text = value.trim();
    if (!text || disabled) {
      return;
    }

    setValue('');
    await onSend(text);
  };

  return (
    <div className="composer">
      <textarea
        ref={textareaRef}
        className="composer__input"
        value={value}
        rows={1}
        maxLength={MAX_LENGTH}
        placeholder={disabled ? 'Сначала создайте чат' : 'Напишите сообщение…'}
        disabled={disabled}
        onChange={(event) => setValue(event.target.value)}
        onKeyDown={(event) => {
          if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            void submit();
          }
        }}
      />
      <button
        className="button button--primary composer__send"
        type="button"
        onClick={() => void submit()}
        disabled={disabled || !value.trim()}
        aria-label="Отправить"
      >
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
          <path
            fill="currentColor"
            d="M3.4 20.4 21.9 12 3.4 3.6 3.39 9.9 15.9 12 3.39 14.1z"
          />
        </svg>
      </button>
    </div>
  );
}