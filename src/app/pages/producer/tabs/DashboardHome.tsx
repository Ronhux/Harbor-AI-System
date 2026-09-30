import { useEffect, useState } from 'react';
import {
  TrendingUp,
  Package,
  DollarSign,
  Users,
  ArrowUpRight,
  ArrowDownRight,
} from 'lucide-react';

import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from '../../../components/ui/card';

import { Button } from '../../../components/ui/button';
import { Badge } from '../../../components/ui/badge';

import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell,
} from 'recharts';

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '../../../components/ui/dialog';

interface DashboardHomeProps {
  onTabChange?: (tab: string) => void;
}

/**
 * Get the Laravel API base URL.
 *
 * If VITE_API_URL is:
 *   http://127.0.0.1:8000/api
 *
 * this returns:
 *   http://127.0.0.1:8000
 *
 * This is important because product images are normally served
 * from Laravel's /storage directory, not from the Vite server.
 */
const getApiBaseUrl = () => {
  const configuredApiUrl = String(import.meta.env.VITE_API_URL || '').trim();

  if (configuredApiUrl) {
    return configuredApiUrl
      .replace(/\/+$/, '')
      .replace(/\/api$/, '');
  }

  // Local Laravel fallback.
  return 'http://127.0.0.1:8000';
};

/**
 * Convert a product image value into a browser-accessible URL.
 */
