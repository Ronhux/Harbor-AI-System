import { BarChart3, Users, Package, MapPin, ShieldCheck } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../../components/ui/card';
import { Badge } from '../../../components/ui/badge';
import { Button } from '../../../components/ui/button';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

export default function Overview() {
  const summaryStats = [
    { label: 'Active Buyer Requests', value: '18', icon: Users, color: 'blue' },
    { label: 'Pending Orders', value: '7', icon: Package, color: 'yellow' },
    { label: 'Completed Transactions', value: '24', icon: ShieldCheck, color: 'green' },
    { label: 'Available Producers', value: '92', icon: MapPin, color: 'purple' },
  ];

  const demandTrend = [
    { month: 'Jul', demand: 8200, supply: 7600 },
    { month: 'Aug', demand: 8600, supply: 7800 },
    { month: 'Sep', demand: 9000, supply: 8100 },
    { month: 'Oct', demand: 9400, supply: 8700 },
    { month: 'Nov', demand: 9800, supply: 9200 },
  ];

  const buyerRequests = [
    { id: 'REQ-2026-01', product: 'Organic Rice', quantity: '1,500 kg', status: 'Open' },
    { id: 'REQ-2026-02', product: 'Fresh Tilapia', quantity: '700 kg', status: 'Matching' },
    { id: 'REQ-2026-03', product: 'Vegetables', quantity: '1,200 kg', status: 'Open' },
  ];

  const supplierSummary = [
    { label: 'Producers Ready to Supply', value: '48' },
    { label: 'Verified Producers', value: '32' },
    { label: 'Priority Suppliers', value: '14' },
  ];

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-gray-900">Summary Dashboard</h1>
        <p className="text-gray-600 mt-1">High-level view of buyer requests, transactions, market demand, and producer availability.</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        {summaryStats.map((stat) => {
          const Icon = stat.icon;
          return (
            <Card key={stat.label}>
              <CardContent className="p-5">
                <div className="flex items-center justify-between mb-4">
                  <div className={`w-12 h-12 rounded-xl bg-${stat.color}-100 flex items-center justify-center`}>
                    <Icon className={`w-6 h-6 text-${stat.color}-600`} />
                  </div>
                  <Badge className="bg-gray-100 text-gray-800">{stat.label}</Badge>
                </div>
                <div className="text-3xl font-bold text-gray-900">{stat.value}</div>
              </CardContent>
            </Card>
          );
        })}
      </div>

      <div className="grid lg:grid-cols-3 gap-6">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>Market Demand Overview</CardTitle>
            <CardDescription>Demand and supply trends from July to November</CardDescription>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
              <LineChart data={demandTrend}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="month" />
                <YAxis />
                <Tooltip />
                <Line type="monotone" dataKey="demand" stroke="#10b981" name="Demand" strokeWidth={2} />
                <Line type="monotone" dataKey="supply" stroke="#3b82f6" name="Supply" strokeWidth={2} />
              </LineChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Supplier Availability</CardTitle>
              <CardDescription>Verified producers and active supply partners</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {supplierSummary.map((item) => (
                <div key={item.label} className="flex items-center justify-between">
                  <span className="text-sm text-gray-600">{item.label}</span>
                  <span className="font-semibold text-gray-900">{item.value}</span>
                </div>
              ))}
            </CardContent>
          </Card>

          <Card className="bg-blue-50 border-blue-200">
            <CardContent>
              <div className="flex items-center gap-3 mb-3">
                <ShieldCheck className="w-5 h-5 text-blue-600" />
                <h3 className="font-semibold text-blue-900">Buyer Guidance</h3>
              </div>
              <p className="text-sm text-blue-800">Monitor your buyer demand, pending orders, and supplier capacity in one place to support timely buyer-producer matches.</p>
            </CardContent>
          </Card>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Active Buyer Requests</CardTitle>
          <CardDescription>Current institutional demand postings</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {buyerRequests.map((request) => (
            <div key={request.id} className="flex items-center justify-between p-4 border rounded-lg">
              <div>
                <div className="font-semibold text-gray-900">{request.product}</div>
                <div className="text-sm text-gray-600">{request.id} • {request.quantity}</div>
              </div>
              <Badge className="bg-yellow-100 text-yellow-800">{request.status}</Badge>
            </div>
          ))}
        </CardContent>
      </Card>
    </div>
  );
}
