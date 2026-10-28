import type { ReactNode } from 'react';

export type PreviewState = 'ready' | 'loading' | 'empty' | 'error' | 'pending' | 'restricted';
const messages: Record<Exclude<PreviewState, 'ready'>, string> = {
  loading: 'Loading synthetic examples. No database request is running.',
  empty: 'No synthetic records match this view. No real records were searched.',
  error: 'The mocked view could not load. No real transaction was attempted.',
  pending: 'Awaiting the required review. No account access or procurement eligibility has been granted.',
  restricted: 'This example requires explicit workflow authorization. A role or association name alone is insufficient.',
};

/** Presentation only: never grants access, submits data, or retries an API request. */
export default function PreviewStatePanel({ state, children }: { state: PreviewState; children: ReactNode }) {
  return <section className="min-w-0 space-y-4" aria-label="Synthetic workflow content" aria-busy={state === 'loading'}>
    <div className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950">
      <strong>Synthetic preview — server actions disabled.</strong> Local edits disappear when this preview closes. Nothing is saved, uploaded, approved, purchased, or paid.
    </div>
    {state === 'ready' ? children : <p role={state === 'error' || state === 'restricted' ? 'alert' : 'status'} className="rounded-lg border bg-card p-4">{messages[state]}</p>}
  </section>;
}
