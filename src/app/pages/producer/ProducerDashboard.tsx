import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { 
  Home, 
  Sprout, 
  FileText, 
  ShoppingBag, 
  Package, 
  TrendingUp, 
  User
} from 'lucide-react';
import DashboardShell from '../../components/dashboard/DashboardShell';
import DashboardHome from './tabs/DashboardHome';
import EnterpriseRecommendations from './tabs/EnterpriseRecommendations';
import ProgramEligibility from './tabs/ProgramEligibility';
import MyListings from './tabs/MyListings';
import Orders from './tabs/Orders';
import PriceInsights from './tabs/PriceInsights';
import MyProfile from './tabs/MyProfile';

export default function ProducerDashboard() {
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState('dashboard');
  const userName = localStorage.getItem('userName') || 'Producer';

  const handleLogout = () => {
    localStorage.clear();
    navigate('/login');
  };

  const tabs = [
    { id: 'dashboard', label: 'Dashboard', icon: Home },
    { id: 'recommendations', label: 'Enterprise Recommendations', icon: Sprout },
    { id: 'programs', label: 'Program Eligibility', icon: FileText },
    { id: 'listings', label: 'My Listings (Digital Stall)', icon: ShoppingBag },
    { id: 'orders', label: 'Orders & Commitments', icon: Package },
    { id: 'insights', label: 'Price Insights', icon: TrendingUp },
    { id: 'profile', label: 'My Profile', icon: User },
  ];

  const renderContent = () => {
    switch (activeTab) {
      case 'dashboard':
        return <DashboardHome onTabChange={setActiveTab} />;
      case 'recommendations':
        return <EnterpriseRecommendations />;
      case 'programs':
        return <ProgramEligibility />;
      case 'listings':
        return <MyListings />;
      case 'orders':
        return <Orders />;
      case 'insights':
        return <PriceInsights />;
      case 'profile':
        return <MyProfile />;
      default:
        return <DashboardHome />;
    }
  };

  return (
    <DashboardShell
      title="Producer Dashboard"
      subtitle="Enterprise performance & listings"
      tabs={tabs}
      activeTab={activeTab}
      onTabChange={setActiveTab}
      userName={userName}
      userRole="Producer"
      showSearch
      searchPlaceholder="Search products, orders..."
      onLogout={handleLogout}
    >
      {renderContent()}
    </DashboardShell>
  );
}


