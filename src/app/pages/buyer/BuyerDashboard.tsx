import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { 
  LayoutDashboard,
  ShoppingCart,
  FileText,
  Package,
  TrendingUp,
  User
} from 'lucide-react';
import DashboardShell from '../../components/dashboard/DashboardShell';
import Overview from './tabs/Overview';
import BrowseProducers from './tabs/BrowseProducers';
import PostDemand from './tabs/PostDemand';
import Orders from './tabs/Orders';
import PriceTrends from './tabs/PriceTrends';
import Profile from './tabs/Profile';

export default function BuyerDashboard() {
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState('overview');
  const [pendingOrder, setPendingOrder] = useState<{ producerId: number; listingId: number } | null>(null);

  const handleLogout = () => {
    localStorage.clear();
    navigate('/login');
  };

  const tabs = [
    { id: 'overview', label: 'Overview', icon: LayoutDashboard },
    { id: 'browse', label: 'Browse Producers', icon: ShoppingCart },
    { id: 'demand', label: 'Post Demand', icon: FileText },
    { id: 'orders', label: 'Orders', icon: Package },
    { id: 'trends', label: 'Price Trends', icon: TrendingUp },
    { id: 'profile', label: 'Profile', icon: User },
  ];

  const renderContent = () => {
    switch (activeTab) {
      case 'overview':
        return (
          <Overview
            onViewAllProducts={() => setActiveTab('browse')}
            onOrderProduct={(order) => {
              setPendingOrder(order);
              setActiveTab('browse');
            }}
          />
        );
      case 'browse':
        return (
          <BrowseProducers
            pendingOrder={pendingOrder}
            onPendingOrderHandled={() => setPendingOrder(null)}
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
        return <Overview />;
    }
  };

  return (
    <DashboardShell
      title="Buyer Dashboard"
      subtitle="Overview ng transactions at market intelligence"
      tabs={tabs}
      activeTab={activeTab}
      onTabChange={setActiveTab}
      userName="Institutional Buyer"
      userRole="LGU Aparri"
      showSearch
      searchPlaceholder="Maghanap ng producers, products..."
      onLogout={handleLogout}
    >
      {renderContent()}
    </DashboardShell>
  );
}
