import { useId, useState } from 'react';
import { FcaApplicantPreview, FcaAdminReviewPreview } from './FcaWorkflowPreviews';
import { KadiwaProducerPreview, KadiwaCoordinatorPreview } from './KadiwaWorkflowPreviews';
import { InstitutionalBuyerPreview, InstitutionalSupplierPreview } from './InstitutionalDemandPreviews';
import DemandDraftPreview from './DemandDraftPreview';
import DemandOfferDetailPreview from './DemandOfferDetailPreview';
import PublicationReadPreview from './PublicationReadPreview';
import PreviewStatePanel, { type PreviewState } from './PreviewStatePanel';
import { previewModules, selectPreviewModule, type PreviewActor, type PreviewModule } from './previewModel';
import { Button } from '../ui/button';

export const MAO_WORKSPACE_PREVIEW_ENABLED = false;
const labels: Record<PreviewModule, string> = { fca: 'FCA representation', kadiwa: 'KADIWA events', demands: 'Institutional demand' };

/** Unrouted synthetic workbench. No API adapter, authenticated role mutation or persistence. */
export default function MaoPreviewWorkspace({ initialActor = 'producer', initialState = 'ready', initialModule }: { initialActor?: PreviewActor; initialState?: PreviewState; initialModule?: PreviewModule }) {
  const id = useId();
  const [actor, setActor] = useState<PreviewActor>(initialActor);
  const [module, setModule] = useState<PreviewModule>(selectPreviewModule(initialActor, initialModule ?? previewModules[initialActor][0]));
  const [state, setState] = useState<PreviewState>(initialState);
  const changeActor = (next: PreviewActor) => { setActor(next); setModule(current => selectPreviewModule(next, current)); };
  return <main className="mx-auto max-w-5xl space-y-5 p-4 text-slate-900 sm:p-6" aria-labelledby={`${id}-title`}>
    <header><h1 id={`${id}-title`} className="text-2xl font-bold">MAO synthetic workspace</h1><p>Explore prepared interfaces. Switching examples does not change your account or permissions.</p></header>
    <div className="grid gap-4 sm:grid-cols-2">
      <label htmlFor={`${id}-actor`}>Synthetic actor<select id={`${id}-actor`} className="mt-1 block min-h-11 w-full rounded border bg-card p-2" value={actor} onChange={e => changeActor(e.target.value as PreviewActor)}>
        <option value="producer">Farmer / fisherfolk example</option><option value="buyer">Institutional buyer example</option><option value="reviewer">Explicitly assigned reviewer example</option>
      </select></label>
      <label htmlFor={`${id}-state`}>Mock view state<select id={`${id}-state`} className="mt-1 block min-h-11 w-full rounded border bg-card p-2" value={state} onChange={e => setState(e.target.value as PreviewState)}>
        {(['ready', 'loading', 'empty', 'error', 'pending', 'restricted'] as const).map(value => <option key={value}>{value}</option>)}
      </select></label>
    </div>
    <nav aria-label="Synthetic MAO workspaces" className="flex flex-wrap gap-2">
      {previewModules[actor].map(value => <Button key={value} type="button" data-fixture-action="navigate" aria-pressed={module === value} variant={module === value ? 'default' : 'outline'} className="min-h-11" onClick={() => setModule(value)}>{labels[value]}</Button>)}
    </nav>
    <PreviewStatePanel state={state}>
      {module === 'fca' && (actor === 'reviewer' ? <FcaAdminReviewPreview /> : <FcaApplicantPreview />)}
      {module === 'kadiwa' && (actor === 'reviewer' ? <KadiwaCoordinatorPreview /> : <KadiwaProducerPreview />)}
      {module === 'demands' && actor === 'buyer' && <div className="space-y-5"><DemandDraftPreview /><PublicationReadPreview /><InstitutionalBuyerPreview /><DemandOfferDetailPreview initialViewer="buyer_owner" /></div>}
      {module === 'demands' && actor === 'producer' && <div className="space-y-5"><InstitutionalSupplierPreview /><DemandOfferDetailPreview initialViewer="producer_owner" /></div>}
      {module === 'demands' && actor === 'reviewer' && <section className="space-y-3 rounded-lg border bg-card p-4" aria-label="Synthetic institutional review queue">
        <h2 className="font-semibold">Institutional review contract — blocked</h2><p>No reviewer queue or private draft is loaded. Existing source permits a configured reviewer transition, but reviewer-read scope and official assignments are not established.</p>
        <p>Check the posting purpose, commodity specifications, deadline, eligibility and any official procurement reference. Provisional allocations are not deliveries, purchases or awards.</p>
        <Button disabled>Approve posting</Button><Button disabled variant="outline">Reject posting</Button><Button disabled variant="outline">Request information</Button>
        <PublicationReadPreview reviewerContext />
        <DemandOfferDetailPreview initialViewer="administrator" />
      </section>}
    </PreviewStatePanel>
  </main>;
}
