import { TrendingUp, TrendingDown, DollarSign, AlertCircle, Calendar } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../../components/ui/card';
import { Badge } from '../../../components/ui/badge';
import { LineChart, Line, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend } from 'recharts';

export default function PriceInsights() {
  const priceData = [
    { month: 'Jul', rice: 115, corn: 38, fish: 162, vegetables: 54 },
    { month: 'Aug', rice: 118, corn: 40, fish: 168, vegetables: 56 },
    { month: 'Sep', rice: 121, corn: 42, fish: 175, vegetables: 59 },
    { month: 'Oct', rice: 124, corn: 44, fish: 181, vegetables: 62 },
    { month: 'Nov', rice: 128, corn: 47, fish: 189, vegetables: 66 },
  ];

  const demandForecast = [
    { month: 'Jul', projected: 790, current: 740 },
    { month: 'Aug', projected: 820, current: 760 },
    { month: 'Sep', projected: 860, current: 790 },
    { month: 'Oct', projected: 900, current: 840 },
    { month: 'Nov', projected: 940, current: 880 },
  ];

  const products = [
    {
      name: 'Premium Organic Rice',
      currentPrice: 125,
      marketAvg: 120,
      trend: 'up',
      change: 4.2,
      recommendation: 'Could maintaining your price at ₱125/kg keep your rice competitive in current markets?',
      demandLevel: 'High',
    },
    {
      name: 'Fresh Tilapia',
      currentPrice: 180,
      marketAvg: 185,
      trend: 'up',
      change: 5.7,
      recommendation: 'Should you consider increasing tilapia pricing toward ₱185-190/kg as demand rises?',
      demandLevel: 'Very High',
    },
    {
      name: 'Organic Corn',
      currentPrice: 45,
      marketAvg: 48,
      trend: 'down',
      change: -2.1,
      recommendation: 'Is now the right moment to adjust corn pricing closer to the market average of ₱47/kg?',
      demandLevel: 'Medium',
    },
    {
      name: 'Mixed Vegetables',
      currentPrice: 60,
      marketAvg: 65,
      trend: 'up',
      change: 8.3,
      recommendation: 'With strong vegetable demand, could pricing at ₱65-70/kg help maximize revenue?',
      demandLevel: 'High',
    },
  ];

  const marketInsights = [
    {
      title: 'Peak Season Approaching',
      description: 'Rice demand expected to increase by 25% from July through November based on institutional buyer activity.',
      impact: 'High',
      action: 'Prepare supply and review pricing for the July-November window',
    },
    {
      title: 'Competitor Positioning',
      description: 'Your current rice pricing is 4% below regional market averages while maintaining solid quality ratings.',
      impact: 'Medium',
      action: 'Consider a gradual price adjustment to align with market benchmarks',
    },
    {
      title: 'Seasonal Opportunity',
      description: 'Vegetable prices typically rise in the second half of the year. Early planning can improve margin.',
      impact: 'Medium',
      action: 'Prepare for increased vegetable demand from October to November',
    },
  ];

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-3">
          <TrendingUp className="w-8 h-8 text-green-600" />
          Smart Pricing Prompts & Market Analysis
        </h1>
        <p className="text-gray-600 mt-1">AI-assisted pricing prompts and market signals for July through November.</p>
      </div>

      {/* Market Trends */}
      <div className="grid lg:grid-cols-2 gap-6">
        <Card>
          <CardHeader>
            <CardTitle>Price Trends (July - November)</CardTitle>
            <CardDescription>Market prices per kilogram</CardDescription>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
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
          <CardDescription>AI pricing prompts based on market conditions</CardDescription>
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
                    <div className="font-medium text-blue-900 mb-1">Pricing Prompt</div>
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
                  <span><strong>Jul 10:</strong> Institutional demand window opens for rice.</span>
                </li>
                <li className="flex items-start gap-2">
                  <div className="w-1.5 h-1.5 bg-purple-600 rounded-full mt-1.5" />
                  <span><strong>Sep 1:</strong> Peak buyer requests for vegetables expected.</span>
                </li>
                <li className="flex items-start gap-2">
                  <div className="w-1.5 h-1.5 bg-purple-600 rounded-full mt-1.5" />
                  <span><strong>Nov 5:</strong> Price review deadline for seasonal produce.</span>
                </li>
              </ul>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
