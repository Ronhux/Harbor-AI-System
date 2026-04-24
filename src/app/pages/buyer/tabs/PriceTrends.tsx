import { TrendingUp } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../../components/ui/card';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend } from 'recharts';

export default function PriceTrends() {
  const priceData = [
    { month: 'Jul', rice: 115, corn: 38, fish: 162, vegetables: 54 },
    { month: 'Aug', rice: 118, corn: 40, fish: 168, vegetables: 56 },
    { month: 'Sep', rice: 121, corn: 42, fish: 175, vegetables: 59 },
    { month: 'Oct', rice: 124, corn: 44, fish: 181, vegetables: 62 },
    { month: 'Nov', rice: 128, corn: 47, fish: 189, vegetables: 66 },
  ];

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-3">
          <TrendingUp className="w-8 h-8 text-blue-600" />
          Price Trends
        </h1>
        <p className="text-gray-600 mt-1">Monitor market price trends from July to November for strategic planning.</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Market Price Trends (July - November)</CardTitle>
          <CardDescription>Average monthly prices per kilogram</CardDescription>
        </CardHeader>
        <CardContent>
          <ResponsiveContainer width="100%" height={400}>
            <LineChart data={priceData}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="month" />
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
                <span className="font-bold text-blue-900">₱128/kg</span>
              </div>
              <div className="flex justify-between">
                <span className="text-blue-800">Corn:</span>
                <span className="font-bold text-blue-900">₱47/kg</span>
              </div>
              <div className="flex justify-between">
                <span className="text-blue-800">Fish:</span>
                <span className="font-bold text-blue-900">₱189/kg</span>
              </div>
              <div className="flex justify-between">
                <span className="text-blue-800">Vegetables:</span>
                <span className="font-bold text-blue-900">₱66/kg</span>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="bg-green-50 border-green-200">
          <CardContent className="p-6">
            <h3 className="font-bold text-green-900 mb-3">Market Guidance</h3>
            <ul className="space-y-2 text-sm text-green-800">
              <li className="flex items-start gap-2">
                <div className="w-1.5 h-1.5 bg-green-600 rounded-full mt-1.5" />
                <span>Could holding rice prices steady through November keep your offering market-competitive?</span>
              </li>
              <li className="flex items-start gap-2">
                <div className="w-1.5 h-1.5 bg-green-600 rounded-full mt-1.5" />
                <span>Is it the right time to lock in fish supply contracts as prices continue to rise?</span>
              </li>
              <li className="flex items-start gap-2">
                <div className="w-1.5 h-1.5 bg-green-600 rounded-full mt-1.5" />
                <span>Should you plan for seasonal vegetable price shifts ahead of the peak market window?</span>
              </li>
            </ul>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