const resolveImageUrl = (
  value: unknown,
): string | null => {
  if (!value) {
    return null;
  }

  // Handle strings normally.
  if (typeof value === 'string') {
    const rawValue = value.trim();

    if (!rawValue) {
      return null;
    }

    // Already a complete URL.
    if (
      rawValue.startsWith('http://') ||
      rawValue.startsWith('https://') ||
      rawValue.startsWith('blob:') ||
      rawValue.startsWith('data:')
    ) {
      return rawValue;
    }

    const apiBaseUrl = getApiBaseUrl();

    // Normalize slashes.
    let normalizedValue = rawValue.replace(/\\/g, '/');

    // Remove leading slashes.
    normalizedValue = normalizedValue.replace(/^\/+/, '');

    // Remove Laravel "public/" prefix if it exists.
    normalizedValue = normalizedValue.replace(/^public\//i, '');

    // Remove "storage/" because we will add it consistently below.
    normalizedValue = normalizedValue.replace(/^storage\//i, '');

    // Remove "storage/app/public/" if returned by backend.
    normalizedValue = normalizedValue.replace(
      /^storage\/app\/public\//i,
      '',
    );

    // If the backend somehow returns an API URL, avoid creating
    // /storage/api/... URLs.
    if (normalizedValue.startsWith('api/')) {
      return `${apiBaseUrl}/${normalizedValue}`;
    }

    return `${apiBaseUrl}/storage/${normalizedValue}`;
  }

  /**
   * Some APIs may return image information as an object.
   * Try the common properties.
   */
  if (typeof value === 'object') {
    const imageObject = value as Record<string, unknown>;

    const possibleValues = [
      imageObject.url,
      imageObject.image_url,
      imageObject.imageUrl,
      imageObject.path,
      imageObject.image_path,
      imageObject.imagePath,
    ];

    for (const possibleValue of possibleValues) {
      const resolved = resolveImageUrl(possibleValue);

      if (resolved) {
        return resolved;
      }
    }
  }

  return null;
};

/**
 * Collect every possible image field returned by the backend.
 *
 * This allows the component to try another field if the first
 * image path is invalid.
 */
const getProductImageCandidates = (
  product: any,
): string[] => {
  const values = [
    product?.image_url,
    product?.imageUrl,
    product?.image_path,
    product?.imagePath,
    product?.image,
    product?.photo_url,
    product?.photoUrl,
    product?.photo_path,
    product?.photoPath,
    product?.product_image,
    product?.productImage,
    product?.thumbnail_url,
    product?.thumbnailUrl,
  ];

  const urls = values
    .map((value) => resolveImageUrl(value))
    .filter((value): value is string => Boolean(value));

  return [...new Set(urls)];
};

/**
 * Product image component.
 *
 * It tries all available image URLs before showing the fallback.
 */
function DashboardProductImage({
  candidates,
  alt,
}: {
  candidates: string[];
  alt: string;
}) {
  const [currentIndex, setCurrentIndex] = useState(0);
  const [imageFailed, setImageFailed] = useState(false);

  useEffect(() => {
    setCurrentIndex(0);
    setImageFailed(false);
  }, [candidates.join('|')]);

  const currentSrc = candidates[currentIndex];

  const handleImageError = () => {
    if (currentIndex < candidates.length - 1) {
      setCurrentIndex((previousIndex) => previousIndex + 1);
      return;
    }

    console.error(
      'Unable to load product image:',
      currentSrc,
    );

    setImageFailed(true);
  };

  if (currentSrc && !imageFailed) {
    return (
      <div className="relative aspect-[4/3] w-full overflow-hidden bg-[#F5F1E5]">
        <img
          src={currentSrc}
          alt={alt}
          className="h-full w-full object-cover"
          loading="lazy"
          onError={handleImageError}
        />
      </div>
    );
  }

  return (
    <div
      className="flex aspect-[4/3] w-full items-center justify-center bg-[#0F9488]/10 text-[#0F9488]"
      aria-label="No product image available"
    >
      <Package
        className="h-12 w-12"
        aria-hidden="true"
      />
    </div>
  );
}

export default function DashboardHome({
  onTabChange,
}: DashboardHomeProps) {
  const [showRecommendations, setShowRecommendations] =
    useState(false);

  const [availableProducts, setAvailableProducts] =
    useState<any[]>([]);

  const [productsLoading, setProductsLoading] =
    useState(true);

  // Product selected by clicking a dashboard product image.
  const [viewingProduct, setViewingProduct] =
    useState<any | null>(null);

  /**
   * Load producer products.
   */
  useEffect(() => {
    let mounted = true;

    const loadAvailableProducts = async () => {
      try {
        const { request } = await import('../../../../lib/api');

        const response = await request(
          '/api/producer/dashboard',
        );

        console.log(
          'HarborAI dashboard response:',
          response,
        );

        const products = Array.isArray(
          response?.product_listings,
        )
          ? response.product_listings
          : [];

        if (!mounted) {
          return;
        }

        setAvailableProducts(
          products.filter(
            (product: any) =>
              Number(
                product?.quantity_available ??
                  product?.quantity ??
                  0,
              ) > 0,
          ),
        );
      } catch (error) {
        console.error(
          'Unable to load producer products:',
          error,
        );

        if (mounted) {
          setAvailableProducts([]);
        }
      } finally {
        if (mounted) {
          setProductsLoading(false);
        }
      }
    };

    loadAvailableProducts();

    return () => {
      mounted = false;
    };
  }, []);

  /**
   * Dashboard statistics.
   */
  const stats = [
    {
      label: 'Total Revenue',
      value: '₱84,250',
      change: '+12.5%',
      trend: 'up',
      icon: DollarSign,
      iconBg: 'bg-[#22C55E]/15',
      iconColor: 'text-[#22C55E]',
    },
    {
      label: 'Active Listings',
      value: '12',
      change: '+3',
      trend: 'up',
      icon: Package,
      iconBg: 'bg-[#123C5C]/10',
      iconColor: 'text-[#123C5C]',
    },
    {
      label: 'Pending Orders',
      value: '8',
      change: '-2',
      trend: 'down',
      icon: Users,
      iconBg: 'bg-[#F59E0B]/15',
      iconColor: 'text-[#F59E0B]',
    },
    {
      label: 'Average Price',
      value: '₱125/kg',
      change: '+5.2%',
      trend: 'up',
      icon: TrendingUp,
      iconBg: 'bg-[#0F9488]/15',
      iconColor: 'text-[#0F9488]',
    },
  ];

  /**
   * Revenue chart data.
   */
  const salesData = [
    {
      month: 'Jan',
      revenue: 45000,
    },
    {
      month: 'Feb',
      revenue: 52000,
    },
    {
      month: 'Mar',
      revenue: 48000,
    },
    {
      month: 'Apr',
      revenue: 61000,
    },
    {
      month: 'May',
      revenue: 55000,
    },
    {
      month: 'Jun',
      revenue: 68000,
    },
  ];

  /**
   * Product distribution.
   */
  const productDistribution = [
    {
      name: 'Rice',
      value: 35,
      id: 'rice',
    },
    {
      name: 'Corn',
      value: 25,
      id: 'corn',
    },
    {
      name: 'Fish',
      value: 20,
      id: 'fish',
    },
    {
      name: 'Vegetables',
      value: 15,
      id: 'vegetables',
    },
    {
      name: 'Others',
      value: 5,
      id: 'others',
    },
  ];

  const COLORS = [
    '#22C55E',
    '#F59E0B',
    '#0F9488',
    '#15803D',
    '#123C5C',
  ];

  /**
   * Recent orders.
   */
  const recentOrders = [
    {
      id: 'ORD-001',
      buyer: 'Aparri LGU',
      product: 'Premium Rice',
      quantity: '500 kg',
      status: 'Processing',
      amount: '₱25,000',
    },
    {
      id: 'ORD-002',
      buyer: 'Kadiwa Outlet 1',
      product: 'Fresh Tilapia',
      quantity: '200 kg',
      status: 'Delivered',
      amount: '₱18,000',
    },
    {
      id: 'ORD-003',
      buyer: 'DA-RFO II',
      product: 'Organic Corn',
      quantity: '300 kg',
      status: 'Pending',
      amount: '₱15,000',
    },
  ];

  return (
    <>
      <div className="space-y-6 p-6">

      {/* =========================================================
          WELCOME SECTION
      ========================================================= */}
      <div>
        <h1 className="font-display text-2xl tracking-tight text-[#123C5C] sm:text-3xl">
          Welcome ulit!
        </h1>

        <p className="mt-1 text-[#45586B]">
          Ito ang mga nangyayari sa iyong enterprise ngayon.
        </p>
      </div>

      {/* =========================================================
          STATS GRID
      ========================================================= */}
      <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        {stats.map((stat, idx) => {
          const Icon = stat.icon;

          return (
            <Card
              key={idx}
              className="border border-[#E7E1D0] transition-shadow duration-300 hover:shadow-md"
            >
              <CardContent className="min-w-0 p-3 sm:p-6">
                <div className="mb-2 flex items-center justify-between sm:mb-4">
                  <div
                    className={`flex h-8 w-8 items-center justify-center rounded-full sm:h-10 sm:w-10 ${stat.iconBg}`}
                  >
                    <Icon
                      className={`h-5 w-5 ${stat.iconColor}`}
                    />
                  </div>

                  <div
                    className={`flex items-center gap-1 text-sm ${
                      stat.trend === 'up'
                        ? 'text-[#22C55E]'
                        : 'text-red-600'
                    }`}
                  >
                    {stat.trend === 'up' ? (
                      <ArrowUpRight className="h-4 w-4" />
                    ) : (
                      <ArrowDownRight className="h-4 w-4" />
                    )}

                    <span>{stat.change}</span>
                  </div>
                </div>

                <div>
                  <div className="truncate text-lg font-bold leading-tight text-[#123C5C] sm:text-2xl">
                    {stat.value}
                  </div>

                  <div className="text-xs leading-tight text-[#45586B] sm:text-sm">
                    {stat.label}
                  </div>
                </div>
              </CardContent>
            </Card>
          );
        })}
      </div>

      {/* =========================================================
          MY DIGITAL STALL
      ========================================================= */}
      <Card className="overflow-hidden border-[#E7E1D0] bg-white shadow-sm">

        <CardHeader className="border-b border-[#E7E1D0]/70 bg-[#F5F1E5]/45">
          <div className="flex items-center justify-between gap-3">

            <div>
              <CardTitle className="font-display text-2xl text-[#123C5C]">
                My Digital Stall
              </CardTitle>

              <CardDescription className="mt-2">
                Your products and listings at a glance.
              </CardDescription>
            </div>

            <Button
              variant="outline"
              size="sm"
              onClick={() => onTabChange?.('listings')}
              className="border-[#0F9488] text-[#0F9488] hover:bg-[#0F9488]/10"
            >
              View All Listings
            </Button>

          </div>
        </CardHeader>

        <CardContent className="p-4 sm:p-6">

          {/* LOADING */}
          {productsLoading ? (
            <div className="flex items-center gap-2 text-sm text-[#45586B]">
              <div className="h-4 w-4 animate-spin rounded-full border-2 border-[#0F9488] border-t-transparent" />
              <span>Nilo-load ang mga produkto...</span>
            </div>

          ) : availableProducts.length === 0 ? (

            /* EMPTY STATE */
            <div className="rounded-xl border border-dashed border-[#E7E1D0] bg-[#F5F1E5]/40 p-8 text-center">
              <Package className="mx-auto mb-3 h-10 w-10 text-[#0F9488]" />

              <p className="text-sm font-medium text-[#123C5C]">
                Wala ka pang available na produkto.
              </p>

              <p className="mt-1 text-xs text-[#45586B]">
                Add a product listing to display it here.
              </p>
            </div>

          ) : (

            /* PRODUCT GRID */
            <div className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-3 2xl:grid-cols-4">

              {availableProducts
                .slice(0, 6)
                .map(
                  (
                    product: any,
                    index: number,
                  ) => {

                    const productName =
                      product?.product_name ||
                      product?.name ||
                      'Unnamed Product';

                    const category =
                      product?.product_category ||
                      product?.category ||
                      'General';

                    const unit =
                      product?.unit ||
                      product?.unit_of_measure ||
                      'kg';

                    const price = Number(
                      product?.current_price_per_unit ??
                        product?.price_per_unit ??
                        product?.price ??
                        0,
                    );

                    const quantity =
                      product?.quantity_available ??
                      product?.quantity ??
                      0;

                    const status = String(
                      product?.status || 'Active',
                    ).replace(
                      /^./,
                      (char: string) =>
                        char.toUpperCase(),
                    );

                    /**
                     * IMPORTANT:
                     * Collect all possible image fields.
                     */
                    const imageCandidates =
                      getProductImageCandidates(
                        product,
                      );

                    /**
                     * Temporary debugging information.
                     *
                     * You can see the actual image information
                     * in the browser console.
                     */
                    console.log(
                      `HarborAI product ${productName} image candidates:`,
                      imageCandidates,
                      product,
                    );

                    return (
                      <article
                        key={`${
                          product?.listing_id ??
                          product?.id ??
                          productName
                        }-${index}`}
                        className="overflow-hidden rounded-xl border border-[#E7E1D0] bg-white shadow-sm transition-shadow hover:shadow-md"
                      >

                        {/* =================================================
                            PRODUCT IMAGE
                        ================================================= */}
                        {/* =================================================
                            CLICKABLE PRODUCT IMAGE
                            Clicking the image opens product details.
                        ================================================= */}
                        <button
                          type="button"
                          onClick={() => setViewingProduct(product)}
                          onKeyDown={(event) => {
                            if (
                              event.key === 'Enter' ||
                              event.key === ' '
                            ) {
                              event.preventDefault();
                              setViewingProduct(product);
                            }
                          }}
                          className="group block w-full cursor-pointer border-0 bg-transparent p-0 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-[#22C55E] focus-visible:ring-offset-2"
                          aria-label={`View details for ${productName}`}
                        >
                          <div className="relative overflow-hidden">
                            <DashboardProductImage
                              candidates={imageCandidates}
                              alt={productName}
                            />

                            <div className="pointer-events-none absolute inset-0 flex items-center justify-center bg-[#123C5C]/0 transition-all duration-300 group-hover:bg-[#123C5C]/35">
                              <div className="translate-y-2 rounded-full bg-white/95 px-4 py-2 text-sm font-bold text-[#123C5C] opacity-0 shadow-lg transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100">
                                Click to view details
                              </div>
                            </div>
                          </div>
                        </button>

                        {/* =================================================
                            PRODUCT INFORMATION
                        ================================================= */}
                        <div className="space-y-3 p-4">

                          <div className="flex items-start justify-between gap-3">

                            <div className="min-w-0">
                              <h3 className="truncate font-bold text-[#123C5C]">
                                {productName}
                              </h3>

                              <p className="mt-1 text-sm text-[#45586B]">
                                {category}
                              </p>
                            </div>

                            <Badge className="shrink-0 bg-[#22C55E]/15 text-[#0B4842]">
                              {status}
                            </Badge>

                          </div>

                          <div className="space-y-1 text-sm text-[#45586B]">

                            <p>
                              <span className="font-semibold text-[#123C5C]">
                                Price:
                              </span>{' '}
                              ₱
                              {price.toLocaleString()}{' '}
                              / {unit}
                            </p>

                            <p>
                              <span className="font-semibold text-[#123C5C]">
                                Available:
                              </span>{' '}
                              {quantity} {unit}
                            </p>

                          </div>
                        </div>

                      </article>
                    );
                  },
                )}

            </div>
          )}

        </CardContent>
      </Card>

      {/* =========================================================
          CHARTS
      ========================================================= */}
      <div className="grid gap-6 lg:grid-cols-3">

        {/* REVENUE CHART */}
        <Card className="lg:col-span-2">

          <CardHeader>
            <CardTitle>
              Revenue Trend
            </CardTitle>

            <CardDescription>
              Buwanang revenue sa nakalipas na 6 na buwan
            </CardDescription>
          </CardHeader>

          <CardContent>

            <ResponsiveContainer
              width="100%"
              height={260}
            >
              <LineChart data={salesData}>

                <CartesianGrid
                  strokeDasharray="3 3"
                  stroke="#E7E1D0"
                />

                <XAxis dataKey="month" tick={{ fontSize: 12 }} />

                <YAxis width={48} tick={{ fontSize: 11 }} tickFormatter={(value: number) => `₱${Math.round(value / 1000)}K`} />

                <Tooltip
                  formatter={(value: any) =>
                    `₱${Number(value).toLocaleString()}`
                  }
                />

                <Line
                  type="monotone"
                  dataKey="revenue"
                  stroke="#22C55E"
                  strokeWidth={2}
                />

              </LineChart>
            </ResponsiveContainer>

          </CardContent>
        </Card>

        {/* PRODUCT DISTRIBUTION */}
        <Card>

          <CardHeader>
            <CardTitle>
              Product Mix
            </CardTitle>

            <CardDescription>
              Distribution ayon sa uri ng produkto
            </CardDescription>
          </CardHeader>

          <CardContent>

            <ResponsiveContainer
              width="100%"
              height={300}
            >
              <PieChart>

                <Pie
                  data={productDistribution}
                  cx="50%"
                  cy="50%"
                  labelLine={false}
                  label={({
                    name,
                    percent,
                  }: any) =>
                    `${name} ${(
                      Number(percent) * 100
                    ).toFixed(0)}%`
                  }
                  outerRadius={80}
                  fill="#8884d8"
                  dataKey="value"
                  nameKey="name"
                >

                  {productDistribution.map(
                    (entry, index) => (
                      <Cell
                        key={entry.id}
                        fill={
                          COLORS[
                            index %
                              COLORS.length
                          ]
                        }
                      />
                    ),
                  )}

                </Pie>

                <Tooltip />

              </PieChart>
            </ResponsiveContainer>

          </CardContent>
        </Card>

      </div>

      {/* =========================================================
          RECENT ORDERS
      ========================================================= */}
      <Card>

        <CardHeader>

          <div className="flex items-center justify-between">

            <div>
              <CardTitle>
                Mga Kamakailang Orders
              </CardTitle>

              <CardDescription>
                Pinakabagong activity ng iyong orders
              </CardDescription>
            </div>

            <Button
              variant="outline"
              size="sm"
              onClick={() =>
                onTabChange?.('orders')
              }
            >
              Tingnan Lahat
            </Button>

          </div>

        </CardHeader>

        <CardContent>

          <div className="space-y-4">

            {recentOrders.map(
              (order) => (
                <div
                  key={order.id}
                  className="flex flex-col gap-2 rounded-xl border border-[#E7E1D0] p-3 transition hover:bg-[#F5F1E5] sm:flex-row sm:items-center sm:justify-between sm:p-4"
                >

                  <div className="flex-1">

                    <div className="mb-1 flex min-w-0 items-center justify-between gap-2 sm:justify-start sm:gap-3">

                      <span className="truncate font-semibold text-[#123C5C]">{order.product}</span>

                      <span className="font-medium text-[#123C5C]">
                        {order.id}
                      </span>

                      <Badge
                        className="shrink-0"
                        variant={
                          order.status ===
                          'Delivered'
                            ? 'default'
                            : order.status ===
                                'Processing'
                              ? 'secondary'
                              : 'outline'
                        }
                      >
                        {order.status}
                      </Badge>

                    </div>

                    <div className="text-sm text-[#45586B]">
                      {order.buyer} •{' '}
                      {order.product}
                    </div>

                  </div>

                  <div className="flex items-center justify-between gap-3 text-left sm:block sm:text-right">

                    <div className="font-bold text-[#123C5C]">
                      {order.amount}
                    </div>

                    <div className="text-sm text-[#45586B]">
                      {order.quantity}
                    </div>

                  </div>

                </div>
              ),
            )}

          </div>

        </CardContent>
      </Card>

      {/* =========================================================
          QUICK ACTIONS
      ========================================================= */}
      <div className="grid gap-4 md:grid-cols-3">

        {/* ADD LISTING */}
        <Card className="border-[#22C55E]/30 bg-[#22C55E]/5">

          <CardContent className="p-6">

            <h3 className="mb-2 font-bold text-[#123C5C]">
              Magdagdag ng Bagong Listing
            </h3>

            <p className="mb-4 text-sm text-[#15803D]">
              Mag-list ng bagong produkto sa iyong digital stall
            </p>

            <Button
              className="rounded-full bg-[#22C55E] font-bold hover:bg-[#15803D]"
              onClick={() =>
                onTabChange?.('listings')
              }
            >
              Gumawa ng Listing
            </Button>

          </CardContent>
        </Card>

        {/* MARKET INSIGHTS */}
        <Card className="border-[#0F9488]/30 bg-[#0F9488]/5">

          <CardContent className="p-6">

            <h3 className="mb-2 font-bold text-[#123C5C]">
              Tingnan ang Market Insights
            </h3>

            <p className="mb-4 text-sm text-[#0B4842]">
              Makakuha ng AI-powered market insight cards
            </p>

            <Dialog
              open={showRecommendations}
              onOpenChange={
                setShowRecommendations
              }
            >

              <DialogTrigger asChild>

                <Button
                  variant="outline"
                  className="rounded-full border-[#0F9488] font-bold text-[#0F9488] hover:bg-[#0F9488]/10"
                >
                  View Now
                </Button>

              </DialogTrigger>

              <DialogContent className="max-w-2xl">

                <DialogHeader>

                  <DialogTitle>
                    AI Market Insights
                  </DialogTitle>

                  <DialogDescription>
                    Personalized insights based on your farming data and market trends
                  </DialogDescription>

                </DialogHeader>

                <div className="space-y-4">

                  <div className="rounded-lg bg-[#0F9488]/5 p-4">

                    <h4 className="mb-2 font-semibold text-[#123C5C]">
                      🌾 Crop Optimization
                    </h4>

                    <p className="text-sm text-[#0B4842]">
                      Based on your recent rice production, consider switching 20% of your land to high-yield varieties.
                      This could increase your revenue by 15-25% based on current market prices.
                    </p>

                  </div>

                  <div className="rounded-lg bg-[#22C55E]/5 p-4">

                    <h4 className="mb-2 font-semibold text-[#123C5C]">
                      📈 Market Timing
                    </h4>

                    <p className="text-sm text-[#15803D]">
                      Historical data shows optimal selling time for rice is in 2 weeks.
                      Current trends indicate a 8% price increase expected.
                    </p>

                  </div>

                  <div className="rounded-lg bg-[#123C5C]/5 p-4">

                    <h4 className="mb-2 font-semibold text-[#123C5C]">
                      🔄 Diversification
                    </h4>

                    <p className="text-sm text-[#45586B]">
                      Your enterprise could benefit from adding aquaculture.
                      Local demand for fresh fish is growing by 12% annually.
                    </p>

                  </div>

                  <div className="rounded-lg bg-[#F59E0B]/10 p-4">

                    <h4 className="mb-2 font-semibold text-[#123C5C]">
                      💡 Sustainability
                    </h4>

                    <p className="text-sm text-[#92400E]">
                      Implementing organic farming practices could qualify you for premium pricing
                      and government subsidies worth ₱50,000 annually.
                    </p>

                  </div>

                </div>

              </DialogContent>
            </Dialog>

          </CardContent>
        </Card>

        {/* PROGRAMS */}
        <Card className="border-[#123C5C]/20 bg-[#123C5C]/5">

          <CardContent className="p-6">

            <h3 className="mb-2 font-bold text-[#123C5C]">
              Tingnan ang Programs
            </h3>

            <p className="mb-4 text-sm text-[#45586B]">
              Tingnan ang status ng iyong program eligibility
            </p>

            <Button
              variant="outline"
              className="rounded-full border-[#123C5C] font-bold text-[#123C5C] hover:bg-[#123C5C]/10"
              onClick={() =>
                onTabChange?.('programs')
              }
            >
              Check Now
            </Button>

          </CardContent>
        </Card>

      </div>


      {/* =========================================================
          PRODUCT DETAIL DIALOG
          Matches the clickable-image behavior of My Digital Stall.
      ========================================================= */}
      <Dialog
        open={!!viewingProduct}
        onOpenChange={(open) => {
          if (!open) {
            setViewingProduct(null);
          }
        }}
      >
        <DialogContent className="w-[96vw] max-w-5xl overflow-hidden border-0 bg-[#F8F7F3] p-0 shadow-2xl sm:rounded-2xl">
          {viewingProduct && (() => {
            const productName =
              viewingProduct?.product_name ||
              viewingProduct?.name ||
              'Unnamed Product';

            const category =
              viewingProduct?.product_category ||
              viewingProduct?.category ||
              'General';

            const unit =
              viewingProduct?.unit ||
              viewingProduct?.unit_of_measure ||
              'kg';

            const price = Number(
              viewingProduct?.current_price_per_unit ??
                viewingProduct?.price_per_unit ??
                viewingProduct?.price ??
                0,
            );

            const quantity =
              viewingProduct?.quantity_available ??
              viewingProduct?.quantity ??
              0;

            const status = String(
              viewingProduct?.status || 'Active',
            ).replace(
              /^./,
              (char: string) => char.toUpperCase(),
            );

            const imageCandidates =
              getProductImageCandidates(viewingProduct);

            const description =
              typeof viewingProduct?.description === 'string' &&
              viewingProduct.description.trim()
                ? viewingProduct.description.trim()
                : `Fresh ${productName} available from your HarborAI digital stall.`;

            return (
              <div className="max-h-[90vh] overflow-y-auto">
                <div className="flex items-center justify-between border-b border-[#E7E1D0] bg-[#F8F7F3] px-5 py-4 sm:px-8">
                  <button
                    type="button"
                    onClick={() => setViewingProduct(null)}
                    className="text-sm font-semibold text-[#7C7468] transition-colors hover:text-[#123C5C]"
                  >
                    ← Bumalik sa Dashboard
                  </button>

                  <div className="pr-8 text-right text-xs font-medium uppercase tracking-[0.16em] text-[#9A9287]">
                    Detalye ng Produkto
                  </div>
                </div>

                <div className="grid lg:grid-cols-[1.08fr_0.92fr]">
                  <div className="border-b border-[#E7E1D0] bg-[#F8F7F3] p-5 sm:p-8 lg:border-b-0 lg:border-r">
                    <div className="overflow-hidden rounded-xl bg-[#F1EEE6] shadow-sm">
                      <DashboardProductImage
                        candidates={imageCandidates}
                        alt={productName}
                      />
                    </div>

                    <div className="mt-7">
                      <p className="mb-2 text-xs font-bold uppercase tracking-[0.14em] text-[#8D857A]">
                        About This Item
                      </p>

                      <p className="max-w-2xl text-[15px] leading-7 text-[#45586B]">
                        {description}
                      </p>

                      <div className="mt-6 border-t border-[#E7E1D0] pt-5">
                        <div className="grid gap-4 sm:grid-cols-2">
                          <div>
                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-[#8D857A]">
                              Category
                            </p>
                            <p className="mt-1 font-semibold capitalize text-[#123C5C]">
                              {category}
                            </p>
                          </div>

                          <div>
                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-[#8D857A]">
                              Unit
                            </p>
                            <p className="mt-1 font-semibold text-[#123C5C]">
                              {unit}
                            </p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div className="bg-white p-5 sm:p-8 lg:p-10">
                    <div className="max-w-xl">
                      <p className="text-sm font-bold uppercase tracking-[0.14em] text-[#15803D]">
                        {category}
                      </p>

                      <h2 className="mt-2 break-words font-display text-3xl font-black uppercase leading-[0.98] tracking-tight text-[#2F281D] sm:text-5xl">
                        {productName}
                      </h2>

                      <div className="mt-7 flex flex-wrap items-end gap-x-3 gap-y-1">
                        <span className="font-display text-4xl font-black tracking-tight text-[#15803D] sm:text-5xl">
                          ₱{price.toLocaleString()}
                        </span>

                        <span className="pb-1 text-lg text-[#8D857A]">
                          / {unit}
                        </span>
                      </div>

                      <div className="mt-6 grid grid-cols-2 border-y border-[#E7E1D0]">
                        <div className="border-r border-[#E7E1D0] py-4 pr-4">
                          <p className="text-xs uppercase tracking-[0.1em] text-[#9A9287]">
                            Available
                          </p>
                          <p className="mt-1 text-base font-bold text-[#123C5C]">
                            {Number(quantity).toLocaleString()} {unit}
                          </p>
                        </div>

                        <div className="py-4 pl-4">
                          <p className="text-xs uppercase tracking-[0.1em] text-[#9A9287]">
                            Status
                          </p>
                          <div className="mt-1">
                            <Badge className="border-0 bg-[#22C55E] px-3 py-1 text-xs font-bold text-white">
                              {status}
                            </Badge>
                          </div>
                        </div>
                      </div>

                      <div className="mt-8">
                        <p className="text-xs font-bold uppercase tracking-[0.14em] text-[#9A9287]">
                          Inventory
                        </p>

                        <div className="mt-3 flex items-center justify-between rounded-xl border border-[#E7E1D0] bg-[#F8F7F3] px-5 py-4">
                          <div>
                            <p className="text-sm text-[#7C7468]">
                              Available quantity
                            </p>
                            <p className="mt-1 text-2xl font-black text-[#123C5C]">
                              {Number(quantity).toLocaleString()}
                              <span className="ml-2 text-base font-medium text-[#7C7468]">
                                {unit}
                              </span>
                            </p>
                          </div>

                          <Package className="h-8 w-8 text-[#15803D]" />
                        </div>
                      </div>

                      <div className="mt-7">
                        <Button
                          type="button"
                          onClick={() => setViewingProduct(null)}
                          className="h-12 w-full rounded-lg bg-[#123C5C] font-bold text-white shadow-sm hover:bg-[#0F2F48]"
                        >
                          Isara
                        </Button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            );
          })()}
        </DialogContent>
      </Dialog>

    </div>
    </>
  );
}
