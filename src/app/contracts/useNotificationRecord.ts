import { useEffect } from 'react';
import type { NotificationTarget } from './notificationNavigation';
/** Consume only in the destination workspace, after its authorized list has loaded. */
export function useNotificationRecord(tab: NotificationTarget['tab'], ready: boolean, open: (id: number) => void) {
  useEffect(() => {
    const consume = () => {
      if (!ready) return;
      try {
        const raw: unknown = JSON.parse(sessionStorage.getItem('harbor:notification') ?? 'null');
        if (raw && typeof raw === 'object' && 'tab' in raw && raw.tab === tab && 'recordId' in raw && typeof raw.recordId === 'number' && Number.isSafeInteger(raw.recordId) && raw.recordId > 0) {
          sessionStorage.removeItem('harbor:notification');
          open(raw.recordId);
        }
      } catch { sessionStorage.removeItem('harbor:notification'); }
    };
    consume();
    window.addEventListener('harbor:notification', consume);
    return () => window.removeEventListener('harbor:notification', consume);
  }, [tab, ready, open]);
}
