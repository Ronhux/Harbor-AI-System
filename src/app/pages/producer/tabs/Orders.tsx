import { Package, CheckCircle2, Clock, Truck, XCircle } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../../components/ui/card';
import { Badge } from '../../../components/ui/badge';
import { Button } from '../../../components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../../../components/ui/tabs';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '../../../components/ui/dialog';
import { useState } from 'react';

export default function Orders() {
  const [selectedOrder, setSelectedOrder] = useState<typeof orders[0] | null>(null);
  const orders = [
    {
      id: 'ORD-2026-001',
      buyer: 'LGU Aparri',
      product: 'Premium Organic Rice',
      quantity: '500 kg',
      amount: 62500,
      status: 'Processing',
      date: '2026-02-10',
      deliveryDate: '2026-02-20',
      paymentStatus: 'Pending',
    },
    {
      id: 'ORD-2026-002',
      buyer: 'Kadiwa Outlet 1',
      product: 'Fresh Tilapia',
      quantity: '200 kg',
      amount: 36000,
      status: 'In Transit',
      date: '2026-02-08',
      deliveryDate: '2026-02-15',
      paymentStatus: 'Paid',
    },
    {
      id: 'ORD-2026-003',
      buyer: 'DA-RFO II',
      product: 'Organic Corn',
      quantity: '300 kg',
      amount: 13500,
      status: 'Delivered',
      date: '2026-02-01',
      deliveryDate: '2026-02-12',
      paymentStatus: 'Paid',
    },
    {
      id: 'ORD-2026-004',
      buyer: 'BFAR Office',
      product: 'Mixed Vegetables',
      quantity: '150 kg',
      amount: 9000,
      status: 'Pending',
      date: '2026-02-12',
      deliveryDate: '2026-02-22',
      paymentStatus: 'Pending',
    },
    {
      id: 'ORD-2026-005',
      buyer: 'Kadiwa Outlet 2',
      product: 'Premium Organic Rice',
      quantity: '400 kg',
      amount: 50000,
      status: 'Cancelled',
      date: '2026-02-05',
      deliveryDate: 'N/A',
      paymentStatus: 'Refunded',
    },
  ];

  const getStatusConfig = (status: string) => {
    const configs: Record<string, { icon: any; color: string; badge: string }> = {
      'Pending': { icon: Clock, color: 'yellow', badge: 'bg-yellow-500' },
      'Processing': { icon: Package, color: 'blue', badge: 'bg-blue-500' },
      'In Transit': { icon: Truck, color: 'purple', badge: 'bg-purple-500' },
      'Delivered': { icon: CheckCircle2, color: 'green', badge: 'bg-green-500' },
      'Cancelled': { icon: XCircle, color: 'red', badge: 'bg-red-500' },
    };
    return configs[status] || configs['Pending'];
  };

  const filterOrders = (status?: string) => {
    if (!status) return orders;
    if (status === 'active') return orders.filter(o => ['Pending', 'Processing', 'In Transit'].includes(o.status));
    return orders.filter(o => o.status === status);
  };

  const renderOrderCard = (order: typeof orders[0]) => {
    const config = getStatusConfig(order.status);
    const StatusIcon = config.icon;

    return (
      <Card key={order.id} className="border-2 hover:shadow-md transition">
        <CardHeader className="pb-3">
          <div className="flex items-start justify-between">
            <div>
              <div className="flex items-center gap-2 mb-1">
                <CardTitle className="text-lg">{order.id}</CardTitle>
                <Badge className={config.badge + ' text-white'}>
                  {order.status}
                </Badge>
              </div>
              <CardDescription>{order.buyer}</CardDescription>
            </div>
            <StatusIcon className={`w-6 h-6 text-${config.color}-600`} />
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <div className="flex justify-between text-sm">
              <span className="text-gray-600">Product:</span>
              <span className="font-medium text-gray-900">{order.product}</span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-gray-600">Quantity:</span>
              <span className="font-medium text-gray-900">{order.quantity}</span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-gray-600">Order Date:</span>
              <span className="font-medium text-gray-900">{order.date}</span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-gray-600">Delivery Date:</span>
              <span className="font-medium text-gray-900">{order.deliveryDate}</span>
            </div>
          </div>

          <div className="pt-3 border-t flex items-center justify-between">
            <div>
              <div className="text-xs text-gray-600 mb-1">Total Amount</div>
              <div className="text-xl font-bold text-green-600">₱{order.amount.toLocaleString()}</div>
            </div>
            <div className="text-right">
              <Badge variant={order.paymentStatus === 'Paid' ? 'default' : 'outline'}>
                {order.paymentStatus}
              </Badge>
            </div>
          </div>

          <div className="flex gap-2">
            <Button variant="outline" size="sm" className="flex-1" onClick={() => setSelectedOrder(order)}>
              View Details
            </Button>
            {order.status === 'Pending' && (
              <Button size="sm" className="flex-1 bg-green-600 hover:bg-green-700">
                Accept Order
              </Button>
            )}
            {order.status === 'Processing' && (
              <Button size="sm" className="flex-1 bg-blue-600 hover:bg-blue-700">
                Mark as Shipped
              </Button>
            )}
          </div>
        </CardContent>
      </Card>
    );
  };

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-3">
          <Package className="w-8 h-8 text-blue-600" />
          Orders & Commitments
        </h1>
        <p className="text-gray-600 mt-1">Track and manage your institutional orders</p>
      </div>

      {/* Summary Stats */}
      <div className="grid md:grid-cols-5 gap-4">
        <Card>
          <CardContent className="p-4">
            <div className="text-2xl font-bold text-gray-900">{orders.length}</div>
            <div className="text-sm text-gray-600">Total Orders</div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <div className="text-2xl font-bold text-yellow-600">
              {filterOrders('Pending').length}
            </div>
            <div className="text-sm text-gray-600">Pending</div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <div className="text-2xl font-bold text-blue-600">
              {filterOrders('Processing').length}
            </div>
            <div className="text-sm text-gray-600">Processing</div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <div className="text-2xl font-bold text-purple-600">
              {filterOrders('In Transit').length}
            </div>
            <div className="text-sm text-gray-600">In Transit</div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <div className="text-2xl font-bold text-green-600">
              {filterOrders('Delivered').length}
            </div>
            <div className="text-sm text-gray-600">Delivered</div>
          </CardContent>
        </Card>
      </div>

      {/* Orders Tabs */}
      <Tabs defaultValue="all" className="space-y-6">
        <TabsList>
          <TabsTrigger value="all">All Orders</TabsTrigger>
          <TabsTrigger value="active">Active</TabsTrigger>
          <TabsTrigger value="Delivered">Completed</TabsTrigger>
          <TabsTrigger value="Cancelled">Cancelled</TabsTrigger>
        </TabsList>

        <TabsContent value="all" className="space-y-4">
          <div className="grid md:grid-cols-2 gap-4">
            {orders.map(renderOrderCard)}
          </div>
        </TabsContent>

        <TabsContent value="active" className="space-y-4">
          <div className="grid md:grid-cols-2 gap-4">
            {filterOrders('active').map(renderOrderCard)}
          </div>
        </TabsContent>

        <TabsContent value="Delivered" className="space-y-4">
          <div className="grid md:grid-cols-2 gap-4">
            {filterOrders('Delivered').map(renderOrderCard)}
          </div>
        </TabsContent>

        <TabsContent value="Cancelled" className="space-y-4">
          <div className="grid md:grid-cols-2 gap-4">
            {filterOrders('Cancelled').map(renderOrderCard)}
          </div>
        </TabsContent>
      </Tabs>

      {/* Tips */}
      <Card className="bg-green-50 border-green-200">
        <CardContent className="p-6">
          <h3 className="font-bold text-green-900 mb-3">Order Management Tips</h3>
          <div className="grid md:grid-cols-2 gap-3 text-sm text-green-800">
            <div className="flex items-start gap-2">
              <CheckCircle2 className="w-4 h-4 mt-0.5 flex-shrink-0" />
              <span>Respond to new orders within 24 hours</span>
            </div>
            <div className="flex items-start gap-2">
              <CheckCircle2 className="w-4 h-4 mt-0.5 flex-shrink-0" />
              <span>Update order status promptly</span>
            </div>
            <div className="flex items-start gap-2">
              <CheckCircle2 className="w-4 h-4 mt-0.5 flex-shrink-0" />
              <span>Maintain quality standards for repeat orders</span>
            </div>
            <div className="flex items-start gap-2">
              <CheckCircle2 className="w-4 h-4 mt-0.5 flex-shrink-0" />
              <span>Communicate delivery delays early</span>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Order Details Dialog */}
      <Dialog open={!!selectedOrder} onOpenChange={() => setSelectedOrder(null)}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>Order Details - {selectedOrder?.id}</DialogTitle>
            <DialogDescription>Complete information about this order</DialogDescription>
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
                        <span className="text-gray-600">Buyer:</span>
                        <span className="font-medium">{selectedOrder.buyer}</span>
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
                        <span className="font-medium">{selectedOrder.deliveryDate}</span>
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
                        <Badge className={getStatusConfig(selectedOrder.status).badge + ' text-white'}>
                          {selectedOrder.status}
                        </Badge>
                      </div>
                      <div className="flex justify-between items-center">
                        <span className="text-gray-600">Payment Status:</span>
                        <Badge variant={selectedOrder.paymentStatus === 'Paid' ? 'default' : 'outline'}>
                          {selectedOrder.paymentStatus}
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
                  {selectedOrder.status !== 'Pending' && (
                    <div className="flex items-center gap-3">
                      <div className="w-3 h-3 bg-blue-500 rounded-full"></div>
                      <span className="text-sm">Order accepted and processing</span>
                    </div>
                  )}
                  {['In Transit', 'Delivered'].includes(selectedOrder.status) && (
                    <div className="flex items-center gap-3">
                      <div className="w-3 h-3 bg-purple-500 rounded-full"></div>
                      <span className="text-sm">Order shipped on {selectedOrder.deliveryDate}</span>
                    </div>
                  )}
                  {selectedOrder.status === 'Delivered' && (
                    <div className="flex items-center gap-3">
                      <div className="w-3 h-3 bg-green-500 rounded-full"></div>
                      <span className="text-sm">Order delivered successfully</span>
                    </div>
                  )}
                  {selectedOrder.status === 'Cancelled' && (
                    <div className="flex items-center gap-3">
                      <div className="w-3 h-3 bg-red-500 rounded-full"></div>
                      <span className="text-sm">Order cancelled</span>
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
