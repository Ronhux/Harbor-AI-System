import { DashboardText, useDashboardCopy } from '../../../i18n/DashboardCopy';
import { useCallback, useEffect, useState } from 'react';
import { Search, MapPin, CheckCircle2, ShoppingCart } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../components/ui/card';
import { Button } from '../../../components/ui/button';
import { Badge } from '../../../components/ui/badge';
import { Input } from '../../../components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../../../components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '../../../components/ui/dialog';
import { request } from '../../../../lib/api';
import { useLanguage } from '../../../i18n/useLanguage';

type ProductListing = {
  listing_id: number;
  product_name: string;
  product_category?: string;
  category?: string;
  current_price_per_unit?: number;
  price_per_unit?: number;
  unit_of_measure?: string;
  unit?: string;
  quantity_available?: number;
  quantity?: number;
  description?: string;
  location?: string;
  harvest_date?: string;
  expiry_date?: string;
  status?: string;
  image_url?: string;
  image_path?: string;
};

type Producer = {
  id: number;
  name: string;
  type: 'Both' | 'Farmer' | 'Fisher';
  location: string;
  products: string[];
  verified: boolean;
  /**
   * Raw verification text from the registry payload. Displayed verbatim so an
   * unknown or unverified value is never shown as a successful verification.
   */
  verification_status?: string | null;
  activeListingCount: number;

  // Producer profile picture
  image?: string | null;

  raw?: any;
  product_listings?: ProductListing[];
};

type BrowseProducersProps = {
  pendingOrder?: { producerId: number; listingId: number } | null;
  onPendingOrderHandled?: () => void;
  onOpenProducts?: (selection: { listingId: number; draft: { quantity: number; deliveryDate?: string; deliveryArrangement?: string } }) => void;
};

const getApiUrl = (path: string): string => {
  /*
   * IMPORTANT:
   * HarborAI already loads the buyer-producer data through Laravel's
   * same-origin /api routes. Do NOT hard-code 127.0.0.1:8000 here.
   *
   * Using a relative URL keeps the order request on the exact same
   * Laravel/Vite host that is currently serving the application and
   * avoids CORS/network failures caused by a different port.
   */
  const configured = String(import.meta.env.VITE_API_URL || '').trim();

  if (configured) {
    const base = configured.replace(/\/+$/, '');
    return `${base}${path.startsWith('/') ? path : `/${path}`}`;
  }

  return path.startsWith('/') ? path : `/${path}`;
};

const getXsrfToken = (): string | null => {
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
  if (!match) return null;

  try {
    return decodeURIComponent(match[1]);
  } catch {
    return match[1];
  }
};

