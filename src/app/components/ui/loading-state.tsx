import { LoaderCircle } from 'lucide-react';

export function LoadingState({ message = 'Loading...', detail = 'Please wait.' }: { message?: string; detail?: string }) {
  return <div role="status" aria-live="polite" className="flex min-h-40 flex-col items-center justify-center gap-2 rounded-2xl border border-border bg-card/80 px-5 py-8 text-center text-muted-foreground">
    <LoaderCircle className="h-8 w-8 animate-spin text-[#0F9488]" aria-hidden="true" />
    <span className="font-bold text-foreground">{message}</span>
    <span className="text-sm">{detail}</span>
  </div>;
}
