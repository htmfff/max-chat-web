import type {
  CheckAccountResponse,
  Credentials,
  InstanceSettings,
  InstanceState,
  NotificationResponse,
} from '../types';
import { normalizePhone } from '../utils/format';

export class GreenApiError extends Error {
  readonly status: number;

  constructor(message: string, status: number) {
    super(message);
    this.name = 'GreenApiError';
    this.status = status;
  }
}

type HttpMethod = 'GET' | 'POST' | 'DELETE';

interface RequestOptions {
  httpMethod?: HttpMethod;
  body?: unknown;
  query?: Record<string, string>;
  signal?: AbortSignal;
}

// Путь любого метода GREEN-API v3:
// {apiUrl}/waInstance{idInstance}/{method}/{apiTokenInstance}
const API_PREFIX = '/api';

function buildUrl(
  credentials: Credentials,
  method: string,
  query?: Record<string, string>,
): string {
  const { idInstance, apiTokenInstance } = credentials;
  const base = `${API_PREFIX}/waInstance${idInstance}/${method}/${apiTokenInstance}`;

  if (!query) {
    return base;
  }

  const search = new URLSearchParams(query).toString();
  return search ? `${base}?${search}` : base;
}

function parseJson(text: string): unknown {
  try {
    return JSON.parse(text);
  } catch {
    return null;
  }
}

const STATUS_HINTS: Record<number, string> = {
  400: 'Некорректный запрос к GREEN-API. Проверьте idInstance и apiTokenInstance.',
  403:
    'GREEN-API отклонил запрос (403). Проверьте учётные данные и доступ к api.green-api.com из вашей сети.',
  429: 'Превышен лимит запросов к GREEN-API. Попробуйте позже.',
  466: 'Превышены ограничения тарифа GREEN-API Developer.',
};

function extractError(payload: unknown, status: number, rawText: string): string {
  if (payload && typeof payload === 'object') {
    const record = payload as Record<string, unknown>;
    const message = record.message ?? record.error ?? record.errorMessage;

    if (typeof message === 'string' && message) {
      return message;
    }
  }

  if (STATUS_HINTS[status]) {
    return STATUS_HINTS[status];
  }

  // Ответы вроде nginx-страницы 403 не несут полезной информации.
  if (rawText && !rawText.trimStart().startsWith('<')) {
    return rawText.slice(0, 300);
  }

  return `GREEN-API вернул HTTP ${status}`;
}

async function request<T>(
  credentials: Credentials,
  method: string,
  options: RequestOptions = {},
): Promise<T> {
  const { httpMethod = 'GET', body, query, signal } = options;

  let response: Response;
  try {
    response = await fetch(buildUrl(credentials, method, query), {
      method: httpMethod,
      signal,
      headers: body === undefined ? undefined : { 'Content-Type': 'application/json' },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  } catch (error) {
    if ((error as Error).name === 'AbortError') {
      throw error;
    }
    throw new GreenApiError(
      'Нет связи с GREEN-API. Проверьте интернет и что vite-прокси запущен (npm run dev).',
      0,
    );
  }

  const rawText = await response.text();
  const payload = rawText ? parseJson(rawText) : null;

  if (!response.ok) {
    throw new GreenApiError(extractError(payload, response.status, rawText), response.status);
  }

  return payload as T;
}

/** Проверка учётных данных + текущее состояние инстанса. */
export function getSettings(credentials: Credentials, signal?: AbortSignal) {
  return request<InstanceSettings>(credentials, 'getSettings', { signal });
}

export function getStateInstance(credentials: Credentials, signal?: AbortSignal) {
  return request<InstanceState>(credentials, 'getStateInstance', { signal });
}

/**
 * Резолвит номер телефона в chatId MAX.
 * Лимит на тарифе Developer — 100 проверок в сутки.
 */
export function checkAccount(credentials: Credentials, phone: string, signal?: AbortSignal) {
  const digits = normalizePhone(phone);

  if (!digits) {
    return Promise.reject(new GreenApiError('Введите номер телефона получателя', 400));
  }

  return request<CheckAccountResponse>(credentials, 'checkAccount', {
    httpMethod: 'POST',
    body: { phoneNumber: Number(digits) },
    signal,
  });
}

/** Отправка текстового сообщения. */
export function sendMessage(
  credentials: Credentials,
  chatId: string,
  text: string,
  signal?: AbortSignal,
) {
  return request<{ idMessage: string }>(credentials, 'sendMessage', {
    httpMethod: 'POST',
    body: { chatId, message: text, linkPreview: false },
    signal,
  });
}

/** Long polling: держит соединение до receiveTimeout секунд. */
export function receiveNotification(
  credentials: Credentials,
  receiveTimeout: number,
  signal?: AbortSignal,
) {
  return request<NotificationResponse>(credentials, 'receiveNotification', {
    query: { receiveTimeout: String(receiveTimeout) },
    signal,
  });
}

export function deleteNotification(
  credentials: Credentials,
  receiptId: number,
  signal?: AbortSignal,
) {
  return request<null>(credentials, `deleteNotification/${receiptId}`, {
    httpMethod: 'DELETE',
    signal,
  });
}