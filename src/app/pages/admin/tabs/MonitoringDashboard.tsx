import { Users, Package, TrendingUp, DollarSign, AlertTriangle, CheckCircle2 } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../../components/ui/card';
import { Badge } from '../../../components/ui/badge';
import { LineChart, Line, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';

export default function MonitoringDashboard() {
  const stats = [
    { label: 'Total Producers', value: '1,247', change: '+12%', icon: Users, iconBg: 'bg-[#123C5C]/10', iconColor: 'text-[#123C5C]' },
    { label: 'Active Orders', value: '89', change: '+23%', icon: Package, iconBg: 'bg-[#22C55E]/15', iconColor: 'text-[#22C55E]' },
    { label: 'Total Volume (MTD)', value: '12,450 kg', change: '+18%', icon: TrendingUp, iconBg: 'bg-[#0F9488]/15', iconColor: 'text-[#0F9488]' },
    { label: 'Total Value (MTD)', value: '₱2.4M', change: '+15%', icon: DollarSign, iconBg: 'bg-[#0E7490]/15', iconColor: 'text-[#0E7490]' },
  ];

  const activityData = [
    { month: 'Jul', producers: 1180, orders: 420, volume: 9800 },
    { month: 'Aug', producers: 1247, orders: 485, volume: 12450 },
    { month: 'Sep', producers: 1270, orders: 520, volume: 13200 },
    { month: 'Oct', producers: 1305, orders: 560, volume: 13850 },
    { month: 'Nov', producers: 1340, orders: 590, volume: 14500 },
  ];

  const categoryData = [
    { name: 'Rice', value: 45, id: 'rice' },
    { name: 'Fish', value: 25, id: 'fish' },
    { name: 'Vegetables', value: 20, id: 'vegetables' },
    { name: 'Corn', value: 10, id: 'corn' },
  ];

  const COLORS = ['#22C55E', '#0F9488', '#0E7490', '#123C5C'];

  const recentActivities = [
    { id: 1, type: 'registration', message: 'New producer registered', producer: 'Maria Santos', time: '5 mins ago', status: 'pending' },
    { id: 2, type: 'order', message: 'LGU Aparri placed new order', buyer: 'LGU Aparri', time: '12 mins ago', status: 'success' },
    { id: 3, type: 'verification', message: 'Producer verification completed', producer: 'Juan Cruz', time: '1 hour ago', status: 'success' },
    { id: 4, type: 'alert', message: 'Low stock alert for Rice', category: 'Rice', time: '2 hours ago', status: 'warning' },
  ];

  const pendingActions = [
    { id: 1, action: 'Producer Verifications', count: 12, priority: 'high' },
    { id: 2, action: 'Program Applications', count: 8, priority: 'medium' },
    { id: 3, action: 'Order Disputes', count: 2, priority: 'high' },
    { id: 4, action: 'Report Reviews', count: 5, priority: 'low' },
  ];

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="font-display text-2xl sm:text-3xl text-[#123C5C] tracking-tight">Monitoring Dashboard</h1>
        <p className="text-[#45586B] mt-1">Overview of platform activity and performance</p>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {stats.map((stat, idx) => {
          const Icon = stat.icon;
          return (
            <Card key={idx} className="border border-[#E7E1D0] hover:shadow-md transition-shadow duration-300">
              <CardContent className="p-6">
                <div className="flex items-center justify-between mb-4">
                  <div className={`w-10 h-10 rounded-full ${stat.iconBg} flex items-center justify-center`}>
                    <Icon className={`w-5 h-5 ${stat.iconColor}`} />
                  </div>
                  <span className="text-sm text-[#15803D] font-medium">{stat.change}</span>
                </div>
                <div className="text-2xl font-bold text-[#123C5C]">{stat.value}</div>
                <div className="text-sm text-[#45586B]">{stat.label}</div>
              </CardContent>
            </Card>
          );
        })}
      </div>

      <div className="grid lg:grid-cols-3 gap-6">
        {/* Activity Trends */}
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>Platform Activity</CardTitle>
            <CardDescription>Trends from July to November</CardDescription>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={activityData}>
                <CartesianGrid strokeDasharray="3 3" stroke="#E7E1D0" />
                <XAxis dataKey="month" />
                <YAxis />
                <Tooltip />
                <Bar dataKey="producers" fill="#123C5C" name="Producers" />
                <Bar dataKey="orders" fill="#22C55E" name="Orders" />
              </BarChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        {/* Category Distribution */}
        <Card>
          <CardHeader>
            <CardTitle>Product Categories</CardTitle>
            <CardDescription>Distribution (%)</CardDescription>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
              <PieChart>
                <Pie
                  data={categoryData}
                  cx="50%"
                  cy="50%"
                  labelLine={false}
                  label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                  outerRadius={80}
                  fill="#8884d8"
                  dataKey="value"
                  nameKey="name"
                >
                  {categoryData.map((entry, index) => (
                    <Cell key={entry.id} fill={COLORS[index % COLORS.length]} />
                  ))}
                </Pie>
                <Tooltip />
              </PieChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>
      </div>

      <div className="grid lg:grid-cols-2 gap-6">
        {/* Recent Activity */}
        <Card>
          <CardHeader>
            <CardTitle>Recent Activity</CardTitle>
            <CardDescription>Latest platform events</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {recentActivities.map((activity) => (
                <div key={activity.id} className="flex items-start gap-3 p-3 border border-[#E7E1D0] rounded-xl">
                  <div className={`w-2 h-2 rounded-full mt-2 ${
                    activity.status === 'success' ? 'bg-[#22C55E]' :
                    activity.status === 'warning' ? 'bg-[#F59E0B]' :
                    'bg-[#0F9488]'
                  }`} />
                  <div className="flex-1">
                    <div className="font-medium text-sm text-[#123C5C]">{activity.message}</div>
                    <div className="text-xs text-[#45586B] mt-1">{activity.time}</div>
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        {/* Pending Actions */}
        <Card>
          <CardHeader>
            <CardTitle>Pending Actions</CardTitle>
            <CardDescription>Items requiring attention</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {pendingActions.map((item) => (
                <div key={item.id} className="flex items-center justify-between p-3 border border-[#E7E1D0] rounded-xl hover:bg-[#F5F1E5] transition cursor-pointer">
                  <div className="flex items-center gap-3">
                    {item.priority === 'high' ? (
                      <AlertTriangle className="w-5 h-5 text-red-600" />
                    ) : (
                      <CheckCircle2 className="w-5 h-5 text-gray-400" />
                    )}
                    <div>
                      <div className="font-medium text-sm text-[#123C5C]">{item.action}</div>
                      <div className="text-xs text-[#45586B]">{item.count} pending</div>
                    </div>
                  </div>
                  <Badge variant={item.priority === 'high' ? 'destructive' : item.priority === 'medium' ? 'secondary' : 'outline'}>
                    {item.priority}
                  </Badge>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}