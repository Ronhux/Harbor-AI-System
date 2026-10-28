import { logoutSession } from '../../lib/authStorage';
import { useLanguage } from '../i18n/useLanguage';
import { useEffect, useState, type ReactNode } from 'react';
import { request } from '../../lib/api';
import { Button } from './ui/button';

/** Confirm access with the server before mounting any producer dashboard features. */
export function ProducerApprovalGate({ children }: { children: ReactNode }) {
  const { lang } = useLanguage();
  const tr = (en: string, fil: string) => lang === 'fil' ? fil : en;
  const [leaving, setLeaving] = useState(false);
  const [approved, setApproved] = useState(false);
  const [error, setError] = useState('');
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    let cancelled = false;
    setError('');
    request('/api/producer/dashboard')
      .then(() => { if (!cancelled) setApproved(true); })
      .catch((err: unknown) => {
        if (!cancelled) setError(err instanceof Error ? err.message : 'Could not confirm producer access.');
      });
    return () => { cancelled = true; };
  }, [attempt]);

  if (approved) return children;

  return <main className="mx-auto max-w-xl space-y-4 p-8 text-foreground">
    <h1 className="text-2xl font-bold">{tr('Producer access', 'Pag-access ng Producer')}</h1>
    <p role={error ? 'alert' : 'status'}>{error || tr('Checking administrator approval…', 'Sinusuri ang pag-apruba ng administrator…')}</p>
    {error && <div className="flex gap-3">
      <Button onClick={() => setAttempt((value) => value + 1)}>{tr('Check again', 'Suriin muli')}</Button>
      <Button variant="outline" disabled={leaving} onClick={async () => {
        setLeaving(true);
        try { await logoutSession(); window.location.assign('/login'); }
        catch { setError(tr('Sign-out failed. Please try again.', 'Nabigo ang pag-sign out. Subukan muli.')); }
        finally { setLeaving(false); }
      }}>{tr('Return to login', 'Bumalik sa pag-sign in')}</Button>
    </div>}
  </main>;
}
