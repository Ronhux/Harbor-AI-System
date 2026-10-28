import { useEffect, useState } from 'react';
import { Store } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../components/ui/card';
import { createKadiwaApi, KADIWA_INTEGRATION_ENABLED, KadiwaApiError } from '../../../components/mao/kadiwaApi';
import { KadiwaManagement } from '../../../components/mao/KadiwaIntegration';
import type { KadiwaEventRead } from '../../../components/mao/kadiwaPreviewModel';

/**
 * Program Management.
 *
 * Earlier versions of this page printed hard-coded applicant, approval and budget
 * figures for three programs. Those numbers were invented, so they are gone.
 *
 * KADIWA figures below come from the real /api/kadiwa/* endpoints only. Sagip Saka and
 * LGU Direct Procurement have no connected data source yet and therefore show no
 * numbers at all - a zero with a "no data source" label, never a placeholder count.
 */

interface ProgrammeRow {
  id: string;
  name: string;
  note: string;
  /** Present only when a real endpoint backs this programme. */
  live?: boolean;
  events?: KadiwaEventRead[];
}

const api = createKadiwaApi();
const KADIWA_ENABLED = KADIWA_INTEGRATION_ENABLED;

function ProgrammeCard({ programme }: { programme: ProgrammeRow }) {
  return (
    <Card className="border-2 border-[#E7E1D0]">
      <CardHeader>
        <CardTitle className="text-lg">{programme.name}</CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
        <p className="text-sm text-[#45586B]">{programme.note}</p>

        {programme.live ? (
          programme.events && programme.events.length > 0 ? (
            <div className="space-y-2">
              <p className="text-sm">
                <span className="font-bold text-[#123C5C]">{programme.events.length}</span> published event
                {programme.events.length === 1 ? '' : 's'}
              </p>
              <ul className="space-y-1 text-sm text-[#45586B]">
                {programme.events.slice(0, 5).map((event) => (
                  <li key={event.id}>
                    {event.title} - {event.status}
                  </li>
                ))}
              </ul>
            </div>
          ) : (
            <p className="text-sm text-[#45586B]">No published events. Nothing is counted yet.</p>
          )
        ) : (
          <div className="grid grid-cols-3 gap-4 text-center">
            <div>
              <div className="text-2xl font-bold text-[#123C5C]">-</div>
              <div className="text-xs text-[#45586B]">Applicants</div>
            </div>
            <div>
              <div className="text-2xl font-bold text-[#15803D]">-</div>
              <div className="text-xs text-[#45586B]">Approved</div>
            </div>
            <div>
              <div className="text-2xl font-bold text-[#F59E0B]">-</div>
              <div className="text-xs text-[#45586B]">Pending</div>
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

export default function ProgramManagement() {
  const [events, setEvents] = useState<KadiwaEventRead[] | null>(null);
  const [kadiwaError, setKadiwaError] = useState<string | null>(null);

  useEffect(() => {
    if (!KADIWA_ENABLED) return;
    let active = true;
    api
      .events()
      .then((rows) => {
        if (active) setEvents(rows);
      })
      .catch((error: unknown) => {
        if (!active) return;
        // A disabled integration is not an error state; it has no figures to show.
        if (!(error instanceof KadiwaApiError) || error.status !== 404) {
          setKadiwaError(
            error instanceof Error ? error.message : 'KADIWA figures are unavailable. No data is shown.',
          );
        }
      });
    return () => {
      active = false;
    };
  }, []);

  const programmes: ProgrammeRow[] = [
    {
      id: 'kadiwa',
      name: 'Kadiwa Program',
      note: 'Direct producer-to-customer selling at published venues. Not an institutional buyer, procurement award, order or payment service.',
      live: true,
      ...(events ? { events } : {}),
    },
    {
      id: 'sagip-saka',
      name: 'Sagip Saka Act (RA 11321)',
      note: 'No connected data source. No applicant, approval or budget figure is available, so none is displayed.',
    },
    {
      id: 'lgu-direct',
      name: 'LGU Direct Procurement',
      note: 'No connected data source. No applicant, approval or budget figure is available, so none is displayed.',
    },
  ];

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="font-display text-2xl sm:text-3xl text-[#123C5C] tracking-tight">Program Management</h1>
        <p className="text-[#45586B] mt-1">
          Figures are read from live records. A programme with no connected data source shows no numbers.
        </p>
      </div>

      {kadiwaError && (
        <p role="alert" className="rounded border border-[#E7E1D0] bg-white p-3 text-sm text-[#B45309]">
          {kadiwaError}
        </p>
      )}

      <div className="grid md:grid-cols-2 gap-6">
        {programmes.map((programme) => (
          <ProgrammeCard key={programme.id} programme={programme} />
        ))}
      </div>

      <section className="space-y-3 rounded border-2 border-[#E7E1D0] bg-white p-4">
        <h2 className="flex items-center gap-2 text-lg font-semibold text-[#123C5C]">
          <Store aria-hidden="true" className="h-5 w-5" />
          KADIWA event management
        </h2>
        <KadiwaManagement api={api} enabled={KADIWA_ENABLED} />
      </section>
    </div>
  );
}