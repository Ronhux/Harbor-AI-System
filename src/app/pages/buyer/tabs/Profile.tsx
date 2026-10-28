import { DashboardText, useDashboardCopy } from '../../../i18n/DashboardCopy';
import { useEffect, useState, type FormEvent } from 'react';
import {
  AlertCircle,
  Building2,
  CheckCircle2,
  Hash,
  Loader2,
  Mail,
  MapPin,
  Phone,
  RefreshCw,
  Save,
  ShieldCheck,
  UserRound,
  X,
} from 'lucide-react';
import { request } from '../../../../lib/api';
import { Badge } from '../../../components/ui/badge';
import { Button } from '../../../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../../../components/ui/card';
import { Input } from '../../../components/ui/input';
import { Label } from '../../../components/ui/label';

/**
 * Buyer profile contract.
 *
 * The `BuyerProfileController::present()` payload supplies buyer_id,
 * organization_name, buyer_type, verification_status, contact_person,
 * shipping_address, email and contact_number.
 *
 * `verification_status` and `buyer_id` are read-only: approval is decided by
 * an administrator, and the field is rendered exactly as the backend returns
 * it. No value in this component is defaulted, assumed or fabricated.
 */
type Buyer = {
  buyer_id?: number;
  organization_name?: string;
  buyer_type?: string;
  verification_status?: string;
  contact_person?: string;
  shipping_address?: string;
  email?: string;
  contact_number?: string;
};

type FieldErrors = Record<string, string[]>;

const emptyBuyer: Buyer = {
  organization_name: '',
  buyer_type: '',
  contact_person: '',
  shipping_address: '',
  email: '',
  contact_number: '',
};

/** Only the fields the PUT endpoint accepts as editable. */
type EditableBuyer = {
  organization_name: string;
  buyer_type: string;
  contact_person: string;
  shipping_address: string;
  email: string;
  contact_number: string;
};

const text = (value?: string | null) => (value || '').trim();

/**
 * Copy shown beside the verification status. It never claims a successful
 * verification unless the backend field literally says so.
 */
const verificationCopy = (status: string, buyerType: string) => {
  const normalized = status.toLowerCase();

  if (normalized === 'approved' || normalized === 'verified') {
    return {
      tone: 'positive',
      label: status,
      message: 'Your institutional buyer account has been approved. You can post demand requests, negotiate with producers and manage orders.',
    };
  }

  if (normalized === 'rejected') {
    return {
      tone: 'negative',
      label: status,
      message: 'Your application was not approved. Contact an administrator if you need help with your account.',
    };
  }

  return {
    tone: 'pending',
    label: status || 'Pending',
    message: buyerType === 'Institutional Buyer'
      ? 'Your institutional buyer application is still awaiting administrator review. Demand posting and negotiations become available once it is approved.'
      : 'Your account verification status is shown by the server. Some actions may be limited until an administrator reviews your account.',
  };
};

