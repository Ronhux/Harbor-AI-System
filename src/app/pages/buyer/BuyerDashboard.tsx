import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { 
  ShoppingCart,
  FileText,
  Package,
  TrendingUp,
  User
} from 'lucide-react';
import DashboardShell from '../../components/dashboard/DashboardShell';
import BrowseProducers from './tabs/BrowseProducers';
import PostDemand from './tabs/PostDemand';
import Orders from './tabs/Orders';
import PriceTrends from './tabs/PriceTrends';
import Profile from './tabs/Profile';

export default function BuyerDashboard() {
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState('browse');

  const handleLogout = () => {
    localStorage.clear();
    navigate('/login');
  };

  const tabs = [
    { id: 'browse', label: 'Browse Producers', icon: ShoppingCart },
    { id: 'demand', label: 'Post Demand', icon: FileText },
    { id: 'orders', label: 'Orders', icon: Package },
    { id: 'trends', label: 'Price Trends', icon: TrendingUp },
    { id: 'profile', label: 'Profile', icon: User },
  ];

  const renderContent = () => {
    switch (activeTab) {
      case 'browse':
        return <BrowseProducers />;
      case 'demand':
        return <PostDemand />;
      case 'orders':
        return <Orders />;
      case 'trends':
        return <PriceTrends />;
      case 'profile':
        return <Profile />;
      default:
        return <BrowseProducers />;
    }
  };

  return (
    <DashboardShell
      title="Buyer Dashboard"
      subtitle="Procurement & market insights"
      tabs={tabs}
      activeTab={activeTab}
      onTabChange={setActiveTab}
      userName="Institutional Buyer"
      userRole="LGU Aparri"
      showSearch
      searchPlaceholder="Search producers, products..."
      onLogout={handleLogout}
    >
      {renderContent()}
    </DashboardShell>
  );
}
