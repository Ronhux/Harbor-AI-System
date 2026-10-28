import { useState, useEffect } from 'react';
import { Link, Navigate, useNavigate } from 'react-router-dom';
import { ArrowLeft, ChevronRight, Clock, ShieldAlert } from 'lucide-react';
import { request } from '../../lib/api';
import { useLanguage } from '../i18n/useLanguage';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '../components/ui/card';

/**
 * FCA Authorized Review page.
 *
 * Authorized reviewers (fca_reviewer_user_ids) can:
 * - View pending preliminary registration requests
 * - Inspect submitted applicant information
 * - Record authorize / reject / revoke decisions
 * - Preserve review history
 *
 * A directory match is NOT proof of representative authority.
 */

export default function FcaReviewPage() {
  const navigate = useNavigate();
  const { lang, t } = useLanguage();
  const tr = (en: string, fil: string) => (lang === 'fil' ? fil : en);

  const [applications, setApplications] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [reviewing, setReviewing] = useState<number | null>(null);
  const [selected, setSelected] = useState<any>(null);

  useEffect(() => {
    let active = true;
    async function load() {
      try {
        const page = await request('/api/fca/review/pending');
        if (active) setApplications(page?.data ?? []);
      } catch (e: any) {
        if (active) setError(e.message || 'Unable to load pending requests.');
      } finally {
        if (active) setLoading(false);
      }
    }
    void load();
    return () => { active = false; };
  }, []);

  const handleDecision = async (appId: number, decision: 'authorize' | 'reject' | 'revoke', reason: string) => {
    setReviewing(appId);
    try {
      await request(`/api/fca/review/${appId}`, {
        method: 'POST',
        body: {
          decision_key: crypto.randomUUID(),
          expected_version: 0,
          decision,
          review_reason: reason,
        },
      });
      setSelected(null);
      setApplications(applications.filter((a) => a.id !== appId));
    } catch (e: any) {
      setError(e.message || 'Decision could not be recorded.');
    } finally {
      setReviewing(null);
    }
  };

  const formatDate = (value: string | null): string => {
    if (!value) return t('pricing.notAvailable');
    return new Date(value).toLocaleString('en-PH', { timeZone: 'Asia/Manila' });
  };

  if (loading) return <div className="min-h-screen flex items-center justify-center p-4">{tr('Loading…', 'Naloload…')}</div>;

  if (error) return (
    <div className="min-h-screen flex items-center justify-center p-4">
      <div className="text-center">
        <ShieldAlert className="mx-auto h-12 w-12 text-red-600 mb-3" />
        <h2 className="text-xl font-bold text-foreground">{tr('Unable to load requests', 'Hindi ma-load ang mga hiling')}</h2>
        <p className="text-sm text-muted-foreground">{error}</p>
        <Button onClick={() => navigate('/admin')} variant="outline">{tr('Back to Admin', 'Bumalik sa Admin')}</Button>
      </div>
    </div>
  );

  return (
    <main className="min-h-screen overflow-x-hidden bg-background">
      <div className="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6 sm:py-10">
        <header className="mb-6 flex flex-wrap items-center justify-between gap-3">
          <Link to="/admin" className="inline-flex min-w-0 items-center gap-1 text-sm font-semibold text-foreground hover:underline">
            <ArrowLeft className="h-4 w-4 shrink-0" aria-hidden="true" />
            <span className="min-w-0 break-words">{tr('Admin Control Panel', 'Panel ng Kontrol ng Admin')}</span>
          </Link>
        </header>

        <div className="min-w-0 rounded-2xl border-2 border-[#D8E4E8] bg-card p-4 shadow-sm sm:p-7">
          <div className="flex min-w-0 items-start justify-between gap-3 mb-6">
            <div className="min-w-0">
              <h1 className="break-words text-xl font-black leading-tight text-foreground sm:text-2xl">
                {tr('FCA Authorized Review', 'Awtorisadong Pagsusuri ng FCA')}
              </h1>
              <p className="mt-1 break-words text-sm leading-relaxed text-muted-foreground">
                {tr(
                  'Review pending preliminary registration requests. A directory match confirms an association exists; it does not verify representative authority.',
                  'Suriin ang mga hiling sa pagpaparehistrong unaagang nakahanda. Isang pagtugma sa direktoryo ay kumpirmado lamang na umiiral ang samahan; hindi ito patunay ng awtoridad na kumatawan.',
                )}
              </p>
            </div>
          </div>

          {error && (
            <div role="alert" className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
              {error}
            </div>
          )}

          {applications.length === 0 ? (
            <div className="text-center py-12">
              <Clock className="mx-auto h-12 w-12 text-muted-foreground mb-3" />
              <h3 className="text-lg font-semibold text-foreground">{tr('No pending requests', 'Walang naghihintay na hiling')}</h3>
              <p className="mt-1 text-sm text-muted-foreground">
                {tr(
                  'All preliminary FCA registration requests have been reviewed. New submissions will appear here.',
                  'Suriin na ang lahat ng mga hiling sa pagpaparehistrong unaagang FCA. Magpaparating ang mga bagong hiling sa pagdoro.',
                )}
              </p>
            </div>
          ) : (
            <div className="space-y-3">
              {applications.map((app) => (
                <Card key={app.id} className="border-border">
                  <CardHeader>
                    <div className="flex items-center justify-between gap-3">
                      <div className="min-w-0 flex-1">
                        <CardTitle className="text-lg font-bold text-foreground">
                          {tr('Application #', 'Aplikasyon #')} {app.id}
                        </CardTitle>
                        <p className="mt-1 text-sm text-muted-foreground">
                          {tr('Association', 'Samahan')}: {app.submitted_details?.fca_name ?? '—'}
                        </p>
                      </div>
                      <span className="rounded-full border px-2 py-0.5 text-xs font-semibold border-border text-muted-foreground">
                        {tr(app.status, app.status)}
                      </span>
                    </div>
                  </CardHeader>
                  <CardContent className="space-y-3">
                    <div className="grid gap-3 sm:grid-cols-2">
                      <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.06em] text-muted-foreground">
                          {tr('Applicant name', 'Pangalan ng aplikante')}
                        </p>
                        <p className="mt-1 text-sm text-foreground">{app.submitted_details?.applicant_name ?? '—'}</p>
                      </div>
                      <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.06em] text-muted-foreground">
                          {tr('Contact number', 'Numero ng telepono')}
                        </p>
                        <p className="mt-1 text-sm text-foreground">{app.submitted_details?.contact_number ?? '—'}</p>
                      </div>
                      <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.06em] text-muted-foreground">
                          {tr('Claimed role', 'Tungkuling hinahangal')}
                        </p>
                        <p className="mt-1 text-sm text-foreground">{app.submitted_details?.claimed_role ?? '—'}</p>
                      </div>
                      <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.06em] text-muted-foreground">
                          {tr('Submitted', 'Naipadala')}
                        </p>
                        <p className="mt-1 text-sm text-foreground">{formatDate(app.created_at)}</p>
                      </div>
                    </div>
                    <div>
                      <p className="text-xs font-semibold uppercase tracking-[0.06em] text-muted-foreground">
                        {tr('Authorization statement', 'Pahayag ng awtorisasyon')}
                      </p>
                      <p className="mt-1 text-sm text-muted-foreground" style={{ whiteSpace: 'pre-wrap' }}>
                        {app.submitted_details?.authorization_statement ?? '—'}
                      </p>
                    </div>
                    <p className="text-xs text-muted-foreground">
                      {tr(
                        'Directory match is not proof of presidency. Verify identity and authority through your established process before authorizing.',
                        'Ang pagtugma sa direktoryo ay hindi patunay ng pangulo. Beripikahin ang pagkakakilanlan at awtoridad sa pamamagitan ng iyong itinatanging proseso bago i-apruba.',
                      )}
                    </p>
                  </CardContent>
                  <CardFooter className="flex flex-wrap gap-2">
                    <Button
                      onClick={() => setSelected(app)}
                      disabled={reviewing === app.id}
                      variant="outline"
                      className="text-sm text-[#0F9488] border-[#0F9488] hover:bg-[#0F9488]/10">
                      {tr('View details', 'Tingnan ang detalye')}
                    </Button>
                    {reviewing === app.id ? (
                      <span className="text-xs text-muted-foreground">{tr('Recording…', 'Naitala…')}</span>
                    ) : (
                      <>
                        <Button
                          onClick={() => handleDecision(app.id, 'authorize', tr('Authorized after review.', 'Na-apruba pagkatapos ng pagsusuri.'))}
                          disabled={reviewing !== null}
                          className="text-sm bg-[#0F9488] text-white hover:bg-[#0B7C72]">
                          {tr('Authorize', 'I-apruba')}
                        </Button>
                        <Button
                          onClick={() => handleDecision(app.id, 'reject', tr('Rejected after review.', 'Itinanggapi pagkatapos ng pagsusuri.'))}
                          disabled={reviewing !== null}
                          variant="outline"
                          className="text-sm border-red-200 text-red-700 hover:bg-red-50">
                          {tr('Reject', 'Itanggapi')}
                        </Button>
                      </>
                    )}
                  </CardFooter>
                </Card>
              ))}
            </div>
          )}
        </div>

        {selected && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-md rounded-xl border bg-card p-5 shadow-lg">
              <CardHeader>
                <CardTitle className="text-lg font-bold text-foreground">{tr('Application Details', 'Detalye ng Aplikasyon')}</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <p className="text-sm text-muted-foreground">
                  <strong>{tr('Association', 'Samahan')}:</strong> {selected.submitted_details?.fca_name ?? '—'}
                </p>
                <p className="text-sm text-muted-foreground">
                  <strong>{tr('Applicant', 'Aplikante')}:</strong> {selected.submitted_details?.applicant_name ?? '—'}
                </p>
                <p className="text-sm text-muted-foreground">
                  <strong>{tr('Contact', 'Kontak')}:</strong> {selected.submitted_details?.contact_number ?? '—'}
                </p>
                <p className="text-sm text-muted-foreground">
                  <strong>{tr('Claimed role', 'Tungkuling hinahangal')}:</strong> {selected.submitted_details?.claimed_role ?? '—'}
                </p>
                <p className="text-sm text-muted-foreground">
                  <strong>{tr('Statement', 'Pahayag')}:</strong>
                  <br />
                  <span className="block mt-1 text-xs text-muted-foreground" style={{ whiteSpace: 'pre-wrap' }}>
                    {selected.submitted_details?.authorization_statement ?? '—'}
                  </span>
                </p>
              </CardContent>
              <CardFooter className="flex justify-end gap-2">
                <Button variant="outline" onClick={() => setSelected(null)}>{tr('Close', 'Isara')}</Button>
              </CardFooter>
            </div>
          </div>
        )}
      </div>
    </main>
  );
}