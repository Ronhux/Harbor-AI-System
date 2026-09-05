import { useState, useEffect } from 'react';
import { Plus, Edit, Trash2, Eye, Package, TrendingUp } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../../components/ui/card';
import { Button } from '../../../components/ui/button';
import { Badge } from '../../../components/ui/badge';
import { Input } from '../../../components/ui/input';
import { Label } from '../../../components/ui/label';
import { Textarea } from '../../../components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '../../../components/ui/dialog';

type Listing = {
  id: number;
  name: string;
  category: string;
  price: number;
  unit: string;
  quantity: number;
  status: string;
  views: number;
  orders: number;
  image?: string;
  image_url?: string | null;
  image_path?: string | null;
  raw?: any;
};

const defaultListings: Listing[] = [
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
];

const resolveImageUrl = (value: string | null | undefined) => {
  if (!value) return null;
  if (value.startsWith('http://') || value.startsWith('https://')) return value;
  if (value.startsWith('/')) {
    const backendBase = (import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
    return `${backendBase}${value}`;
  }
  return value;
};

const normalizeListing = (item: any): Listing => {
  const category = item.product_category || item.category || 'General';
  const unit = item.unit_of_measure || item.unit || 'kg';
  const price = Number(item.current_price_per_unit ?? item.price_per_unit ?? item.price ?? 0);
  const quantity = Number(item.quantity_available ?? item.quantity ?? 0);
  const status = String(item.status || 'Active').replace(/^./, (char) => char.toUpperCase());
  const imageUrl = resolveImageUrl(item.image_url || item.imageUrl || item.imagePath || item.image_path || null);

  return {
    id: Number(item.listing_id ?? item.id ?? Date.now()),
    name: item.product_name || item.name || 'Unnamed Product',
    category,
    price,
    unit,
    quantity,
    status,
    views: Number(item.views ?? 0),
    orders: Number(item.orders ?? 0),
    image: item.image || getProductEmoji(category),
    image_url: imageUrl,
    image_path: item.image_path || item.imagePath || null,
    raw: item,
  };
};

const getProductEmoji = (category: string) => {
  const map: Record<string, string> = {
    grains: '🌾',
    fish: '🐟',
    vegetables: '🥬',
    fruits: '🍋',
    livestock: '🐄',
    poultry: '🐔',
  };
  const normalized = String(category || '').toLowerCase();
  return map[normalized] || '📦';
};

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    Active: 'bg-green-500',
    'Low Stock': 'bg-yellow-500',
    'Out of Stock': 'bg-red-500',
    Draft: 'bg-gray-500',
    available: 'bg-green-500',
    inactive: 'bg-gray-500',
  };
  return colors[status] || 'bg-gray-500';
};

