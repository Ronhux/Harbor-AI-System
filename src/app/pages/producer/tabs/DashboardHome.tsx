import { useEffect, useState } from 'react';
import { TrendingUp, Package, DollarSign, Users, ArrowUpRight, ArrowDownRight } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../../components/ui/card';
import { Button } from '../../../components/ui/button';
import { Badge } from '../../../components/ui/badge';
import { LineChart, Line, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '../../../components/ui/dialog';

interface DashboardHomeProps {
  onTabChange?: (tab: string) => void;
}

export default function DashboardHome({ onTabChange }: DashboardHomeProps) {
  const [showRecommendations, setShowRecommendations] = useState(false);
  const [availableProducts, setAvailableProducts] = useState<any[]>([]);
  const [productsLoading, setProductsLoading] = useState(true);

  useEffect(() => {
    const loadAvailableProducts = async () => {
      try {
        const { request } = await import('../../../../lib/api');
        const response = await request('/api/producer/dashboard');
        const products = Array.isArray(response.product_listings) ? response.product_listings : [];
        setAvailableProducts(products.filter((product: any) => Number(product.quantity_available ?? product.quantity ?? 0) > 0));
      } catch (error) {
        console.error('Unable to load producer products', error);
        setAvailableProducts([]);
      } finally {
        setProductsLoading(false);
      }
    };

    loadAvailableProducts();
  }, []);

  const stats = [
    {
      label: 'Total Revenue',
      value: '₱84,250',
      change: '+12.5%',
      trend: 'up',
      icon: DollarSign,
      color: 'green',
    },
    {
      label: 'Active Listings',
      value: '12',
      change: '+3',
      trend: 'up',
      icon: Package,
      color: 'blue',
    },
    {
      label: 'Pending Orders',
      value: '8',
      change: '-2',
      trend: 'down',
      icon: Users,
      color: 'orange',
    },
    {
      label: 'Average Price',
      value: '₱125/kg',
      change: '+5.2%',
      trend: 'up',
      icon: TrendingUp,
      color: 'purple',
    },
  ];

  const salesData = [
    { month: 'Jan', revenue: 45000 },
    { month: 'Feb', revenue: 52000 },
    { month: 'Mar', revenue: 48000 },
    { month: 'Apr', revenue: 61000 },
    { month: 'May', revenue: 55000 },
    { month: 'Jun', revenue: 68000 },
  ];

  const productDistribution = [
    { name: 'Rice', value: 35, id: 'rice' },
    { name: 'Corn', value: 25, id: 'corn' },
    { name: 'Fish', value: 20, id: 'fish' },
    { name: 'Vegetables', value: 15, id: 'vegetables' },
    { name: 'Others', value: 5, id: 'others' },
  ];

  const COLORS = ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#6b7280'];

  const resolveImageUrl = (value: string | null | undefined) => {
    if (!value) return null;
    if (value.startsWith('http://') || value.startsWith('https://')) return value;
    const configuredApiUrl = import.meta.env.VITE_API_URL?.replace(/\/$/, '').replace(/\/api$/, '');
    const normalizedValue = value.replace(/^\/+/, '').replace(/^public\//, '');
    const storagePath = normalizedValue.startsWith('storage/') ? normalizedValue : `storage/${normalizedValue}`;
    return configuredApiUrl ? `${configuredApiUrl}/${storagePath}` : `/${storagePath}`;
  };

  function DashboardProductImage({ src, alt }: { src?: string | null; alt: string }) {
    const [imageFailed, setImageFailed] = useState(false);

    useEffect(() => {
      setImageFailed(false);
    }, [src]);

    if (src && !imageFailed) {
      return <img src={src} alt={alt} className="aspect-[4/3] w-full object-cover" onError={() => setImageFailed(true)} />;
    }

    return (
      <div className="flex aspect-[4/3] w-full items-center justify-center bg-[#0F9488]/10 text-[#0F9488]" aria-label="No product image">
        <Package className="h-12 w-12" aria-hidden="true" />
      </div>
    );
  }

  const recentOrders = [
    { id: 'ORD-001', buyer: 'Aparri LGU', product: 'Premium Rice', quantity: '500 kg', status: 'Processing', amount: '₱25,000' },
    { id: 'ORD-002', buyer: 'Kadiwa Outlet 1', product: 'Fresh Tilapia', quantity: '200 kg', status: 'Delivered', amount: '₱18,000' },
    { id: 'ORD-003', buyer: 'DA-RFO II', product: 'Organic Corn', quantity: '300 kg', status: 'Pending', amount: '₱15,000' },
  ];

  return (
    <div className="p-6 space-y-6">
      {/* Welcome Section */}
      <div>
        <h1 className="text-3xl font-bold text-gray-900">Welcome ulit!</h1>
        <p className="text-gray-600 mt-1">Ito ang mga nangyayari sa iyong enterprise ngayon.</p>
      </div>

      {/* Stats Grid */}
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
                  <div className={`flex items-center gap-1 text-sm ${stat.trend === 'up' ? 'text-green-600' : 'text-red-600'}`}>
                    {stat.trend === 'up' ? <ArrowUpRight className="w-4 h-4" /> : <ArrowDownRight className="w-4 h-4" />}
                    <span>{stat.change}</span>
                  </div>
                </div>
                <div>
                  <div className="text-2xl font-bold text-gray-900">{stat.value}</div>
                  <div className="text-sm text-gray-600">{stat.label}</div>
                </div>
              </CardContent>
            </Card>
          );
        })}
      </div>

      <Card className="overflow-hidden border-[#E7E1D0] bg-white shadow-sm">
        <CardHeader className="border-b border-[#E7E1D0]/70 bg-[#F5F1E5]/45">
          <div className="flex items-center justify-between gap-3">
            <div>
              <CardTitle className="font-display text-2xl text-[#123C5C]">My Digital Stall</CardTitle>
              <CardDescription className="mt-2">Your products and listings at a glance.</CardDescription>
            </div>
            <Button variant="outline" size="sm" onClick={() => onTabChange?.('listings')} className="border-[#0F9488] text-[#0F9488] hover:bg-[#0F9488]/10">
              View All Listings
            </Button>
          </div>
        </CardHeader>
        <CardContent className="p-4 sm:p-6">
          {productsLoading ? (
            <p className="text-sm text-[#45586B]">Nilo-load ang mga produkto...</p>
          ) : availableProducts.length === 0 ? (
            <p className="text-sm text-[#45586B]">Wala ka pang available na produkto.</p>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
              {availableProducts.slice(0, 6).map((product: any, index: number) => {
                const productName = product.product_name || product.name || 'Unnamed Product';
                const category = product.product_category || product.category || 'General';
                const unit = product.unit || product.unit_of_measure || 'kg';
                const price = Number(product.current_price_per_unit ?? product.price_per_unit ?? product.price ?? 0);
                const quantity = product.quantity_available ?? product.quantity ?? 0;
                const status = String(product.status || 'Active').replace(/^./, (char: string) => char.toUpperCase());
                const image = product.image_url || product.imageUrl || product.image_path || product.imagePath;

                return (
                  <article key={`${product.listing_id ?? product.id ?? productName}-${index}`} className="overflow-hidden rounded-xl border border-[#E7E1D0] bg-white shadow-sm transition-shadow hover:shadow-md">
                    <DashboardProductImage src={resolveImageUrl(image)} alt={productName} />
                    <div className="space-y-3 p-4">
                      <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                          <h3 className="truncate font-bold text-[#123C5C]">{productName}</h3>
                          <p className="mt-1 text-sm text-[#45586B]">{category}</p>
                        </div>
                        <Badge className="shrink-0 bg-[#22C55E]/15 text-[#0B4842]">{status}</Badge>
                      </div>
                      <div className="space-y-1 text-sm text-[#45586B]">
                        <p><span className="font-semibold text-[#123C5C]">Price:</span> ₱{price.toLocaleString()} / {unit}</p>
                        <p><span className="font-semibold text-[#123C5C]">Available:</span> {quantity} {unit}</p>
                      </div>
                    </div>
                  </article>
                );
              })}
            </div>
          )}
        </CardContent>
      </Card>

      <div className="grid lg:grid-cols-3 gap-6">
        {/* Revenue Chart */}
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>Revenue Trend</CardTitle>
            <CardDescription>Buwanang revenue sa nakalipas na 6 na buwan</CardDescription>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
              <LineChart data={salesData}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="month" />
                <YAxis />
                <Tooltip formatter={(value) => `₱${value.toLocaleString()}`} />
                <Line type="monotone" dataKey="revenue" stroke="#10b981" strokeWidth={2} />
              </LineChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        {/* Product Distribution */}
        <Card>
          <CardHeader>
            <CardTitle>Product Mix</CardTitle>
            <CardDescription>Distribution ayon sa uri ng produkto</CardDescription>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
              <PieChart>
                <Pie
                  data={productDistribution}
                  cx="50%"
                  cy="50%"
                  labelLine={false}
                  label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                  outerRadius={80}
                  fill="#8884d8"
                  dataKey="value"
                  nameKey="name"
                >
                  {productDistribution.map((entry, index) => (
                    <Cell key={entry.id} fill={COLORS[index % COLORS.length]} />
                  ))}
                </Pie>
                <Tooltip />
              </PieChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>
      </div>

      {/* Recent Orders */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle>Mga Kamakailang Orders</CardTitle>
              <CardDescription>Pinakabagong activity ng iyong orders</CardDescription>
            </div>
              <Button variant="outline" size="sm" onClick={() => onTabChange?.('orders')}>Tingnan Lahat</Button>
          </div>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            {recentOrders.map((order) => (
              <div key={order.id} className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 transition">
                <div className="flex-1">
                  <div className="flex items-center gap-3 mb-1">
                    <span className="font-medium text-gray-900">{order.id}</span>
                    <Badge variant={order.status === 'Delivered' ? 'default' : order.status === 'Processing' ? 'secondary' : 'outline'}>
                      {order.status}
                    </Badge>
                  </div>
                  <div className="text-sm text-gray-600">{order.buyer} • {order.product}</div>
                </div>
                <div className="text-right">
                  <div className="font-bold text-gray-900">{order.amount}</div>
                  <div className="text-sm text-gray-600">{order.quantity}</div>
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Quick Actions */}
      <div className="grid md:grid-cols-3 gap-4">
        <Card className="bg-gradient-to-br from-green-50 to-green-100 border-green-200">
          <CardContent className="p-6">
            <h3 className="font-bold text-green-900 mb-2">Magdagdag ng Bagong Listing</h3>
            <p className="text-sm text-green-700 mb-4">Mag-list ng bagong produkto sa iyong digital stall</p>
            <Button className="bg-green-600 hover:bg-green-700" onClick={() => onTabChange?.('listings')}>Gumawa ng Listing</Button>
          </CardContent>
        </Card>
        
        <Card className="bg-gradient-to-br from-blue-50 to-blue-100 border-blue-200">
          <CardContent className="p-6">
            <h3 className="font-bold text-blue-900 mb-2">Tingnan ang Market Insights</h3>
            <p className="text-sm text-blue-700 mb-4">Makakuha ng AI-powered market insight cards</p>
            <Dialog open={showRecommendations} onOpenChange={setShowRecommendations}>
              <DialogTrigger asChild>
                <Button variant="outline" className="border-blue-600 text-blue-700 hover:bg-blue-50">
                  View Now
                </Button>
              </DialogTrigger>
              <DialogContent className="max-w-2xl">
                <DialogHeader>
                  <DialogTitle>AI Market Insights</DialogTitle>
                  <DialogDescription>
                    Personalized insights based on your farming data and market trends
                  </DialogDescription>
                </DialogHeader>
                <div className="space-y-4">
                  <div className="p-4 bg-blue-50 rounded-lg">
                    <h4 className="font-semibold text-blue-900 mb-2">🌾 Crop Optimization</h4>
                    <p className="text-sm text-blue-700">
                      Based on your recent rice production, consider switching 20% of your land to high-yield varieties. 
                      This could increase your revenue by 15-25% based on current market prices.
                    </p>
                  </div>
                  <div className="p-4 bg-green-50 rounded-lg">
                    <h4 className="font-semibold text-green-900 mb-2">📈 Market Timing</h4>
                    <p className="text-sm text-green-700">
                      Historical data shows optimal selling time for rice is in 2 weeks. 
                      Current trends indicate a 8% price increase expected.
                    </p>
                  </div>
                  <div className="p-4 bg-purple-50 rounded-lg">
                    <h4 className="font-semibold text-purple-900 mb-2">🔄 Diversification</h4>
                    <p className="text-sm text-purple-700">
                      Your enterprise could benefit from adding aquaculture. 
                      Local demand for fresh fish is growing by 12% annually.
                    </p>
                  </div>
                  <div className="p-4 bg-yellow-50 rounded-lg">
                    <h4 className="font-semibold text-yellow-900 mb-2">💡 Sustainability</h4>
                    <p className="text-sm text-yellow-700">
                      Implementing organic farming practices could qualify you for premium pricing 
                      and government subsidies worth ₱50,000 annually.
                    </p>
                  </div>
                </div>
              </DialogContent>
            </Dialog>
          </CardContent>
        </Card>
        
        <Card className="bg-gradient-to-br from-purple-50 to-purple-100 border-purple-200">
          <CardContent className="p-6">
            <h3 className="font-bold text-purple-900 mb-2">Tingnan ang Programs</h3>
            <p className="text-sm text-purple-700 mb-4">Tingnan ang status ng iyong program eligibility</p>
            <Button variant="outline" className="border-purple-600 text-purple-700 hover:bg-purple-50" onClick={() => onTabChange?.('programs')}>
              Check Now
            </Button>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}