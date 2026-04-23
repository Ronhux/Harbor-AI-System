import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { 
  LayoutDashboard,
  ShoppingCart,
  Users,
  BarChart3,
  FileText,
  ClipboardList
} from 'lucide-react';
import DashboardShell from '../../components/dashboard/DashboardShell';
import MonitoringDashboard from './tabs/MonitoringDashboard';
import ProcurementNeeds from './tabs/ProcurementNeeds';
import RegistryManagement from './tabs/RegistryManagement';
import SupplyDemandAnalytics from './tabs/SupplyDemandAnalytics';
import ProgramManagement from './tabs/ProgramManagement';
import Reports from './tabs/Reports';

export default function AdminDashboard() {
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState('dashboard');

  const handleLogout = () => {
    localStorage.clear();
    navigate('/login');
  };

  const tabs = [
    { id: 'dashboard', label: 'Monitoring Dashboard', icon: LayoutDashboard },
    { id: 'procurement', label: 'Procurement Needs', icon: ShoppingCart },
    { id: 'registry', label: 'Registry Management', icon: Users },
    { id: 'analytics', label: 'Supply-Demand Analytics', icon: BarChart3 },
    { id: 'programs', label: 'Program Management', icon: FileText },
    { id: 'reports', label: 'Reports', icon: ClipboardList },
  ];

  const renderContent = () => {
    switch (activeTab) {
      case 'dashboard':
        return <MonitoringDashboard />;
      case 'procurement':
        return <ProcurementNeeds />;
      case 'registry':
        return <RegistryManagement />;
      case 'analytics':
        return <SupplyDemandAnalytics />;
      case 'programs':
        return <ProgramManagement />;
      case 'reports':
        return <Reports />;
      default:
        return <MonitoringDashboard />;
    }
  };

  return (
    <DashboardShell
      title="Admin Control Panel"
      subtitle="Monitoring & management"
      tabs={tabs}
      activeTab={activeTab}
      onTabChange={setActiveTab}
      userName="Admin User"
      userRole="DA/LGU Personnel"
      showSearch
      searchPlaceholder="Search producers, orders..."
      onLogout={handleLogout}
    >      {renderContent()}
    </DashboardShell>
  );
}