export default function MyListings() {
  const [listings, setListings] = useState<Listing[]>(defaultListings);
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [isEditOpen, setIsEditOpen] = useState(false);
  const [editingListing, setEditingListing] = useState<Listing | null>(null);
  const [viewingListing, setViewingListing] = useState<Listing | null>(null);

  const fetchListings = async () => {
    try {
      const { request } = await import('../../../../lib/api');
      const data = await request('/api/producer/dashboard');
      const items = (data.product_listings || []).map(normalizeListing);
      setListings(items);
    } catch (err) {
      console.error('Unable to load producer listings', err);
    }
  };

  useEffect(() => {
    fetchListings();
  }, []);

  const handleEdit = (listing: Listing) => {
    setEditingListing({
      ...listing,
      raw: {
        ...(listing.raw ?? {}),
        description: listing.raw?.description ?? '',
        product_name: listing.name,
        category: listing.category,
        price: listing.price,
        unit: listing.unit,
        quantity: listing.quantity,
        status: listing.status,
      },
    });
    setIsEditOpen(true);
  };

  const handleView = (listing: Listing) => {
    setViewingListing(listing);
  };

  const handleDelete = async (id: number) => {
    if (!window.confirm('Are you sure you want to delete this listing?')) return;

    try {
      const { request } = await import('../../../../lib/api');
      await request(`/api/producer/listings/${id}`, { method: 'DELETE' });
      setListings((current) => current.filter((listing) => listing.id !== id));
    } catch (err: any) {
      console.error(err);
      alert(err.message || 'Delete listing failed');
    }
  };

  const addListingToState = (item: any) => {
    const nextItem = normalizeListing(item);
    setListings((current) => {
      const exists = current.some((entry) => entry.id === nextItem.id);
      if (!exists) return [nextItem, ...current];
      return current.map((entry) => (entry.id === nextItem.id ? nextItem : entry));
    });
  };

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-3">
            <Package className="w-8 h-8 text-green-600" />
            My Digital Stall
          </h1>
          <p className="text-gray-600 mt-1">Manage your product listings and inventory</p>
        </div>

        <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
          <DialogTrigger asChild>
            <Button className="bg-green-600 hover:bg-green-700">
              <Plus className="w-4 h-4 mr-2" />
              Add New Listing
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-2xl">
            <DialogHeader>
              <DialogTitle>Create New Product Listing</DialogTitle>
              <DialogDescription>Add a new product to your digital stall</DialogDescription>
            </DialogHeader>
            <ListingForm
              mode="create"
              onCancel={() => setIsCreateOpen(false)}
              onSave={(item) => {
                addListingToState(item);
                setIsCreateOpen(false);
              }}
            />
          </DialogContent>
        </Dialog>

        <Dialog open={!!viewingListing} onOpenChange={() => setViewingListing(null)}>
          <DialogContent className="max-w-md">
            <DialogHeader>
              <DialogTitle>Product Details</DialogTitle>
            </DialogHeader>
            {viewingListing && (
              <div className="space-y-4">
                <div className="text-center">
                  <div className="text-6xl mb-4">{viewingListing.image_url ? <img src={viewingListing.image_url} alt={viewingListing.name} className="w-24 h-24 object-cover rounded-lg mx-auto" /> : viewingListing.image}</div>
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
                  <div className="flex justify-between items-center">
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
              {listings.filter((listing) => String(listing.status).toLowerCase() === 'active' || String(listing.status).toLowerCase() === 'available').length}
            </div>
            <div className="text-sm text-gray-600">Active Products</div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <div className="text-2xl font-bold text-blue-600">{listings.reduce((sum, listing) => sum + listing.views, 0)}</div>
            <div className="text-sm text-gray-600">Total Views</div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <div className="text-2xl font-bold text-purple-600">{listings.reduce((sum, listing) => sum + listing.orders, 0)}</div>
            <div className="text-sm text-gray-600">Total Orders</div>
          </CardContent>
        </Card>
      </div>

      <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
        {listings.map((listing) => (
          <Card key={listing.id} className="border-2 hover:shadow-lg transition">
            <CardHeader className="pb-3">
              <div className="flex items-start justify-between mb-2">
                <div className="text-4xl">{listing.image_url ? <img src={listing.image_url} alt={listing.name} className="w-12 h-12 object-cover rounded-lg" /> : listing.image}</div>
                <Badge className={getStatusColor(listing.status) + ' text-white'}>{listing.status}</Badge>
              </div>
              <CardTitle className="text-xl">{listing.name}</CardTitle>
              <CardDescription>{listing.category}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-baseline gap-2">
                <span className="text-2xl font-bold text-green-600">₱{listing.price}</span>
                <span className="text-gray-600">per {listing.unit}</span>
              </div>

              <div className="flex items-center justify-between text-sm">
                <span className="text-gray-600">Available:</span>
                <span className="font-bold text-gray-900">{listing.quantity} {listing.unit}</span>
              </div>

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

              <div className="flex gap-2 pt-3 border-t">
                <Button type="button" variant="outline" size="sm" className="flex-1" onClick={() => handleEdit(listing)}>
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

      <Dialog
        open={isEditOpen}
        onOpenChange={(open) => {
          setIsEditOpen(open);
          if (!open) {
            setEditingListing(null);
          }
        }}
      >
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>Edit Product Listing</DialogTitle>
            <DialogDescription>Update the listing details and inventory</DialogDescription>
          </DialogHeader>
          {editingListing && (
            <ListingForm
              key={editingListing.id}
              mode="edit"
              existingListing={editingListing}
              onCancel={() => {
                setIsEditOpen(false);
                setEditingListing(null);
              }}
              onSave={async (item) => {
                const updatedId = Number(item.listing_id ?? item.id ?? 0);
                const nextItem = normalizeListing(item);
                setListings((current) => {
                  const hasMatch = current.some((entry) => entry.id === updatedId);
                  if (!hasMatch) return [nextItem, ...current];
                  return current.map((entry) => (entry.id === updatedId ? nextItem : entry));
                });
                await fetchListings();
                setEditingListing(null);
                setIsEditOpen(false);
              }}
            />
          )}
        </DialogContent>
      </Dialog>

      <Card className="bg-blue-50 border-blue-200">
        <CardContent className="p-6">
          <h3 className="font-bold text-blue-900 mb-3">Tips for Better Listings</h3>
          <div className="grid md:grid-cols-2 gap-4 text-sm text-blue-800">
            <div className="flex items-start gap-2">
              <div className="w-5 h-5 bg-blue-600 text-white rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold mt-0.5">1</div>
              <span>Use clear, descriptive product names that highlight quality</span>
            </div>
            <div className="flex items-start gap-2">
              <div className="w-5 h-5 bg-blue-600 text-white rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold mt-0.5">2</div>
              <span>Keep pricing competitive based on AI insights</span>
            </div>
            <div className="flex items-start gap-2">
              <div className="w-5 h-5 bg-blue-600 text-white rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold mt-0.5">3</div>
              <span>Update inventory regularly to maintain buyer trust</span>
            </div>
            <div className="flex items-start gap-2">
              <div className="w-5 h-5 bg-blue-600 text-white rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold mt-0.5">4</div>
              <span>Mention certifications and quality standards</span>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

function ListingForm({
  mode,
  existingListing,
  onCancel,
  onSave,
}: {
  mode: 'create' | 'edit';
  existingListing?: Listing | null;
  onCancel: () => void;
  onSave: (item: any) => void;
}) {
  const [productName, setProductName] = useState(existingListing?.name || '');
  const [category, setCategory] = useState((existingListing?.category || 'grains').toLowerCase());
  const [description, setDescription] = useState(existingListing?.raw?.description || '');
  const [price, setPrice] = useState<number | ''>(existingListing?.price ?? '');
  const [unit, setUnit] = useState(existingListing?.unit || 'kg');
  const [quantity, setQuantity] = useState<number | ''>(existingListing?.quantity ?? '');
  const [imageFile, setImageFile] = useState<File | null>(null);

  useEffect(() => {
    if (!existingListing) return;
    setProductName(existingListing.name || '');
    setCategory((existingListing.category || 'grains').toLowerCase());
    setDescription(existingListing.raw?.description || '');
    setPrice(existingListing.price ?? '');
    setUnit(existingListing.unit || 'kg');
    setQuantity(existingListing.quantity ?? '');
  }, [existingListing, mode]);

  const handleSubmit = async () => {
    if (!productName.trim() || price === '' || quantity === '') {
      alert('Please complete the product name, price, and quantity fields.');
      return;
    }

    const numericPrice = Number(price);
    const numericQuantity = Number(quantity);

    if (Number.isNaN(numericPrice) || Number.isNaN(numericQuantity)) {
      alert('Price and quantity must be valid numbers.');
      return;
    }

    const payload: Record<string, any> = {
      product_name: productName.trim(),
      category,
      price_per_unit: numericPrice,
      unit,
      quantity: numericQuantity,
      description,
      status: existingListing?.status || 'Active',
    };

    if (imageFile) {
      const form = new FormData();
      Object.entries(payload).forEach(([key, value]) => {
        if (value !== undefined && value !== null) form.append(key, String(value));
      });
      form.append('image', imageFile);

      try {
        const { request } = await import('../../../../lib/api');
        const endpoint = mode === 'edit' && existingListing ? `/api/producer/listings/${existingListing.id}` : '/api/producer/listings';
        const method = mode === 'edit' ? 'PUT' : 'POST';
        const data = await request(endpoint, { method, body: form });
        const responseData = data.listing || data.data || data;
        const nextItem = normalizeListing(responseData);
        onSave(nextItem);
        alert(mode === 'edit' ? 'Listing updated' : 'Listing created');
      } catch (err: any) {
        console.error(err);
        alert(err.message || (mode === 'edit' ? 'Update listing failed' : 'Create listing failed'));
      }
      return;
    }

    try {
      const { request } = await import('../../../../lib/api');
      const endpoint = mode === 'edit' && existingListing ? `/api/producer/listings/${existingListing.id}` : '/api/producer/listings';
      const method = mode === 'edit' ? 'PUT' : 'POST';
      const data = await request(endpoint, { method, body: payload });
      const responseData = data.listing || data.data || data;
      const nextItem = normalizeListing(responseData);
      onSave(nextItem);
      alert(mode === 'edit' ? 'Listing updated' : 'Listing created');
    } catch (err: any) {
      console.error(err);
      alert(err.message || (mode === 'edit' ? 'Update listing failed' : 'Create listing failed'));
    }
  };

  return (
    <div className="space-y-4 py-4">
      <div className="grid md:grid-cols-2 gap-4">
        <div>
          <Label htmlFor="productName">Product Name</Label>
          <Input id="productName" value={productName} onChange={(e) => setProductName(e.target.value)} placeholder="e.g., Premium Rice" />
        </div>
        <div>
          <Label htmlFor="category">Category</Label>
          <select id="category" value={category} onChange={(e) => setCategory(e.target.value)} className="block w-full border rounded p-2">
            <option value="grains">Grains</option>
            <option value="fish">Fish</option>
            <option value="vegetables">Vegetables</option>
            <option value="fruits">Fruits</option>
            <option value="livestock">Livestock</option>
          </select>
        </div>
      </div>

      <div>
        <Label htmlFor="description">Description</Label>
        <Textarea id="description" value={description} onChange={(e) => setDescription(e.target.value)} rows={3} placeholder="Describe your product" />
      </div>

      <div className="grid md:grid-cols-3 gap-4">
        <div>
          <Label htmlFor="price">Price</Label>
          <Input id="price" type="number" value={price} onChange={(e) => setPrice(e.target.value === '' ? '' : Number(e.target.value))} placeholder="125" />
        </div>
        <div>
          <Label htmlFor="unit">Unit</Label>
          <select id="unit" value={unit} onChange={(e) => setUnit(e.target.value)} className="block w-full border rounded p-2">
            <option value="kg">Kilogram (kg)</option>
            <option value="g">Gram (g)</option>
            <option value="sack">Sack</option>
            <option value="piece">Piece</option>
          </select>
        </div>
        <div>
          <Label htmlFor="quantity">Available Quantity</Label>
          <Input id="quantity" type="number" value={quantity} onChange={(e) => setQuantity(e.target.value === '' ? '' : Number(e.target.value))} placeholder="500" />
        </div>
      </div>

      <div>
        <Label htmlFor="imageInput">Product Image</Label>
        <input id="imageInput" type="file" accept="image/*" className="mt-2" onChange={(e) => setImageFile(e.target.files?.[0] || null)} />
      </div>

      <div className="flex justify-end gap-2 pt-4">
        <Button variant="outline" onClick={onCancel}>Cancel</Button>
        <Button className="bg-green-600 hover:bg-green-700" onClick={handleSubmit}>{mode === 'edit' ? 'Save Changes' : 'Create Listing'}</Button>
      </div>
    </div>
  );
}
