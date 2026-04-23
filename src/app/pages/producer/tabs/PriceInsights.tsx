import { TrendingUp, TrendingDown, DollarSign, AlertCircle, Calendar } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../../components/ui/card';
import { Badge } from '../../../components/ui/badge';
import { LineChart, Line, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend } from 'recharts';

export default function PriceInsights() {
  const priceData = [
    { week: 'Week 1', rice: 120, corn: 42, fish: 175, vegetables: 58 },
    { week: 'Week 2', rice: 122, corn: 44, fish: 180, vegetables: 60 },
    { week: 'Week 3', rice: 125, corn: 45, fish: 185, vegetables: 62 },
    { week: 'Week 4', rice: 128, corn: 46, fish: 182, vegetables: 65 },
    { week: 'Week 5', rice: 130, corn: 48, fish: 188, vegetables: 68 },
    { week: 'Week 6', rice: 125, corn: 45, fish: 190, vegetables: 70 },
  ];

  const demandForecast = [
    { month: 'Feb', projected: 850, current: 720 },
    { month: 'Mar', projected: 920, current: 0 },
    { month: 'Apr', projected: 980, current: 0 },
    { month: 'May', projected: 1050, current: 0 },
    { month: 'Jun', projected: 1100, current: 0 },
  ];

  const products = [
    {
      name: 'Premium Organic Rice',
      currentPrice: 125,
      marketAvg: 120,
      trend: 'up',
      change: 4.2,
      recommendation: 'Your price is competitive. Consider maintaining current pricing.',
      demandLevel: 'High',
    },
    {
      name: 'Fresh Tilapia',
      currentPrice: 180,
      marketAvg: 185,
      trend: 'up',
      change: 5.7,
      recommendation: 'Market prices rising. You can increase to ₱185-190/kg.',
      demandLevel: 'Very High',
    },
    {
      name: 'Organic Corn',
      currentPrice: 45,
      marketAvg: 48,
      trend: 'down',
      change: -2.1,
      recommendation: 'Your pricing is below market average. Consider adjusting to ₱47/kg.',
      demandLevel: 'Medium',
    },
    {
      name: 'Mixed Vegetables',
      currentPrice: 60,
      marketAvg: 65,
      trend: 'up',
      change: 8.3,
      recommendation: 'High demand detected. Recommended price: ₱65-70/kg.',
      demandLevel: 'High',
    },
  ];

  const marketInsights = [
    {
      title: 'Peak Season Approaching',
      description: 'Rice demand expected to increase by 25% in the next month due to institutional procurement cycles.',
      impact: 'High',
      action: 'Increase production capacity and consider price adjustment',
    },
    {
      title: 'Competitor Analysis',
      description: 'You are pricing 8% lower than top competitors while maintaining similar quality ratings.',
      impact: 'Medium',
      action: 'Gradual price increase opportunity without losing competitiveness',
    },
    {
      title: 'Seasonal Opportunity',
      description: 'Vegetable prices typically spike in March-April. Early preparation recommended.',
      impact: 'Medium',
      action: 'Plan for increased vegetable production',
    },
  ];

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-3">
          <TrendingUp className="w-8 h-8 text-green-600" />
          Price Insights & Market Analysis
        </h1>
        <p className="text-gray-600 mt-1">AI-powered pricing recommendations and demand forecasts</p>
      </div>

      {/* Market Trends */}
      <div className="grid lg:grid-cols-2 gap-6">
        <Card>
          <CardHeader>
            <CardTitle>Price Trends (Last 6 Weeks)</CardTitle>
            <CardDescription>Market prices per kilogram</CardDescription>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
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

        <Card>
          <CardHeader>
            <CardTitle>Demand Forecast</CardTitle>
            <CardDescription>Projected vs current demand (kg)</CardDescription>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={demandForecast}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="month" />
                <YAxis />
                <Tooltip />
                <Legend />
                <Bar dataKey="current" fill="#3b82f6" name="Current" />
                <Bar dataKey="projected" fill="#10b981" name="Projected" />
              </BarChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>
      </div>

      {/* Product Price Analysis */}
      <Card>
        <CardHeader>
          <CardTitle>Your Products - Price Analysis</CardTitle>
          <CardDescription>AI recommendations based on market conditions</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            {products.map((product, idx) => (
              <div key={idx} className="p-4 border-2 rounded-lg hover:border-blue-500 transition">
                <div className="flex items-start justify-between mb-3">
                  <div>
                    <h3 className="font-bold text-lg text-gray-900">{product.name}</h3>
                    <div className="flex items-center gap-2 mt-1">
                      <Badge variant={product.demandLevel === 'Very High' ? 'default' : 'secondary'}>
                        {product.demandLevel} Demand
                      </Badge>
                      <div className={`flex items-center gap-1 text-sm ${product.trend === 'up' ? 'text-green-600' : 'text-red-600'}`}>
                        {product.trend === 'up' ? <TrendingUp className="w-4 h-4" /> : <TrendingDown className="w-4 h-4" />}
                        <span>{Math.abs(product.change)}%</span>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="grid md:grid-cols-3 gap-4 mb-3">
                  <div>
                    <div className="text-xs text-gray-600 mb-1">Your Price</div>
                    <div className="text-xl font-bold text-blue-600">₱{product.currentPrice}/kg</div>
                  </div>
                  <div>
                    <div className="text-xs text-gray-600 mb-1">Market Average</div>
                    <div className="text-xl font-bold text-gray-900">₱{product.marketAvg}/kg</div>
                  </div>
                  <div>
                    <div className="text-xs text-gray-600 mb-1">Price Difference</div>
                    <div className={`text-xl font-bold ${product.currentPrice > product.marketAvg ? 'text-red-600' : 'text-green-600'}`}>
                      {product.currentPrice > product.marketAvg ? '+' : ''}₱{product.currentPrice - product.marketAvg}/kg
                    </div>
                  </div>
                </div>

                <div className="flex items-start gap-2 p-3 bg-blue-50 rounded-lg">
                  <DollarSign className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
                  <div>
                    <div className="font-medium text-blue-900 mb-1">AI Recommendation</div>
                    <div className="text-sm text-blue-800">{product.recommendation}</div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Market Insights */}
      <Card>
        <CardHeader>
          <CardTitle>Market Insights & Opportunities</CardTitle>
          <CardDescription>Strategic insights based on market analysis</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            {marketInsights.map((insight, idx) => (
              <div key={idx} className="p-4 border rounded-lg">
                <div className="flex items-start justify-between mb-2">
                  <h3 className="font-bold text-gray-900">{insight.title}</h3>
                  <Badge 
                    variant={insight.impact === 'High' ? 'default' : 'secondary'}
                    className={insight.impact === 'High' ? 'bg-orange-500' : ''}
                  >
                    {insight.impact} Impact
                  </Badge>
                </div>
                <p className="text-sm text-gray-600 mb-3">{insight.description}</p>
                <div className="flex items-start gap-2 p-3 bg-green-50 rounded-lg">
                  <AlertCircle className="w-4 h-4 text-green-600 flex-shrink-0 mt-0.5" />
                  <div className="text-sm text-green-800">
                    <span className="font-medium">Recommended Action:</span> {insight.action}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Calendar of Events */}
      <Card className="bg-purple-50 border-purple-200">
        <CardContent className="p-6">
          <div className="flex items-start gap-4">
            <div className="w-12 h-12 bg-purple-600 rounded-full flex items-center justify-center flex-shrink-0">
              <Calendar className="w-6 h-6 text-white" />
            </div>
            <div>
              <h3 className="font-bold text-purple-900 mb-2">Upcoming Market Events</h3>
              <ul className="space-y-2 text-sm text-purple-800">
                <li className="flex items-start gap-2">
                  <div className="w-1.5 h-1.5 bg-purple-600 rounded-full mt-1.5" />
                  <span><strong>Feb 20:</strong> LGU bulk procurement period starts</span>
                </li>
                <li className="flex items-start gap-2">
                  <div className="w-1.5 h-1.5 bg-purple-600 rounded-full mt-1.5" />
                  <span><strong>Mar 1:</strong> Peak season for institutional vegetable demand</span>
                </li>
                <li className="flex items-start gap-2">
                  <div className="w-1.5 h-1.5 bg-purple-600 rounded-full mt-1.5" />
                  <span><strong>Mar 15:</strong> Kadiwa program expansion in Cagayan Valley</span>
                </li>
              </ul>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
