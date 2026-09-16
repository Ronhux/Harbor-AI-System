import { useEffect, useState } from 'react';
import {
  CheckCircle2,
  Clock,
  Truck,
  Package,
} from 'lucide-react';

import { Card, CardContent } from '../../../components/ui/card';
import { Badge } from '../../../components/ui/badge';
import { Button } from '../../../components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '../../../components/ui/dialog';

interface Producer {
  producer_id: number;
  first_name?: string;
  last_name?: string;
  name?: string;
}

interface ProductListing {
  listing_id: number;
  product_name: string;
  unit_of_measure?: string;
  unit?: string;
  producer?: Producer;
}

interface OrderDetail {
  order_detail_id: number;
  listing_id: number;
  quantity: number | string;
  unit_price: number | string;
  subtotal: number | string;
  listing?: ProductListing;
}

interface Order {
  order_id: number;
  buyer_id: number;
  order_date: string;
  total_amount: number | string;
  shipping_address?: string;
  fulfillment_status: string;
  payment_status: string;
  details: OrderDetail[];
}

export default function Orders() {
  const [orders, setOrders] = useState<Order[]>([]);
  const [selectedOrder, setSelectedOrder] = useState<Order | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    fetchOrders();
    
    // Listen for order placement events
    const handleOrderPlaced = () => {
      fetchOrders();
    };
    
    window.addEventListener('orderPlaced', handleOrderPlaced);
    
    return () => {
      window.removeEventListener('orderPlaced', handleOrderPlaced);
    };
  }, []);

  const fetchOrders = async () => {
    try {
      setLoading(true);
      setError('');

      const token = localStorage.getItem('authToken');

      const response = await fetch(
        'http://127.0.0.1:8000/api/buyer/orders',
        {
          method: 'GET',
          headers: {
            Accept: 'application/json',
            ...(token
              ? {
                  Authorization: `Bearer ${token}`,
                }
              : {}),
          },
        }
      );

      if (!response.ok) {
        throw new Error('Failed to fetch orders');
      }

      const data = await response.json();

      setOrders(Array.isArray(data) ? data : []);
    } catch (err) {
      console.error('Error loading orders:', err);
      setError('Unable to load your orders.');
    } finally {
      setLoading(false);
    }
  };

  const getStatusConfig = (status: string) => {
    const normalizedStatus = status?.toLowerCase();

    if (normalizedStatus === 'delivered') {
      return {
        icon: CheckCircle2,
        color: 'bg-green-500',
      };
    }

    if (normalizedStatus === 'in transit' || normalizedStatus === 'in_transit') {
      return {
        icon: Truck,
        color: 'bg-purple-500',
      };
    }
    
    if (normalizedStatus === 'confirmed' || normalizedStatus === 'pending' || normalizedStatus === 'processing') {
      return {
        icon: Clock,
        color: 'bg-blue-500',
      };
    }
    
    if (normalizedStatus === 'cancelled') {
      return {
        icon: Clock,
        color: 'bg-red-500',
      };
    }

    return {
      icon: Clock,
      color: 'bg-blue-500',
    };
  };

  const getProducerName = (producer?: Producer) => {
    if (!producer) {
      return 'Hindi kilalang Producer';
    }

    if (producer.name) {
      return producer.name;
    }

    return `${producer.first_name ?? ''} ${producer.last_name ?? ''}`.trim()
      || 'Hindi kilalang Producer';
  };

  const formatAmount = (amount: number | string) => {
    return Number(amount).toLocaleString('en-PH', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  };

  if (loading) {
    return (
      <div className="p-6">
        <h1 className="text-3xl font-bold text-gray-900">
          Aking Orders
        </h1>

        <p className="text-gray-600 mt-2">
          Nilo-load ang iyong orders...
        </p>
      </div>
    );
  }

  return (
    <div className="p-6 space-y-6">

      {/* Page Header */}
      <div>
        <h1 className="text-3xl font-bold text-gray-900">
          Aking Orders
        </h1>

        <p className="text-gray-600 mt-1">
          Subaybayan ang lifecycle ng iyong institutional orders
        </p>
      </div>

      {/* Error */}
      {error && (
        <Card className="border-red-200">
          <CardContent className="p-4">
            <p className="text-red-600">
              {error}
            </p>
          </CardContent>
        </Card>
      )}

      {/* No Orders */}
      {!error && orders.length === 0 && (
        <Card>
          <CardContent className="p-8 text-center">
            <Package className="w-12 h-12 mx-auto text-gray-400 mb-3" />

            <h3 className="font-semibold text-gray-900">
              Wala pang orders
            </h3>

            <p className="text-gray-500 mt-1">
              Lalabas dito ang iyong mga nabiling produkto.
            </p>
          </CardContent>
        </Card>
      )}

      {/* Orders */}
      <div className="space-y-4">

        {orders.map((order) => {

          const config = getStatusConfig(
            order.fulfillment_status
          );

          const StatusIcon = config.icon;

          return (
            <Card
              key={order.order_id}
              className="border-2"
            >
              <CardContent className="p-4">

                <div className="flex items-center justify-between gap-4">

                  {/* Left */}
                  <div className="flex items-center gap-4 flex-1">

                    <StatusIcon
                      className="w-10 h-10 text-white bg-gradient-to-br from-blue-500 to-purple-500 rounded-lg p-2"
                    />

                    <div className="flex-1">

                      <div className="flex items-center gap-2 mb-1">

                        <h3 className="font-bold text-gray-900">
                          ORD-{order.order_id}
                        </h3>

                        <Badge
                          className={`${config.color} text-white`}
                        >
                          {order.fulfillment_status}
                        </Badge>

                      </div>

                      {/* Products */}
                      <div className="text-sm text-gray-600 space-y-1">

                        {order.details?.map((detail) => (

                          <div
                            key={detail.order_detail_id}
                          >
                            {detail.listing?.product_name
                              ?? 'Product'}
                            {' • '}
                            {detail.quantity}
                            {' '}
                            {detail.listing?.unit_of_measure
                              ?? detail.listing?.unit
                              ?? ''}
                            {' • '}
                            {getProducerName(
                              detail.listing?.producer
                            )}
                          </div>

                        ))}

                      </div>

                    </div>
                  </div>

                  {/* Right */}
                  <div className="text-right">

                    <div className="text-2xl font-bold text-gray-900">
                      ₱{formatAmount(order.total_amount)}
                    </div>

                    <div className="text-sm text-gray-600">
                      Ordered:{' '}
                      {order.order_date
                        ? new Date(
                            order.order_date
                          ).toLocaleDateString()
                        : 'N/A'}
                    </div>

                    <Button
                      size="sm"
                      variant="outline"
                      className="mt-2"
                      onClick={() =>
                        setSelectedOrder(order)
                      }
                    >
                      Tingnan ang Detalye
                    </Button>

                  </div>

                </div>

              </CardContent>
            </Card>
          );
        })}

      </div>

      {/* Order Details Dialog */}
      <Dialog
        open={!!selectedOrder}
        onOpenChange={() =>
          setSelectedOrder(null)
        }
      >

        <DialogContent className="max-w-2xl">

          <DialogHeader>

            <DialogTitle>
              Order Details -{' '}
              {selectedOrder
                ? `ORD-${selectedOrder.order_id}`
                : ''}
            </DialogTitle>

            <DialogDescription>
              Kumpletong impormasyon tungkol sa order na ito
            </DialogDescription>

          </DialogHeader>

          {selectedOrder && (

            <div className="space-y-6">

              {/* Order Information */}
              <div className="grid md:grid-cols-2 gap-6">

                <div className="space-y-4">

                  <div>

                    <h4 className="font-bold text-gray-900 mb-2">
                      Impormasyon ng Order
                    </h4>

                    <div className="space-y-2 text-sm">

                      <div className="flex justify-between">
                        <span className="text-gray-600">
                          Order ID:
                        </span>

                        <span className="font-medium">
                          ORD-{selectedOrder.order_id}
                        </span>
                      </div>

                      <div className="flex justify-between">
                        <span className="text-gray-600">
                          Petsa ng Order:
                        </span>

                        <span className="font-medium">
                          {selectedOrder.order_date}
                        </span>
                      </div>

                      <div className="flex justify-between">
                        <span className="text-gray-600">
                          Shipping Address:
                        </span>

                        <span className="font-medium text-right max-w-[250px]">
                          {selectedOrder.shipping_address ?? 'N/A'}
                        </span>
                      </div>

                    </div>

                  </div>

                </div>

                {/* Payment */}
                <div className="space-y-4">

                  <div>

                    <h4 className="font-bold text-gray-900 mb-2">
                      Status at Payment
                    </h4>

                    <div className="space-y-3 text-sm">

                      <div className="flex justify-between items-center">

                        <span className="text-gray-600">
                          Status ng Order:
                        </span>

                        <Badge
                          className={`${getStatusConfig(
                            selectedOrder.fulfillment_status
                          ).color} text-white`}
                        >
                          {selectedOrder.fulfillment_status}
                        </Badge>

                      </div>

                      <div className="flex justify-between">

                        <span className="text-gray-600">
                          Payment:
                        </span>

                        <span className="font-medium">
                          {selectedOrder.payment_status}
                        </span>

                      </div>

                      <div className="flex justify-between">

                        <span className="text-gray-600">
                          Kabuuang Halaga:
                        </span>

                        <span className="font-bold text-green-600 text-lg">
                          ₱{formatAmount(
                            selectedOrder.total_amount
                          )}
                        </span>

                      </div>

                    </div>

                  </div>

                </div>

              </div>

              {/* Ordered Products */}
              <div className="pt-4 border-t">

                <h4 className="font-bold text-gray-900 mb-3">
                  Mga Na-order na Produkto
                </h4>

                <div className="space-y-3">

                  {selectedOrder.details?.map(
                    (detail) => (

                      <div
                        key={detail.order_detail_id}
                        className="border rounded-lg p-3"
                      >

                        <div className="flex justify-between">

                          <div>

                            <p className="font-semibold">
                              {detail.listing?.product_name
                                ?? 'Product'}
                            </p>

                            <p className="text-sm text-gray-600">
                              Producer:{' '}
                              {getProducerName(
                                detail.listing?.producer
                              )}
                            </p>

                            <p className="text-sm text-gray-600">
                              Quantity:{' '}
                              {detail.quantity}{' '}
                              {detail.listing?.unit_of_measure
                                ?? detail.listing?.unit
                                ?? ''}
                            </p>

                          </div>

                          <div className="text-right">

                            <p className="font-semibold">
                              ₱{formatAmount(
                                detail.subtotal
                              )}
                            </p>

                            <p className="text-sm text-gray-500">
                              ₱{formatAmount(
                                detail.unit_price
                              )}{' '}
                              per unit
                            </p>

                          </div>

                        </div>

                      </div>

                    )
                  )}

                </div>

              </div>

              {/* Timeline */}
              <div className="pt-4 border-t">

                <h4 className="font-bold text-gray-900 mb-3">
                  Order Timeline
                </h4>

                <div className="space-y-3">

                  <div className="flex items-center gap-3">

                    <div className="w-3 h-3 bg-green-500 rounded-full" />

                    <span className="text-sm">
                      Order placed on{' '}
                      {selectedOrder.order_date}
                    </span>

                  </div>

                  {selectedOrder.fulfillment_status !== 'Confirmed' && (

                    <div className="flex items-center gap-3">

                      <div className="w-3 h-3 bg-blue-500 rounded-full" />

                      <span className="text-sm">
                        Order accepted and processing
                      </span>

                    </div>

                  )}

                  {[
                    'In Transit',
                    'Delivered',
                  ].includes(
                    selectedOrder.fulfillment_status
                  ) && (

                    <div className="flex items-center gap-3">

                      <div className="w-3 h-3 bg-purple-500 rounded-full" />

                      <span className="text-sm">
                        Order shipped
                      </span>

                    </div>

                  )}

                  {selectedOrder.fulfillment_status === 'Delivered' && (

                    <div className="flex items-center gap-3">

                      <div className="w-3 h-3 bg-green-500 rounded-full" />

                      <span className="text-sm">
                        Order delivered successfully
                      </span>

                    </div>

                  )}

                </div>

              </div>

            </div>

          )}

        </DialogContent>

      </Dialog>

    </div>
  );
}