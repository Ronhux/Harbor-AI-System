import { useEffect, useState } from 'react';
import { Search, MapPin, CheckCircle2, Star, ShoppingCart } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../components/ui/card';
import { Button } from '../../../components/ui/button';
import { Badge } from '../../../components/ui/badge';
import { Input } from '../../../components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../../../components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '../../../components/ui/dialog';

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
  rating: number;
  orders: number;
  verified: boolean;
  price: string;
  raw?: any;
  product_listings?: ProductListing[];
};

type BrowseProducersProps = {
  pendingOrder?: { producerId: number; listingId: number } | null;
  onPendingOrderHandled?: () => void;
};

const normalizeProducer = (item: any): Producer => {
  const productListings: ProductListing[] = Array.isArray(item.product_listings)
    ? item.product_listings
    : [];

  const products = Array.isArray(item.products)
    ? item.products
    : productListings
        .map((listing: ProductListing) => listing.product_name)
        .filter(Boolean);

  const type =
    item.type === 'Fisher' || item.type === 'Fisherfolk'
      ? 'Fisher'
      : item.type === 'Farmer'
      ? 'Farmer'
      : 'Both';

  return {
    id: Number(item.id ?? item.producer_id ?? 0),
    name: item.name || 'Verified Producer',
    type,
    location: item.location || 'N/A',
    products,
    rating: Number(item.rating ?? 4.8),
    orders: Number(item.orders ?? (products.length * 3 || 0)),
    verified: Boolean(item.verified ?? true),
    price: item.price || '₱0/kg',
    raw: item,
    product_listings: productListings,
  };
};

