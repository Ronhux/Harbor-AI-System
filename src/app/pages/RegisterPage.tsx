import { toast } from 'sonner';
import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { CheckCircle2, ExternalLink, UserPlus } from 'lucide-react';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../components/ui/select';
import { Checkbox } from '../components/ui/checkbox';
import { aparriBarangays } from '../data/aparriBarangays';
import { TERMS_VERSION } from '../data/termsSections';
import LanguageSwitcher from '../i18n/LanguageSwitcher';
import { useLanguage } from '../i18n/useLanguage';

const formatRsbsaNumber = (value: string): string => {
  const digits = value.replace(/\D/g, '').slice(0, 15);
  const lengths = [2, 2, 2, 3, 6];
  const groups: string[] = [];
  let offset = 0;

  for (const length of lengths) {
    const group = digits.slice(offset, offset + length);
    if (!group) break;
    groups.push(group);
    offset += length;
  }

  return groups.join('-');
};

type RegistrySummary = {
  rsbsa_number: string;
  full_name: string;
  barangay: string;
  municipality: string;
};

type PolicyMeta = {
  type: string;
  title: string;
  version: string;
  path: string;
};

type PoliciesResponse = {
  policies?: Record<string, PolicyMeta>;
};

type IdentityField =
  | 'rsbsa_number'
  | 'first_name'
  | 'middle_name'
  | 'last_name'
  | 'suffix'
  | 'sex'
  | 'barangay';

type FieldErrors = Record<string, string[]>;

const apiHeaders = (): Record<string, string> => {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  return {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-CSRF-TOKEN': csrfToken,
  };
};

/** First message for a field, if the API returned one. */
const firstError = (errors: FieldErrors, field: string): string => {
  const messages = errors[field];
  return Array.isArray(messages) && messages.length > 0 ? messages[0] : '';
};

