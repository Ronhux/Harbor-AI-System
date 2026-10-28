import { DashboardText, useDashboardCopy } from '../../i18n/DashboardCopy';
import { toast } from 'sonner';
import { logoutSession } from '../../../lib/authStorage';
import { useEffect, useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import {
  LayoutDashboard,
  ShoppingCart,
  ShoppingBag,
  FileText,
  Handshake,
  Package,
  TrendingUp,
  User,
} from 'lucide-react';
import DashboardShell from '../../components/dashboard/DashboardShell';
import Overview from './tabs/Overview';
import BrowseProducers from './tabs/BrowseProducers';
import BrowseProducts from './tabs/BrowseProducts';
import PostDemand from './tabs/PostDemand';
import Orders from './tabs/Orders';
import Negotiations from '../shared/Negotiations';
import PriceTrends from './tabs/PriceTrends';
import Profile from './tabs/Profile';
import { PolicyAcceptanceGate } from '../../components/PolicyAcceptanceGate';
import { request } from '../../../lib/api';
import { useLanguage } from '../../i18n/useLanguage';

type ProposalDraft = { quantity: number; deliveryDate?: string; deliveryArrangement?: string };

/** Destinations kept in the mobile dock. Everything else stays in "More". */
const PRIMARY_MOBILE_TAB_IDS = ['overview', 'browse', 'products', 'orders'];

export default function BuyerDashboard() {
  const copy = useDashboardCopy();
  const navigate = useNavigate();
  const { t } = useLanguage();
  const [activeTab, setActiveTab] = useState('overview');
  const [activeNavigationId, setActiveNavigationId] = useState('overview');
  const [pendingOrder, setPendingOrder] = useState<{ producerId: number; listingId: number; shippingAddress?: string } | null>(null);
  const [pendingProductListingId, setPendingProductListingId] = useState<number | null>(null);
  const [pendingProposalDraft, setPendingProposalDraft] = useState<ProposalDraft | null>(null);

  /*
   * Identity comes from the authenticated buyer profile, so the dashboard does
   * not hardcode an organization or contact name. The profile route may be
   * unavailable on some deployments; the UI then falls back to the session name
   * instead of inventing account details.
   */
  const [buyerIdentity, setBuyerIdentity] = useState<{ organization_name?: string; contact_person?: string } | null>(null);

  const handleLogout = async () => {
    try { await logoutSession(); navigate('/login'); }
    catch { toast.error('Logout could not be completed. Please try again.'); }
  };

  useEffect(() => {
    const controller = new AbortController();

    const loadIdentity = async () => {
      try {
        const result = await request('/api/buyer/profile', { signal: controller.signal });
        setBuyerIdentity({
          organization_name: result?.data?.organization_name,
          contact_person: result?.data?.contact_person,
        });
      } catch {
        if (controller.signal.aborted) return;
        setBuyerIdentity(null);
      }
    };

    if (localStorage.getItem('userRole') === 'buyer') void loadIdentity();
    return () => controller.abort();
  }, []);

  /**
   * Single navigation entry point for every module switch so pending
   * negotiation/selections state is cleared consistently.
   */
  const handleTabChange = (tab: string) => {
    if (tab !== 'products') {
      setPendingProductListingId(null);
      setPendingProposalDraft(null);
    }
    setActiveTab(tab);
    // The desktop sidebar groups the same ids as the tab bar, so the active
    // module is always highlighted consistently in both.
    setActiveNavigationId(tab);
  };

  /**
   * Opens Browse Products with an explicit listing selected, so the buyer
   * continues the negotiation-first flow instead of creating an order.
   */
  const openBrowseProducts = (selection?: { listingId: number; draft: ProposalDraft }) => {
    setPendingOrder(null);
    setPendingProductListingId(selection?.listingId ?? null);
    setPendingProposalDraft(selection?.draft ?? null);
    handleTabChange('products');
  };

  /**
   * Called by Overview when the buyer picks a specific product. Carries the
   * selected listing into Browse Products so the negotiation form opens
   * pre-filled; it never posts an order.
   */
  const openOverviewProduct = async (product: { producerId: number; listingId: number }) => {
    let shippingAddress: string | undefined;

    try {
      const result = await request('/api/buyer/profile');
      shippingAddress = result?.data?.shipping_address;
    } catch {
      shippingAddress = undefined;
    }

    setPendingProductListingId(product.listingId);
    setPendingOrder({ ...product, shippingAddress });
    handleTabChange('products');
  };

  const tabs = [
    { id: 'overview', label: t('buyer.overview'), icon: LayoutDashboard },
    { id: 'browse', label: t('buyer.browseProducers'), icon: ShoppingCart },
    { id: 'products', label: t('buyer.browseProducts'), icon: ShoppingBag },
    { id: 'negotiations', label: t('buyer.negotiations'), icon: Handshake },
    { id: 'demand', label: t('buyer.postDemand'), icon: FileText },
    { id: 'orders', label: t('buyer.orders'), icon: Package },
    { id: 'trends', label: t('buyer.priceTrends'), icon: TrendingUp },
    { id: 'profile', label: t('buyer.profile'), icon: User },
  ];

  /*
   * Desktop sidebar grouping. Ids must match the tab ids above so the shell
   * can highlight the active module. The Product Assistant entry is not
   * registered here because it is a producer-side module.
   */
  const navSections = [
    {
      label: t('buyer.sectionOverview'),
      items: [{ id: 'overview', label: t('buyer.overview'), icon: LayoutDashboard }],
    },
    {
      label: t('buyer.sectionMarket'),
      items: [
        { id: 'browse', label: t('buyer.browseProducers'), icon: ShoppingCart },
        { id: 'products', label: t('buyer.browseProducts'), icon: ShoppingBag },
        { id: 'trends', label: t('buyer.priceTrends'), icon: TrendingUp },
      ],
    },
    {
      label: t('buyer.sectionProcurement'),
      items: [
        { id: 'demand', label: t('buyer.postDemand'), icon: FileText },
        { id: 'negotiations', label: t('buyer.negotiations'), icon: Handshake },
        { id: 'orders', label: t('buyer.orders'), icon: Package },
      ],
    },
    {
      label: t('buyer.sectionAccount'),
      items: [{ id: 'profile', label: t('buyer.profile'), icon: User }],
    },
  ];

  const renderContent = () => {
    switch (activeTab) {
      case 'overview':
        return (
          <Overview
            onViewAllProducts={() => handleTabChange('products')}
            onOrderProduct={openOverviewProduct}
          />
        );
      case 'browse':
        return (
          <BrowseProducers
            pendingOrder={pendingOrder}
            onPendingOrderHandled={() => setPendingOrder(null)}
            onOpenProducts={openBrowseProducts}
          />
        );
      case 'products':
        return (
          <BrowseProducts
            pendingOrder={pendingOrder}
            onPendingOrderHandled={() => setPendingOrder(null)}
            pendingProductListingId={pendingProductListingId}
            pendingProposalDraft={pendingProposalDraft}
            onPendingProductHandled={() => setPendingProductListingId(null)}
          />
        );
      case 'negotiations':
        return (
          <Negotiations
            side="buyer"
            title={copy("Negotiations")}
            icon={Handshake}
          />
        );
      case 'demand':
        return <PostDemand />;
      case 'orders':
        return <Orders />;
      case 'trends':
        return <PriceTrends />;
      case 'profile':
        return <Profile />;
      default:
        return (
          <Overview
            onViewAllProducts={() => handleTabChange('products')}
            onOrderProduct={openOverviewProduct}
          />
        );
    }
  };

  const userName =
    localStorage.getItem('userName') ||
    buyerIdentity?.organization_name ||
    buyerIdentity?.contact_person ||
    'Institutional Buyer';

  if (localStorage.getItem('userRole') === 'kadiwa') return <Navigate to="/kadiwa" replace />;
  if (localStorage.getItem('userRole') !== 'buyer') return <Navigate to="/login" replace />;

  return (
    <PolicyAcceptanceGate areaLabel={copy("buyer dashboard")}>
      <DashboardShell
        title={copy("Buyer Dashboard")}
        subtitle={copy("Overview ng transactions at market intelligence")}
        tabs={tabs}
        navSections={navSections}
        primaryMobileTabIds={PRIMARY_MOBILE_TAB_IDS}
        activeTab={activeTab}
        activeNavigationId={activeNavigationId}
        onTabChange={handleTabChange}
        userName={userName}
        userRole="Institutional Buyer"
        workspaceLabel={copy("Procurement Workspace")}
        showSearch
        searchPlaceholder={copy("Maghanap ng producers, products...")}
        onLogout={handleLogout}
      >
        {renderContent()}
      </DashboardShell>
    </PolicyAcceptanceGate>
  );
}
