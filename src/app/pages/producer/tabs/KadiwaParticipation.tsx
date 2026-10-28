import { useLanguage } from '../../../i18n/useLanguage';
import { Store } from 'lucide-react';
import { KadiwaFarmer } from '../../../components/mao/KadiwaIntegration';
import { createKadiwaApi, KADIWA_INTEGRATION_ENABLED } from '../../../components/mao/kadiwaApi';

/**
 * Producer KADIWA participation.
 *
 * Reads real /api/kadiwa/* endpoints through the shared transport. While the KADIWA
 * feature flag is false no request is sent and the component states plainly that the
 * integration is not enabled. Nothing here is sampled or pre-filled: the event list,
 * participation status and review reasons come only from the server.
 */
export default function KadiwaParticipation() {
  const {lang}=useLanguage();
  return (
    <div className="space-y-4">
      <header className="space-y-1">
        <h1 className="flex items-center gap-2 text-2xl font-bold text-foreground">
          <Store aria-hidden="true" className="h-6 w-6" />
          {lang==='fil'?'Mga pagkakataong magbenta sa KADIWA':'KADIWA Selling Opportunities'}
        </h1>
        <p className="text-muted-foreground">
          {lang==='fil'?'Tingnan ang mga event at pamahalaan ang iyong paglahok.':'Explore published events and manage your participation.'}
        </p>
      </header>
      <KadiwaFarmer api={createKadiwaApi()} enabled={KADIWA_INTEGRATION_ENABLED} />
    </div>
  );
}