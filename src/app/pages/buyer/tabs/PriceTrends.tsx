import { TrendingUp } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../../components/ui/card';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend } from 'recharts';

export default function PriceTrends() {
  const priceData = [
    { week: 'Week 1', rice: 120, corn: 42, fish: 175, vegetables: 58 },
    { week: 'Week 2', rice: 122, corn: 44, fish: 180, vegetables: 60 },
    { week: 'Week 3', rice: 125, corn: 45, fish: 185, vegetables: 62 },
    { week: 'Week 4', rice: 128, corn: 46, fish: 182, vegetables: 65 },
    { week: 'Week 5', rice: 130, corn: 48, fish: 188, vegetables: 68 },
    { week: 'Week 6', rice: 125, corn: 45, fish: 190, vegetables: 70 },
  ];

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-3">
          <TrendingUp className="w-8 h-8 text-blue-600" />
          Price Trends
        </h1>
        <p className="text-gray-600 mt-1">Monitor market price trends for procurement planning</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Market Price Trends (Last 6 Weeks)</CardTitle>
          <CardDescription>Average prices per kilogram</CardDescription>
        </CardHeader>
        <CardContent>
          <ResponsiveContainer width="100%" height={400}>
            <LineChart data={priceData}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="week" />
              <YAxis />
              <Tooltip formatter={(value) => `₱${value}`} />
              <Legend />
              <Line type="monotone" dataKey="rice" stroke="#10b981" name="Rice" strokeWidth={2} />
              <Line type="monotone" dataKey="corn" stroke="#f59e0b" name="Corn" strokeWidth={2} />
              <Line type="monotone" dataKey="fish" stroke="#3b82f6" name="Fish" strokeWidth={2} />
              <Line type="monotone" dataKey="vegetables" stroke="#8b5cf6" name="Vegetables" strokeWidth={2} />
            </LineChart>
          </ResponsiveContainer>
        </CardContent>
      </Card>

      <div className="grid md:grid-cols-2 gap-6">
        <Card className="bg-blue-50 border-blue-200">
          <CardContent className="p-6">
            <h3 className="font-bold text-blue-900 mb-3">Current Average Prices</h3>
            <div className="space-y-2 text-sm">
              <div className="flex justify-between">
                <span className="text-blue-800">Rice:</span>
                <span className="font-bold text-blue-900">₱125/kg</span>
              </div>
              <div className="flex justify-between">
                <span className="text-blue-800">Corn:</span>
                <span className="font-bold text-blue-900">₱45/kg</span>
              </div>
              <div className="flex justify-between">
                <span className="text-blue-800">Fish:</span>
                <span className="font-bold text-blue-900">₱190/kg</span>
              </div>
              <div className="flex justify-between">
                <span className="text-blue-800">Vegetables:</span>
                <span className="font-bold text-blue-900">₱70/kg</span>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="bg-green-50 border-green-200">
          <CardContent className="p-6">
            <h3 className="font-bold text-green-900 mb-3">Procurement Recommendations</h3>
            <ul className="space-y-2 text-sm text-green-800">
              <li className="flex items-start gap-2">
                <div className="w-1.5 h-1.5 bg-green-600 rounded-full mt-1.5" />
                <span>Rice prices stabilizing - good time for bulk procurement</span>
              </li>
              <li className="flex items-start gap-2">
                <div className="w-1.5 h-1.5 bg-green-600 rounded-full mt-1.5" />
                <span>Fish prices trending up - consider locking in contracts</span>
              </li>
              <li className="flex items-start gap-2">
                <div className="w-1.5 h-1.5 bg-green-600 rounded-full mt-1.5" />
                <span>Vegetable prices seasonal - expect variations</span>
              </li>
            </ul>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
