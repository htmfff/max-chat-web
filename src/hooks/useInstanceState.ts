import { useEffect, useState } from 'react';
import { getStateInstance } from '../api/greenApi';
import type { Credentials } from '../types';

const POLL_INTERVAL = 15000;

/** Периодически опрашивает состояние инстанса (authorized / notAuthorized / ...). */
export function useInstanceState(
  credentials: Credentials | null,
  interval: number = POLL_INTERVAL,
): string | null {
  const [state, setState] = useState<string | null>(null);

  useEffect(() => {
    if (!credentials) {
      setState(null);
      return;
    }

    const controller = new AbortController();
    let stopped = false;

    const load = async () => {
      try {
        const response = await getStateInstance(credentials, controller.signal);
        if (!stopped) {
          setState(response.stateInstance ?? 'unknown');
        }
      } catch {
        if (!stopped && !controller.signal.aborted) {
          setState('unknown');
        }
      }
    };

    void load();
    const timer = window.setInterval(load, interval);

    return () => {
      stopped = true;
      controller.abort();
      window.clearInterval(timer);
    };
  }, [credentials, interval]);

  return state;
}