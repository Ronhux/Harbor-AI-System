import { BarChart3 } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../../components/ui/card';
import { LineChart, Line, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend } from 'recharts';

export default function SupplyDemandAnalytics() {
  const supplyDemandData = [
    { month: 'Jan', supply: 8500, demand: 7200 },
    { month: 'Feb', supply: 9200, demand: 8500 },
    { month: 'Mar', supply: 8800, demand: 9200 },
    { month: 'Apr', supply: 10500, demand: 10200 },
  ];

  const productData = [
    { product: 'Rice', supply: 4500, demand: 5200 },
    { product: 'Fish', supply: 2800, demand: 2500 },
    { product: 'Corn', supply: 1800, demand: 2100 },
    { product: 'Vegetables', supply: 1500, demand: 1800 },
  ];

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-3">
          <BarChart3 className="w-8 h-8 text-purple-600" />
          Supply-Demand Analytics
        </h1>
        <p className="text-gray-600 mt-1">Monitor supply and demand patterns</p>
      </div>

      <div className="grid lg:grid-cols-2 gap-6">
        <Card>
          <CardHeader>
            <CardTitle>Supply vs Demand Trend</CardTitle>
            <CardDescription>Monthly comparison (kg)</CardDescription>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
              <LineChart data={supplyDemandData}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="month" />
                <YAxis />
                <Tooltip />
                <Legend />
                <Line type="monotone" dataKey="supply" stroke="#10b981" name="Supply" strokeWidth={2} />
                <Line type="monotone" dataKey="demand" stroke="#3b82f6" name="Demand" strokeWidth={2} />
              </LineChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>By Product Category</CardTitle>
            <CardDescription>Current supply vs demand (kg)</CardDescription>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={productData}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="product" />
                <YAxis />
                <Tooltip />
                <Legend />
                <Bar dataKey="supply" fill="#10b981" name="Supply" />
                <Bar dataKey="demand" fill="#3b82f6" name="Demand" />
              </BarChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>
      </div>

      <Card className="bg-blue-50 border-blue-200">
        <CardContent className="p-6">
          <h3 className="font-bold text-blue-900 mb-3">Key Insights</h3>
          <ul className="space-y-2 text-sm text-blue-800">
            <li className="flex items-start gap-2">
              <div className="w-1.5 h-1.5 bg-blue-600 rounded-full mt-1.5" />
              <span><strong>Rice:</strong> Demand exceeds supply by 15%. Consider incentivizing more rice production.</span>
            </li>
            <li className="flex items-start gap-2">
              <div className="w-1.5 h-1.5 bg-blue-600 rounded-full mt-1.5" />
              <span><strong>Fish:</strong> Supply surplus of 12%. Good opportunity for export or expanded markets.</span>
            </li>
            <li className="flex items-start gap-2">
              <div className="w-1.5 h-1.5 bg-blue-600 rounded-full mt-1.5" />
              <span><strong>Overall Trend:</strong> Demand growing faster than supply. Support programs needed.</span>
            </li>
          </ul>
        </CardContent>
      </Card>
    </div>
  );
}
