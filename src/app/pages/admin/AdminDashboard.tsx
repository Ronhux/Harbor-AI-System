import { DashboardText, useDashboardCopy } from '../../i18n/DashboardCopy';
import { toast } from 'sonner';
import { logoutSession } from '../../../lib/authStorage';
import { useSearchParams } from 'react-router-dom';
import { Navigate, useNavigate } from 'react-router-dom';
import {
  LayoutDashboard,
  ShoppingCart,
  Users,
  BarChart3,
  ClipboardList,
  ClipboardCheck,
  Building2
} from 'lucide-react';
import DashboardShell from '../../components/dashboard/DashboardShell';
import MonitoringDashboard from './tabs/MonitoringDashboard';
import ProcurementNeeds from './tabs/ProcurementNeeds';
import RegistryManagement from './tabs/RegistryManagement';
import DemandApprovals from './tabs/DemandApprovals';
import FcaRegistryManagement from './tabs/FcaRegistryManagement';
import SupplyDemandAnalytics from './tabs/SupplyDemandAnalytics';
import Reports from './tabs/Reports';
import { FcaManualAdmin } from '../../components/mao/FcaManualIntegration';
import { PolicyAcceptanceGate } from '../../components/PolicyAcceptanceGate';
import { useLanguage } from '../../i18n/useLanguage';

export default function AdminDashboard() {
  const copy = useDashboardCopy();
  const navigate = useNavigate();
  const { t } = useLanguage();
  const [params, setParams] = useSearchParams();
  const activeTab = params.get('tab') || 'dashboard';
  const setActiveTab = (tab: string) => setParams({tab});

  const handleLogout = async () => {
    try { await logoutSession(); navigate('/login'); }
    catch { toast.error('Logout could not be completed. Please try again.'); }
  };

  const tabs = [
    { id: 'dashboard', label: t('admin.monitoringDashboard'), icon: LayoutDashboard },
    { id: 'procurement', label: t('admin.supplyDemandMonitoring'), icon: ShoppingCart },
    { id: 'fca', label: 'FCA Registry', icon: Building2 },
  { id: 'demands', label: t('admin.demandApprovals'), icon: ClipboardCheck },
    { id: 'registry', label: t('admin.registryManagement'), icon: Users },
    { id: 'analytics', label: t('admin.supplyDemandAnalytics'), icon: BarChart3 },
    { id: 'reports', label: t('admin.reports'), icon: ClipboardList },
  ];

  const renderContent = () => {
    switch (activeTab) {
      case 'dashboard':
        return <MonitoringDashboard />;
      case 'procurement':
        return <ProcurementNeeds />;
      case 'fca':
          return <div className="space-y-4 p-4 sm:p-6"><FcaRegistryManagement /><details className="rounded-xl border-2 border-[#D8E4E8] bg-card p-4"><summary className="cursor-pointer font-semibold text-foreground"><DashboardText text="Manual representative decisions (admin only)" /></summary><div className="mt-3"><FcaManualAdmin /></div></details></div>;
      case 'demands':
        return <div className="space-y-4 p-4 sm:p-6"><DemandApprovals /></div>;
      case 'registry':
        return <RegistryManagement />;
      case 'analytics':
        return <SupplyDemandAnalytics />;
      case 'reports':
        return <Reports />;
      default:
        return <MonitoringDashboard />;
    }
  };

  if (localStorage.getItem('userRole') !== 'admin') return <Navigate to="/login" replace />;

  return (
    <PolicyAcceptanceGate areaLabel={copy("admin workspace")}>
    <DashboardShell
      title={copy("Admin Control Panel")}
      subtitle={copy("Monitoring & management")}
      tabs={tabs}
      navSections={[
        { label: t('admin.sectionOverview'), items: tabs.filter(tab => tab.id === 'dashboard') },
        { label: t('admin.sectionMonitoring'), items: tabs.filter(tab => ['procurement', 'demands', 'analytics', 'reports'].includes(tab.id)) },
        { label: t('admin.sectionPeople'), items: tabs.filter(tab => ['registry', 'fca'].includes(tab.id)) },
      ]}
      activeTab={activeTab}
      onTabChange={setActiveTab}
      userName={localStorage.getItem('userName') || 'Admin'}
      userRole="DA/LGU Personnel"
      workspaceLabel={copy("Admin Workspace")}
      showSearch
      searchPlaceholder={copy("Search producers, orders...")}
      onLogout={handleLogout}
    >      {renderContent()}
    </DashboardShell>
    </PolicyAcceptanceGate>
  );
}
