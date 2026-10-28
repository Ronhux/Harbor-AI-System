import { useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { AlertTriangle, ArrowLeft, CheckCircle2, ShieldCheck } from 'lucide-react';
import { useLanguage } from '../i18n/useLanguage';
import LanguageSwitcher from '../i18n/LanguageSwitcher';
import AssociationAutocomplete, { type AssociationRecord } from '../components/fca/AssociationAutocomplete';
import { FcaManualOnboarding } from '../components/mao/FcaManualIntegration';
import { request } from '../../lib/api';
import '../components/mao/workflow.css';

/**
 * FCA President / Authorized Representative registration.
 *
 * WHAT THIS PAGE DOES AND DOES NOT DO
 * -----------------------------------
 * The association is chosen from the real `fca_registry` records through a PUBLIC, read-only
 * search that works before you have an account. That search returns only an identifier, a name
 * and an address, so this page never displays a listed president's name to an anonymous visitor.
 * Selecting a directory record grants nothing: it does not verify the applicant, the
 * association, or any authority to represent it.
 *
 * The preliminary application request records basic applicant details and is stored with
 * "Pending Verification" status. It does NOT require an identity-document upload. Identity
 * evidence is handled separately and only when an approved evidence-handling policy exists.
 *
 * Submission creates a real database record visible to authorized FCA reviewers. It does not
 * mark the applicant as a verified FCA president or representative, and grants no procurement
 * authority.
 */

type Step = 1 | 2 | 3 | 4;

export default function FcaRegistrationPage() {
  const { lang } = useLanguage();
  const tr = (en: string, fil: string) => (lang === 'fil' ? fil : en);

  const submissionAttempt = useRef<{ payload: string; key: string } | null>(null);
  const [step, setStep] = useState<Step>(1);
  const [org, setOrg] = useState<AssociationRecord | null>(null);
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [position, setPosition] = useState<'president' | 'delegated'>('president');
  const [consent, setConsent] = useState(false);
  const [privacy, setPrivacy] = useState(false);
  const [notice, setNotice] = useState('');
  const [submitted, setSubmitted] = useState<{reference: number; status: string} | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [problems, setProblems] = useState<Record<string, string>>({});

  const steps: { id: Step; label: string }[] = [
    { id: 1, label: tr('Select Association', 'Pumili ng Samahan') },
    { id: 2, label: tr('Applicant Information', 'Impormasyon ng Aplikante') },
    { id: 3, label: tr('Privacy and Consent', 'Pahintulot at Preysado') },
    { id: 4, label: tr('Submit Request', 'Isumite ang Hiling') },
  ];

  const canContinue = step !== 1 || org !== null;

  const next = () => {
    setProblems({});
    if (step === 1 && !org) { setProblems({ org: tr('Select an association first.', 'Pumili muna ng samahan.') }); return; }
    if (step === 2) {
      const bad: Record<string, string> = {};
      if (name.trim().length < 2) bad.name = tr('Enter your full name.', 'Ilagay ang buong pangalan mo.');
      if (!/^[0-9+\-\s()]{7,20}$/.test(phone.trim())) bad.phone = tr('Enter a valid contact number.', 'Ilagay ang wastong numero ng telepono.');
      if (Object.keys(bad).length) { setProblems(bad); return; }
    }
    setStep((s) => (Math.min(4, s + 1) as Step));
  };

  const handleSubmit = async () => {
    setProblems({});
    if (submitting || !consent || !privacy || !org) return;

    setSubmitting(true);
    setNotice('');

    try {
      const authorizationStatement = `Applicant: ${name.trim()}\nContact: ${phone.trim()}\nClaimed role: ${position}\nAssociation: ${org.official_name} (${org.address})\nPreliminary intake - identity evidence to be provided at review.`;

      const payload = JSON.stringify([org.id, position, authorizationStatement]);
      if (submissionAttempt.current?.payload !== payload) submissionAttempt.current = { payload, key: crypto.randomUUID() };
      const submissionKey = submissionAttempt.current.key;
      const response = await request(`/api/fca/applications/preliminary`, {
        method: 'POST',
        body: {
          fca_id: org.id,
          representative_position: position,
          authorization_statement: authorizationStatement,
          submission_key: submissionKey,
        },
      });

      const data = (response as any)?.data;
      if (data?.id) {
        setSubmitted({ reference: data.id, status: data.status || 'pending' });
        setStep(4 as Step);
      } else {
        setNotice(tr('Submission did not return a reference number. Please try again.', 'Ang pagsumite ay hindi nagbalik ng numero ng reperensya. Pakisubukang muli.'));
      }
    } catch (e: any) {
      setNotice(tr('Submission could not be completed. Please refresh and try again.', 'Hindi ma-complete ang pagsumite. I-refresh at subukang muli.'));

    } finally {
      setSubmitting(false);
    }
  };

  return (
    <main className="min-h-screen overflow-x-hidden bg-background">
      <div className="mx-auto w-full max-w-3xl px-4 py-6 sm:px-6 sm:py-10">
        <header className="mb-6 flex flex-wrap items-center justify-between gap-3">
          <Link to="/register" className="inline-flex min-w-0 items-center gap-1 text-sm font-semibold text-foreground hover:underline">
            <ArrowLeft className="h-4 w-4 shrink-0" aria-hidden="true" />
            <span className="min-w-0 break-words">
              {tr('Individual Farmer registration', 'Pagpaparehistro ng indibidwal na magsasaka')}
            </span>
          </Link>
          <LanguageSwitcher />
        </header>

        {submitted ? (
          /* SUBMISSION CONFIRMATION */
          <div className="min-w-0 rounded-2xl border-2 border-[#D8E4E8] bg-card p-4 shadow-sm sm:p-7 text-center">
            <span className="mt-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#E7F4F2] text-[#0F9488] mx-auto mb-4">
              <CheckCircle2 className="h-6 w-6" aria-hidden="true" />
            </span>
            <h1 className="break-words text-xl font-black leading-tight text-foreground sm:text-2xl">
              {tr('Registration Request Received', 'Natanggap ang Hiling ng Pagpaparehistro')}
            </h1>
            <p className="mt-3 break-words text-sm leading-relaxed text-muted-foreground">
              {tr(
                'Your request has been recorded and is pending verification. Association membership and representative authority have not yet been confirmed.',
                'Naitala na ang iyong hiling at naghihintay ng beripikasyon. Hindi pa kumpirmado ang kabisaan sa samahan at ang awtoridad na kumatawan.',
              )}
            </p>
            <div className="mt-6 rounded-xl border border-border bg-background p-4">
              <p className="text-xs font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                {tr('Reference Number', 'Numero ng Reperensya')}
              </p>
              <p className="mt-1 text-xl font-bold text-foreground">{submitted.reference}</p>
              <p className="mt-1 text-sm text-muted-foreground">
                {tr(`Status: ${submitted.status}`, `Kataguan: ${submitted.status}`)}
              </p>
            </div>
            <p className="mt-4 text-sm text-muted-foreground">
              {tr(
                'An authorized reviewer will evaluate your request. You will not be contacted about this submission through this page.',
                'Suriin ng isang awtorisadong nauna ang iyong hiling. Hindi ka makakasagot tungkol sa sumisumiting ito sa pahina na ito.',
              )}
            </p>
            <div className="mt-6">
              <Link to="/register" className="inline-flex items-center gap-1 text-sm font-semibold text-foreground hover:underline">
                <ArrowLeft className="h-4 w-4 shrink-0" aria-hidden="true" />
                <span>{tr('Return to registration', 'Bumalik sa pagpaparehistro')}</span>
              </Link>
            </div>
          </div>
        ) : (
          /* REGISTRATION FORM */
          <div className="min-w-0 rounded-2xl border-2 border-[#D8E4E8] bg-card p-4 shadow-sm sm:p-7">
            <div className="flex min-w-0 items-start gap-3">
              <span className="mt-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#E7F4F2] text-[#0F9488]">
                <ShieldCheck className="h-6 w-6" aria-hidden="true" />
              </span>
              <div className="min-w-0 flex-1">
                <h1 className="break-words text-xl font-black leading-tight text-foreground sm:text-2xl">
                  {tr('FCA President / Authorized Representative', 'Pangulo / Awtorisadong Kinatawan ng FCA')}
                </h1>
                <p className="mt-1 break-words text-sm leading-relaxed text-muted-foreground">
                  {tr(
                    'Register to represent an association or cooperative. Selecting an association does not verify you, and approval is decided by an administrator after review.',
                    'Magrehistro upangrepresentahan ang isang samahan o kooperatiba. Ang pagpili ng samahan ay hindi beripikado sa iyo, at ang pag-apruba ay pasyahan ng administrator.',
                  )}
                </p>
              </div>
            </div>

            {/* Step indicator */}
            <ol className="mt-6 grid min-w-0 grid-cols-2 gap-2 sm:grid-cols-4" aria-label={tr('Registration steps', 'Mga hakbang sa pagpaparehistro')}>
              {steps.map((s) => {
                const state = s.id === step ? 'current' : s.id < step ? 'done' : 'todo';
                return (
                  <li key={s.id}
                    className={`min-w-0 rounded-xl border px-2.5 py-2 text-xs font-semibold ${

                    state === 'current'
                      ? 'border-[#0F9488] bg-[#F0FDFA] text-foreground'
                      : state === 'done'
                        ? 'border-[#D8E4E8] bg-background text-[#0F766E]'
                        : 'border-[#E7EDF0] bg-card text-[#8A9AA5]'
                  }`}
                    aria-current={state === 'current' ? 'step' : undefined}>
                    <span className="block text-[10px] uppercase tracking-wide opacity-70">{s.id} / 4</span>
                    <span className="block break-words">{s.label}</span>
                  </li>
                );
              })}
            </ol>

            <div className="mt-6 min-w-0 space-y-5">
              {/* STEP 1 */}
              {step === 1 && (
                <section className="space-y-3">
                  <AssociationAutocomplete value={org} onChange={setOrg} />
                  {problems.org && <p role="alert" className="text-sm font-semibold text-red-700">{problems.org}</p>}
                  {org && (
                    <p className="rounded-lg border border-[#D8E4E8] bg-background p-3 text-xs leading-relaxed text-muted-foreground">
                      {tr(
                        'Selecting this association confirms only that it exists in the supplied directory. It does not confirm the association, and it does not verify your identity or your authority to represent it.',
                        'Ang pagpili nito ay kumpirmado lamang na umiiral sa ibinigay na direktoryo. Hindi nito kumpirmado ang samahan, at hindi beripikado ang iyong pagkakakilanlan o awtoridad na kumatawan.',
                      )}
                    </p>
                  )}
                </section>
              )}

              {/* STEP 2 */}
              {step === 2 && (
                <section className="space-y-4">
                  <label className="block">
                    <span className="text-sm font-semibold text-foreground">{tr('Full name', 'Buong pangalan')}</span>
                    <input value={name} maxLength={255} autoComplete="name" onChange={(e) => setName(e.target.value)}
                      className="mt-1 w-full rounded-xl border-2 border-[#D8E4E8] px-3 py-2.5 outline-none focus:border-[#0F9488]" />
                    {problems.name && <span className="mt-1 block text-xs font-semibold text-red-700">{problems.name}</span>}
                  </label>
                  <label className="block">
                    <span className="text-sm font-semibold text-foreground">{tr('Contact number', 'Numero ng telepono')}</span>
                    <input value={phone} type="tel" maxLength={40} onChange={(e) => setPhone(e.target.value)}
                      className="mt-1 w-full rounded-xl border-2 border-[#D8E4E8] px-3 py-2.5 outline-none focus:border-[#0F9488]" />
                    {problems.phone && <span className="mt-1 block text-xs font-semibold text-red-700">{problems.phone}</span>}
                  </label>
                  <label className="block">
                    <span className="text-sm font-semibold text-foreground">{tr('Claimed role', 'Tungkuling hinahangal')}</span>
                    <select value={position} onChange={(e) => setPosition(e.target.value as 'president' | 'delegated')}
                      className="mt-1 w-full rounded-xl border-2 border-[#D8E4E8] bg-card px-3 py-2.5 outline-none focus:border-[#0F9488]">
                      <option value="president">{tr('FCA President', 'Pangulo ng FCA')}</option>
                      <option value="delegated">{tr('Authorized Representative', 'Awtorisadong Kinatawan')}</option>
                    </select>
                  </label>
                  <p className="text-xs text-muted-foreground">
                    {tr(
                      'A claimed role is what you assert, not what HarborAI has confirmed. An administrator verifies it separately.',
                      'Ang hinahangal na tungkulin ay iyong ipinapangako, hindi ang kinukumpirma ng HarborAI. Hiwalay itong susuriin ng administrator.',
                    )}
                  </p>
                </section>
              )}

              {/* STEP 3 */}
              {step === 3 && (
                <section className="space-y-3">
                  <h2 className="text-sm font-bold uppercase tracking-wide text-foreground">
                    {tr('Privacy Notice', 'Patakaran sa Privacy')}
                  </h2>
                  <div className="rounded-xl border border-[#D8E4E8] bg-background p-4 text-sm leading-relaxed text-muted-foreground">
                    <p>
                      {tr(
                        'Your name, contact number and claimed role are collected solely for the purpose of reviewing this FCA registration request. These details are not published and are accessible only to authorized FCA reviewers.',
                        'Ikinokolehita lamang ang iyong pangalan, numero ng telepono at hinahangal na tungkulin layuning suriin ang hiling na ito ng pagpaparehistro ng FCA. Hindi ito nakapublish at maaaring ma-access lamang ng awtorisadong nag-aalis ng FCA.',
                      )}
                    </p>
                    <p>
                      {tr(
                        'An identity document is not required at this stage. If an approved evidence-handling policy is in place, a reviewer may request it separately before authorizing your request.',
                        'Kasalukuyang hindi kinakailangan ang isang dokumento ng pagkakakilanlan sa puntong ito. Kung may aprubadong patakaran sa paghawak ng ebidensya, maaaring humiling sa iyo ng isang nauna sa pagsusuri bago i-apruba ang iyong hiling.',
                      )}
                    </p>
                  </div>
                  <label className="flex items-start gap-2 rounded-xl border border-[#D8E4E8] p-3 text-sm text-muted-foreground">
                    <input type="checkbox" checked={privacy} onChange={(e) => setPrivacy(e.target.checked)} className="mt-1" />
                    <span>
                      {tr(
                        'I have read and understand this privacy notice.',
                        'Nabasa ko at nauunawaan ang paalala sa privacy na ito.',
                      )}
                    </span>
                  </label>
                </section>
              )}

              {/* STEP 4 */}
              {step === 4 && (
                <section className="space-y-4">
                  <label className="flex items-start gap-2 rounded-xl border border-[#D8E4E8] p-3 text-sm text-muted-foreground">
                    <input type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)} className="mt-1" />
                    <span>
                      {tr(
                        'I explicitly consent to my submitted claim being reviewed by an administrator.',
                        'Pumapayag ako na suriin ng administrator ang aking isinumiteng pag-claim.',
                      )}
                    </span>
                  </label>

                  {!consent || !privacy ? (
                    <p className="text-sm font-semibold text-[#B45309]">
                      {tr('Both confirmations are required before anything can be sent.', 'Kailangan ang dalawang kumpirmasyon bago ipadala ang anumang bagay.')}
                    </p>
                  ) : null}

                  <button
                    type="button"
                    disabled={submitting || !consent || !privacy}
                    onClick={handleSubmit}
                    className={`w-full rounded-xl px-4 py-3 font-bold text-white transition sm:w-auto ${
                      !consent || !privacy
                        ? 'bg-[#B45309] disabled:cursor-not-allowed disabled:opacity-50'
                        : 'bg-[#0F9488] hover:bg-[#0B7C72]'
                    }`}>
                    {submitting ? tr('Submitting…', 'Ipinapadala…') : tr('Submit for administrative review', 'Isumite para sa pagsusuri')}
                  </button>

                  {notice && (
                    <div role="alert" className="flex items-start gap-2 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                      <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                      <span>{notice}</span>
                    </div>
                  )}
                </section>
              )}
            </div>

            {/* Navigation */}
            <div className="mt-6 flex min-w-0 flex-wrap items-center justify-between gap-3 border-t border-[#E7EDF0] pt-5">
              <button type="button" onClick={() => setStep((s) => (Math.max(1, s - 1) as Step))}
                disabled={step === 1}
                className="shrink-0 rounded-xl border-2 border-[#D8E4E8] px-4 py-2.5 text-sm font-semibold text-foreground disabled:opacity-40">
                {tr('Back', 'Bumalik')}
              </button>
              {step < 4 ? (
                <button type="button" onClick={next} disabled={!canContinue}
                  className="shrink-0 rounded-xl bg-[#123C5C] px-5 py-2.5 text-sm font-bold text-white transition hover:bg-[#0E314B] disabled:opacity-40">
                  {tr('Continue', 'Magpatuloy')}
                </button>
              ) : (
                <span className="text-xs text-muted-foreground">{tr('Step 4 of 4', 'Hakbang 4 sa 4')}</span>
              )}
            </div>
          </div>
        )}

        <details className="mt-4 min-w-0 rounded-2xl border-2 border-[#D8E4E8] bg-card p-4">
          <summary className="cursor-pointer text-sm font-semibold text-foreground">
            {tr('Existing account: restricted FCA workflow', 'May account na: limitadong FCA workflow')}
          </summary>
          <div className="mt-3">
            <FcaManualOnboarding />
          </div>
        </details>
      </div>
    </main>
  );
}