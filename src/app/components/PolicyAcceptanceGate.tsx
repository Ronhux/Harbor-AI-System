import { useCallback, useEffect, useState, type ReactNode } from 'react';
import { AlertTriangle, ExternalLink, Loader2, ShieldCheck } from 'lucide-react';
import { Button } from './ui/button';
import { Checkbox } from './ui/checkbox';
import { Label } from './ui/label';

type Policy = {
  type: string;
  title: string;
  version: string;
  path: string;
};

type AcceptanceState = {
  accepted: boolean;
  pending: Policy[];
  policies: Record<string, Policy>;
};

type Props = {
  children: ReactNode;
  /** Human-readable label for the signed-in area, e.g. "producer dashboard". */
  areaLabel?: string;
};

const apiHeaders = (): Record<string, string> => {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const token = typeof window !== 'undefined' ? localStorage.getItem('authToken') : null;

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-CSRF-TOKEN': csrfToken,
  };

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  return headers;
};

/**
 * Client-side mirror of the server-side `EnsurePoliciesAccepted` gate.
 *
 * The API is the authority: every protected route is gated server-side, so a
 * pending account receives `403 policy_acceptance_required` regardless of this
 * component. This gate exists so the user is told what to do instead of being
 * met with a wall of failed requests.
 */
export function PolicyAcceptanceGate({ children, areaLabel = 'dashboard' }: Props) {
  const [state, setState] = useState<AcceptanceState | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');
  const [termsChecked, setTermsChecked] = useState(false);
  const [privacyChecked, setPrivacyChecked] = useState(false);
  const [submitError, setSubmitError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setLoadError('');
    try {
      const response = await fetch('/api/policies/acceptance/status', {
        headers: apiHeaders(),
      });
      const result = await response.json().catch(() => ({}));

      if (!response.ok) {
        setLoadError(
          result?.message ||
            'We could not confirm your policy acceptance status. Please reload the page.',
        );
        return;
      }

      setState({
        accepted: Boolean(result?.accepted),
        pending: Array.isArray(result?.pending) ? result.pending : [],
        policies: result?.policies ?? {},
      });
    } catch {
      setLoadError('We could not reach HarborAI to confirm your policy acceptance status.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setSubmitError('');

    if (!termsChecked || !privacyChecked) {
      setSubmitError('Please read and accept both the Terms and Conditions and the Privacy Policy.');
      return;
    }

    setSubmitting(true);
    try {
      const response = await fetch('/api/policies/acceptance', {
        method: 'POST',
        headers: apiHeaders(),
        body: JSON.stringify({
          terms_accepted: true,
          privacy_accepted: true,
          privacy_acknowledged: true,
          terms_version: state?.policies?.terms?.version,
          privacy_policy_version: state?.policies?.privacy_policy?.version,
        }),
      });
      const result = await response.json().catch(() => ({}));

      if (!response.ok) {
        setSubmitError(
          result?.message ||
            'Your acceptance could not be recorded. Please review the policies and try again.',
        );
        return;
      }

      await load();
    } catch {
      setSubmitError('Your acceptance could not be recorded. Please check your connection and try again.');
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <div className="flex min-h-[60vh] flex-col items-center justify-center gap-3 text-muted-foreground">
        <Loader2 className="h-6 w-6 animate-spin" aria-hidden="true" />
        <p className="text-sm">Checking your policy acceptance…</p>
      </div>
    );
  }

  if (loadError) {
    return (
      <div className="mx-auto max-w-2xl px-4 py-16">
        <div className="rounded-2xl border border-[#F59E0B]/40 bg-[#FFFBEB] p-8 text-center shadow-sm">
          <AlertTriangle className="mx-auto h-10 w-10 text-[#B45309]" aria-hidden="true" />
          <h1 className="mt-3 font-display text-2xl text-foreground">Acceptance status unavailable</h1>
          <p className="mt-2 text-sm text-muted-foreground">{loadError}</p>
          <Button
            type="button"
            onClick={() => void load()}
            className="mt-6 rounded-full bg-[#0F9488] font-bold text-white hover:bg-[#0B6661]"
          >
            Try again
          </Button>
        </div>
      </div>
    );
  }

  if (!state || state.accepted) {
    return <>{children}</>;
  }

  const policies = state.policies ?? {};
  const terms = policies.terms;
  const privacy = policies.privacy_policy;

  return (
    <div className="min-h-screen bg-background px-4 py-12 font-body">
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Anton&family=Baloo+2:wght@400;500;600;700;800&display=swap');
        .font-display { font-family: 'Anton', ui-sans-serif, sans-serif; }
        .font-body { font-family: 'Baloo 2', ui-rounded, system-ui, sans-serif; }
      `}</style>

      <div className="mx-auto max-w-2xl">
        <div className="mb-6 flex items-center gap-3">
          <img src="/logo.png" alt="HarborAI Logo" className="h-12 w-12 object-contain" />
          <span className="font-display text-2xl tracking-tight text-foreground">HarborAI</span>
        </div>

        <div className="overflow-hidden rounded-2xl border-2 border-border bg-card shadow-xl">
          <div className="h-2 bg-gradient-to-r from-[#22C55E] via-[#0F9488] to-[#22D3EE]" />
          <div className="p-6 sm:p-8">
            <ShieldCheck className="h-9 w-9 text-[#0F9488]" aria-hidden="true" />
            <h1 className="mt-3 font-display text-2xl tracking-tight text-foreground">
              Review and accept the HarborAI policies
            </h1>
            <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
              Before you can use the {areaLabel}, please read and accept the HarborAI Terms and
              Conditions and acknowledge the HarborAI Privacy Policy. Your acceptance is recorded
              with the current policy version and the date and time you accepted it.
            </p>

            {submitError && (
              <div className="mt-4 rounded-lg border border-red-200 bg-red-50 p-3">
                <p className="text-sm text-red-800">{submitError}</p>
              </div>
            )}

            <form onSubmit={submit} className="mt-6 space-y-4">
              <PolicyCheckbox
                id="policy-terms"
                checked={termsChecked}
                onCheckedChange={setTermsChecked}
                error={submitError !== '' && !termsChecked}
              >
                I agree to the{' '}
                <PolicyLink href={terms?.path ?? '/terms-and-conditions'} label={terms?.title ?? 'HarborAI Terms and Conditions'} />
                {terms?.version ? (
                  <span className="ml-1 text-xs text-muted-foreground">(version {terms.version})</span>
                ) : null}
                .
              </PolicyCheckbox>

              <PolicyCheckbox
                id="policy-privacy"
                checked={privacyChecked}
                onCheckedChange={setPrivacyChecked}
                error={submitError !== '' && !privacyChecked}
              >
                I acknowledge that I have read the{' '}
                <PolicyLink href={privacy?.path ?? '/privacy'} label={privacy?.title ?? 'HarborAI Privacy Policy'} />
                {privacy?.version ? (
                  <span className="ml-1 text-xs text-muted-foreground">(version {privacy.version})</span>
                ) : null}
                .
              </PolicyCheckbox>

              <Button
                type="submit"
                disabled={submitting}
                className="w-full rounded-full bg-[#22C55E] font-bold text-white hover:bg-[#15803D]"
              >
                {submitting ? 'Recording acceptance...' : 'Accept and continue'}
              </Button>
            </form>
          </div>
        </div>
      </div>
    </div>
  );
}

type PolicyCheckboxProps = {
  id: string;
  checked: boolean;
  onCheckedChange: (checked: boolean) => void;
  error?: boolean;
  children: ReactNode;
};

/**
 * The checkbox label is a separate control from the checkbox itself, and the
 * policy link inside the label stops propagation, so following a policy link
 * never toggles the checkbox and never submits the form.
 */
function PolicyCheckbox({ id, checked, onCheckedChange, error, children }: PolicyCheckboxProps) {
  return (
    <div className="rounded-xl border border-[#0F9488]/20 bg-[#0F9488]/5 p-4">
      <div className="flex items-start gap-3">
        <Checkbox
          id={id}
          checked={checked}
          onCheckedChange={(value) => onCheckedChange(value === true)}
          aria-describedby={error ? `${id}-error` : undefined}
        />
        <div className="min-w-0 flex-1">
          <Label htmlFor={id} className="cursor-pointer text-sm leading-relaxed text-muted-foreground">
            {children}
          </Label>
          {error && (
            <p id={`${id}-error`} className="mt-1 text-sm font-medium text-red-700">
              This acknowledgement is required.
            </p>
          )}
        </div>
      </div>
    </div>
  );
}

function PolicyLink({ href, label }: { href: string; label: string }) {
  return (
    <a
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      onClick={(event) => event.stopPropagation()}
      onMouseDown={(event) => event.stopPropagation()}
      className="inline-flex items-center gap-0.5 font-semibold text-[#0F9488] hover:underline"
    >
      {label}
      <ExternalLink className="h-3 w-3" aria-hidden="true" />
      <span className="sr-only"> (opens in a new tab)</span>
    </a>
  );
}