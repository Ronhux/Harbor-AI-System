import { DashboardText, useDashboardCopy } from '../../i18n/DashboardCopy';
import { toast } from 'sonner';
import { logoutSession } from '../../../lib/authStorage';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useLanguage } from '../../i18n/useLanguage';
import LanguageSwitcher from '../../i18n/LanguageSwitcher';
import {
  Home,
  ShoppingBag,
  Package,
  TrendingUp,
  Sprout,
  Target,
  Handshake,
  User,
  Store,
} from 'lucide-react';
import DashboardShell from '../../components/dashboard/DashboardShell';
import DashboardHome from './tabs/DashboardHome';
import MyListings from './tabs/MyListings';
import Orders from './tabs/Orders';
import PriceInsights from './tabs/PriceInsights';
import Negotiations from '../shared/Negotiations';
import DemandOpportunities from './tabs/DemandOpportunities';
import KadiwaParticipation from './tabs/KadiwaParticipation';
import MyProfile from './tabs/MyProfile';
import { PolicyAcceptanceGate } from '../../components/PolicyAcceptanceGate';
import { ProducerApprovalGate } from '../../components/ProducerApprovalGate';

/**
 * Modules kept in the mobile bottom bar. Every remaining module stays
 * reachable through the shell's "More" menu, so no destination is lost
 * while the bar itself stays comfortably tappable.
 */
const PRIMARY_MOBILE_TABS = ['dashboard', 'listings', 'orders', 'insights'];

export default function ProducerDashboard() {
  const copy = useDashboardCopy();
  const navigate = useNavigate();
  const { t } = useLanguage();
  const [activeTab, setActiveTab] = useState('dashboard');
  const [activeNavigationId, setActiveNavigationId] = useState('dashboard');
  const [focusAssistant, setFocusAssistant] = useState(false);
  const userName = localStorage.getItem('userName') || 'Producer';

  const handleLogout = async () => {
    try { await logoutSession(); navigate('/login'); }
    catch { toast.error('Logout could not be completed. Please try again.'); }
  };

  /**
   * The registry-aligned producer modules. Removed or obsolete modules
   * (Program Eligibility, Program Management, Policy Matchmaker) are not
   * re-registered here; the Product Assistant is surfaced as a navigation
   * entry that focuses the assistant inside My Digital Stall.
   */
  const handleTabChange = (id: string) => {
    // Older dashboard shortcuts that pointed at a consolidated insights
    // view resolve to the live Market & Pricing Insights module.
    if (id === 'recommendations') id = 'insights';

    setActiveNavigationId(id);

    if (id === 'product-assistant') {
      setFocusAssistant(true);
      setActiveTab('listings');
      return;
    }

    setFocusAssistant(false);
    setActiveTab(id);
  };

  const tabs = [
    { id: 'dashboard', label: t('producer.dashboard'), icon: Home },
    { id: 'listings', label: t('producer.digitalStall'), icon: ShoppingBag },
    { id: 'insights', label: t('producer.smartPricingPrompts'), icon: TrendingUp },
    { id: 'negotiations', label: t('producer.negotiations'), icon: Handshake },
    { id: 'demand-opportunities', label: t('producer.demandOpportunities'), icon: Target },
    { id: 'orders', label: t('producer.orders'), icon: Package },
    { id: 'kadiwa', label: t('producer.kadiwa'), icon: Store },
    { id: 'profile', label: t('producer.profile'), icon: User },
  ];

  /* Desktop sidebar grouping. Ids match the tab ids above, so the shell
     highlights the active module without extra state. The Product Assistant
     entry scrolls to the in-form assistant inside My Digital Stall. */
  const navSections = [
    {
      label: t('nav.overview'),
      items: [{ id: 'dashboard', label: t('producer.dashboard'), icon: Home }],
    },
    {
      label: t('nav.marketIntelligence'),
      items: [
        {
          id: 'product-assistant',
          label: t('nav.productAssistant'),
          icon: Sprout,
          tabId: 'listings',
          anchorId: 'product-assistant',
        },
        { id: 'insights', label: t('nav.smartPricingPrompts'), icon: TrendingUp },
      ],
    },
    {
      label: t('nav.myBusiness'),
      items: [
        { id: 'listings', label: t('producer.digitalStall'), icon: ShoppingBag },
        { id: 'negotiations', label: t('producer.negotiations'), icon: Handshake },
        { id: 'demand-opportunities', label: t('producer.demandOpportunities'), icon: Target },
        { id: 'orders', label: t('producer.orders'), icon: Package },
        { id: 'kadiwa', label: t('producer.kadiwa'), icon: Store },
      ],
    },
    {
      label: t('nav.account'),
      items: [{ id: 'profile', label: t('producer.profile'), icon: User }],
    },
  ];

  const renderContent = () => {
    switch (activeTab) {
      case 'dashboard':
        return <DashboardHome onTabChange={handleTabChange} />;
      case 'recommendations':
        return <PriceInsights />;
      case 'listings':
        return <MyListings focusAssistant={focusAssistant} />;
      case 'orders':
        return <Orders />;
      case 'insights':
        return <PriceInsights />;
      case 'negotiations':
        return (
          <Negotiations
            side="producer"
            title={copy("Negotiation Requests")}
            icon={Handshake}
          />
        );
      case 'demand-opportunities':
        return <DemandOpportunities />;
      case 'kadiwa':
        return <KadiwaParticipation />;
      case 'profile':
        return <MyProfile />;
      default:
        return <DashboardHome onTabChange={handleTabChange} />;
    }
  };

  return (
    <PolicyAcceptanceGate areaLabel={copy("producer dashboard")}>
      <ProducerApprovalGate>
        <DashboardShell
          title={t('producer.dashboard')}
          subtitle={t('producer.dashboardSub') || copy("Performance at listings ng enterprise")}
          tabs={tabs}
          navSections={navSections}
          primaryMobileTabIds={PRIMARY_MOBILE_TABS}
          activeTab={activeTab}
          activeNavigationId={activeNavigationId}
          onTabChange={handleTabChange}
          userName={userName}
          userRole="Producer"
          workspaceLabel={copy("Producer Workspace")}
          showSearch
          searchPlaceholder={t('producer.searchPlaceholder') || t('nav.searchPlaceholder')}
          onLogout={handleLogout}
        >
          {renderContent()}
        </DashboardShell>
      </ProducerApprovalGate>
    </PolicyAcceptanceGate>
  );
}
