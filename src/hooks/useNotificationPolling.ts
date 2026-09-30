import { useEffect, useRef } from 'react';
import { deleteNotification, receiveNotification } from '../api/greenApi';
import type { Credentials, WebhookBody } from '../types';

const RECEIVE_TIMEOUT = 25;
const RETRY_DELAY = 4000;
const IDLE_DELAY = 500;

function delay(ms: number, signal: AbortSignal): Promise<void> {
  return new Promise((resolve) => {
    const timeout = window.setTimeout(resolve, ms);
    signal.addEventListener(
      'abort',
      () => {
        window.clearTimeout(timeout);
        resolve();
      },
      { once: true },
    );
  });
}

/**
 * Приём сообщений по HTTP API GREEN-API (receiveNotification/deleteNotification).
 * Держим long polling: пока уведомлений нет — соединение висит на стороне API.
 */
export function useNotificationPolling(
  credentials: Credentials | null,
  onNotification: (body: WebhookBody) => void,
  onError?: (error: Error) => void,
): void {
  const notifyRef = useRef(onNotification);
  const errorRef = useRef(onError);

  useEffect(() => {
    notifyRef.current = onNotification;
    errorRef.current = onError;
  });

  useEffect(() => {
    if (!credentials) {
      return;
    }

    const controller = new AbortController();
    let stopped = false;

    const loop = async () => {
      while (!stopped) {
        try {
          const response = await receiveNotification(
            credentials,
            RECEIVE_TIMEOUT,
            controller.signal,
          );

          if (response?.receiptId) {
            await deleteNotification(credentials, response.receiptId, controller.signal).catch(
              () => undefined,
            );
          }

          if (response?.body) {
            notifyRef.current(response.body);
          } else {
            await delay(IDLE_DELAY, controller.signal);
          }
        } catch (error) {
          if (stopped || (error as Error).name === 'AbortError') {
            return;
          }

          errorRef.current?.(error as Error);
          await delay(RETRY_DELAY, controller.signal);
        }
      }
    };

    void loop();

    return () => {
      stopped = true;
      controller.abort();
    };
  }, [credentials]);
}