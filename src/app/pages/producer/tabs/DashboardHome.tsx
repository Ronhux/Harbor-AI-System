import { DashboardText, useDashboardCopy } from '../../../i18n/DashboardCopy';
import { useEffect, useState } from 'react';
import { resolveListingImages } from '../../../../lib/image';
import { useLanguage } from '../../../i18n/useLanguage';
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

const getProductImageCandidates = (product: any): string[] => resolveListingImages(product).map(image => image.url);

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
  const copy = useDashboardCopy();
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
      <div className="relative aspect-[4/3] w-full overflow-hidden bg-background">
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
      aria-label={copy("No product image available")}
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
  const copy = useDashboardCopy();
  const { t } = useLanguage();

  const [availableProducts, setAvailableProducts] =
    useState<any[]>([]);

  const [dashboard, setDashboard] = useState<any>(null);
  const [dashboardError, setDashboardError] = useState('');

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



        const products = Array.isArray(
          response?.product_listings,
        )
          ? response.product_listings
          : [];

        if (!mounted) {
          return;
        }

        setDashboardError('');
        setDashboard(response);
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
          setDashboardError('Unable to load dashboard. Please reload to try again.');
          setAvailableProducts([]);
        }
      } finally {
        if (mounted) {
          setProductsLoading(false);
        }
      }
    };

    loadAvailableProducts();
    window.addEventListener('focus', loadAvailableProducts);

    return () => {
      mounted = false;
      window.removeEventListener('focus', loadAvailableProducts);
    };
  }, []);

  /**
   * Dashboard statistics.
   */
  const money = (value: number) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(value);
  const metrics = dashboard?.stats;
  const averages = metrics?.average_prices_by_unit ?? [];
  const stats = [
    { label: t('producer.totalRevenue'), value: money(metrics?.total_revenue ?? 0), key: 'total_revenue', icon: DollarSign },
    { label: t('producer.activeListings'), value: metrics?.active_listings ?? 0, key: 'active_listings', icon: Package },
    { label: t('producer.pendingOrders'), value: metrics?.pending_orders ?? 0, key: 'pending_orders', icon: Users },
    { label: t('producer.avgPrice'), value: averages.length ? averages.map((a: any) => money(a.price) + '/' + a.unit).join(' · ') : '—', key: 'average_listing_price', icon: TrendingUp },
  ].map(stat => {
    const comparison = metrics?.changes?.[stat.key];
    return { ...stat, value: productsLoading ? '…' : dashboardError ? '—' : stat.value,
      change: comparison?.available ? String(comparison.percent) + '%' : null,
      trend: comparison?.direction, iconBg: 'bg-[#0F9488]/10', iconColor: 'text-[#0F9488]' };
  });
  const salesData: {month: string; revenue: number}[] = dashboard?.revenue_by_month ?? [];
  const productDistribution: {name: string; value: number; id: string}[] = (dashboard?.product_mix ?? []).map((row: any) => ({ name: row.category, value: row.listings, id: row.category }));
  const COLORS = ['#22C55E', '#F59E0B', '#0F9488', '#15803D', '#123C5C'];
  const recentOrders: {id: string; buyer: string; product: string; quantity: string; status: string; amount: string}[] = (dashboard?.recent_orders ?? []).map((order: any) => ({
    id: String(order.order_id), buyer: order.buyer?.organization_name || order.buyer?.name,
    product: order.details.map((d: any) => d.product_name).join(', '),
    quantity: order.details.map((d: any) => String(d.quantity) + ' ' + (d.unit || '')).join(', '),
    status: order.fulfillment_status, amount: money(order.producer_subtotal),
  }));

  return (
    <>
      {/* Compact mobile rhythm: tight spacing and padding, restored to
          generous desktop spacing from sm upward. */}
      <div className="space-y-4 p-4 sm:space-y-6 sm:p-6">

      {dashboardError && <p role="alert">{dashboardError}</p>}
      {/* =========================================================
          WELCOME SECTION
      ========================================================= */}
      <div>
        <h1 className="font-display text-2xl tracking-tight text-foreground sm:text-3xl">
          {t('producer.welcome')}
        </h1>

        <p className="mt-1 text-muted-foreground">
          {t('producer.welcomeSub')}
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
              className="border border-border transition-shadow duration-300 hover:shadow-md"
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

                  {stat.change && <div
                    title={metrics?.comparison_label}
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
                  </div>}
                </div>

                <div>
                  <div className="break-words text-lg font-bold leading-tight text-foreground sm:text-2xl">
                    {stat.value}
                  </div>

                  <div className="text-xs leading-tight text-muted-foreground sm:text-sm">
                    {stat.label}
                  </div>
                  {!productsLoading && !dashboardError && !stat.change && <p className="mt-1 text-xs text-muted-foreground"><DashboardText text="No comparable history" /></p>}
                </div>
              </CardContent>
            </Card>
          );
        })}
      </div>

      {/* =========================================================
          MY DIGITAL STALL
      ========================================================= */}
      <Card className="overflow-hidden border-border bg-card shadow-sm">

        <CardHeader className="border-b border-border/70 bg-background/45">
          <div className="flex items-center justify-between gap-3">

            <div>
              <CardTitle className="font-display text-2xl text-foreground">
                {t('producer.digitalStall')}
              </CardTitle>

              <CardDescription className="mt-2">
                {t('producer.yourProductsAtGlance') || copy("Your products and listings at a glance.")}
              </CardDescription>
            </div>

            <Button
              variant="outline"
              size="sm"
              onClick={() => onTabChange?.('listings')}
              className="border-[#0F9488] text-[#0F9488] hover:bg-[#0F9488]/10"
            ><DashboardText text="View All Listings" /></Button>

          </div>
        </CardHeader>

        <CardContent className="p-4 sm:p-6">

          {/* LOADING */}
          {productsLoading ? (
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
              <div className="h-4 w-4 animate-spin rounded-full border-2 border-[#0F9488] border-t-transparent" />
              <span><DashboardText text="Nilo-load ang mga produkto..." /></span>
            </div>

          ) : availableProducts.length === 0 ? (

            /* EMPTY STATE */
            <div className="rounded-xl border border-dashed border-border bg-background/40 p-6 text-center sm:p-8">
              <Package className="mx-auto mb-3 h-10 w-10 text-[#0F9488]" />

              <p className="text-sm font-medium text-foreground"><DashboardText text="Wala ka pang available na produkto." /></p>

              <p className="mt-1 text-xs text-muted-foreground"><DashboardText text="Add a product listing to display it here." /></p>
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


                    return (
                      <article
                        key={`${
                          product?.listing_id ??
                          product?.id ??
                          productName
                        }-${index}`}
                        className="overflow-hidden rounded-xl border border-border bg-card shadow-sm transition-shadow hover:shadow-md"
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
                          aria-label={copy("View details for {0}", [productName])}
                        >
                          <div className="relative overflow-hidden">
                            <DashboardProductImage
                              candidates={imageCandidates}
                              alt={productName}
                            />

                            <div className="pointer-events-none absolute inset-0 flex items-center justify-center bg-[#123C5C]/0 transition-all duration-300 group-hover:bg-[#123C5C]/35">
                              <div className="translate-y-2 rounded-full bg-card/95 px-4 py-2 text-sm font-bold text-foreground opacity-0 shadow-lg transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100"><DashboardText text="Click to view details" /></div>
                            </div>
                          </div>
                        </button>

                        {/* =================================================
                            PRODUCT INFORMATION
                        ================================================= */}
                        <div className="space-y-3 p-4">

                          <div className="flex items-start justify-between gap-3">

                            <div className="min-w-0">
                              <h3 className="truncate font-bold text-foreground">
                                {productName}
                              </h3>

                              <p className="mt-1 text-sm text-muted-foreground">
                                {category}
                              </p>
                            </div>

                            <Badge className="shrink-0 bg-[#22C55E]/15 text-[#0B4842]">
                              {status}
                            </Badge>

                          </div>

                          <div className="space-y-1 text-sm text-muted-foreground">

                            <p>
                              <span className="font-semibold text-foreground"><DashboardText text="Price:" /></span>{' '}
                              ₱
                              {price.toLocaleString()}{' '}
                              / {unit}
                            </p>

                            <p>
                              <span className="font-semibold text-foreground"><DashboardText text="Available:" /></span>{' '}
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
        <div className="grid gap-4 sm:gap-6 lg:grid-cols-3">

        {/* REVENUE CHART */}
        <Card className="lg:col-span-2">

          <CardHeader>
            <CardTitle>
              {t('producer.revenueTrend')}
            </CardTitle>

            <CardDescription><DashboardText text="Buwanang revenue sa nakalipas na 6 na buwan" /></CardDescription>
          </CardHeader>

          <CardContent>

            {!salesData.some(row => row.revenue > 0) ? <p className="py-12 text-center text-sm">{productsLoading ? copy("Loading…") : dashboardError ? copy("Revenue unavailable") : copy("No revenue data yet")}</p> : <ResponsiveContainer
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
            </ResponsiveContainer>}

          </CardContent>
        </Card>

        {/* PRODUCT DISTRIBUTION */}
        <Card>

          <CardHeader>
            <CardTitle>
              {t('producer.productMix')}
            </CardTitle>

            <CardDescription><DashboardText text="Active in-stock listings by category" /></CardDescription>
          </CardHeader>

          <CardContent>

            {!productDistribution.length ? <p className="py-12 text-center text-sm">{productsLoading ? copy("Loading…") : dashboardError ? copy("Product data unavailable") : copy("No product data yet")}</p> : <ResponsiveContainer
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
            </ResponsiveContainer>}

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
              <CardTitle><DashboardText text="Mga Kamakailang Orders" /></CardTitle>

              <CardDescription><DashboardText text="Pinakabagong activity ng iyong orders" /></CardDescription>
            </div>

            <Button
              variant="outline"
              size="sm"
              onClick={() =>
                onTabChange?.('orders')
              }
            ><DashboardText text="Tingnan Lahat" /></Button>

          </div>

        </CardHeader>

        <CardContent>

          <div className="space-y-4">

            {!recentOrders.length && <p className="py-6 text-center text-sm">{productsLoading ? copy("Loading…") : dashboardError ? copy("Orders unavailable") : copy("No orders yet")}</p>}
            {recentOrders.map(
              (order) => (
                <div
                  key={order.id}
                  className="flex flex-col gap-2 rounded-xl border border-border p-3 transition hover:bg-background sm:flex-row sm:items-center sm:justify-between sm:p-4"
                >

                  <div className="flex-1">

                    <div className="mb-1 flex min-w-0 items-center justify-between gap-2 sm:justify-start sm:gap-3">

                      <span className="truncate font-semibold text-foreground">{order.product}</span>

                      <span className="font-medium text-foreground">
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

                    <div className="text-sm text-muted-foreground">
                      {order.buyer} •{' '}
                      {order.product}
                    </div>

                  </div>

                  <div className="flex items-center justify-between gap-3 text-left sm:block sm:text-right">

                    <div className="font-bold text-foreground">
                      {order.amount}
                    </div>

                    <div className="text-sm text-muted-foreground">
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

          <CardContent className="p-4 sm:p-6">

            <h3 className="mb-2 font-bold text-foreground"><DashboardText text="Magdagdag ng Bagong Listing" /></h3>

            <p className="mb-4 text-sm text-[#15803D]"><DashboardText text="Mag-list ng bagong produkto sa iyong digital stall" /></p>

            <Button
              className="rounded-full bg-[#22C55E] font-bold hover:bg-[#15803D]"
              onClick={() =>
                onTabChange?.('listings')
              }
            ><DashboardText text="Gumawa ng Listing" /></Button>

          </CardContent>
        </Card>

        {/* MARKET INSIGHTS */}
        <Card className="border-[#0F9488]/30 bg-[#0F9488]/5">

          <CardContent className="p-4 sm:p-6">

            <h3 className="mb-2 font-bold text-foreground"><DashboardText text="Tingnan ang Market Insights" /></h3>

            <p className="mb-4 text-sm text-[#0B4842]"><DashboardText text="Review available market references. Product Assistant interpretation depends on provider availability." /></p>

            <Button variant="outline" onClick={() => onTabChange?.('insights')}><DashboardText text="View market insights" /></Button>

          </CardContent>
        </Card>

        {/* DEMAND OPPORTUNITIES */}
        <Card className="border-[#123C5C]/20 bg-[#123C5C]/5">

          <CardContent className="p-4 sm:p-6">

            <h3 className="mb-2 font-bold text-foreground"><DashboardText text="Tingnan ang Demand Opportunities" /></h3>

            <p className="mb-4 text-sm text-muted-foreground"><DashboardText text="Review active buyer requests using persisted marketplace data." /></p>

            <Button
              variant="outline"
              className="rounded-full border-[#123C5C] font-bold text-foreground hover:bg-[#123C5C]/10"
              onClick={() =>
                onTabChange?.('demand-opportunities')
              }
            ><DashboardText text="View Requests" /></Button>

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
        <DialogContent className="w-[96vw] max-w-5xl overflow-hidden border-0 bg-card p-0 shadow-2xl sm:rounded-2xl">
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
                <div className="flex items-center justify-between border-b border-border bg-card px-5 py-4 sm:px-8">
                  <button
                    type="button"
                    onClick={() => setViewingProduct(null)}
                    className="text-sm font-semibold text-muted-foreground transition-colors hover:text-foreground"
                  ><DashboardText text="← Bumalik sa Dashboard" /></button>

                  <div className="pr-8 text-right text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground"><DashboardText text="Detalye ng Produkto" /></div>
                </div>

                <div className="grid lg:grid-cols-[1.08fr_0.92fr]">
                  <div className="border-b border-border bg-card p-5 sm:p-8 lg:border-b-0 lg:border-r">
                    <div className="overflow-hidden rounded-xl bg-[#F1EEE6] shadow-sm">
                      <DashboardProductImage
                        candidates={imageCandidates}
                        alt={productName}
                      />
                    </div>

                    <div className="mt-7">
                      <p className="mb-2 text-xs font-bold uppercase tracking-[0.14em] text-muted-foreground"><DashboardText text="About This Item" /></p>

                      <p className="max-w-2xl text-[15px] leading-7 text-muted-foreground">
                        {description}
                      </p>

                      <div className="mt-6 border-t border-border pt-5">
                        <div className="grid gap-4 sm:grid-cols-2">
                          <div>
                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-muted-foreground"><DashboardText text="Category" /></p>
                            <p className="mt-1 font-semibold capitalize text-foreground">
                              {category}
                            </p>
                          </div>

                          <div>
                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-muted-foreground"><DashboardText text="Unit" /></p>
                            <p className="mt-1 font-semibold text-foreground">
                              {unit}
                            </p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div className="bg-card p-5 sm:p-8 lg:p-10">
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

                        <span className="pb-1 text-lg text-muted-foreground">
                          / {unit}
                        </span>
                      </div>

                      <div className="mt-6 grid grid-cols-2 border-y border-border">
                        <div className="border-r border-border py-4 pr-4">
                          <p className="text-xs uppercase tracking-[0.1em] text-muted-foreground"><DashboardText text="Available" /></p>
                          <p className="mt-1 text-base font-bold text-foreground">
                            {Number(quantity).toLocaleString()} {unit}
                          </p>
                        </div>

                        <div className="py-4 pl-4">
                          <p className="text-xs uppercase tracking-[0.1em] text-muted-foreground"><DashboardText text="Status" /></p>
                          <div className="mt-1">
                            <Badge className="border-0 bg-[#22C55E] px-3 py-1 text-xs font-bold text-white">
                              {status}
                            </Badge>
                          </div>
                        </div>
                      </div>

                      <div className="mt-8">
                        <p className="text-xs font-bold uppercase tracking-[0.14em] text-muted-foreground"><DashboardText text="Inventory" /></p>

                        <div className="mt-3 flex items-center justify-between rounded-xl border border-border bg-card px-5 py-4">
                          <div>
                            <p className="text-sm text-muted-foreground"><DashboardText text="Available quantity" /></p>
                            <p className="mt-1 text-2xl font-black text-foreground">
                              {Number(quantity).toLocaleString()}
                              <span className="ml-2 text-base font-medium text-muted-foreground">
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
                        ><DashboardText text="Isara" /></Button>
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
