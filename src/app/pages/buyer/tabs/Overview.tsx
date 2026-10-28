import { DashboardText, useDashboardCopy } from '../../../i18n/DashboardCopy';
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import {
  Users,
  TrendingUp,
  Package,
  AlertCircle,
  ShoppingBag,
  Search,
  MapPin,
  Sprout,
  RefreshCw,
} from 'lucide-react';
import { request } from '../../../../lib/api';
import { resolveListingImages } from '../../../../lib/image';
import { ImageWithFallback } from '../../../components/figma/ImageWithFallback';
import { Badge } from '../../../components/ui/badge';
import { Button } from '../../../components/ui/button';
import { Card, CardContent } from '../../../components/ui/card';

/**
 * Overview uses the same registered `/api/buyer/producers` response as Browse
 * Producers. Every number shown here is derived from those records.
 *
 * There is no aggregate buyer-stats endpoint registered, so the dashboard
 * deliberately avoids the invented counters and static demand chart the
 * previous version displayed: those numbers were illustrative, not data.
 */
type ProducerListing = {
  listing_id: number;
  product_name: string;
  product_category?: string | null;
  category?: string | null;
  price_per_unit: number | string | null;
  unit?: string | null;
  unit_of_measure?: string | null;
  quantity_available: number | null;
  quantity?: number | null;
  status?: string | null;
  location?: string | null;
  description?: string | null;
  producer?: { name?: string; locality?: string; location?: string } | null;
  producer_id?: number;
  images?: unknown;
  image_url?: unknown;
  image_path?: unknown;
  raw?: any;
};

type Props = {
  onViewAllProducts: () => void;
  onOrderProduct: (order: {
    productId: number;
    producerId: number;
    listingId: number;
    shippingAddress?: string;
  }) => void;
};

