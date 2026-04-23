import { useState } from 'react';
import { Search, MapPin, CheckCircle2, Star, ShoppingCart } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../components/ui/card';
import { Button } from '../../../components/ui/button';
import { Badge } from '../../../components/ui/badge';
import { Input } from '../../../components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../../../components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '../../../components/ui/dialog';

export default function BrowseProducers() {
  const [selectedProducer, setSelectedProducer] = useState<typeof producers[0] | null>(null);
  const [showOrderDialog, setShowOrderDialog] = useState(false);
  const producers = [
    { id: 1, name: 'Juan Dela Cruz', type: 'Both', location: 'Brgy. Centro', products: ['Rice', 'Tilapia'], rating: 4.8, orders: 45, verified: true, price: '₱120/kg' },
    { id: 2, name: 'Maria Santos', type: 'Farmer', location: 'Brgy. Macanaya', products: ['Vegetables', 'Corn'], rating: 4.9, orders: 38, verified: true, price: '₱55/kg' },
    { id: 3, name: 'Pedro Reyes', type: 'Fisher', location: 'Brgy. Sanja', products: ['Tilapia', 'Bangus'], rating: 4.7, orders: 52, verified: true, price: '₱180/kg' },
    { id: 4, name: 'Ana Garcia', type: 'Farmer', location: 'Brgy. Backiling', products: ['Rice', 'Corn'], rating: 4.6, orders: 29, verified: true, price: '₱125/kg' },
  ];

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-gray-900">Browse Producers</h1>
        <p className="text-gray-600 mt-1">Find verified producers for your procurement needs</p>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="p-4">
          <div className="grid md:grid-cols-4 gap-4">
            <Input placeholder="Search by product..." />
            <Select>
              <SelectTrigger>
                <SelectValue placeholder="Producer Type" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Types</SelectItem>
                <SelectItem value="farmer">Farmer</SelectItem>
                <SelectItem value="fisher">Fisher</SelectItem>
              </SelectContent>
            </Select>
            <Select>
              <SelectTrigger>
                <SelectValue placeholder="Location" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Locations</SelectItem>
                <SelectItem value="centro">Brgy. Centro</SelectItem>
                <SelectItem value="macanaya">Brgy. Macanaya</SelectItem>
              </SelectContent>
            </Select>
            <Button>Search</Button>
          </div>
        </CardContent>
      </Card>

      {/* Producers Grid */}
      <div className="grid md:grid-cols-2 gap-6">
        {producers.map((producer) => (
          <Card key={producer.id} className="border-2 hover:shadow-lg transition">
            <CardHeader>
              <div className="flex items-start justify-between">
                <div className="flex items-center gap-3">
                  <div className="w-12 h-12 bg-gradient-to-br from-blue-400 to-green-500 rounded-full flex items-center justify-center text-white font-bold text-xl">
                    {producer.name.charAt(0)}
                  </div>
                  <div>
                    <div className="flex items-center gap-2">
                      <CardTitle className="text-lg">{producer.name}</CardTitle>
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
                <Badge variant="secondary">{producer.type}</Badge>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              <div>
                <div className="text-xs text-gray-600 mb-2">Products Offered</div>
                <div className="flex flex-wrap gap-2">
                  {producer.products.map((product, idx) => (
                    <Badge key={idx} variant="outline">{product}</Badge>
                  ))}
                </div>
              </div>

              <div className="grid grid-cols-3 gap-3 text-center py-3 bg-gray-50 rounded-lg">
                <div>
                  <div className="flex items-center justify-center gap-1 mb-1">
                    <Star className="w-4 h-4 text-yellow-500 fill-yellow-500" />
                    <span className="font-bold text-gray-900">{producer.rating}</span>
                  </div>
                  <div className="text-xs text-gray-600">Rating</div>
                </div>
                <div>
                  <div className="font-bold text-gray-900 mb-1">{producer.orders}</div>
                  <div className="text-xs text-gray-600">Orders</div>
                </div>
                <div>
                  <div className="font-bold text-green-600 mb-1">{producer.price}</div>
                  <div className="text-xs text-gray-600">Avg Price</div>
                </div>
              </div>

              <div className="flex gap-2">
                <Button className="flex-1" variant="outline" onClick={() => setSelectedProducer(producer)}>View Profile</Button>
                <Button className="flex-1 bg-blue-600 hover:bg-blue-700" onClick={() => { setSelectedProducer(producer); setShowOrderDialog(true); }}>
                  <ShoppingCart className="w-4 h-4 mr-2" />
                  Order
                </Button>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Producer Profile Dialog */}
      <Dialog open={!!selectedProducer && !showOrderDialog} onOpenChange={() => setSelectedProducer(null)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Producer Profile</DialogTitle>
            <DialogDescription>Detailed information about {selectedProducer?.name}</DialogDescription>
          </DialogHeader>
          {selectedProducer && (
            <div className="space-y-4">
              <div className="text-center">
                <div className="w-16 h-16 bg-gradient-to-br from-blue-400 to-green-500 rounded-full flex items-center justify-center text-white font-bold text-2xl mx-auto mb-4">
                  {selectedProducer.name.charAt(0)}
                </div>
                <h3 className="text-xl font-bold">{selectedProducer.name}</h3>
                <p className="text-gray-600">{selectedProducer.type} • {selectedProducer.location}</p>
              </div>
              <div className="space-y-3">
                <div className="flex justify-between">
                  <span className="text-gray-600">Products:</span>
                  <div className="flex gap-1">
                    {selectedProducer.products.map((product, idx) => (
                      <Badge key={idx} variant="outline">{product}</Badge>
                    ))}
                  </div>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-600">Rating:</span>
                  <div className="flex items-center gap-1">
                    <Star className="w-4 h-4 text-yellow-500 fill-yellow-500" />
                    <span className="font-bold">{selectedProducer.rating}</span>
                  </div>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-600">Total Orders:</span>
                  <span className="font-bold">{selectedProducer.orders}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-600">Average Price:</span>
                  <span className="font-bold text-green-600">{selectedProducer.price}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-600">Verified:</span>
                  <CheckCircle2 className="w-5 h-5 text-green-600" />
                </div>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>

      {/* Order Dialog */}
      <Dialog open={showOrderDialog} onOpenChange={setShowOrderDialog}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Place Order</DialogTitle>
            <DialogDescription>Order from {selectedProducer?.name}</DialogDescription>
          </DialogHeader>
          {selectedProducer && (
            <div className="space-y-4">
              <div className="p-4 bg-gray-50 rounded-lg">
                <h4 className="font-bold mb-2">Selected Producer</h4>
                <p className="text-sm text-gray-600">{selectedProducer.name}</p>
                <p className="text-sm text-gray-600">{selectedProducer.location}</p>
              </div>
              <div className="space-y-3">
                <div>
                  <label className="text-sm font-medium">Product</label>
                  <Select>
                    <SelectTrigger>
                      <SelectValue placeholder="Select product" />
                    </SelectTrigger>
                    <SelectContent>
                      {selectedProducer.products.map((product, idx) => (
                        <SelectItem key={idx} value={product.toLowerCase()}>{product}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <label className="text-sm font-medium">Quantity (kg)</label>
                  <Input type="number" placeholder="Enter quantity" />
                </div>
                <div>
                  <label className="text-sm font-medium">Delivery Date</label>
                  <Input type="date" />
                </div>
              </div>
              <div className="flex gap-2 pt-4">
                <Button variant="outline" onClick={() => setShowOrderDialog(false)} className="flex-1">Cancel</Button>
                <Button className="flex-1 bg-blue-600 hover:bg-blue-700" onClick={() => { alert('Order placed successfully!'); setShowOrderDialog(false); }}>Place Order</Button>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
}
