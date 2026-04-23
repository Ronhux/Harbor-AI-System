import { useState } from 'react';
import { Plus, Edit, Trash2, Eye, Package, TrendingUp } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../../components/ui/card';
import { Button } from '../../../components/ui/button';
import { Badge } from '../../../components/ui/badge';
import { Input } from '../../../components/ui/input';
import { Label } from '../../../components/ui/label';
import { Textarea } from '../../../components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '../../../components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../../../components/ui/select';

export default function MyListings() {
  const [listings, setListings] = useState([
    {
      id: 1,
      name: 'Premium Organic Rice',
      category: 'Grains',
      price: 125,
      unit: 'kg',
      quantity: 500,
      status: 'Active',
      views: 245,
      orders: 12,
      image: '🌾',
    },
    {
      id: 2,
      name: 'Fresh Tilapia',
      category: 'Fish',
      price: 180,
      unit: 'kg',
      quantity: 200,
      status: 'Active',
      views: 189,
      orders: 8,
      image: '🐟',
    },
    {
      id: 3,
      name: 'Organic Corn',
      category: 'Grains',
      price: 45,
      unit: 'kg',
      quantity: 800,
      status: 'Active',
      views: 156,
      orders: 6,
      image: '🌽',
    },
    {
      id: 4,
      name: 'Mixed Vegetables',
      category: 'Vegetables',
      price: 60,
      unit: 'kg',
      quantity: 150,
      status: 'Low Stock',
      views: 98,
      orders: 4,
      image: '🥬',
    },
  ]);

  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [editingListing, setEditingListing] = useState<typeof listings[0] | null>(null);
  const [viewingListing, setViewingListing] = useState<typeof listings[0] | null>(null);

  const handleEdit = (listing: typeof listings[0]) => {
    setEditingListing(listing);
    setIsDialogOpen(true);
  };

  const handleView = (listing: typeof listings[0]) => {
    setViewingListing(listing);
  };

  const handleDelete = (id: number) => {
    if (window.confirm('Are you sure you want to delete this listing?')) {
      setListings(listings.filter(l => l.id !== id));
    }
  };

  const getStatusColor = (status: string) => {
    const colors: Record<string, string> = {
      'Active': 'bg-green-500',
      'Low Stock': 'bg-yellow-500',
      'Out of Stock': 'bg-red-500',
      'Draft': 'bg-gray-500',
    };
    return colors[status] || 'bg-gray-500';
  };

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-3">
            <Package className="w-8 h-8 text-green-600" />
            My Digital Stall
          </h1>
          <p className="text-gray-600 mt-1">Manage your product listings and inventory</p>
        </div>
        
        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
          <DialogTrigger asChild>
            <Button className="bg-green-600 hover:bg-green-700">
              <Plus className="w-4 h-4 mr-2" />
              Add New Listing
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-2xl">
            <DialogHeader>
              <DialogTitle>Create New Product Listing</DialogTitle>
              <DialogDescription>
                Add a new product to your digital stall
              </DialogDescription>
            </DialogHeader>
            <div className="space-y-4 py-4">
              <div className="grid md:grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="productName">Product Name</Label>
                  <Input id="productName" placeholder="e.g., Premium Rice" />
                </div>
                <div>
                  <Label htmlFor="category">Category</Label>
                  <Select>
                    <SelectTrigger id="category">
                      <SelectValue placeholder="Select category" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="grains">Grains</SelectItem>
                      <SelectItem value="fish">Fish</SelectItem>
                      <SelectItem value="vegetables">Vegetables</SelectItem>
                      <SelectItem value="fruits">Fruits</SelectItem>
                      <SelectItem value="livestock">Livestock</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <div>
                <Label htmlFor="description">Description</Label>
                <Textarea 
                  id="description" 
                  placeholder="Describe your product, growing/harvesting method, certifications..."
                  rows={3}
                />
              </div>

              <div className="grid md:grid-cols-3 gap-4">
                <div>
                  <Label htmlFor="price">Price</Label>
                  <Input id="price" type="number" placeholder="125" />
                </div>
                <div>
                  <Label htmlFor="unit">Unit</Label>
                  <Select>
                    <SelectTrigger id="unit">
                      <SelectValue placeholder="Select unit" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="kg">Kilogram (kg)</SelectItem>
                      <SelectItem value="g">Gram (g)</SelectItem>
                      <SelectItem value="sack">Sack</SelectItem>
                      <SelectItem value="piece">Piece</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <Label htmlFor="quantity">Available Quantity</Label>
                  <Input id="quantity" type="number" placeholder="500" />
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-4">
                <Button variant="outline" onClick={() => setIsDialogOpen(false)}>
                  Cancel
                </Button>
                <Button className="bg-green-600 hover:bg-green-700">
                  Create Listing
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>

        {/* View Listing Dialog */}
        <Dialog open={!!viewingListing} onOpenChange={() => setViewingListing(null)}>
          <DialogContent className="max-w-md">
            <DialogHeader>
              <DialogTitle>Product Details</DialogTitle>
            </DialogHeader>
            {viewingListing && (
              <div className="space-y-4">
                <div className="text-center">
                  <div className="text-6xl mb-4">{viewingListing.image}</div>
                  <h3 className="text-xl font-bold">{viewingListing.name}</h3>
                  <p className="text-gray-600">{viewingListing.category}</p>
                </div>
                <div className="space-y-3">
                  <div className="flex justify-between">
                    <span className="text-gray-600">Price:</span>
                    <span className="font-bold text-green-600">₱{viewingListing.price} per {viewingListing.unit}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-600">Available Quantity:</span>
                    <span className="font-bold">{viewingListing.quantity} {viewingListing.unit}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-600">Status:</span>
                    <Badge className={getStatusColor(viewingListing.status) + ' text-white'}>
                      {viewingListing.status}
                    </Badge>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-600">Views:</span>
                    <span className="font-bold">{viewingListing.views}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-600">Orders:</span>
                    <span className="font-bold">{viewingListing.orders}</span>
                  </div>
                </div>
              </div>
            )}
          </DialogContent>
        </Dialog>
      </div>

      {/* Stats */}
      <div className="grid md:grid-cols-4 gap-4">
        <Card>
          <CardContent className="p-4">
            <div className="text-2xl font-bold text-gray-900">{listings.length}</div>
            <div className="text-sm text-gray-600">Total Listings</div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <div className="text-2xl font-bold text-green-600">
              {listings.filter(l => l.status === 'Active').length}
            </div>
            <div className="text-sm text-gray-600">Active Products</div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <div className="text-2xl font-bold text-blue-600">
              {listings.reduce((sum, l) => sum + l.views, 0)}
            </div>
            <div className="text-sm text-gray-600">Total Views</div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <div className="text-2xl font-bold text-purple-600">
              {listings.reduce((sum, l) => sum + l.orders, 0)}
            </div>
            <div className="text-sm text-gray-600">Total Orders</div>
          </CardContent>
        </Card>
      </div>

      {/* Listings Grid */}
      <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
        {listings.map((listing) => (
          <Card key={listing.id} className="border-2 hover:shadow-lg transition">
            <CardHeader className="pb-3">
              <div className="flex items-start justify-between mb-2">
                <div className="text-4xl">{listing.image}</div>
                <Badge className={getStatusColor(listing.status) + ' text-white'}>
                  {listing.status}
                </Badge>
              </div>
              <CardTitle className="text-xl">{listing.name}</CardTitle>
              <CardDescription>{listing.category}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {/* Price & Quantity */}
              <div className="flex items-baseline gap-2">
                <span className="text-2xl font-bold text-green-600">₱{listing.price}</span>
                <span className="text-gray-600">per {listing.unit}</span>
              </div>
              
              <div className="flex items-center justify-between text-sm">
                <span className="text-gray-600">Available:</span>
                <span className="font-bold text-gray-900">{listing.quantity} {listing.unit}</span>
              </div>

              {/* Stats */}
              <div className="grid grid-cols-2 gap-3 pt-3 border-t">
                <div className="flex items-center gap-2">
                  <Eye className="w-4 h-4 text-blue-600" />
                  <div>
                    <div className="text-sm font-bold text-gray-900">{listing.views}</div>
                    <div className="text-xs text-gray-600">Views</div>
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  <TrendingUp className="w-4 h-4 text-green-600" />
                  <div>
                    <div className="text-sm font-bold text-gray-900">{listing.orders}</div>
                    <div className="text-xs text-gray-600">Orders</div>
                  </div>
                </div>
              </div>

              {/* Actions */}
              <div className="flex gap-2 pt-3 border-t">
                <Button variant="outline" size="sm" className="flex-1" onClick={() => handleEdit(listing)}>
                  <Edit className="w-4 h-4 mr-1" />
                  Edit
                </Button>
                <Button variant="outline" size="sm" className="flex-1" onClick={() => handleView(listing)}>
                  <Eye className="w-4 h-4 mr-1" />
                  View
                </Button>
                <Button variant="ghost" size="sm" onClick={() => handleDelete(listing.id)}>
                  <Trash2 className="w-4 h-4 text-red-600" />
                </Button>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Tips */}
      <Card className="bg-blue-50 border-blue-200">
        <CardContent className="p-6">
          <h3 className="font-bold text-blue-900 mb-3">Tips for Better Listings</h3>
          <div className="grid md:grid-cols-2 gap-4 text-sm text-blue-800">
            <div className="flex items-start gap-2">
              <div className="w-5 h-5 bg-blue-600 text-white rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold mt-0.5">
                1
              </div>
              <span>Use clear, descriptive product names that highlight quality</span>
            </div>
            <div className="flex items-start gap-2">
              <div className="w-5 h-5 bg-blue-600 text-white rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold mt-0.5">
                2
              </div>
              <span>Keep pricing competitive based on AI insights</span>
            </div>
            <div className="flex items-start gap-2">
              <div className="w-5 h-5 bg-blue-600 text-white rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold mt-0.5">
                3
              </div>
              <span>Update inventory regularly to maintain buyer trust</span>
            </div>
            <div className="flex items-start gap-2">
              <div className="w-5 h-5 bg-blue-600 text-white rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold mt-0.5">
                4
              </div>
              <span>Mention certifications and quality standards</span>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
