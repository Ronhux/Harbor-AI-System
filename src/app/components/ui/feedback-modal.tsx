import { useDashboardCopy } from '../../i18n/DashboardCopy';
import { type ReactNode } from 'react';
import { AlertTriangle, CheckCircle2, LoaderCircle } from 'lucide-react';
import { Button } from './button';
import { Dialog, DialogContent, DialogTitle, DialogDescription } from './dialog';

type FeedbackModalProps = {
  open: boolean; tone: 'success' | 'error' | 'loading'; title: string; description: string;
  detail?: ReactNode; onClose: () => void; onCloseLabel?: string;
  action?: { label: string; onClick: () => void }; busy?: boolean;
};
/** Shared focus-managed feedback; the dialog primitive preserves portal theme scope. */
export default function FeedbackModal({open,tone,title,description,detail,onClose,onCloseLabel='Close',action,busy=false}:FeedbackModalProps) {
  const copy=useDashboardCopy();
  const loading=tone==='loading', success=tone==='success', locked=loading||busy;
  const Icon=loading?LoaderCircle:success?CheckCircle2:AlertTriangle;
  return <Dialog open={open} onOpenChange={value=>{if(!value&&!locked)onClose()}}>
    <DialogContent closeDisabled={locked} showCloseButton={!loading} className="z-[100] max-w-md rounded-2xl bg-card p-5 text-center text-foreground sm:p-6" onEscapeKeyDown={event=>{if(locked)event.preventDefault()}} onInteractOutside={event=>{if(locked)event.preventDefault()}}>
      <div className={`mx-auto flex h-14 w-14 items-center justify-center rounded-full ${loading?'bg-muted text-primary':success?'bg-emerald-100 text-emerald-600':'bg-red-100 text-red-600'}`}><Icon className={`h-8 w-8 motion-reduce:animate-none ${loading?'animate-spin':''}`} aria-hidden="true"/></div>
      <DialogTitle className="mt-4 text-xl font-black">{title}</DialogTitle>
      <DialogDescription className="mt-2 text-sm leading-6 text-muted-foreground">{description}</DialogDescription>
      {detail&&<div className="mt-3 text-sm text-muted-foreground">{detail}</div>}
      {!loading&&<div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-center"><Button type="button" variant={success?'default':'outline'} className="min-h-11 w-full sm:w-auto" disabled={busy} onClick={onClose}>{copy(onCloseLabel)}</Button>{action&&<Button type="button" className="min-h-11 w-full sm:w-auto" disabled={busy} onClick={action.onClick}>{copy(action.label)}</Button>}</div>}
    </DialogContent>
  </Dialog>;
}