export default function RegisterPage() {
  const { t } = useLanguage();
  const [formData, setFormData] = useState({
    first_name: '',
    middle_name: '',
    last_name: '',
    suffix: '',
    sex: '',
    barangay: '',
    contact_number: '',
    email: '',
    password: '',
    confirmPassword: '',
    producer_type: '',
    rsbsa_number: '',
  });
  // Two independent agreements, both initially unchecked.
  const [termsAccepted, setTermsAccepted] = useState(false);
  const [privacyAcknowledged, setPrivacyAcknowledged] = useState(false);
  const [passwordError, setPasswordError] = useState('');
  const [error, setError] = useState<string>('');
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [isVerifyingRegistry, setIsVerifyingRegistry] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [registryVerified, setRegistryVerified] = useState(false);
  const [registrySummary, setRegistrySummary] = useState<RegistrySummary | null>(null);
  const [registrationSuccess, setRegistrationSuccess] = useState(false);
  const [policies, setPolicies] = useState<Record<string, PolicyMeta>>({});
  const verificationAttempt = useRef(0);

  // The versions displayed next to each checkbox are the versions submitted
  // with the registration, so the acceptance always references the policy text
  // the user actually saw.
  useEffect(() => {
    let active = true;
    fetch('/api/policies', { headers: { Accept: 'application/json' } })
      .then((response) => (response.ok ? response.json() : null))
      .then((result: PoliciesResponse | null) => {
        if (active && result?.policies) setPolicies(result.policies);
      })
      .catch(() => {
        // Keep the bundled versions as the displayed fallback; the API is
        // still the authority on whether they are current.
      });

    return () => {
      active = false;
    };
  }, []);

  const termsPolicy = policies.terms;
  const privacyPolicy = policies.privacy_policy;
  const termsVersion = termsPolicy?.version ?? TERMS_VERSION;
  const privacyVersion = privacyPolicy?.version ?? '2026-10-01';
  const termsHref = termsPolicy?.path ?? '/terms-and-conditions';
  const privacyHref = privacyPolicy?.path ?? '/privacy';

  const updateIdentity = (field: IdentityField, value: string) => {
    verificationAttempt.current += 1;
    setFormData((current) => ({ ...current, [field]: value }));
    setRegistryVerified(false);
    setRegistrySummary(null);
    setIsVerifyingRegistry(false);
    setError('');
  };

  const handleRegistryVerification = async () => {
    setError('');
    setPasswordError('');

    if (!formData.rsbsa_number || !formData.first_name || !formData.last_name || !formData.sex || !formData.barangay) {
      setError('Please complete the registry verification fields before continuing.');
      return;
    }

    const attempt = ++verificationAttempt.current;
    setIsVerifyingRegistry(true);

    try {
      const response = await fetch('/api/registry/verify-producer', {
        method: 'POST',
        headers: apiHeaders(),
        body: JSON.stringify({
          rsbsa_number: formData.rsbsa_number,
          first_name: formData.first_name,
          middle_name: formData.middle_name,
          last_name: formData.last_name,
          extension_name: formData.suffix,
          sex: formData.sex,
          barangay: formData.barangay,
        }),
      });

      const result = await response.json().catch(() => ({}));

      if (attempt !== verificationAttempt.current) return;

      if (response.ok && result.status === 'registry_verified' && result.registry_match === true) {
        setRegistryVerified(true);
        if (
          result.registry
          && typeof result.registry.rsbsa_number === 'string'
          && typeof result.registry.full_name === 'string'
          && typeof result.registry.barangay === 'string'
          && typeof result.registry.municipality === 'string'
        ) {
          setRegistrySummary(result.registry);
        } else {
          setRegistryVerified(false);
          setError('Registry verification could not be completed. Please try again.');
          return;
        }
        setError('');
        toast.success('Registry details matched. Administrator approval is still required.');
        return;
      }

      setRegistryVerified(false);
      setRegistrySummary(null);
      setError(result.message || 'The information provided does not match the registry record associated with this RSBSA Number. Please review your details.');
    } catch (verificationError) {
      console.error('Registry verification error:', verificationError);
      if (attempt !== verificationAttempt.current) return;
      setRegistryVerified(false);
      setError('Unable to verify this registry record right now. Please try again.');
    } finally {
      if (attempt === verificationAttempt.current) setIsVerifyingRegistry(false);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    setError('');
    setFieldErrors({});

    if (!registryVerified) {
      setError('Please verify the registry record before creating the producer account.');
      return;
    }

    if (formData.password !== formData.confirmPassword) {
      setPasswordError('Passwords do not match');
      return;
    }
    setPasswordError('');

    if (!termsAccepted || !privacyAcknowledged) {
      setError(
        !termsAccepted && !privacyAcknowledged
          ? 'Please agree to the Terms and Conditions and acknowledge the Privacy Policy.'
          : !termsAccepted
            ? 'Please agree to the Terms and Conditions to continue.'
            : 'Please acknowledge that you have read the Privacy Policy to continue.',
      );
      return;
    }

    setIsSubmitting(true);

    try {
      const response = await fetch('/api/register', {
        method: 'POST',
        headers: apiHeaders(),
        body: JSON.stringify({
          first_name: formData.first_name,
          middle_name: formData.middle_name,
          last_name: formData.last_name,
          suffix: formData.suffix,
          extension_name: formData.suffix,
          sex: formData.sex,
          barangay: formData.barangay,
          contact_number: formData.contact_number,
          email: formData.email.trim() === '' ? null : formData.email,
          password: formData.password,
          user_type: 'producer',
          producer_type: formData.producer_type,
          rsbsa_number: formData.rsbsa_number,
          // Affirmative acceptance of the exact versions displayed above.
          terms_accepted: true,
          privacy_acknowledged: true,
          terms_version: termsVersion,
          privacy_policy_version: privacyVersion,
        }),
      });

      let result: {
        message?: string;
        errors?: FieldErrors;
        status?: string;
        token?: string | null;
        verification_status?: string;
      };

      try {
        result = await response.json();
      } catch (parseError) {
        if (parseError instanceof SyntaxError && !response.ok) {
          setError(`Registration could not be completed (HTTP ${response.status}). Please try again or contact support.`);
          return;
        }
        throw parseError;
      }

      if (response.ok) {
        localStorage.removeItem('authToken');
        localStorage.removeItem('userEmail');
        localStorage.removeItem('userRole');
        localStorage.removeItem('userName');
        localStorage.removeItem('userId');
        setRegistrationSuccess(true);
        toast.success('Your details match a registry record. Your account is awaiting administrator approval.');
        return;
      }

      if (result.status === 'policy_update_required') {
        setError(result.message || 'The policies have been updated. Please reload this page, review them, and accept the current version.');
        return;
      }

      if (result.errors) {
        setFieldErrors(result.errors);
        const messages = Object.entries(result.errors).map(([field, fieldMessages]) => {
          const label = field.replace(/_/g, ' ').charAt(0).toUpperCase() + field.slice(1);
          return `${label}: ${Array.isArray(fieldMessages) ? fieldMessages.join(', ') : fieldMessages}`;
        });
        setError(messages.join('\n'));
      } else if (result.message) {
        setError(result.message);
      } else {
        setError('Registration failed. Please try again.');
      }
    } catch (registrationError) {
      console.error('Registration error:', registrationError);
      setError('Registration failed. Please check your connection and try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen bg-background font-body relative overflow-hidden px-4 py-8 sm:py-12">
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Anton&family=Baloo+2:wght@400;500;600;700;800&display=swap');
        .font-display { font-family: 'Anton', ui-sans-serif, sans-serif; }
        .font-body { font-family: 'Baloo 2', ui-rounded, system-ui, sans-serif; }
      `}</style>

      <div className="pointer-events-none absolute -top-24 -left-24 h-72 w-72 rounded-full bg-[#22C55E]/20 blur-[100px]" />
      <div className="relative z-10 mx-auto max-w-3xl"><Link to="/register/fca" className="mb-4 block rounded-xl border border-teal-200 bg-card p-4 font-semibold text-foreground">FCA President / Authorized Representative registration →</Link>
        <div className="mb-5 flex items-center justify-between gap-2 px-1 sm:gap-4">
          <Link to="/" className="flex min-w-0 items-center gap-3 text-foreground">
            <img src="/logo.png" alt="HarborAI Logo" className="h-10 w-10 shrink-0 object-contain sm:h-12 sm:w-12" />
            <span className="truncate font-display text-xl tracking-tight sm:text-2xl">HarborAI</span>
          </Link>
          <div className="flex shrink-0 items-center gap-2 sm:gap-4">
            <LanguageSwitcher />
            <Link to="/login" className="whitespace-nowrap text-sm font-bold text-[#0F9488] hover:text-[#0B4842] hover:underline">Login</Link>
          </div>
        </div>

        <Card className="overflow-hidden rounded-2xl border-2 border-border bg-card/95 shadow-xl backdrop-blur-sm">
          <div className="h-2 bg-gradient-to-r from-[#22C55E] via-[#0F9488] to-[#22D3EE]" />
          <CardHeader className="px-5 pt-7 text-center sm:px-10 sm:pt-9">
            <div className="flex items-center justify-center gap-3 mb-4">
              <img src="/logo.png" alt="HarborAI Logo" className="w-14 h-14 object-contain" />
              <span className="font-display text-3xl tracking-tight text-foreground">HarborAI</span>
            </div>
            <CardTitle className="font-display text-2xl tracking-tight text-foreground">{t('register.producerRegisterTitle')}</CardTitle>
            <CardDescription className="text-muted-foreground">
              {t('register.joinText')}
            </CardDescription>
          </CardHeader>
          <CardContent className="px-5 pb-8 sm:px-10">
            <form onSubmit={handleSubmit} className="space-y-5" hidden={registrationSuccess}>
              {error && (
                <div className="p-3 bg-red-50 border border-red-200 rounded-lg">
                  <p className="text-red-800 text-sm whitespace-pre-line">{error}</p>
                </div>
              )}

              <div className="rounded-xl border border-[#0F9488]/20 bg-[#0F9488]/5 p-4">
                <p className="mb-2 text-xs font-black uppercase tracking-[0.18em] text-[#0F9488]">Step 1 of 2 — Verify Producer Registry</p>
                <p className="mb-4 text-sm text-muted-foreground">Enter the identity information associated with your RSBSA record.</p>
                <div className="grid md:grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="rsbsa_number">RSBSA Number *</Label>
                    <Input
                      id="rsbsa_number"
                      type="text"
                      placeholder="XX-XX-XX-XXX-XXXXXX"
                      value={formData.rsbsa_number}
                      onChange={(e) => updateIdentity('rsbsa_number', formatRsbsaNumber(e.target.value))}
                      inputMode="numeric"
                      autoComplete="off"
                      required
                      className="rounded-full"
                    />
                    {firstError(fieldErrors, 'rsbsa_number') && (
                      <p className="mt-1 text-sm font-medium text-red-700">{firstError(fieldErrors, 'rsbsa_number')}</p>
                    )}
                  </div>
                </div>

                <div className="mt-4 grid md:grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="first_name">First Name</Label>
                    <Input
                      id="first_name"
                      type="text"
                      placeholder="Juan"
                      value={formData.first_name}
                      onChange={(e) => updateIdentity('first_name', e.target.value)}
                      required
                      className="rounded-full"
                    />
                  </div>
                  <div>
                    <Label htmlFor="middle_name">Middle Name <span className="text-gray-400">(optional)</span></Label>
                    <Input
                      id="middle_name"
                      type="text"
                      placeholder="Santos"
                      value={formData.middle_name}
                      onChange={(e) => updateIdentity('middle_name', e.target.value)}
                      className="rounded-full"
                    />
                    <p className="mt-1 text-xs text-muted-foreground">Enter your middle name as listed in the registry; leave blank only if none is listed.</p>
                  </div>
                </div>

                <div className="mt-4 grid md:grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="last_name">Last Name</Label>
                    <Input
                      id="last_name"
                      type="text"
                      placeholder="Dela Cruz"
                      value={formData.last_name}
                      onChange={(e) => updateIdentity('last_name', e.target.value)}
                      required
                      className="rounded-full"
                    />
                  </div>
                  <div>
                    <Label htmlFor="suffix">Extension Name <span className="text-gray-400">(optional)</span></Label>
                    <Input
                      id="suffix"
                      type="text"
                      placeholder="Jr., Sr., III"
                      value={formData.suffix}
                      onChange={(e) => updateIdentity('suffix', e.target.value)}
                      className="rounded-full"
                    />
                  </div>
                </div>

                <div className="mt-4 grid md:grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="sex">Sex</Label>
                    <Select value={formData.sex} onValueChange={(value) => updateIdentity('sex', value)} required>
                      <SelectTrigger id="sex" className="rounded-full">
                        <SelectValue placeholder="Select sex" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="Male">Male</SelectItem>
                        <SelectItem value="Female">Female</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div>
                    <Label htmlFor="barangay">Barangay</Label>
                    <Select value={formData.barangay} onValueChange={(value) => updateIdentity('barangay', value)} required>
                      <SelectTrigger id="barangay" className="rounded-full">
                        <SelectValue placeholder="Select barangay" />
                      </SelectTrigger>
                      <SelectContent>
                        {aparriBarangays.map((name) => <SelectItem key={name} value={name}>{name}</SelectItem>)}
                      </SelectContent>
                    </Select>
                  </div>
                </div>

                <div className="mt-5">
                  <Button
                    type="button"
                    onClick={handleRegistryVerification}
                    disabled={isVerifyingRegistry}
                    className="w-full rounded-full bg-[#0F9488] font-bold text-white hover:bg-[#0B6661]"
                  >
                    {isVerifyingRegistry ? 'Verifying Registry Record...' : 'Verify Registry Record'}
                  </Button>
                </div>
              </div>

              {registryVerified && (
                <div className="rounded-xl border border-[#22C55E]/30 bg-[#ECFDF5] p-4">
                  <div className="flex items-start gap-3">
                    <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-[#15803D]" />
                    <div>
                      <p className="text-sm font-bold text-[#166534]">REGISTRY DETAILS MATCHED</p>
                      <p className="mt-1 text-sm text-[#166534]">Your information matches a registry record. Submit your application for administrator review. A match does not verify your identity or ownership of your mobile number.</p>
                    </div>
                  </div>
                  {registrySummary && (
                    <dl className="mt-3 grid gap-2 border-t border-[#22C55E]/20 pt-3 text-sm sm:grid-cols-2">
                      <div><dt className="font-semibold text-muted-foreground">RSBSA Number</dt><dd className="text-foreground">{registrySummary.rsbsa_number}</dd></div>
                      <div><dt className="font-semibold text-muted-foreground">Registered Name</dt><dd className="text-foreground">{registrySummary.full_name}</dd></div>
                      <div><dt className="font-semibold text-muted-foreground">Barangay</dt><dd className="text-foreground">{registrySummary.barangay}</dd></div>
                      <div><dt className="font-semibold text-muted-foreground">Municipality</dt><dd className="text-foreground">{registrySummary.municipality}</dd></div>
                    </dl>
                  )}
                </div>
              )}

              {registryVerified && (
                <div className="space-y-5 rounded-xl border border-border bg-card p-4">
                  <p className="text-xs font-black uppercase tracking-[0.18em] text-foreground">Step 2 of 2 — Create HarborAI Account</p>

                  <div>
                    <Label htmlFor="producer_type">Producer Type *</Label>
                    <Select
                      value={formData.producer_type}
                      onValueChange={(value) => setFormData({ ...formData, producer_type: value })}
                      required
                    >
                      <SelectTrigger id="producer_type" className="rounded-full">
                        <SelectValue placeholder="Select producer type" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="Farmer">Farmer</SelectItem>
                      </SelectContent>
                    </Select>
                    {firstError(fieldErrors, 'producer_type') && (
                      <p className="mt-1 text-sm font-medium text-red-700">{firstError(fieldErrors, 'producer_type')}</p>
                    )}
                  </div>

                  <div>
                    <Label htmlFor="contact_number">Mobile Number *</Label>
                    <Input
                      id="contact_number"
                      type="tel"
                      inputMode="tel"
                      autoComplete="tel"
                      placeholder="09XXXXXXXXX"
                      aria-describedby="contact_number-help"
                      aria-invalid={firstError(fieldErrors, 'contact_number') ? true : undefined}
                      value={formData.contact_number}
                      onChange={(e) => setFormData({ ...formData, contact_number: e.target.value })}
                      required
                      className="rounded-full"
                    />
                    <p id="contact_number-help" className="mt-1 text-xs text-muted-foreground">
                      Required. This is the number you will use to sign in to HarborAI. Use your own
                      active Philippine mobile number, for example 09171234567.
                    </p>
                    {firstError(fieldErrors, 'contact_number') && (
                      <p className="mt-1 text-sm font-medium text-red-700">{firstError(fieldErrors, 'contact_number')}</p>
                    )}
                  </div>

                  <div>
                    <Label htmlFor="email">Email Address <span className="text-gray-400">(optional)</span></Label>
                    <Input
                      id="email"
                      type="email"
                      autoComplete="email"
                      placeholder="your.email@example.com"
                      aria-describedby="email-help"
                      aria-invalid={firstError(fieldErrors, 'email') ? true : undefined}
                      value={formData.email}
                      onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                      className="rounded-full"
                    />
                    <p id="email-help" className="mt-1 text-xs text-muted-foreground">
                      Optional. You can register and sign in with your mobile number alone. Add an
                      email if you would like a second way to sign in.
                    </p>
                    {firstError(fieldErrors, 'email') && (
                      <p className="mt-1 text-sm font-medium text-red-700">{firstError(fieldErrors, 'email')}</p>
                    )}
                  </div>

                  <div className="grid md:grid-cols-2 gap-4">
                    <div>
                      <Label htmlFor="password">Password *</Label>
                      <Input
                        id="password"
                        type="password"
                        autoComplete="new-password"
                        placeholder="Create password"
                        aria-describedby="password-help"
                        aria-invalid={firstError(fieldErrors, 'password') ? true : undefined}
                        value={formData.password}
                        onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                        required
                      />
                      <p id="password-help" className="mt-1 text-xs text-muted-foreground">Use at least 8 characters.</p>
                      {firstError(fieldErrors, 'password') && (
                        <p className="mt-1 text-sm font-medium text-red-700">{firstError(fieldErrors, 'password')}</p>
                      )}
                    </div>
                    <div>
                      <Label htmlFor="confirmPassword">Confirm Password *</Label>
                      <Input
                        id="confirmPassword"
                        type="password"
                        autoComplete="new-password"
                        placeholder="Re-enter password"
                        value={formData.confirmPassword}
                        onChange={(e) => setFormData({ ...formData, confirmPassword: e.target.value })}
                        required
                      />
                    </div>
                  </div>
                  {passwordError && (
                    <div className="text-red-600 text-sm font-medium mb-2 text-center">{passwordError}</div>
                  )}

                  <div className="space-y-4 rounded-xl border border-[#0F9488]/20 bg-[#0F9488]/5 p-4">
                    <p className="text-xs font-black uppercase tracking-[0.18em] text-[#0F9488]">
                      Policies you must accept
                    </p>

                    <div className="flex items-start gap-3">
                      <Checkbox
                        id="terms-accepted"
                        checked={termsAccepted}
                        onCheckedChange={(checked) => setTermsAccepted(checked === true)}
                        aria-invalid={firstError(fieldErrors, 'terms_accepted') ? true : undefined}
                      />
                      <div className="min-w-0 flex-1">
                        <Label htmlFor="terms-accepted" className="cursor-pointer text-sm leading-relaxed text-muted-foreground">
                          I agree to the{' '}
                          <PolicyLink href={termsHref} label="HarborAI Terms and Conditions" />{' '}
                          (version {termsVersion}).
                        </Label>
                        {firstError(fieldErrors, 'terms_accepted') && (
                          <p className="mt-1 text-sm font-medium text-red-700">{firstError(fieldErrors, 'terms_accepted')}</p>
                        )}
                      </div>
                    </div>

                    <div className="flex items-start gap-3">
                      <Checkbox
                        id="privacy-acknowledged"
                        checked={privacyAcknowledged}
                        onCheckedChange={(checked) => setPrivacyAcknowledged(checked === true)}
                        aria-invalid={firstError(fieldErrors, 'privacy_acknowledged') ? true : undefined}
                      />
                      <div className="min-w-0 flex-1">
                        <Label htmlFor="privacy-acknowledged" className="cursor-pointer text-sm leading-relaxed text-muted-foreground">
                          I acknowledge that I have read the{' '}
                          <PolicyLink href={privacyHref} label="HarborAI Privacy Policy" />{' '}
                          (version {privacyVersion}).
                        </Label>
                        {firstError(fieldErrors, 'privacy_acknowledged') && (
                          <p className="mt-1 text-sm font-medium text-red-700">{firstError(fieldErrors, 'privacy_acknowledged')}</p>
                        )}
                      </div>
                    </div>
                  </div>

                  <Button type="submit" disabled={isSubmitting} className="w-full rounded-full bg-[#22C55E] font-bold text-white hover:bg-[#15803D]">
                    <UserPlus className="w-4 h-4 mr-2" />
                    {isSubmitting ? 'Creating Account...' : 'Create Account'}
                  </Button>
                </div>
              )}
            </form>

            <div className="mt-6 text-center text-sm text-muted-foreground">
              <p>
                Already have an account?{' '}
                <Link to="/login" className="font-bold text-[#0F9488] hover:text-[#0B4842] hover:underline">
                  Login here
                </Link>
              </p>
            </div>

            <div className="mt-6 border-t border-border pt-6">
              <Link to="/">
                <Button variant="ghost" className="w-full rounded-full font-semibold text-foreground hover:bg-[#123C5C]/5">
                  Back to Home
                </Button>
              </Link>
            </div>

            {registrationSuccess && (
              <div className="mb-6 rounded-xl border border-[#22C55E]/30 bg-[#ECFDF5] p-5 text-center">
                <CheckCircle2 className="mx-auto h-10 w-10 text-[#15803D]" />
                <h2 className="mt-2 font-display text-xl text-[#166534]">PENDING ADMIN REVIEW</h2>
                <p className="mt-2 text-sm text-[#166534]">Your details match a registry record. Your account is awaiting administrator approval.</p>
                <p className="mt-2 text-sm text-[#166534]">You can sign in and access producer features after approval. Contact the administrator if you need help with your application.</p>
                <Link to="/login">
                  <Button className="mt-4 rounded-full bg-[#0F9488] font-bold text-white hover:bg-[#0B6661]">Go to Login</Button>
                </Link>
              </div>
            )}

            {!registrationSuccess && <div className="mt-6 rounded-xl border border-[#FDE68A] bg-[#FFFBEB] p-4">
              <p className="text-sm text-[#92400E]">
                <strong>Note:</strong> A successful registry match sends your application for administrator review. Producer access requires approval. Registry mismatches do not create an account.
              </p>
              <p className="mt-2 text-sm text-[#92400E]">
                <strong>Note:</strong> HarborAI does not yet send SMS verification codes, so we cannot
                confirm that you own the mobile number you enter. Use your own active number, and
                contact the HarborAI operator if you need an incorrect number corrected.
              </p>
            </div>}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

/**
 * Policy links open in a new tab. Click propagation is stopped so following a
 * link never toggles the surrounding checkbox or submits the registration form.
 */
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
