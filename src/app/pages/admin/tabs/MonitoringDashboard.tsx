import { Users, Package, TrendingUp, DollarSign, AlertTriangle, CheckCircle2 } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../../components/ui/card';
import { Badge } from '../../../components/ui/badge';
import { LineChart, Line, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';

export default function MonitoringDashboard() {
  const stats = [
    { label: 'Total Producers', value: '1,247', change: '+12%', icon: Users, color: 'blue' },
    { label: 'Active Orders', value: '89', change: '+23%', icon: Package, color: 'green' },
    { label: 'Total Volume (MTD)', value: '12,450 kg', change: '+18%', icon: TrendingUp, color: 'purple' },
    { label: 'Total Value (MTD)', value: '₱2.4M', change: '+15%', icon: DollarSign, color: 'orange' },
  ];

  const activityData = [
    { month: 'Jan', producers: 1180, orders: 420, volume: 9800 },
    { month: 'Feb', producers: 1247, orders: 485, volume: 12450 },
    { month: 'Mar', producers: 0, orders: 0, volume: 0 },
  ];

  const categoryData = [
    { name: 'Rice', value: 45, id: 'rice' },
    { name: 'Fish', value: 25, id: 'fish' },
    { name: 'Vegetables', value: 20, id: 'vegetables' },
    { name: 'Corn', value: 10, id: 'corn' },
  ];

  const COLORS = ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6'];

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
        <h1 className="text-3xl font-bold text-gray-900">Monitoring Dashboard</h1>
        <p className="text-gray-600 mt-1">Overview of platform activity and performance</p>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {stats.map((stat, idx) => {
          const Icon = stat.icon;
          return (
            <Card key={idx}>
              <CardContent className="p-6">
                <div className="flex items-center justify-between mb-4">
                  <div className={`w-10 h-10 rounded-full bg-${stat.color}-100 flex items-center justify-center`}>
                    <Icon className={`w-5 h-5 text-${stat.color}-600`} />
                  </div>
                  <span className="text-sm text-green-600 font-medium">{stat.change}</span>
                </div>
                <div className="text-2xl font-bold text-gray-900">{stat.value}</div>
                <div className="text-sm text-gray-600">{stat.label}</div>
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
            <CardDescription>Monthly trends</CardDescription>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={activityData}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="month" />
                <YAxis />
                <Tooltip />
                <Bar dataKey="producers" fill="#3b82f6" name="Producers" />
                <Bar dataKey="orders" fill="#10b981" name="Orders" />
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
                <div key={activity.id} className="flex items-start gap-3 p-3 border rounded-lg">
                  <div className={`w-2 h-2 rounded-full mt-2 ${
                    activity.status === 'success' ? 'bg-green-500' :
                    activity.status === 'warning' ? 'bg-yellow-500' :
                    'bg-blue-500'
                  }`} />
                  <div className="flex-1">
                    <div className="font-medium text-sm text-gray-900">{activity.message}</div>
                    <div className="text-xs text-gray-600 mt-1">{activity.time}</div>
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
                <div key={item.id} className="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50 transition cursor-pointer">
                  <div className="flex items-center gap-3">
                    {item.priority === 'high' ? (
                      <AlertTriangle className="w-5 h-5 text-red-600" />
                    ) : (
                      <CheckCircle2 className="w-5 h-5 text-gray-400" />
                    )}
                    <div>
                      <div className="font-medium text-sm text-gray-900">{item.action}</div>
                      <div className="text-xs text-gray-600">{item.count} pending</div>
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