import { useState, useEffect } from 'react';
import { Plus, Edit, Trash2, Eye, Package, TrendingUp, Search } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../../components/ui/card';
import { Button } from '../../../components/ui/button';
import { Badge } from '../../../components/ui/badge';
import { Input } from '../../../components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../../../components/ui/select';
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

const resolveImageUrl = (value: string | null | undefined) => {
  if (!value) return null;
  if (value.startsWith('http://') || value.startsWith('https://')) return value;
  const configuredApiUrl = import.meta.env.VITE_API_URL?.replace(/\/$/, '').replace(/\/api$/, '');
  const normalizedValue = value.replace(/^\/+/, '').replace(/^public\//, '');
  const storagePath = normalizedValue.startsWith('storage/') ? normalizedValue : `storage/${normalizedValue}`;
  return configuredApiUrl ? `${configuredApiUrl}/${storagePath}` : `/${storagePath}`;
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
    image: item.image,
    image_url: imageUrl,
    image_path: item.image_path || item.imagePath || null,
    raw: item,
  };
};

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    Active: 'bg-[#22C55E]',
    'Low Stock': 'bg-[#F59E0B]',
    'Out of Stock': 'bg-red-500',
    Draft: 'bg-[#123C5C]',
    available: 'bg-[#22C55E]',
    inactive: 'bg-[#123C5C]',
  };
  return colors[status] || 'bg-[#123C5C]';
};

function ListingImage({ src, alt, className = '' }: { src?: string | null; alt: string; className?: string }) {
  const [imageFailed, setImageFailed] = useState(false);

  useEffect(() => {
    setImageFailed(false);
  }, [src]);

  if (src && !imageFailed) {
    return (
      <img
        src={src}
        alt={alt}
        className={`w-full aspect-[4/3] object-cover rounded-lg ${className}`}
        onError={() => setImageFailed(true)}
      />
    );
  }

  return (
    <div className={`w-full aspect-[4/3] rounded-lg bg-[#0F9488]/5 text-[#0F9488] flex items-center justify-center ${className}`} aria-label="No product image">
      <Package className="w-12 h-12" aria-hidden="true" />
    </div>
  );
}

const getListingImageUrl = (listing?: Listing | null) => {
  if (!listing) return null;

  return resolveImageUrl(
    listing.image_url
      || listing.image_path
      || listing.raw?.image_url
      || listing.raw?.imageUrl
      || listing.raw?.image_path
      || listing.raw?.imagePath
      || null,
  );
};