export default function BrowseProducers({ pendingOrder, onPendingOrderHandled }: BrowseProducersProps) {
  const [selectedProducer, setSelectedProducer] = useState<Producer | null>(null);
  const [showOrderDialog, setShowOrderDialog] = useState(false);
  const [producers, setProducers] = useState<Producer[]>([]);
  const [loading, setLoading] = useState(true);

  // Order states
  const [selectedListingId, setSelectedListingId] = useState<string>('');
  const [orderQuantity, setOrderQuantity] = useState('');
  const [deliveryDate, setDeliveryDate] = useState('');
  const [shippingAddress, setShippingAddress] = useState('');

  // Order submission states
  const [placingOrder, setPlacingOrder] = useState(false);
  const [orderMessage, setOrderMessage] = useState('');
  const [orderError, setOrderError] = useState('');

  useEffect(() => {
    const fetchProducers = async () => {
      try {
        const token = localStorage.getItem('authToken');

        const response = await fetch('/api/buyer/producers', {
          headers: {
            Accept: 'application/json',
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
          },
        });

        if (!response.ok) {
          throw new Error('Unable to load producers');
        }

        const result = await response.json();

        const nextProducers = Array.isArray(result.data)
          ? result.data.map(normalizeProducer)
          : [];

        setProducers(nextProducers);
      } catch (error) {
        console.error('Failed to fetch producers', error);
        setProducers([]);
      } finally {
        setLoading(false);
      }
    };

    fetchProducers();
  }, []);

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
  const handlePlaceOrder = async () => {
    setOrderMessage('');
    setOrderError('');

    if (!selectedProducer) {
      setOrderError('Pumili ng producer.');
      return;
    }

    if (!selectedListingId) {
      setOrderError('Pumili ng produkto.');
      return;
    }

    if (!selectedListing) {
      setOrderError('Hindi makita ang napiling produkto.');
      return;
    }

    if (!numericQuantity || numericQuantity <= 0) {
      setOrderError('Maglagay ng valid na quantity.');
      return;
    }

    if (
      selectedListing.quantity_available !== undefined &&
      numericQuantity > Number(selectedListing.quantity_available)
    ) {
      setOrderError(
        ` ${selectedListing.quantity_available} units lamang ang kasalukuyang available.`
      );
      return;
    }

    if (!deliveryDate) {
      setOrderError('Pumili ng delivery date.');
      return;
    }

    if (!shippingAddress.trim()) {
      setOrderError('Maglagay ng shipping address.');
      return;
    }

    try {
      setPlacingOrder(true);

      const token = localStorage.getItem('authToken');

      const orderPayload = {
        listing_id: selectedListing.listing_id,
        quantity: numericQuantity,
        shipping_address: shippingAddress,
        delivery_date: deliveryDate,
      };

      console.log('Placing order with payload:', orderPayload);

      const response = await fetch('/api/orders', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        body: JSON.stringify(orderPayload),
      });

      const result = await response.json().catch(() => ({}));

      console.log('Order response:', response.status, result);

      if (!response.ok) {
        throw new Error(
          result.message ||
            result.error ||
            `Server error: ${response.status}`
        );
      }

      console.log('Order created:', result);

      setOrderMessage('Matagumpay na na-place ang order!');

      /*
       * Keep the success message visible briefly,
       * then close the dialog.
       */
      setTimeout(() => {
        setShowOrderDialog(false);

        setSelectedListingId('');
        setOrderQuantity('');
        setDeliveryDate('');
        setShippingAddress('');
        setOrderMessage('');
        
        // Trigger orders refresh across tabs
        window.dispatchEvent(new CustomEvent('orderPlaced', { 
          detail: { order: result.order } 
        }));
      }, 1200);
    } catch (error: any) {
      console.error('Failed to place order:', error);

      setOrderError(
        error?.message || 'May nangyaring problema habang nagpa-place ng order.'
      );
    } finally {
      setPlacingOrder(false);
    }
  };

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-gray-900">
          Browse Producers
        </h1>

        <p className="text-gray-600 mt-1">
          Hanapin ang verified producers para sa iyong institutional supply needs
        </p>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="p-4">
          <div className="grid md:grid-cols-4 gap-4">
            <Input placeholder="Maghanap ayon sa produkto..." />

            <Select>
              <SelectTrigger>
                <SelectValue placeholder="Uri ng Producer" />
              </SelectTrigger>

              <SelectContent>
                <SelectItem value="all">Lahat ng Uri</SelectItem>
                <SelectItem value="farmer">Farmer</SelectItem>
                <SelectItem value="fisher">Fisher</SelectItem>
              </SelectContent>
            </Select>

            <Select>
              <SelectTrigger>
                <SelectValue placeholder="Lokasyon" />
              </SelectTrigger>

              <SelectContent>
                <SelectItem value="all">Lahat ng Lokasyon</SelectItem>
                <SelectItem value="centro">Brgy. Centro</SelectItem>
                <SelectItem value="macanaya">Brgy. Macanaya</SelectItem>
              </SelectContent>
            </Select>

            <Button>Maghanap</Button>
          </div>
        </CardContent>
      </Card>

      {/* Loading */}
      {loading && (
        <div className="text-center py-10 text-gray-600">
          Nilo-load ang producers...
        </div>
      )}

      {/* Empty */}
      {!loading && producers.length === 0 && (
        <Card>
          <CardContent className="p-10 text-center">
            <p className="text-gray-600">
              Walang nakitang producers.
            </p>
          </CardContent>
        </Card>
      )}

      {/* Producers Grid */}
      {!loading && producers.length > 0 && (
        <div className="grid md:grid-cols-2 gap-6">
          {producers.map((producer) => (
            <Card
              key={producer.id}
              className="border-2 hover:shadow-lg transition"
            >
              <CardHeader>
                <div className="flex items-start justify-between">
                  <div className="flex items-center gap-3">
                    <div className="w-12 h-12 bg-gradient-to-br from-blue-400 to-green-500 rounded-full flex items-center justify-center text-white font-bold text-xl">
                      {producer.name.charAt(0)}
                    </div>

                    <div>
                      <div className="flex items-center gap-2">
                        <CardTitle className="text-lg">
                          {producer.name}
                        </CardTitle>

                        {producer.verified && (
                          <CheckCircle2 className="w-5 h-5 text-green-600" />
                        )}
                      </div>

                      <div className="flex items-center gap-2 text-sm text-gray-600">
                        <MapPin className="w-3 h-3" />
                        {producer.location}
                      </div>
                    </div>
                  </div>

                  <Badge variant="secondary">
                    {producer.type}
                  </Badge>
                </div>
              </CardHeader>

              <CardContent className="space-y-4">
                <div>
                  <div className="text-xs text-gray-600 mb-2">
                    Mga Produktong Inaalok
                  </div>

                  <div className="flex flex-wrap gap-2">
                    {producer.products.map((product, idx) => (
                      <Badge key={idx} variant="outline">
                        {product}
                      </Badge>
                    ))}
                  </div>
                </div>

                <div className="grid grid-cols-3 gap-3 text-center py-3 bg-gray-50 rounded-lg">
                  <div>
                    <div className="flex items-center justify-center gap-1 mb-1">
                      <Star className="w-4 h-4 text-yellow-500 fill-yellow-500" />

                      <span className="font-bold text-gray-900">
                        {producer.rating}
                      </span>
                    </div>

                    <div className="text-xs text-gray-600">
                      Rating
                    </div>
                  </div>

                  <div>
                    <div className="font-bold text-gray-900 mb-1">
                      {producer.orders}
                    </div>

                    <div className="text-xs text-gray-600">
                      Orders
                    </div>
                  </div>

                  <div>
                    <div className="font-bold text-green-600 mb-1">
                      {producer.price}
                    </div>

                    <div className="text-xs text-gray-600">
                      Average Price
                    </div>
                  </div>
                </div>

                <div className="flex gap-2">
                  <Button
                    className="flex-1"
                    variant="outline"
                    onClick={() =>
                      setSelectedProducer(producer)
                    }
                  >
                    Tingnan ang Profile
                  </Button>

                  <Button
                    className="flex-1 bg-blue-600 hover:bg-blue-700"
                    onClick={() => openOrderDialog(producer)}
                  >
                    <ShoppingCart className="w-4 h-4 mr-2" />
                    Mag-order
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
              Profile ng Producer
            </DialogTitle>

            <DialogDescription>
              Detalyadong impormasyon tungkol kay {selectedProducer?.name}
            </DialogDescription>
          </DialogHeader>

          {selectedProducer && (
            <div className="space-y-4">
              <div className="text-center">
                <div className="w-16 h-16 bg-gradient-to-br from-blue-400 to-green-500 rounded-full flex items-center justify-center text-white font-bold text-2xl mx-auto mb-4">
                  {selectedProducer.name.charAt(0)}
                </div>

                <h3 className="text-xl font-bold">
                  {selectedProducer.name}
                </h3>

                <p className="text-gray-600">
                  {selectedProducer.type} • {selectedProducer.location}
                </p>
              </div>

              <div className="space-y-3">
                <div className="flex justify-between">
                  <span className="text-gray-600">
                    Mga Produkto:
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
                  <span className="text-gray-600">
                    Rating:
                  </span>

                  <div className="flex items-center gap-1">
                    <Star className="w-4 h-4 text-yellow-500 fill-yellow-500" />

                    <span className="font-bold">
                      {selectedProducer.rating}
                    </span>
                  </div>
                </div>

                <div className="flex justify-between">
                  <span className="text-gray-600">
                    Kabuuang Orders:
                  </span>

                  <span className="font-bold">
                    {selectedProducer.orders}
                  </span>
                </div>

                <div className="flex justify-between">
                  <span className="text-gray-600">
                    Average Price:
                  </span>

                  <span className="font-bold text-green-600">
                    {selectedProducer.price}
                  </span>
                </div>

                <div className="flex justify-between">
                  <span className="text-gray-600">
                    Verified:
                  </span>

                  <CheckCircle2 className="w-5 h-5 text-green-600" />
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
        <DialogContent className="max-w-md max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>
              Mag-place ng Order
            </DialogTitle>

            <DialogDescription>
              Mag-order kay {selectedProducer?.name}
            </DialogDescription>
          </DialogHeader>

          {selectedProducer && (
            <div className="space-y-4">

              <div className="p-4 bg-gray-50 rounded-lg">
                <h4 className="font-bold mb-2">
                  Napiling Producer
                </h4>

                <p className="text-sm text-gray-600">
                  {selectedProducer.name}
                </p>

                <p className="text-sm text-gray-600">
                  {selectedProducer.location}
                </p>
              </div>

              <div className="space-y-3">

                {/* Product */}
                <div>
                  <label className="text-sm font-medium">
                    Produkto
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
                      <SelectValue placeholder="Pumili ng produkto" />
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
                      <span className="text-gray-600">
                        Produkto:
                      </span>

                      <span className="font-medium">
                        {selectedListing.product_name}
                      </span>
                    </div>

                    <div className="flex justify-between text-sm">
                      <span className="text-gray-600">
                        Presyo:
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
                        <span className="text-gray-600">
                          Available:
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
                    Quantity (kg)
                  </label>

                  <Input
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="Ilagay ang quantity"
                    value={orderQuantity}
                    onChange={(event) =>
                      setOrderQuantity(event.target.value)
                    }
                  />
                </div>

                {/* Delivery Date */}
                <div>
                  <label className="text-sm font-medium">
                    Delivery Date
                  </label>

                  <Input
                    type="date"
                    value={deliveryDate}
                    onChange={(event) =>
                      setDeliveryDate(event.target.value)
                    }
                  />
                </div>

                {/* Shipping Address */}
                <div>
                  <label className="text-sm font-medium">
                    Shipping Address
                  </label>

                  <Input
                    type="text"
                    placeholder="Ilagay ang shipping address"
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
                        <span className="text-gray-600">
                          Presyo bawat Unit:
                        </span>

                        <span className="font-medium">
                          ₱{unitPrice.toLocaleString()}
                        </span>
                      </div>

                      <div className="flex justify-between">
                        <span className="text-gray-600">
                          Quantity:
                        </span>

                        <span className="font-medium">
                          {numericQuantity}
                        </span>
                      </div>

                      <div className="flex justify-between pt-2 mt-2 border-t">
                        <span className="font-bold">
                          Kabuuan:
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
                  <div className="p-3 bg-red-100 border border-red-200 text-red-700 rounded-lg text-sm">
                    {orderError}
                  </div>
                )}
              </div>

              <div className="flex gap-2 pt-4">
                <Button
                  variant="outline"
                  onClick={closeOrderDialog}
                  className="flex-1"
                  disabled={placingOrder}
                >
                  Kanselahin
                </Button>

                <Button
                  className="flex-1 bg-blue-600 hover:bg-blue-700"
                  onClick={handlePlaceOrder}
                  disabled={placingOrder}
                >
                  {placingOrder
                    ? 'Nilo-load ang Order...'
                    : 'Mag-place ng Order'}
                </Button>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
}