export default function Profile() {
  const copy = useDashboardCopy();
  const [buyer, setBuyer] = useState<Buyer | null>(null);
  const [draft, setDraft] = useState<EditableBuyer | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadFailed, setLoadFailed] = useState('');
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState('');
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    const controller = new AbortController();

    const load = async () => {
      setLoading(true);
      setLoadFailed('');
      try {
        const result = await request('/api/buyer/profile', { signal: controller.signal });
        const profile: Buyer = result?.data;

        if (controller.signal.aborted) return;
        if (!profile) {
          setLoadFailed('No buyer profile was returned for this account.');
          setBuyer(null);
        } else {
          setBuyer(profile);
          setDraft({
            organization_name: text(profile.organization_name),
            buyer_type: text(profile.buyer_type),
            contact_person: text(profile.contact_person),
            shipping_address: text(profile.shipping_address),
            email: text(profile.email),
            contact_number: text(profile.contact_number),
          });
        }
      } catch (failure: any) {
        if ((failure as Error)?.name === 'AbortError' || controller.signal.aborted) return;
        setLoadFailed(failure?.message || 'Unable to load your profile.');
        setBuyer(null);
      } finally {
        if (!controller.signal.aborted) setLoading(false);
      }
    };

    void load();
    return () => controller.abort();
  }, []);

  const startEditing = () => {
    if (!buyer) return;
    setDraft({
      organization_name: text(buyer.organization_name),
      buyer_type: text(buyer.buyer_type),
      contact_person: text(buyer.contact_person),
      shipping_address: text(buyer.shipping_address),
      email: text(buyer.email),
      contact_number: text(buyer.contact_number),
    });
    setSaveError('');
    setFieldErrors({});
    setSaved(false);
  };

  const cancelEditing = () => {
    setDraft(null);
    setSaveError('');
    setFieldErrors({});
  };

  /**
   * Client-side checks mirror only the controller's own required/format rules.
   * The server stays the authority; this simply avoids pointless round trips.
   */
  const validate = (form: EditableBuyer) => {
    const nextErrors: FieldErrors = {};
    if (!text(form.organization_name)) nextErrors.organization_name = ['Organization name is required.'];
    if (!text(form.contact_person)) nextErrors.contact_person = ['Contact person is required.'];
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(text(form.email))) nextErrors.email = ['Enter a valid email address.'];
    setFieldErrors(nextErrors);
    return Object.keys(nextErrors).length === 0;
  };

  const save = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!draft || saving) return;
    if (!validate(draft)) return;

    setSaving(true);
    setSaveError('');
    setFieldErrors({});
    setSaved(false);

    try {
      await request('/api/buyer/profile', {
        method: 'PUT',
        body: {
          organization_name: text(draft.organization_name),
          buyer_type: text(draft.buyer_type) || null,
          contact_person: text(draft.contact_person),
          shipping_address: text(draft.shipping_address) || null,
          email: text(draft.email),
          contact_number: text(draft.contact_number) || null,
        },
      });

      // Re-read from the server so the rendered profile is what the backend
      // now stores, rather than whatever the local draft assumed.
      const refreshed = await request('/api/buyer/profile');
      const profile: Buyer = refreshed?.data;
      setBuyer(profile ?? null);
      setDraft(null);
      setSaved(true);
    } catch (failure: any) {
      const validation = Object.values(failure?.errors || {}).flat() as string[];
      if (validation.length) setFieldErrors({ ...(failure.errors as FieldErrors) });
      setSaveError(validation.join(' ') || failure?.message || 'Unable to save your profile. Please try again.');
    } finally {
      setSaving(false);
    }
  };

  const update = (field: keyof EditableBuyer, value: string) => {
    setDraft((current) => (current ? { ...current, [field]: value } : current));
    setFieldErrors((current) => {
      if (!current[field]) return current;
      const next = { ...current };
      delete next[field];
      return next;
    });
  };

  const fieldError = (field: keyof EditableBuyer) => {
    const messages = fieldErrors[field];
    return messages?.length ? (
      <p className="mt-1 text-sm text-red-700">{messages.join(' ')}</p>
    ) : null;
  };

  if (loading) {
    return (
      <div className="min-h-full bg-muted p-4 sm:p-6 lg:p-8">
        <div className="mx-auto max-w-5xl space-y-4">
          <div className="h-8 w-48 animate-pulse rounded-lg bg-card/70" />
          <div className="h-32 animate-pulse rounded-3xl bg-card/70" />
          <div className="grid gap-4 sm:grid-cols-2">
            {[0, 1, 2, 3].map((item) => (
              <div key={item} className="h-24 animate-pulse rounded-2xl bg-card/70" />
            ))}
          </div>
        </div>
      </div>
    );
  }

  if (loadFailed || !buyer) {
    return (
      <div className="min-h-full bg-muted p-4 sm:p-6 lg:p-8">
        <div className="mx-auto max-w-2xl">
          <Card className="border-red-200 bg-card">
            <CardContent className="space-y-4 p-6 text-center">
              <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-50">
                <AlertCircle className="h-6 w-6 text-red-700" />
              </div>
              <h2 className="text-xl font-black text-foreground"><DashboardText text="Unable to load your profile" /></h2>
              <p role="alert" className="text-sm text-muted-foreground">{loadFailed || copy("No profile data was found for this account.")}</p>
              <Button
                type="button"
                className="min-h-11 rounded-xl bg-[#123C5C] font-bold text-white hover:bg-[#0d2e47]"
                onClick={() => window.location.reload()}
              >
                <RefreshCw className="mr-2 h-4 w-4" aria-hidden="true" /><DashboardText text="Try again" /></Button>
            </CardContent>
          </Card>
        </div>
      </div>
    );
  }

  const verification = verificationCopy(text(buyer.verification_status), text(buyer.buyer_type) || 'Institutional Buyer');
  const initials = (text(buyer.organization_name).replace(/^the\s+/i, '').trim()[0] || 'B').toUpperCase();

  const readOnlyCards = [
    {
      label: 'Contact person',
      value: text(buyer.contact_person) || 'Not provided',
      icon: UserRound,
      wrap: 'bg-[#E2F0FF] text-[#1976D2]',
    },
    {
      label: 'Contact number',
      value: text(buyer.contact_number) || 'Not provided',
      icon: Phone,
      wrap: 'bg-[#E4F5E9] text-[#159447]',
    },
    {
      label: 'Buyer ID',
      value: buyer.buyer_id ? `#${buyer.buyer_id}` : 'Not provided',
      icon: Hash,
      wrap: 'bg-[#FFF0D5] text-[#E99A18]',
    },
    {
      label: 'Buyer type',
      value: text(buyer.buyer_type) || 'Not provided',
      icon: Building2,
      wrap: 'bg-[#DFF4EA] text-[#168B65]',
    },
  ];

  return (
    <div className="min-h-full overflow-x-hidden bg-muted p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-5xl space-y-5">
        <header className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="font-display text-2xl tracking-tight text-foreground sm:text-3xl"><DashboardText text="Institutional Profile" /></h1>
            <p className="mt-1 text-sm text-muted-foreground"><DashboardText text="Impormasyon ng account at organizers na ginagamit sa demand at orders." /></p>
          </div>

          {!draft && (
            <Button
              type="button"
              onClick={startEditing}
              className="min-h-11 rounded-xl bg-[#123C5C] font-bold text-white hover:bg-[#0d2e47]"
            ><DashboardText text="Edit profile" /></Button>
          )}
        </header>

        {saved && !draft && (
          <p role="status" className="flex items-center gap-2 rounded-xl bg-[#EAF8E9] p-3 text-sm font-semibold text-[#13843E]">
            <CheckCircle2 className="h-4 w-4 shrink-0" aria-hidden="true" /><DashboardText text="Profile saved." /></p>
        )}

        {/* Identity and verification */}
        <Card className="overflow-hidden border-[#DDE3D9] bg-card shadow-sm">
          <div className="grid lg:grid-cols-[1fr_300px]">
            <div className="flex items-center gap-4 p-5 sm:p-6">
              <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-[#123C5C] text-2xl font-black text-white sm:h-20 sm:w-20">
                {initials}
              </div>
              <div className="min-w-0">
                <h2 className="break-words text-xl font-black text-foreground sm:text-2xl">{text(buyer.organization_name) || copy("Institutional buyer")}</h2>
                <p className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
                  <span className="inline-flex items-center gap-1.5">
                    <Mail className="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    <span className="break-all">{text(buyer.email) || copy("No email on file")}</span>
                  </span>
                  {text(buyer.buyer_type) && (
                    <span className="text-muted-foreground">· {text(buyer.buyer_type)}</span>
                  )}
                </p>
              </div>
            </div>

            <div
              className={`border-t border-[#E7EAE5] p-5 sm:p-6 lg:border-l lg:border-t-0 ${
                verification.tone === 'positive'
                  ? 'bg-[#EAF8E9]'
                  : verification.tone === 'negative'
                    ? 'bg-red-50'
                    : 'bg-amber-50'
              }`}
            >
              <div className="flex items-start gap-3">
                <div
                  className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${
                    verification.tone === 'positive'
                      ? 'bg-[#159447] text-white'
                      : verification.tone === 'negative'
                        ? 'bg-red-600 text-white'
                        : 'bg-amber-500 text-white'
                  }`}
                >
                  <ShieldCheck className="h-5 w-5" aria-hidden="true" />
                </div>
                <div className="min-w-0">
                  <p className="text-xs font-bold uppercase tracking-wide text-muted-foreground"><DashboardText text="Verification status" /></p>
                  <p
                    className={`text-lg font-black ${
                      verification.tone === 'positive'
                        ? 'text-[#13843E]'
                        : verification.tone === 'negative'
                          ? 'text-red-800'
                          : 'text-amber-900'
                    }`}
                  >
                    {verification.label}
                  </p>
                  <p className="mt-1 text-sm leading-relaxed text-muted-foreground">{verification.message}</p>
                </div>
              </div>
            </div>
          </div>
        </Card>

        {draft ? (
          <Card className="border-[#DDE3D9] bg-card shadow-sm">
            <CardHeader>
              <CardTitle className="text-lg text-foreground"><DashboardText text="Edit profile details" /></CardTitle>
            </CardHeader>
            <CardContent>
              <form className="grid gap-4 sm:grid-cols-2" onSubmit={save} noValidate>
                <div className="sm:col-span-2">
                  <Label htmlFor="buyer-organization"><DashboardText text="Organization name" /></Label>
                  <Input
                    id="buyer-organization"
                    className="mt-1.5 min-h-11"
                    value={draft.organization_name}
                    onChange={(event) => update('organization_name', event.target.value)}
                    aria-invalid={Boolean(fieldErrors.organization_name)}
                    required
                  />
                  {fieldError('organization_name')}
                </div>

                <div>
                  <Label htmlFor="buyer-contact-person"><DashboardText text="Contact person" /></Label>
                  <Input
                    id="buyer-contact-person"
                    className="mt-1.5 min-h-11"
                    value={draft.contact_person}
                    onChange={(event) => update('contact_person', event.target.value)}
                    aria-invalid={Boolean(fieldErrors.contact_person)}
                    required
                  />
                  {fieldError('contact_person')}
                </div>

                <div>
                  <Label htmlFor="buyer-email">Email</Label>
                  <Input
                    id="buyer-email"
                    type="email"
                    className="mt-1.5 min-h-11"
                    value={draft.email}
                    onChange={(event) => update('email', event.target.value)}
                    aria-invalid={Boolean(fieldErrors.email)}
                    required
                  />
                  {fieldError('email')}
                </div>

                <div>
                  <Label htmlFor="buyer-contact-number"><DashboardText text="Contact number" /></Label>
                  <Input
                    id="buyer-contact-number"
                    type="tel"
                    inputMode="tel"
                    className="mt-1.5 min-h-11"
                    value={draft.contact_number}
                    onChange={(event) => update('contact_number', event.target.value)}
                    placeholder={copy("Optional")}
                  />
                </div>

                <div>
                  <Label htmlFor="buyer-type"><DashboardText text="Buyer type" /></Label>
                  <Input
                    id="buyer-type"
                    className="mt-1.5 min-h-11"
                    value={draft.buyer_type}
                    onChange={(event) => update('buyer_type', event.target.value)}
                    placeholder={copy("Optional")}
                  />
                </div>

                <div className="sm:col-span-2">
                  <Label htmlFor="buyer-shipping-address"><DashboardText text="Shipping address" /></Label>
                  <textarea
                    id="buyer-shipping-address"
                    className="mt-1.5 min-h-20 w-full resize-none rounded-md border border-input bg-input-background px-3 py-2 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm"
                    value={draft.shipping_address}
                    onChange={(event) => update('shipping_address', event.target.value)}
                    placeholder={copy("Optional")}
                    maxLength={2000}
                  />
                </div>

                <div className="sm:col-span-2">
                  <p className="rounded-lg bg-background p-3 text-xs leading-relaxed text-muted-foreground"><DashboardText text="Organization name in the registry, buyer type, contact person, shipping address, email and contact number can be edited here. Verification status is decided by an administrator and cannot be changed from this form." /></p>
                </div>

                {saveError && (
                  <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-800 sm:col-span-2">
                    {saveError}
                  </p>
                )}

                <div className="flex flex-col-reverse gap-3 border-t border-border pt-4 sm:col-span-2 sm:flex-row sm:justify-end">
                  <Button type="button" variant="outline" onClick={cancelEditing} disabled={saving} className="min-h-11">
                    <X className="mr-2 h-4 w-4" aria-hidden="true" /><DashboardText text="Cancel" /></Button>
                  <Button type="submit" disabled={saving} className="min-h-11 bg-[#159447] font-bold text-white hover:bg-[#107638]">
                    {saving ? <Loader2 className="mr-2 h-4 w-4 animate-spin" aria-hidden="true" /> : <Save className="mr-2 h-4 w-4" aria-hidden="true" />}
                    {saving ? copy("Saving…") : copy("Save changes")}
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2">
            {readOnlyCards.map((card) => {
              const Icon = card.icon;
              return (
                <Card key={card.label} className="border-[#E0E3DD] bg-card shadow-sm">
                  <CardContent className="flex items-center gap-4 p-4">
                    <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl ${card.wrap}`}>
                      <Icon className="h-6 w-6" aria-hidden="true" />
                    </div>
                    <div className="min-w-0">
                      <p className="text-xs font-bold uppercase tracking-wide text-muted-foreground">{card.label}</p>
                      <p className="mt-0.5 break-words font-semibold text-foreground">{card.value}</p>
                    </div>
                  </CardContent>
                </Card>
              );
            })}

            <Card className="border-[#E0E3DD] bg-card shadow-sm">
              <CardContent className="flex items-start gap-4 p-4">
                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#EEE5FF] text-[#7048D7]">
                  <MapPin className="h-6 w-6" aria-hidden="true" />
                </div>
                <div className="min-w-0">
                  <p className="text-xs font-bold uppercase tracking-wide text-muted-foreground"><DashboardText text="Shipping address" /></p>
                  <p className="mt-0.5 break-words font-semibold text-foreground">
                    {text(buyer.shipping_address) || copy("Not provided")}
                  </p>
                </div>
              </CardContent>
            </Card>

            <Card className="border-[#E0E3DD] bg-card shadow-sm">
              <CardContent className="flex items-start gap-4 p-4">
                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#DDF4F7] text-[#138BA3]">
                  <ShieldCheck className="h-6 w-6" aria-hidden="true" />
                </div>
                <div className="min-w-0">
                  <p className="text-xs font-bold uppercase tracking-wide text-muted-foreground"><DashboardText text="Verification status" /></p>
                  <div className="mt-1 flex items-center gap-2">
                    <Badge
                      className={
                        verification.tone === 'positive'
                          ? 'bg-[#DDF6E6] text-[#13843E]'
                          : verification.tone === 'negative'
                            ? 'bg-red-100 text-red-800'
                            : 'bg-amber-100 text-amber-900'
                      }
                    >
                      {verification.label}
                    </Badge>
                    <span className="text-xs text-muted-foreground"><DashboardText text="Set by an administrator" /></span>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        )}
      </div>
    </div>
  );
}