export default function MyListings() {
  const [listings, setListings] = useState<Listing[]>([]);
  const [listingSearch, setListingSearch] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('all');
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

  const categories = Array.from(new Set(listings.map((listing) => listing.category))).sort();
  const normalizedSearch = listingSearch.trim().toLowerCase();
  const filteredListings = listings.filter((listing) => {
    const matchesSearch = [listing.name, listing.category]
      .some((value) => value.toLowerCase().includes(normalizedSearch));
    const matchesCategory = selectedCategory === 'all' || listing.category === selectedCategory;
    return matchesSearch && matchesCategory;
  });

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

  // `item` here is already a normalized Listing (ListingForm normalizes the API
  // response itself before calling onSave), so we use it directly instead of
  // re-running normalizeListing on it — re-normalizing an already-normalized
  // object looked up raw-API field names like `listing_id` that don't exist on
  // it, which is fragile and was a likely source of listings losing data.
  const addListingToState = (item: Listing) => {
    setListings((current) => {
      const exists = current.some((entry) => entry.id === item.id);
      if (!exists) return [item, ...current];
      return current.map((entry) => (entry.id === item.id ? item : entry));
    });
  };

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="font-display text-2xl sm:text-3xl text-[#123C5C] tracking-tight flex items-center gap-3">
            <Package className="w-8 h-8 text-[#22C55E]" />
            My Digital Stall
          </h1>
          <p className="text-[#45586B] mt-1">Pamahalaan ang iyong product listings at inventory</p>
        </div>

        <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
          <DialogTrigger asChild>
            <Button className="rounded-full font-bold bg-[#22C55E] hover:bg-[#15803D]">
              <Plus className="w-4 h-4 mr-2" />
              Magdagdag ng Listing
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-2xl">
            <DialogHeader>
              <DialogTitle>Gumawa ng Bagong Product Listing</DialogTitle>
              <DialogDescription>Magdagdag ng produkto sa iyong digital stall</DialogDescription>
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
              <DialogTitle>Detalye ng Produkto</DialogTitle>
            </DialogHeader>
            {viewingListing && (
              <div className="space-y-4">
                <div className="text-center">
                  <ListingImage src={getListingImageUrl(viewingListing)} alt={viewingListing.name} className="max-w-sm mx-auto mb-4" />
                  <h3 className="text-xl font-bold text-[#123C5C]">{viewingListing.name}</h3>
                  <p className="text-[#45586B]">{viewingListing.category}</p>
                </div>
                <div className="space-y-3">
                  <div className="flex justify-between">
                    <span className="text-[#45586B]">Presyo:</span>
                    <span className="font-bold text-[#22C55E]">₱{viewingListing.price} bawat {viewingListing.unit}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-[#45586B]">Available na Quantity:</span>
                    <span className="font-bold text-[#123C5C]">{viewingListing.quantity} {viewingListing.unit}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-[#45586B]">Status:</span>
                    <Badge className={getStatusColor(viewingListing.status) + ' text-white'}>
                      {viewingListing.status}
                    </Badge>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-[#45586B]">Views:</span>
                    <span className="font-bold text-[#123C5C]">{viewingListing.views}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-[#45586B]">Orders:</span>
                    <span className="font-bold text-[#123C5C]">{viewingListing.orders}</span>
                  </div>
                </div>
              </div>
            )}
          </DialogContent>
        </Dialog>
      </div>

      <div className="grid md:grid-cols-4 gap-4">
        <Card className="border border-[#E7E1D0]">
          <CardContent className="p-4">
            <div className="text-2xl font-bold text-[#123C5C]">{listings.length}</div>
            <div className="text-sm text-[#45586B]">Kabuuang Listings</div>
          </CardContent>
        </Card>
        <Card className="border border-[#E7E1D0]">
          <CardContent className="p-4">
            <div className="text-2xl font-bold text-[#22C55E]">
              {listings.filter((listing) => String(listing.status).toLowerCase() === 'active' || String(listing.status).toLowerCase() === 'available').length}
            </div>
            <div className="text-sm text-[#45586B]">Active na Produkto</div>
          </CardContent>
        </Card>
        <Card className="border border-[#E7E1D0]">
          <CardContent className="p-4">
            <div className="text-2xl font-bold text-[#0F9488]">{listings.reduce((sum, listing) => sum + listing.views, 0)}</div>
            <div className="text-sm text-[#45586B]">Kabuuang Views</div>
          </CardContent>
        </Card>
        <Card className="border border-[#E7E1D0]">
          <CardContent className="p-4">
            <div className="text-2xl font-bold text-[#0E7490]">{listings.reduce((sum, listing) => sum + listing.orders, 0)}</div>
            <div className="text-sm text-[#45586B]">Kabuuang Orders</div>
          </CardContent>
        </Card>
      </div>

      <Card className="overflow-hidden border-[#E7E1D0] bg-white shadow-sm">
        <CardHeader className="border-b border-[#E7E1D0]/70 bg-[#F5F1E5]/45">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <CardTitle className="font-display text-2xl text-[#123C5C]">My Digital Stall</CardTitle>
              <CardDescription className="mt-2">Manage your products and listings.</CardDescription>
            </div>
          </div>
          <div className="grid gap-3 pt-2 md:grid-cols-[minmax(0,1fr)_220px]">
            <div className="relative">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#45586B]" aria-hidden="true" />
              <Input
                value={listingSearch}
                onChange={(event) => setListingSearch(event.target.value)}
                placeholder="Search products..."
                aria-label="Search listings"
                className="border-[#E7E1D0] bg-white pl-9 text-[#123C5C] focus-visible:ring-[#22C55E]"
              />
            </div>
            <Select value={selectedCategory} onValueChange={setSelectedCategory}>
              <SelectTrigger className="border-[#E7E1D0] bg-white text-[#123C5C] focus:ring-[#22C55E]">
                <SelectValue placeholder="All Categories" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Categories</SelectItem>
                {categories.map((category) => <SelectItem key={category} value={category}>{category}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
        </CardHeader>
        <CardContent className="p-4 sm:p-6">
          {listings.length === 0 ? (
            <p className="py-8 text-center text-sm text-[#45586B]">Wala ka pang listings.</p>
          ) : filteredListings.length === 0 ? (
            <p className="py-8 text-center text-sm text-[#45586B]">No products found.</p>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
              {filteredListings.map((listing) => (
                <Card key={listing.id} className="overflow-hidden border border-[#E7E1D0] bg-white shadow-sm transition-shadow hover:shadow-md">
                  <ListingImage src={getListingImageUrl(listing)} alt={listing.name} className="rounded-none" />
                  <CardHeader className="pb-3">
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <CardTitle className="truncate text-lg text-[#123C5C]">{listing.name}</CardTitle>
                        <CardDescription className="mt-1">{listing.category}</CardDescription>
                      </div>
                      <Badge className={`${getStatusColor(listing.status)} shrink-0 text-white`}>{listing.status}</Badge>
                    </div>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="flex items-baseline gap-2">
                      <span className="text-xl font-bold text-[#22C55E]">₱{listing.price.toLocaleString()}</span>
                      <span className="text-sm text-[#45586B]">per {listing.unit}</span>
                    </div>
                    <div className="flex items-center justify-between text-sm">
                      <span className="text-[#45586B]">Available quantity</span>
                      <span className="font-bold text-[#123C5C]">{listing.quantity} {listing.unit}</span>
                    </div>
                    <div className="flex gap-2 border-t border-[#E7E1D0] pt-3">
                      <Button type="button" variant="outline" size="sm" className="flex-1" onClick={() => handleView(listing)}>
                        <Eye className="mr-1 h-4 w-4" />
                        View
                      </Button>
                      <Button type="button" variant="outline" size="sm" className="flex-1" onClick={() => handleEdit(listing)}>
                        <Edit className="mr-1 h-4 w-4" />
                        Edit
                      </Button>
                      <Button type="button" variant="ghost" size="sm" aria-label={`Delete ${listing.name}`} onClick={() => handleDelete(listing.id)}>
                        <Trash2 className="h-4 w-4 text-red-600" />
                      </Button>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

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
            <DialogTitle>I-edit ang Product Listing</DialogTitle>
            <DialogDescription>I-update ang detalye ng listing at inventory</DialogDescription>
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
                addListingToState(item);
                await fetchListings();
                setEditingListing(null);
                setIsEditOpen(false);
              }}
            />
          )}
        </DialogContent>
      </Dialog>

      <Card className="bg-[#0F9488]/5 border-[#0F9488]/30">
        <CardContent className="p-6">
          <h3 className="font-bold text-[#123C5C] mb-3">Tips para sa Mas Maayos na Listings</h3>
          <div className="grid md:grid-cols-2 gap-4 text-sm text-[#0B4842]">
            <div className="flex items-start gap-2">
              <div className="w-5 h-5 bg-[#0F9488] text-white rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold mt-0.5">1</div>
              <span>Gumamit ng malinaw at descriptive na product names na nagpapakita ng quality</span>
            </div>
            <div className="flex items-start gap-2">
              <div className="w-5 h-5 bg-[#0F9488] text-white rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold mt-0.5">2</div>
              <span>Panatilihing competitive ang pricing batay sa AI insights</span>
            </div>
            <div className="flex items-start gap-2">
              <div className="w-5 h-5 bg-[#0F9488] text-white rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold mt-0.5">3</div>
              <span>Regular na i-update ang inventory para mapanatili ang tiwala ng buyers</span>
            </div>
            <div className="flex items-start gap-2">
              <div className="w-5 h-5 bg-[#0F9488] text-white rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold mt-0.5">4</div>
              <span>Banggitin ang certifications at quality standards</span>
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
  onSave: (item: Listing) => void;
}) {
  const [productName, setProductName] = useState(existingListing?.name || '');
  const [category, setCategory] = useState((existingListing?.category || 'grains').toLowerCase());
  const [description, setDescription] = useState(existingListing?.raw?.description || '');
  const [price, setPrice] = useState<number | ''>(existingListing?.price ?? '');
  const [unit, setUnit] = useState(existingListing?.unit || 'kg');
  const [quantity, setQuantity] = useState<number | ''>(existingListing?.quantity ?? '');
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState<string | null>(getListingImageUrl(existingListing));

  useEffect(() => {
    if (!existingListing) return;
    setProductName(existingListing.name || '');
    setCategory((existingListing.category || 'grains').toLowerCase());
    setDescription(existingListing.raw?.description || '');
    setPrice(existingListing.price ?? '');
    setUnit(existingListing.unit || 'kg');
    setQuantity(existingListing.quantity ?? '');
    setImageFile(null);
    setImagePreview(getListingImageUrl(existingListing));
  }, [existingListing, mode]);

  useEffect(() => {
    if (!imageFile) return;
    const objectUrl = URL.createObjectURL(imageFile);
    setImagePreview(objectUrl);
    return () => URL.revokeObjectURL(objectUrl);
  }, [imageFile]);

  const handleSubmit = async () => {
    if (!productName.trim() || price === '' || quantity === '') {
      alert('Kumpletuhin ang product name, price, at quantity fields.');
      return;
    }

    const numericPrice = Number(price);
    const numericQuantity = Number(quantity);

    if (Number.isNaN(numericPrice) || Number.isNaN(numericQuantity)) {
      alert('Dapat valid numbers ang price at quantity.');
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
        const method = mode === 'edit' ? 'POST' : 'POST';
        if (mode === 'edit') form.append('_method', 'PUT');
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
      // No new file was uploaded in this save, so if the update response
      // doesn't include an image field, keep showing the listing's existing
      // photo instead of silently blanking it out.
      if (!nextItem.image_url && existingListing?.image_url) {
        nextItem.image_url = existingListing.image_url;
      }
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
          <select id="category" value={category} onChange={(e) => setCategory(e.target.value)} className="block w-full border border-[#E7E1D0] rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-[#22C55E]">
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
        <Textarea id="description" value={description} onChange={(e) => setDescription(e.target.value)} rows={3} placeholder="Ilarawan ang iyong produkto" />
      </div>

      <div className="grid md:grid-cols-3 gap-4">
        <div>
          <Label htmlFor="price">Price</Label>
          <Input id="price" type="number" value={price} onChange={(e) => setPrice(e.target.value === '' ? '' : Number(e.target.value))} placeholder="125" />
        </div>
        <div>
          <Label htmlFor="unit">Unit</Label>
          <select id="unit" value={unit} onChange={(e) => setUnit(e.target.value)} className="block w-full border border-[#E7E1D0] rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-[#22C55E]">
            <option value="kg">Kilogram (kg)</option>
            <option value="g">Gram (g)</option>
            <option value="sack">Sack</option>
            <option value="piece">Piece</option>
          </select>
        </div>
        <div>
          <Label htmlFor="quantity">Available na Quantity</Label>
          <Input id="quantity" type="number" value={quantity} onChange={(e) => setQuantity(e.target.value === '' ? '' : Number(e.target.value))} placeholder="500" />
        </div>
      </div>

      <div>
        <Label htmlFor="imageInput">Product Image</Label>
        <input id="imageInput" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" className="mt-2" onChange={(e) => setImageFile(e.target.files?.[0] || null)} />
        {imagePreview && (
          <div className="mt-3 max-w-xs">
            <ListingImage src={imagePreview} alt="Selected product preview" />
          </div>
        )}
        {!imagePreview && <p className="mt-2 text-sm text-[#45586B]">Walang napiling image. HarborAI placeholder ang ipapakita.</p>}
      </div>

      <div className="flex justify-end gap-2 pt-4">
        <Button variant="outline" className="rounded-full" onClick={onCancel}>Kanselahin</Button>
        <Button className="rounded-full font-bold bg-[#22C55E] hover:bg-[#15803D]" onClick={handleSubmit}>{mode === 'edit' ? 'I-save ang Changes' : 'Gumawa ng Listing'}</Button>
      </div>
    </div>
  );
}