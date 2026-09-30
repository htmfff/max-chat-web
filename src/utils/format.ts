const WEEKDAYS = ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'];

/** 14:32 */
export function formatTime(timestamp: number): string {
  const date = new Date(timestamp);
  return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}

function isSameDay(a: Date, b: Date): boolean {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  );
}

/** Сегодня / Вчера / 12 марта / 12.03.2024 — для разделителя дат. */
export function formatDateLabel(timestamp: number): string {
  const date = new Date(timestamp);
  const now = new Date();

  if (isSameDay(date, now)) {
    return 'Сегодня';
  }

  const yesterday = new Date(now);
  yesterday.setDate(now.getDate() - 1);
  if (isSameDay(date, yesterday)) {
    return 'Вчера';
  }

  if (date.getFullYear() === now.getFullYear()) {
    return date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long' });
  }

  return date.toLocaleDateString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}

/** Короткая дата для списка чатов: 14:32 / вчера / 12.03 */
export function formatChatTimestamp(timestamp: number): string {
  const date = new Date(timestamp);
  const now = new Date();

  if (isSameDay(date, now)) {
    return formatTime(timestamp);
  }

  if (now.getTime() - date.getTime() < 7 * 24 * 60 * 60 * 1000) {
    return WEEKDAYS[date.getDay()];
  }

  return date.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit' });
}

export function formatPhone(phone: string): string {
  const digits = phone.replace(/\D/g, '');

  if (digits.length === 11 && digits.startsWith('7')) {
    return `+7 ${digits.slice(1, 4)} ${digits.slice(4, 7)}-${digits.slice(7, 9)}-${digits.slice(9)}`;
  }

  return phone.trim();
}

/**
 * Номер для checkAccount: только цифры, с кодом страны.
 * 8XXXXXXXXXX → 7XXXXXXXXXX (РФ), 9XXXXXXXXXX → 7XXXXXXXXXX.
 */
export function normalizePhone(phone: string): string {
  let digits = phone.replace(/\D/g, '');

  if (digits.length === 11 && digits.startsWith('8')) {
    digits = `7${digits.slice(1)}`;
  } else if (digits.length === 10 && digits.startsWith('9')) {
    digits = `7${digits}`;
  }

  return digits;
}

/** Две буквы для аватар-заглушки. */
export function initials(title: string): string {
  const cleaned = title.replace(/\D/g, '');
  if (cleaned) {
    return cleaned.slice(-2);
  }
  return title.trim().slice(0, 2).toUpperCase() || '?';
}

export function isSameMinute(a: number, b: number): boolean {
  return Math.abs(a - b) < 60 * 1000;
}