import { useRef } from 'react';
import { mutationKey } from './client';

export function useIdempotency() {
  const attempt = useRef<{ payload: string; key: string } | null>(null);
  return {
    keyFor(payload: unknown) {
      const serialized = JSON.stringify(payload);
      if (attempt.current?.payload !== serialized) {
        attempt.current = { payload: serialized, key: mutationKey() };
      }
      return attempt.current.key;
    },
    clear() {
      attempt.current = null;
    },
  };
}
