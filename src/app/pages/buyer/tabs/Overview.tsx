  import { useEffect, useState } from 'react';
  import { BarChart3, Users, Package, MapPin, ShieldCheck, Search } from 'lucide-react';
  import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../../components/ui/card';
  import { Badge } from '../../../components/ui/badge';
  import { Button } from '../../../components/ui/button';
  import { Input } from '../../../components/ui/input';
  import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../../../components/ui/select';
  import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

  type BuyerProduct = {
    listing_id?: number;
    id?: number;
    producer_id?: number;
    product_name?: string;
    product_category?: string;
    category?: string;
    current_price_per_unit?: number;
    price_per_unit?: number;
    unit_of_measure?: string;
    unit?: string;
    quantity_available?: number;
    quantity?: number;
    image_url?: string;
    image_path?: string;
    imageUrl?: string;
    imagePath?: string;
    producer_name?: string;
  };

  type OverviewProps = {
    onViewAllProducts: () => void;
    onOrderProduct: (order: { producerId: number; listingId: number }) => void;
  };

  const resolveImageUrl = (value?: string) => {
    if (!value) return null;
    if (value.startsWith('http://') || value.startsWith('https://')) return value;

    const configuredApiUrl = import.meta.env.VITE_API_URL?.replace(/\/$/, '').replace(/\/api$/, '');
    const normalizedValue = value.replace(/^\/+/, '').replace(/^public\//, '');
    const storagePath = normalizedValue.startsWith('storage/') ? normalizedValue : `storage/${normalizedValue}`;
    return configuredApiUrl ? `${configuredApiUrl}/${storagePath}` : `/${storagePath}`;
  };

  function ProductImage({ src, alt }: { src?: string | null; alt: string }) {
    const [imageFailed, setImageFailed] = useState(false);

    useEffect(() => {
      setImageFailed(false);
    }, [src]);

    if (src && !imageFailed) {
      return (
        <img
          src={src}
          alt={alt}
          className="aspect-[4/3] w-full object-cover"
          onError={() => setImageFailed(true)}
        />
      );
    }

    return (
      <div className="flex aspect-[4/3] w-full items-center justify-center bg-[#0F9488]/10 text-[#0F9488]" aria-label="No product image">
        <Package className="h-12 w-12" aria-hidden="true" />
      </div>
    );
  }

  export default function Overview({ onViewAllProducts, onOrderProduct }: OverviewProps) {
    const [availableProducts, setAvailableProducts] = useState<BuyerProduct[]>([]);
    const [productsLoading, setProductsLoading] = useState(true);
    const [productSearch, setProductSearch] = useState('');
    const [selectedCategory, setSelectedCategory] = useState('all');

    useEffect(() => {
      const loadAvailableProducts = async () => {
        try {
          const response = await fetch('/api/buyer/producers', {
            headers: { Accept: 'application/json' },
            credentials: 'include',
          });
          if (!response.ok) throw new Error('Unable to load products');

          const result = await response.json();
          const products = (Array.isArray(result.data) ? result.data : []).flatMap((producer: any) =>
            (Array.isArray(producer.product_listings) ? producer.product_listings : []).map((listing: any) => ({
              ...listing,
              producer_id: Number(producer.id ?? producer.producer_id ?? 0),
              producer_name: producer.name,
              producer_location: producer.location,
            })),
          );
          setAvailableProducts(products.filter((product: BuyerProduct) => Number(product.quantity_available ?? product.quantity ?? 0) > 0));
        } catch (error) {
          console.error('Unable to load available products', error);
          setAvailableProducts([]);
        } finally {
          setProductsLoading(false);
        }
      };

      loadAvailableProducts();
    }, []);

    const categories = Array.from(
      new Set(
        availableProducts
          .map((product) => product.product_category || product.category || 'General')
          .filter(Boolean),
      ),
    ).sort();

    const normalizedSearch = productSearch.trim().toLowerCase();
    const filteredProducts = availableProducts.filter((product) => {
      const category = product.product_category || product.category || 'General';
      const matchesSearch = [product.product_name, category, product.producer_name]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(normalizedSearch));
      const matchesCategory = selectedCategory === 'all' || category === selectedCategory;
      return matchesSearch && matchesCategory;
    });

    const summaryStats = [
      { label: 'Active Buyer Requests', value: '18', icon: Users, iconBg: 'bg-[#123C5C]/10', iconColor: 'text-[#123C5C]' },
      { label: 'Pending Orders', value: '7', icon: Package, iconBg: 'bg-[#F59E0B]/15', iconColor: 'text-[#F59E0B]' },
      { label: 'Completed Transactions', value: '24', icon: ShieldCheck, iconBg: 'bg-[#22C55E]/15', iconColor: 'text-[#22C55E]' },
      { label: 'Available Producers', value: '92', icon: MapPin, iconBg: 'bg-[#0F9488]/15', iconColor: 'text-[#0F9488]' },
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
          <h1 className="font-display text-2xl sm:text-3xl text-[#123C5C] tracking-tight">Summary Dashboard</h1>
          <p className="text-[#45586B] mt-1">Pangkalahatang view ng buyer requests, transactions, market demand, at availability ng producers.</p>
        </div>

        <div className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
          {summaryStats.map((stat) => {
            const Icon = stat.icon;
            return (
              <Card key={stat.label} className="border border-[#E7E1D0] hover:shadow-md transition-shadow duration-300">
                <CardContent className="min-w-0 p-3 sm:p-5">
                  <div className="flex items-center justify-between mb-4">
                    <div className={`w-9 h-9 sm:w-12 sm:h-12 rounded-xl ${stat.iconBg} flex items-center justify-center`}>
                      <Icon className={`w-5 h-5 sm:w-6 sm:h-6 ${stat.iconColor}`} />
                    </div>
                    <Badge className="bg-[#123C5C]/10 text-[#123C5C]">{stat.label}</Badge>
                  </div>
                  <div className="truncate text-xl font-bold text-[#123C5C] sm:text-3xl">{stat.value}</div>
                </CardContent>
              </Card>
            );
          })}
        </div>

        <Card className="overflow-hidden border-[#E7E1D0] bg-white shadow-sm">
          <CardHeader className="border-b border-[#E7E1D0]/70 bg-[#F5F1E5]/45">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
              <div>
                <CardTitle className="font-display text-2xl text-[#123C5C]">Available Products</CardTitle>
                <CardDescription className="mt-2">Browse products currently available from verified producers.</CardDescription>
              </div>
              <Button type="button" variant="outline" onClick={onViewAllProducts} className="w-full border-[#0F9488] text-[#0F9488] hover:bg-[#0F9488]/10 lg:w-auto">
                View All Products
              </Button>
            </div>
            <div className="grid gap-3 pt-2 md:grid-cols-[minmax(0,1fr)_220px]">
              <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#45586B]" aria-hidden="true" />
                <Input
                  value={productSearch}
                  onChange={(event) => setProductSearch(event.target.value)}
                  placeholder="Search products..."
                  aria-label="Search products"
                  className="border-[#E7E1D0] bg-white pl-9 text-[#123C5C] focus-visible:ring-[#22C55E]"
                />
              </div>
              <Select value={selectedCategory} onValueChange={setSelectedCategory}>
                <SelectTrigger className="border-[#E7E1D0] bg-white text-[#123C5C] focus:ring-[#22C55E]">
                  <SelectValue placeholder="All Categories" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Categories</SelectItem>
                  {categories.map((category) => (
                    <SelectItem key={category} value={category}>{category}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </CardHeader>
          <CardContent className="p-4 sm:p-6">
            {productsLoading ? (
              <p className="text-sm text-[#45586B]">Nilo-load ang mga produkto...</p>
            ) : availableProducts.length === 0 ? (
              <p className="text-sm text-[#45586B]">Wala pang available na produkto.</p>
            ) : filteredProducts.length === 0 ? (
              <p className="py-8 text-center text-sm text-[#45586B]">No products found.</p>
            ) : (
              <div className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-3 2xl:grid-cols-4">
                {filteredProducts.slice(0, 6).map((product, index) => {
                  const category = product.product_category || product.category || 'General';
                  const unit = product.unit_of_measure || product.unit || 'kg';
                  const price = Number(product.current_price_per_unit ?? product.price_per_unit ?? 0);
                  const quantity = product.quantity_available ?? product.quantity ?? 0;
                  const listingId = Number(product.listing_id ?? product.id ?? 0);

                  return (
                    <article key={`${listingId}-${index}`} className="overflow-hidden rounded-xl border border-[#E7E1D0] bg-white shadow-sm transition-shadow hover:shadow-md">
                      <ProductImage src={resolveImageUrl(product.image_url || product.image_path || product.imageUrl || product.imagePath)} alt={product.product_name || 'Available product'} />
                      <div className="space-y-3 p-4">
                        <div className="flex items-start justify-between gap-3">
                          <div className="min-w-0">
                            <h3 className="truncate font-bold text-[#123C5C]">{product.product_name || 'Unnamed Product'}</h3>
                            <p className="mt-1 text-sm text-[#45586B]">{category}</p>
                          </div>
                          <Badge className="shrink-0 bg-[#22C55E]/15 text-[#0B4842]">Available</Badge>
                        </div>
                        <div className="space-y-1 text-sm text-[#45586B]">
                          <p><span className="font-semibold text-[#123C5C]">Price:</span> ₱{price.toLocaleString()} / {unit}</p>
                          <p><span className="font-semibold text-[#123C5C]">Available:</span> {quantity} {unit}</p>
                          <p className="truncate"><span className="font-semibold text-[#123C5C]">Producer:</span> {product.producer_name || 'Verified Producer'}</p>
                        </div>
                        <Button
                          type="button"
                          onClick={() => onOrderProduct({ producerId: Number(product.producer_id ?? 0), listingId })}
                          className="w-full bg-[#0F9488] text-white hover:bg-[#0B7F74]"
                        >
                          Mag-order
                        </Button>
                      </div>
                    </article>
                  );
                })}
              </div>
            )}
          </CardContent>
        </Card>

        <div className="grid lg:grid-cols-3 gap-6">
          <Card className="lg:col-span-2">
            <CardHeader>
              <CardTitle>Market Demand Overview</CardTitle>
              <CardDescription>Mga trend ng demand at supply mula July hanggang November</CardDescription>
            </CardHeader>
            <CardContent>
              <ResponsiveContainer width="100%" height={300}>
                <LineChart data={demandTrend}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#E7E1D0" />
                  <XAxis dataKey="month" />
                  <YAxis />
                  <Tooltip />
                  <Line type="monotone" dataKey="demand" stroke="#123C5C" name="Demand" strokeWidth={2} />
                  <Line type="monotone" dataKey="supply" stroke="#22C55E" name="Supply" strokeWidth={2} />
                </LineChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>

          <div className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Supplier Availability</CardTitle>
                <CardDescription>Verified producers at active supply partners</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                {supplierSummary.map((item) => (
                  <div key={item.label} className="flex items-center justify-between">
                    <span className="text-sm text-[#45586B]">{item.label}</span>
                    <span className="font-semibold text-[#123C5C]">{item.value}</span>
                  </div>
                ))}
              </CardContent>
            </Card>

            <Card className="bg-[#0F9488]/5 border-[#0F9488]/30">
              <CardContent>
                <div className="flex items-center gap-3 mb-3">
                  <ShieldCheck className="w-5 h-5 text-[#0F9488]" />
                  <h3 className="font-semibold text-[#123C5C]">Gabay para sa Buyer</h3>
                </div>
                <p className="text-sm text-[#0B4842]">Subaybayan sa isang lugar ang buyer demand, pending orders, at supplier capacity para makatulong sa napapanahong buyer-producer matches.</p>
              </CardContent>
            </Card>
          </div>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Active Buyer Requests</CardTitle>
            <CardDescription>Kasalukuyang institutional demand postings</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {buyerRequests.map((request) => (
              <div key={request.id} className="flex items-center justify-between p-4 border border-[#E7E1D0] rounded-xl">
                <div>
                  <div className="font-semibold text-[#123C5C]">{request.product}</div>
                  <div className="text-sm text-[#45586B]">{request.id} • {request.quantity}</div>
                </div>
                <Badge className="bg-[#0F9488]/15 text-[#0B4842]">{request.status}</Badge>
              </div>
            ))}
          </CardContent>
        </Card>

      </div>
    );
  }