const getTodayLocal = (): string => {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const extractServerError = async (response: Response): Promise<string> => {
  const contentType = response.headers.get('content-type') || '';
  let data: any = null;
  let rawText = '';

  try {
    if (contentType.includes('application/json')) {
      data = await response.json();
    } else {
      rawText = await response.text();
      try {
        data = JSON.parse(rawText);
      } catch {
        data = null;
      }
    }
  } catch {
    data = null;
  }

  if (data?.errors && typeof data.errors === 'object') {
    const validationMessages = Object.entries(data.errors)
      .flatMap(([field, messages]) => {
        const values = Array.isArray(messages) ? messages : [messages];
        return values.map((message) => `${field}: ${String(message)}`);
      })
      .filter(Boolean);

    if (validationMessages.length > 0) {
      return validationMessages.join(' ');
    }
  }

  if (data?.message) {
    return String(data.message);
  }

  if (data?.error) {
    return String(data.error);
  }

  switch (response.status) {
    case 401:
      return 'Hindi authenticated ang account. Mag-login muli bago mag-order.';
    case 403:
      return 'Walang permission ang account na ito para mag-place ng order.';
    case 404:
      return 'Hindi makita ang order API endpoint. Siguraduhing tama ang Laravel /api/orders route.';
    case 405:
      return 'Hindi supported ang POST method sa /api/orders. Pakicheck ang Laravel route.';
    case 419:
      return 'Nag-expire o nawawala ang CSRF token. I-refresh ang page at subukan muli.';
    case 422:
      return 'Hindi tinanggap ng Laravel ang order data. Tingnan ang validation details at siguraduhing tama ang listing, quantity, date, at address.';
    case 500:
      return 'May server error sa Laravel habang ginagawa ang order. Tingnan ang Laravel log para sa exact error.';
    default:
      if (rawText.trim()) {
        const cleaned = rawText.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        if (cleaned) return cleaned.slice(0, 500);
      }
      return `Hindi na-place ang order. Server error: ${response.status}.`;
  }
};

/**
 * Converts different possible Laravel image fields into a usable browser URL.
 *
 * Supports common fields such as:
 * - profile_image
 * - profile_picture
 * - profile_photo
 * - image_url
 * - image_path
 * - avatar
 * - photo
 * - picture
 *
 * It also checks the nested user object because the producer image
 * may belong to the authenticated user's profile.
 */
const resolveProducerImage = (item: any): string | null => {
  const possibleImage =
    item?.profile_image ||
    item?.profile_picture ||
    item?.profile_photo ||
    item?.image_url ||
    item?.image_path ||
    item?.avatar ||
    item?.photo ||
    item?.picture ||
    item?.user?.profile_image ||
    item?.user?.profile_picture ||
    item?.user?.profile_photo ||
    item?.user?.image_url ||
    item?.user?.image_path ||
    item?.user?.avatar ||
    item?.user?.photo ||
    null;

  if (!possibleImage || typeof possibleImage !== 'string') {
    return null;
  }

  const image = possibleImage.trim();

  if (!image) {
    return null;
  }

  // Full URL
  if (
    image.startsWith('http://') ||
    image.startsWith('https://') ||
    image.startsWith('data:image/')
  ) {
    return image;
  }

  // Laravel public storage
  if (image.startsWith('/storage/')) {
    return image;
  }

  if (image.startsWith('storage/')) {
    return `/${image}`;
  }

  // Public uploads
  if (image.startsWith('/uploads/')) {
    return image;
  }

  if (image.startsWith('uploads/')) {
    return `/${image}`;
  }

  // Other relative paths
  return `/${image.replace(/^\/+/, '')}`;
};

const normalizeProducer = (item: any): Producer => {
  const productListings: ProductListing[] = (Array.isArray(item.product_listings)
    ? item.product_listings
    : []).filter((listing: ProductListing) =>
      ['active', 'available'].includes(String(listing.status || '').toLowerCase()) &&
      Number(listing.quantity_available ?? listing.quantity ?? 0) > 0,
    );

  const products = productListings.map((listing) => listing.product_name).filter(Boolean);

  const type =
    item.type === 'Fisher' || item.type === 'Fisherfolk'
      ? 'Fisher'
      : item.type === 'Farmer'
      ? 'Farmer'
      : 'Both';

  return {
    id: Number(item.id ?? item.producer_id ?? 0),
    // Neutral fallback. A producer with no name on file is labelled as such
    // rather than being given a trust-implying name.
    name: item.name || 'Producer',
    type,
    location: item.location || 'N/A',
    products,
    // Only a value the registry actually supplies marks a producer verified;
    // a missing field stays unverified instead of defaulting to true.
    verified: ['verified', 'approved'].includes(String(item.verification_status ?? '').toLowerCase()),
    verification_status: item.verification_status ?? null,
    activeListingCount: productListings.length,
    image: resolveProducerImage(item),
    raw: item,
    product_listings: productListings,
  };
};

export default function BrowseProducers({ pendingOrder, onPendingOrderHandled, onOpenProducts }: BrowseProducersProps) {
  const copy = useDashboardCopy();
  const { t } = useLanguage();
  const [selectedProducer, setSelectedProducer] = useState<Producer | null>(null);
  const [showOrderDialog, setShowOrderDialog] = useState(false);
  const [producers, setProducers] = useState<Producer[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const displayedProducers = producers.filter((producer) => {
    const query = searchTerm.trim().toLocaleLowerCase();
    if (!query) return true;
    return [producer.name, producer.location, producer.type, ...producer.products]
      .some((value) => String(value || '').toLocaleLowerCase().includes(query));
  });

  // Order states
  const [selectedListingId, setSelectedListingId] = useState<string>('');
  const [orderQuantity, setOrderQuantity] = useState('');
  const [deliveryDate, setDeliveryDate] = useState('');
  const [shippingAddress, setShippingAddress] = useState('');

  // Order submission states
  const [placingOrder, setPlacingOrder] = useState(false);
  const [orderMessage, setOrderMessage] = useState('');
  const [orderError, setOrderError] = useState('');

  const loadProducers = useCallback(async () => {
    setLoading(true);
    setLoadError('');
    try {
      const result = await request('/api/buyer/producers');
      setProducers(Array.isArray(result?.data) ? result.data.map(normalizeProducer) : []);
    } catch (error: any) {
      setLoadError(error?.message || 'Unable to load producers.');
      setProducers([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void loadProducers(); }, [loadProducers]);

  useEffect(() => {
    if (loading || !pendingOrder) return;

    const producer = producers.find((item) => item.id === pendingOrder.producerId);
    if (!producer) return;

    openOrderDialog(producer);
    setSelectedListingId(String(pendingOrder.listingId));
    onPendingOrderHandled?.();
  }, [loading, pendingOrder, producers]);

  /*
   * Get the currently selected product listing.
   *
   * This is important because order_details.listing_id
   * must contain the actual product_listings.listing_id.
   */
  const selectedListing =
    selectedProducer?.product_listings?.find(
      (listing) => String(listing.listing_id) === String(selectedListingId)
    ) || null;

  /*
   * Calculate the current order subtotal.
   */
  const unitPrice = selectedListing
    ? Number(
        selectedListing.current_price_per_unit ??
          selectedListing.price_per_unit ??
          0
      )
    : 0;

  const numericQuantity = Number(orderQuantity || 0);

  const subtotal = unitPrice * numericQuantity;

  /*
   * Open order dialog and reset order fields.
   */
  const openOrderDialog = (producer: Producer) => {
    setSelectedProducer(producer);
    setShowOrderDialog(true);

    setSelectedListingId('');
    setOrderQuantity('');
    setDeliveryDate('');
    setShippingAddress('');

    setOrderMessage('');
    setOrderError('');
  };

  /*
   * Close order dialog.
   */
  const closeOrderDialog = () => {
    if (placingOrder) {
      return;
    }

    setShowOrderDialog(false);
    setOrderMessage('');
    setOrderError('');
  };

  /*
   * Submit the order.
   *
   * Laravel should receive:
   *
   * listing_id
   * quantity
   * shipping_address
   * delivery_date
   *
   * The backend should then create:
   *
   * orders
   * order_details
   */
  const handlePlaceOrder = () => {
    if (!selectedListing) {
      setOrderError('Choose an available product listing to continue.');
      return;
    }

    const quantity = Number(orderQuantity);
    const available = Number(selectedListing.quantity_available ?? selectedListing.quantity ?? 0);
    if (!Number.isFinite(quantity) || quantity <= 0) {
      setOrderError('Enter a quantity greater than zero.');
      return;
    }
    if (quantity > available) {
      setOrderError(`The listing has ${available.toLocaleString()} ${selectedListing.unit_of_measure || selectedListing.unit || 'units'} available.`);
      return;
    }

    setOrderError('');
    onPendingOrderHandled?.();
    onOpenProducts?.({
      listingId: selectedListing.listing_id,
      draft: {
        quantity,
        deliveryDate: deliveryDate || undefined,
        deliveryArrangement: shippingAddress.trim() || undefined,
      },
    });
  };

  return (
    <div className="space-y-6 p-4 sm:p-6">
      <div>
        <h1 className="font-display text-2xl tracking-tight text-foreground sm:text-3xl">
          {t('buyer.browseProducersPage.pageTitle')}
        </h1>

        <p className="mt-1 text-sm text-muted-foreground">
          {t('buyer.browseProducersPage.subtitle')}
        </p>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="p-4">
          <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
            <div className="relative">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input value={searchTerm} onChange={(event) => setSearchTerm(event.target.value)} placeholder={copy("Maghanap ng producer, produkto, o lokasyon...")} className="pl-9" aria-label={copy("Maghanap ng producer, produkto, o lokasyon")} />
            </div>

            <Button type="button" variant="outline" onClick={() => setSearchTerm('')} disabled={!searchTerm}>{t('buyer.browseProducersPage.clearSearch')}</Button>
          </div>
        </CardContent>
      </Card>

      {/* Loading */}
      {loading && (
        <div className="text-center py-10 text-muted-foreground">
          {t('buyer.browseProducersPage.loading')}
        </div>
      )}

      {/* Empty */}
      {!loading && loadError && (
        <Card>
          <CardContent className="flex flex-wrap items-center justify-between gap-3 p-6">
            <p role="alert" className="text-sm text-red-700">{loadError}</p>
            <Button type="button" variant="outline" onClick={() => void loadProducers()}>{t('buyer.browseProducersPage.retry')}</Button>
          </CardContent>
        </Card>
      )}

      {!loading && !loadError && displayedProducers.length === 0 && (
        <Card>
          <CardContent className="p-10 text-center">
            <p className="text-muted-foreground">
              {producers.length ? t('buyer.browseProducersPage.noSearchResults') : t('buyer.browseProducersPage.noProducers')}
            </p>
          </CardContent>
        </Card>
      )}

      {/* Producers Grid */}
      {!loading && !loadError && displayedProducers.length > 0 && (
        <div className="grid grid-cols-2 gap-2.5 sm:gap-4 lg:grid-cols-3 xl:grid-cols-4">
          {displayedProducers.map((producer) => (
            <Card
              key={producer.id}
              className="group min-w-0 overflow-hidden border-border transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg focus-within:ring-2 focus-within:ring-[#0F9488] motion-reduce:transition-none motion-reduce:hover:translate-y-0"
            >
              <CardHeader className="p-0">
                <div className="flex flex-col items-center gap-3 p-3 text-center sm:p-4">
                  <div className="w-16 h-16 shrink-0 overflow-hidden rounded-full border-2 border-white shadow-md bg-gradient-to-br from-[#0F9488] to-[#22C55E] flex items-center justify-center">
                    {producer.image ? (
                      <img
                        src={producer.image}
                        alt={`${producer.name} profile`}
                        className="w-full h-full object-cover"
                        onError={(event) => {
                          event.currentTarget.style.display = 'none';

                          const fallback =
                            event.currentTarget.parentElement?.querySelector(
                              '[data-avatar-fallback]'
                            ) as HTMLElement | null;

                          if (fallback) {
                            fallback.classList.remove('hidden');
                          }
                        }}
                      />
                    ) : null}

                    <span
                      data-avatar-fallback
                      className={`text-white font-bold text-2xl ${
                        producer.image ? 'hidden' : ''
                      }`}
                    >
                      {producer.name.charAt(0).toUpperCase()}
                    </span>
                  </div>

                  <div className="min-w-0 w-full">
                    <div className="flex items-center justify-center gap-1.5">
                      <CardTitle className="line-clamp-2 min-w-0 text-sm font-bold text-foreground sm:text-base">
                        {producer.name}
                      </CardTitle>

                      {producer.verified && (
                        <CheckCircle2
                          className="w-4 h-4 shrink-0 text-green-600"
                          aria-label={copy("Verified")}
                        />
                      )}
                    </div>

                    <div className="mt-1 flex items-center justify-center gap-1 text-xs text-muted-foreground">
                      <MapPin className="w-3 h-3 shrink-0" />
                      <span className="line-clamp-1">{producer.location}</span>
                    </div>

                    <div className="mt-2 flex justify-center">
                      <Badge variant="secondary" className="text-[0.65rem]">
                        {producer.type}
                      </Badge>
                    </div>
                  </div>
                </div>
              </CardHeader>

              <CardContent className="space-y-3 p-3 pt-0 sm:p-4 sm:pt-0">
                {/* Compact product summary: "Banana, Camote, Palay +3" */}
                  <div>
                    <p className="mb-1 text-[0.65rem] font-bold uppercase tracking-wide text-muted-foreground">
                      {t('buyer.browseProducersPage.productsHeader')}
                    </p>

                    {producer.products.length > 0 ? (
                      <p className="line-clamp-2 text-xs leading-snug text-foreground sm:text-sm" title={producer.products.join(', ')}>
                        {producer.products.slice(0, 3).join(', ')}
                        {producer.products.length > 3
                          ? ` +${producer.products.length - 3}`
                          : ''}
                      </p>
                    ) : (
                      <p className="text-xs italic text-muted-foreground sm:text-sm">
                        {t('buyer.browseProducersPage.noActiveListings')}
                      </p>
                    )}
                  </div>

                  <div className="flex items-center justify-between rounded-lg bg-card px-3 py-2 text-xs sm:text-sm">
                    <span className="text-muted-foreground">{t('buyer.browseProducersPage.availableListings')}</span>
                    <span className="font-bold text-foreground">{producer.activeListingCount}</span>
                  </div>

                  <div className="flex flex-col gap-2">
                    <Button
                      className="min-h-11 w-full"
                      variant="outline"
                      onClick={() =>
                        setSelectedProducer(producer)
                      }
                    >
                      {t('buyer.browseProducersPage.viewProfile')}
                    </Button>

                    <Button
                      className="min-h-11 w-full bg-[#0F9488] font-bold text-white hover:bg-[#0D7F76]"
                      onClick={() => openOrderDialog(producer)}
                      disabled={producer.activeListingCount === 0}
                    >
                      <ShoppingCart className="w-4 h-4 mr-2" aria-hidden="true" />
                      {t('buyer.browseProducersPage.placeOrder')}
                    </Button>
                  </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      {/* Producer Profile Dialog */}
      <Dialog
        open={!!selectedProducer && !showOrderDialog}
        onOpenChange={() => setSelectedProducer(null)}
      >
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>
              {t('buyer.browseProducersPage.dialogTitle')}
            </DialogTitle>

            <DialogDescription>
              {t('buyer.browseProducersPage.dialogDescription').replace('{name}', selectedProducer?.name ?? '')}
            </DialogDescription>
          </DialogHeader>

          {selectedProducer && (
            <div className="space-y-4">
              <div className="text-center">
                <div className="w-24 h-24 rounded-full overflow-hidden flex-shrink-0 border-4 border-white shadow-lg bg-gradient-to-br from-blue-400 to-green-500 flex items-center justify-center mx-auto mb-4">
                  {selectedProducer.image ? (
                    <img
                      src={selectedProducer.image}
                      alt={`${selectedProducer.name} profile`}
                      className="w-full h-full object-cover"
                      onError={(event) => {
                        event.currentTarget.style.display = 'none';

                        const fallback =
                          event.currentTarget.parentElement?.querySelector(
                            '[data-profile-fallback]'
                          ) as HTMLElement | null;

                        if (fallback) {
                          fallback.classList.remove('hidden');
                        }
                      }}
                    />
                  ) : null}

                  <span
                    data-profile-fallback
                    className={`text-white font-bold text-4xl ${
                      selectedProducer.image ? 'hidden' : ''
                    }`}
                  >
                    {selectedProducer.name.charAt(0).toUpperCase()}
                  </span>
                </div>

                <h3 className="text-xl font-bold">
                  {selectedProducer.name}
                </h3>

                <p className="text-muted-foreground">
                  {selectedProducer.type} • {selectedProducer.location}
                </p>
                {selectedProducer.raw?.contact_number && <p className="text-sm text-muted-foreground">{t('buyer.browseProducersPage.contactLabel')} {selectedProducer.raw.contact_number}</p>}
                {selectedProducer.raw?.email && <p className="break-all text-sm text-muted-foreground">{t('buyer.browseProducersPage.emailLabel')} {selectedProducer.raw.email}</p>}
                {selectedProducer.raw?.primary_product_type && <p className="text-sm text-muted-foreground">{t('buyer.browseProducersPage.primaryProductLabel')} {selectedProducer.raw.primary_product_type}</p>}
              </div>

              <div className="space-y-3">
                <div className="flex justify-between">
                  <span className="text-muted-foreground">
                    {t('buyer.browseProducersPage.productsLabel')}
                  </span>

                  <div className="flex gap-1 flex-wrap justify-end">
                    {selectedProducer.products.map(
                      (product, idx) => (
                        <Badge
                          key={idx}
                          variant="outline"
                        >
                          {product}
                        </Badge>
                      )
                    )}
                  </div>
                </div>

                <div className="flex justify-between">
                  <span className="text-muted-foreground">{t('buyer.browseProducersPage.availableListingsLabel')}</span>
                  <span className="font-bold">{selectedProducer.activeListingCount}</span>
                </div>

                <div className="flex items-center justify-between gap-3">
                  <span className="text-muted-foreground">{t('buyer.browseProducersPage.verificationLabel')}</span>

                  {selectedProducer.verified ? (
                    <span className="inline-flex items-center gap-1.5 font-bold text-green-700">
                      <CheckCircle2 className="w-5 h-5" aria-hidden="true" />
                      {selectedProducer.verification_status || copy("Verified")}
                    </span>
                  ) : (
                    <span className="text-sm font-semibold text-muted-foreground">
                      {selectedProducer.verification_status || t('buyer.browseProducersPage.notConfirmedByRegistry')}
                    </span>
                  )}
                </div>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>

      {/* Order Dialog */}
      <Dialog
        open={showOrderDialog}
        onOpenChange={closeOrderDialog}
      >
        <DialogContent className="w-[calc(100vw-2rem)] max-w-xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle><DashboardText text="Contact Producer to Negotiate" /></DialogTitle>

            <DialogDescription>
              {t('buyer.browseProducersPage.orderDialogDescription')}
            </DialogDescription>
          </DialogHeader>

          {selectedProducer && (
            <div className="space-y-4">

                <div className="p-4 bg-muted rounded-lg">
                <h4 className="font-bold mb-2">
                  {t('buyer.browseProducersPage.selectedProducerLabel')}
                </h4>

                <p className="text-sm text-muted-foreground">
                  {selectedProducer.name}
                </p>

                <p className="text-sm text-muted-foreground">
                  {selectedProducer.location}
                </p>
              </div>

              <div className="space-y-3">

                {/* Product */}
                <div>
                  <label className="text-sm font-medium">
                    {t('buyer.browseProducersPage.productLabel')}
                  </label>

                  <Select
                    value={selectedListingId}
                    onValueChange={(value) => {
                      setSelectedListingId(value);
                      setOrderQuantity('');
                      setOrderError('');
                    }}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder={t('buyer.browseProducersPage.chooseProductPlaceholder')} />
                    </SelectTrigger>

                    <SelectContent>
                      {selectedProducer.product_listings &&
                      selectedProducer.product_listings.length > 0 ? (
                        selectedProducer.product_listings.map(
                          (listing) => (
                            <SelectItem
                              key={listing.listing_id}
                              value={String(listing.listing_id)}
                            >
                              {listing.product_name}
                            </SelectItem>
                          )
                        )
                      ) : (
                        selectedProducer.products.map(
                          (product, idx) => (
                            <SelectItem
                              key={idx}
                              value={product.toLowerCase()}
                            >
                              {product}
                            </SelectItem>
                          )
                        )
                      )}
                    </SelectContent>
                  </Select>
                </div>

                {/* Selected Product Information */}
                {selectedListing && (
                  <div className="p-3 bg-blue-50 border border-blue-100 rounded-lg space-y-1">
                    <div className="flex justify-between text-sm">
                      <span className="text-muted-foreground">
                        {t('buyer.browseProducersPage.productDetailLabel')}
                      </span>

                      <span className="font-medium">
                        {selectedListing.product_name}
                      </span>
                    </div>

                    <div className="flex justify-between text-sm">
                      <span className="text-muted-foreground">
                        {t('buyer.browseProducersPage.priceLabel')}
                      </span>

                      <span className="font-medium">
                        ₱{unitPrice.toLocaleString()}
                        {selectedListing.unit_of_measure
                          ? `/${selectedListing.unit_of_measure}`
                          : selectedListing.unit
                          ? `/${selectedListing.unit}`
                          : ''}
                      </span>
                    </div>

                    {selectedListing.quantity_available !==
                      undefined && (
                      <div className="flex justify-between text-sm">
                        <span className="text-muted-foreground">
                          {t('buyer.browseProducersPage.availableLabel')}
                        </span>

                        <span className="font-medium">
                          {selectedListing.quantity_available}
                        </span>
                      </div>
                    )}
                  </div>
                )}

                {/* Quantity */}
                <div>
                  <label className="text-sm font-medium">
                    {t('buyer.browseProducersPage.quantityLabel').replace('{unit}', selectedListing?.unit_of_measure || selectedListing?.unit || 'units')}
                  </label>

                  <Input
                    type="number"
                    min="0.01"
                    step="0.01"
                    placeholder={copy("Ilagay ang quantity")}
                    value={orderQuantity}
                    onChange={(event) =>
                      setOrderQuantity(event.target.value)
                    }
                  />
                </div>

                {/* Delivery Date */}
                <div>
                  <label className="text-sm font-medium">
                    {t('buyer.browseProducersPage.deliveryDateLabel')}
                  </label>

                  <Input
                    type="date"
                    min={getTodayLocal()}
                    value={deliveryDate}
                    onChange={(event) =>
                      setDeliveryDate(event.target.value)
                    }
                  />
                </div>

                {/* Shipping Address */}
                <div>
                  <label className="text-sm font-medium">
                    {t('buyer.browseProducersPage.shippingAddressLabel')}
                  </label>

                  <Input
                    type="text"
                    placeholder={t('buyer.browseProducersPage.shippingAddressPlaceholder')}
                    value={shippingAddress}
                    onChange={(event) =>
                      setShippingAddress(event.target.value)
                    }
                  />
                </div>

                {/* Order Total */}
                {selectedListing &&
                  numericQuantity > 0 && (
                    <div className="p-4 bg-green-50 border border-green-100 rounded-lg">
                      <div className="flex justify-between">
                        <span className="text-muted-foreground">
                          {t('buyer.browseProducersPage.unitPriceLabel')}
                        </span>

                        <span className="font-medium">
                          ₱{unitPrice.toLocaleString()}
                        </span>
                      </div>

                      <div className="flex justify-between">
                        <span className="text-muted-foreground">
                          {t('buyer.browseProducersPage.quantityLabel2')}
                        </span>

                        <span className="font-medium">
                          {numericQuantity}
                        </span>
                      </div>

                      <div className="flex justify-between pt-2 mt-2 border-t">
                        <span className="font-bold">
                          {t('buyer.browseProducersPage.totalLabel')}
                        </span>

                        <span className="font-bold text-green-600 text-lg">
                          ₱{subtotal.toLocaleString()}
                        </span>
                      </div>
                    </div>
                  )}

                {/* Success Message */}
                {orderMessage && (
                  <div className="p-3 bg-green-100 border border-green-200 text-green-700 rounded-lg text-sm">
                    {orderMessage}
                  </div>
                )}

                {/* Error Message */}
                {orderError && (
                  <div className="p-3 bg-red-100 border border-red-200 text-red-700 rounded-lg text-sm leading-relaxed">
                    <div className="font-semibold">{t('buyer.browseProducersPage.cannotContinue')}</div>
                    <div className="mt-1">{orderError}</div>
                  </div>
                )}
              </div>

                <div className="flex gap-2 pt-4">
                <Button
                  type="button"
                  variant="outline"
                  onClick={closeOrderDialog}
                  className="flex-1"
                  disabled={placingOrder}
                >
                  {t('buyer.browseProducersPage.cancelButton')}
                </Button>

                <Button
                  type="button"
                  className="flex-1 bg-blue-600 hover:bg-blue-700"
                  onClick={handlePlaceOrder}
                  disabled={placingOrder}
                >
                  {placingOrder
                    ? t('buyer.browseProducersPage.loadingOrder')
                    : t('buyer.browseProducersPage.continueToNegotiation')}
                </Button>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
}
