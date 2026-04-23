import { useState } from 'react';
import { Package, CheckCircle2, Clock, Truck } from 'lucide-react';
import { Card, CardContent } from '../../../components/ui/card';
import { Badge } from '../../../components/ui/badge';
import { Button } from '../../../components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '../../../components/ui/dialog';

export default function Orders() {
  const [selectedOrder, setSelectedOrder] = useState<typeof orders[0] | null>(null);
  const orders = [
    { id: 'ORD-2026-101', producer: 'Juan Dela Cruz', product: 'Premium Rice', quantity: '2000 kg', amount: 250000, status: 'In Transit', date: '2026-02-10', delivery: '2026-02-18' },
    { id: 'ORD-2026-102', producer: 'Pedro Reyes', product: 'Fresh Tilapia', quantity: '500 kg', amount: 90000, status: 'Delivered', date: '2026-02-05', delivery: '2026-02-12' },
    { id: 'ORD-2026-103', producer: 'Maria Santos', product: 'Vegetables', quantity: '300 kg', amount: 18000, status: 'Processing', date: '2026-02-12', delivery: '2026-02-20' },
  ];

  const getStatusConfig = (status: string) => {
    const configs: Record<string, { icon: any; color: string }> = {
      'Processing': { icon: Clock, color: 'bg-blue-500' },
      'In Transit': { icon: Truck, color: 'bg-purple-500' },
      'Delivered': { icon: CheckCircle2, color: 'bg-green-500' },
    };
    return configs[status];
  };

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-gray-900">My Orders</h1>
        <p className="text-gray-600 mt-1">Track your procurement orders</p>
      </div>

      <div className="space-y-4">
        {orders.map((order) => {
          const config = getStatusConfig(order.status);
          const StatusIcon = config.icon;

          return (
            <Card key={order.id} className="border-2">
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-4 flex-1">
                    <StatusIcon className="w-10 h-10 text-white bg-gradient-to-br from-blue-500 to-purple-500 rounded-lg p-2" />
                    <div className="flex-1">
                      <div className="flex items-center gap-2 mb-1">
                        <h3 className="font-bold text-gray-900">{order.id}</h3>
                        <Badge className={config.color + ' text-white'}>{order.status}</Badge>
                      </div>
                      <div className="text-sm text-gray-600">
                        {order.producer} • {order.product} • {order.quantity}
                      </div>
                    </div>
                  </div>
                  <div className="text-right">
                    <div className="text-2xl font-bold text-gray-900">₱{order.amount.toLocaleString()}</div>
                    <div className="text-sm text-gray-600">Delivery: {order.delivery}</div>
                    <Button size="sm" variant="outline" className="mt-2" onClick={() => setSelectedOrder(order)}>View Details</Button>
                  </div>
                </div>
              </CardContent>
            </Card>
          );
        })}
      </div>

      {/* Order Details Dialog */}
      <Dialog open={!!selectedOrder} onOpenChange={() => setSelectedOrder(null)}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>Order Details - {selectedOrder?.id}</DialogTitle>
            <DialogDescription>Complete information about this procurement order</DialogDescription>
          </DialogHeader>
          {selectedOrder && (
            <div className="space-y-6">
              <div className="grid md:grid-cols-2 gap-6">
                <div className="space-y-4">
                  <div>
                    <h4 className="font-bold text-gray-900 mb-2">Order Information</h4>
                    <div className="space-y-2 text-sm">
                      <div className="flex justify-between">
                        <span className="text-gray-600">Order ID:</span>
                        <span className="font-medium">{selectedOrder.id}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-gray-600">Producer:</span>
                        <span className="font-medium">{selectedOrder.producer}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-gray-600">Product:</span>
                        <span className="font-medium">{selectedOrder.product}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-gray-600">Quantity:</span>
                        <span className="font-medium">{selectedOrder.quantity}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-gray-600">Order Date:</span>
                        <span className="font-medium">{selectedOrder.date}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-gray-600">Delivery Date:</span>
                        <span className="font-medium">{selectedOrder.delivery}</span>
                      </div>
                    </div>
                  </div>
                </div>
                <div className="space-y-4">
                  <div>
                    <h4 className="font-bold text-gray-900 mb-2">Status & Payment</h4>
                    <div className="space-y-2 text-sm">
                      <div className="flex justify-between items-center">
                        <span className="text-gray-600">Order Status:</span>
                        <Badge className={getStatusConfig(selectedOrder.status).color + ' text-white'}>
                          {selectedOrder.status}
                        </Badge>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-gray-600">Total Amount:</span>
                        <span className="font-bold text-green-600 text-lg">₱{selectedOrder.amount.toLocaleString()}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div className="pt-4 border-t">
                <h4 className="font-bold text-gray-900 mb-3">Order Timeline</h4>
                <div className="space-y-3">
                  <div className="flex items-center gap-3">
                    <div className="w-3 h-3 bg-green-500 rounded-full"></div>
                    <span className="text-sm">Order placed on {selectedOrder.date}</span>
                  </div>
                  {selectedOrder.status !== 'Processing' && (
                    <div className="flex items-center gap-3">
                      <div className="w-3 h-3 bg-blue-500 rounded-full"></div>
                      <span className="text-sm">Order accepted and processing</span>
                    </div>
                  )}
                  {['In Transit', 'Delivered'].includes(selectedOrder.status) && (
                    <div className="flex items-center gap-3">
                      <div className="w-3 h-3 bg-purple-500 rounded-full"></div>
                      <span className="text-sm">Order shipped on {selectedOrder.delivery}</span>
                    </div>
                  )}
                  {selectedOrder.status === 'Delivered' && (
                    <div className="flex items-center gap-3">
                      <div className="w-3 h-3 bg-green-500 rounded-full"></div>
                      <span className="text-sm">Order delivered successfully</span>
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