export default function Overview({ onViewAllProducts, onOrderProduct }: Props) {
  const copy = useDashboardCopy();
  const [availableProducts, setAvailableProducts] = useState<ProducerListing[]>([]);
  const [productsLoading, setProductsLoading] = useState(true);
  const [productsError, setProductsError] = useState('');
  const [searchTerm, setSearchTerm] = useState('');

  /**
   * Loads the registered buyer producer endpoint with the shared request
   * helper so auth headers and Sanctum cookies match the rest of the app.
   */
  useEffect(() => {
    const controller = new AbortController();

    const load = async () => {
      setProductsLoading(true);
      setProductsError('');
      try {
        const result = await request('/api/buyer/producers', { signal: controller.signal });
        const rows: ProducerListing[] = Array.isArray(result?.data) ? result.data : [];

        const listings = rows.flatMap((producer: any) =>
          (Array.isArray(producer?.product_listings) ? producer.product_listings : []).map(
            (listing: any) => ({
              ...listing,
              producer: { name: producer?.name, locality: producer?.locality ?? producer?.location },
              producer_id: producer?.id ?? producer?.producer_id,
              producer_verified: producer?.verified,
              raw: producer,
            }),
          ),
        );

        const inStock = listings.filter((listing) =>
          ['active', 'available'].includes(String(listing.status ?? '').toLowerCase()) &&
          Number(listing.quantity_available ?? listing.quantity ?? 0) > 0,
        );

        setAvailableProducts(inStock);
      } catch (error: any) {
        if ((error as Error)?.name === 'AbortError' || controller.signal.aborted) return;
        setProductsError(error?.message || 'Unable to load available products right now.');
        setAvailableProducts([]);
      } finally {
        if (!controller.signal.aborted) setProductsLoading(false);
      }
    };

    void load();
    return () => controller.abort();
  }, []);

  const products = availableProducts;

  const filteredProducts = useMemo(() => {
    const needle = searchTerm.trim().toLowerCase();
    if (!needle) return products;
    return products.filter((p) =>
      [p.product_name, p.product_category, p.category, p.producer?.name, p.location]
        .some((value) => String(value ?? '').toLowerCase().includes(needle)),
    );
  }, [products, searchTerm]);

  /**
   * Derived counts replace the previous hardcoded statistic cards, so the
   * numbers always describe the same records the marketplace list is read from.
   */
  const metricSummary = useMemo(() => {
    const categories = new Set(
      products.map((p) => p.product_category ?? p.category ?? '').filter(Boolean),
    );
    const locations = new Set(
      products.map((p) => p.producer?.locality ?? p.producer?.location ?? p.location ?? '').filter(Boolean),
    );

    return {
      availableProducts: products.length,
      localProducers: new Set(products.map((p) => p.producer_id).filter(Boolean)).size,
      categories: categories.size,
      locations: locations.size,
    };
  }, [products]);

  const handleOrder = (p: ProducerListing & { producer_verified?: boolean }) => {
    onOrderProduct({
      productId: p.producer_id ?? 0,
      producerId: p.producer_id ?? 0,
      listingId: p.listing_id,
      shippingAddress: undefined,
    });
  };

  const handleSearch = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
  };

  const summaryCards = [
    ['Available products', metricSummary.availableProducts, Package],
    ['Active producers', metricSummary.localProducers, Users],
    ['Categories offered', metricSummary.categories, Sprout],
    ['Localities covered', metricSummary.locations, MapPin],
  ] as const;

  return (
    <div className="min-h-full space-y-6 overflow-x-hidden bg-[#F8F8F5] px-4 py-8 md:px-8 lg:px-12">

      <div className="mx-auto max-w-[1440px] space-y-6">
        {/* Summary metrics */}
        <div className="grid grid-cols-2 gap-3 sm:gap-5 xl:grid-cols-4">
          {summaryCards.map(([label, value, Icon]) => (
            <Card key={copy(label)} className="border-border bg-card shadow-sm">
              <CardContent className="p-4">
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <p className="text-xs tracking-[0.08em] text-muted-foreground sm:text-sm">{copy(label)}</p>
                    <div className="mt-1.5 text-2xl font-black text-foreground sm:text-3xl">
                      {metricSummary.availableProducts > 0 ? value : 0}
                    </div>
                  </div>

                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[#E3F4ED] text-[#158C64]">
                    <Icon className="h-5 w-5" strokeWidth={2} aria-hidden="true" />
                  </div>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>

        <div className="grid gap-5 lg:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">
          <Card className="min-w-0 overflow-hidden border-border bg-card shadow-sm">
            <div className="p-5 sm:p-7">

              <h2 className="font-display text-2xl text-foreground sm:text-3xl"><DashboardText text="Welcome to HarborAI" /></h2>

              <p className="mt-2 max-w-2xl text-base text-muted-foreground sm:text-lg"><DashboardText text="Discover local agricultural products from Aparri producers, negotiate directly with them, and manage your institution's supply needs." /></p>

              <div className="mt-6 grid gap-3 sm:grid-cols-2">
                <Button
                  onClick={() => {
                    if (products.length > 0) handleOrder(products[0]);
                    else onViewAllProducts();
                  }}
                  className="min-h-11 justify-start rounded-lg bg-[#123C5C] px-4 font-bold hover:bg-[#0d2e47]"
                >
                  <ShoppingBag className="mr-2 h-5 w-5" aria-hidden="true" /><DashboardText text="Start Negotiation" /></Button>

                <Button
                  variant="outline"
                  onClick={onViewAllProducts}
                  className="min-h-11 justify-start rounded-lg px-4 font-bold"
                >
                  <Search className="mr-2 h-5 w-5" aria-hidden="true" /><DashboardText text="Browse All Products" /></Button>
              </div>
            </div>
          </Card>

          {/* Data provenance notice: replaces the previous static demand chart */}
          <Card className="min-w-0 border-border bg-card shadow-sm">
            <CardContent className="space-y-4 p-5 sm:p-6">
              <div className="flex items-start gap-3">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[#FDF0D7] text-[#B8202B]">
                  <TrendingUp className="h-5 w-5" aria-hidden="true" />
                </div>

                <div>
                  <h3 className="font-display text-lg text-foreground sm:text-xl"><DashboardText text="How these numbers are produced" /></h3>
                  <p className="mt-1 text-sm leading-relaxed text-muted-foreground"><DashboardText text="The stats above come from your" /><strong><DashboardText text="active producer marketplace" /></strong><DashboardText text=": available products, active producers, categories covered, and localities represented." /></p>
                </div>
              </div>

              <div className="rounded-lg bg-[#F8F7F1] p-4">
                <p className="text-xs font-semibold text-muted-foreground sm:text-sm"><DashboardText text="HarborAI shows marketplace records only. It does not publish a statistical forecast of institutional demand or a guaranteed market price, and historic values are not treated as a live price." /></p>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Product list */}
        <Card className="border-border bg-card shadow-sm">
          <div className="p-5 sm:p-6">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
              <h3 className="font-display text-lg font-bold text-foreground sm:text-xl"><DashboardText text="Recently Listed Products" /></h3>
              <Badge className="rounded-full border-[#CBE6D1] bg-[#E3F4ED] px-3 py-1 text-[#158C64]">
                {filteredProducts.length} {filteredProducts.length === 1 ? copy("result") : copy("results")}
              </Badge>
            </div>

            <form onSubmit={handleSearch} className="relative mb-5">
              <Search
                className="pointer-events-none absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
              />
              <input
                value={searchTerm}
                onChange={(event) => setSearchTerm(event.target.value)}
                type="search"
                placeholder={copy("Maghanap ng produkto, kategorya, o lokasyon...")}
                aria-label={copy("Hanapin ang produkto, kategorya, o lokasyon")}
                className="h-11 min-h-11 w-full rounded-lg border border-[#DCD8D2] bg-card pl-10 pr-3 text-sm text-foreground outline-none placeholder:text-[#9AA0A6] focus-visible:border-[#123C5C] focus-visible:ring-[3px] focus-visible:ring-[#123C5C]/25"
              />
            </form>

            {productsLoading ? (
              <p className="p-6 text-center text-muted-foreground"><DashboardText text="Nilo-load ang available products..." /></p>
            ) : productsError ? (
              <div className="flex flex-wrap items-center justify-between gap-3 p-4">
                <p role="alert" className="flex items-center gap-2 text-sm text-red-700">
                  <AlertCircle className="h-4 w-4" aria-hidden="true" />
                  {productsError}
                </p>
                <Button variant="outline" onClick={() => window.location.reload()}>
                  <RefreshCw className="mr-2 h-4 w-4" aria-hidden="true" /><DashboardText text="Subukan muli" /></Button>
              </div>
            ) : filteredProducts.length === 0 ? (
              <div className="p-6 text-center text-muted-foreground sm:p-10">
                <ShoppingBag className="mx-auto mb-3 h-10 w-10 text-[#9AA0A6]" aria-hidden="true" /><DashboardText text="Walang nakitang available products." /></div>
            ) : (
              <div className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-3">
                {filteredProducts.slice(0, 8).map((product) => {
                  const images = resolveListingImages(product);
                  const price = Number(product.price_per_unit ?? 0);
                  const unit = product.unit ?? product.unit_of_measure ?? 'unit';
                  const quantity = Number(product.quantity_available ?? product.quantity ?? 0);

                  return (
                    <article
                      key={product.listing_id}
                      className="group flex min-w-0 flex-col overflow-hidden rounded-2xl border border-border bg-card transition hover:-translate-y-0.5 hover:shadow-md"
                    >
                      <div className="aspect-[4/3] overflow-hidden bg-[#EAF4EF]">
                        {images[0]?.url ? (
                          <ImageWithFallback
                            key={images[0].url}
                            src={images[0].url}
                            alt={product.product_name}
                            loading="lazy"
                            className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                          />
                        ) : (
                          <div className="flex h-full w-full items-center justify-center text-[#158C64]">
                            <Sprout className="h-9 w-9" aria-hidden="true" />
                          </div>
                        )}
                      </div>

                      <div className="flex flex-1 flex-col p-3">
                        <h4 className="line-clamp-2 min-h-10 font-bold text-foreground">
                          {product.product_name || product.category || copy("Product")}
                        </h4>

                        <p className="mt-1 font-black text-[#158C64]">
                          ₱{price.toLocaleString()}{' '}
                          <span className="text-xs font-medium text-muted-foreground">/ {unit}</span>
                        </p>

                        <p className="mt-0.5 text-xs text-muted-foreground">
                          {quantity.toLocaleString()} {unit}{' '}<DashboardText text="available" /></p>

                        <p className="mt-1 flex items-center gap-1 truncate text-xs font-semibold text-foreground">
                          {product.producer?.name || copy("Producer")}
                        </p>

                        <p className="flex items-center gap-1 truncate text-xs text-muted-foreground">
                          <MapPin size={13} aria-hidden="true" />
                          {product.producer?.locality ?? product.producer?.location ?? product.location ?? copy("Location not specified")}
                        </p>

                        <div className="mt-3">
                          <Button
                            variant="outline"
                            onClick={() => handleOrder(product)}
                            className="min-h-11 w-full border-[#158C64] font-bold text-[#158C64] hover:bg-[#E3F4ED]"
                          ><DashboardText text="Contact & Negotiate" /></Button>
                        </div>
                      </div>
                    </article>
                  );
                })}
              </div>
            )}

            {!productsLoading && !productsError && filteredProducts.length > 8 && (
              <div className="mt-4">
                <Button variant="ghost" onClick={onViewAllProducts} className="min-h-11 w-full"><DashboardText text="View all products in Browse Products" /></Button>
              </div>
            )}
          </div>
        </Card>
      </div>
    </div>
  );
}
