import { useState, useEffect } from 'react';
import {
  Plus,
  Edit,
  Trash2,
  Package,
  Search,
  ArrowLeft,
  Sparkles,
  Wand2,
} from 'lucide-react';

import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from '../../../components/ui/card';

import { Button } from '../../../components/ui/button';
import { Badge } from '../../../components/ui/badge';
import { Input } from '../../../components/ui/input';

import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '../../../components/ui/select';

import { Label } from '../../../components/ui/label';
import { Textarea } from '../../../components/ui/textarea';

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '../../../components/ui/dialog';

/* =========================================================
   LISTING TYPE
========================================================= */

type Listing = {
  id: number;
  name: string;
  category: string;
  price: number;
  unit: string;
  quantity: number;
  origin?: string | null;
  status: string;
  views: number;
  orders: number;

  // Original image fields
  image?: string | null;
  image_url?: string | null;
  image_path?: string | null;

  // Keep original API response
  raw?: any;
};

/* =========================================================
   API / IMAGE CONFIGURATION
========================================================= */

/**
 * Gets the Laravel backend URL.
 *
 * Example:
 *
 * VITE_API_URL=http://127.0.0.1:8000/api
 *
 * becomes:
 *
 * http://127.0.0.1:8000
 */
const getLaravelBaseUrl = (): string => {
  const configuredApiUrl = String(
    import.meta.env.VITE_API_URL || '',
  ).trim();

  if (configuredApiUrl) {
    return configuredApiUrl
      .replace(/\/+$/, '')
      .replace(/\/api$/i, '');
  }

  /**
   * IMPORTANT:
   *
   * Your Laravel server is running on:
   *
   * http://127.0.0.1:8000
   *
   * Therefore, if VITE_API_URL is not configured,
   * use Laravel directly.
   */
  return 'http://127.0.0.1:8000';
};

/* =========================================================
   IMAGE URL RESOLVER
========================================================= */

/**
 * Converts whatever image path Laravel returns
 * into a browser-accessible image URL.
 */
const resolveImageUrl = (
  value: unknown,
): string | null => {
  if (value === null || value === undefined) {
    return null;
  }

  if (typeof value !== 'string') {
    return null;
  }

  let imageValue = value.trim();

  if (!imageValue) {
    return null;
  }

  /**
   * Full URLs should be used directly.
   *
   * This also supports:
   * - blob URLs for newly selected files
   * - data URLs
   */
  if (
    imageValue.startsWith('http://') ||
    imageValue.startsWith('https://') ||
    imageValue.startsWith('blob:') ||
    imageValue.startsWith('data:')
  ) {
    return imageValue;
  }

  /**
   * Normalize Windows-style backslashes.
   */
  imageValue = imageValue.replace(/\\/g, '/');

  /**
   * Remove leading slash.
   *
   * /storage/products/test.jpg
   *
   * becomes:
   *
   * storage/products/test.jpg
   */
  imageValue = imageValue.replace(/^\/+/, '');

  /**
   * Remove public/ prefix.
   *
   * public/products/test.jpg
   *
   * becomes:
   *
   * products/test.jpg
   */
  imageValue = imageValue.replace(
    /^public\//i,
    '',
  );

  /**
   * Handle:
   *
   * storage/app/public/products/test.jpg
   *
   * This is a filesystem path and should become:
   *
   * /storage/products/test.jpg
   */
  imageValue = imageValue.replace(
    /^storage\/app\/public\//i,
    '',
  );

  /**
   * Handle:
   *
   * app/public/products/test.jpg
   */
  imageValue = imageValue.replace(
    /^app\/public\//i,
    '',
  );

  /**
   * If Laravel already returned:
   *
   * storage/products/test.jpg
   *
   * remove storage/ so we don't create:
   *
   * /storage/storage/products/test.jpg
   */
  imageValue = imageValue.replace(
    /^storage\//i,
    '',
  );

  /**
   * If Laravel returns a URL path beginning with /api,
   * don't force it into /storage.
   */
  if (
    imageValue.startsWith('api/') ||
    imageValue.startsWith('api\\')
  ) {
    return `${getLaravelBaseUrl()}/${imageValue}`;
  }

  /**
   * Final Laravel storage URL.
   *
   * Example:
   *
   * http://127.0.0.1:8000/storage/products/rice.jpg
   */
  return `${getLaravelBaseUrl()}/storage/${imageValue}`;
};

/* =========================================================
   GET ALL POSSIBLE IMAGE VALUES
========================================================= */

/**
 * Laravel may return the product image under different
 * property names depending on the controller/resource.
 *
 * This function collects all common possibilities.
 */
const getImageValues = (
  item: any,
): unknown[] => {
  if (!item) {
    return [];
  }

  return [
    item.image_url,
    item.imageUrl,

    item.image_path,
    item.imagePath,

    item.image,

    item.photo_url,
    item.photoUrl,

    item.photo_path,
    item.photoPath,

    item.photo,

    item.product_image,
    item.productImage,

    item.thumbnail_url,
    item.thumbnailUrl,

    item.thumbnail,

    item.picture_url,
    item.pictureUrl,

    item.picture,

    /**
     * Some Laravel API resources may return:
     *
     * image: {
     *   url: "..."
     * }
     */
    item.image?.url,
    item.image?.image_url,
    item.image?.imageUrl,
    item.image?.path,
    item.image?.image_path,

    item.raw?.image_url,
    item.raw?.imageUrl,

    item.raw?.image_path,
    item.raw?.imagePath,

    item.raw?.image,

    item.raw?.photo_url,
    item.raw?.photoUrl,

    item.raw?.photo_path,
    item.raw?.photoPath,

    item.raw?.photo,

    item.raw?.product_image,
    item.raw?.productImage,

    item.raw?.thumbnail_url,
    item.raw?.thumbnailUrl,

    item.raw?.thumbnail,

    item.raw?.picture_url,
    item.raw?.pictureUrl,

    item.raw?.picture,

    item.raw?.image?.url,
    item.raw?.image?.image_url,
    item.raw?.image?.imageUrl,
    item.raw?.image?.path,
    item.raw?.image?.image_path,
  ];
};

/* =========================================================
   GET IMAGE CANDIDATES
========================================================= */

/**
 * Converts all possible image values into usable URLs.
 */
const getImageCandidates = (
  item: any,
): string[] => {
  const values = getImageValues(item);

  const urls = values
    .map((value) => resolveImageUrl(value))
    .filter(
      (url): url is string =>
        Boolean(url),
    );

  /**
   * Remove duplicate URLs.
   */
  return [...new Set(urls)];
};

/* =========================================================
   NORMALIZE LISTING
========================================================= */

const normalizeListing = (
  item: any,
): Listing => {
  const category =
    item?.product_category ||
    item?.category ||
    'General';

  const unit =
    item?.unit_of_measure ||
    item?.unit ||
    'kg';

  const price = Number(
    item?.current_price_per_unit ??
      item?.price_per_unit ??
      item?.price ??
      0,
  );

  const quantity = Number(
    item?.quantity_available ??
      item?.quantity ??
      0,
  );

  const origin =
    item?.origin ??
    item?.product_origin ??
    item?.source ??
    item?.place_of_origin ??
    '';

  const status = String(
    item?.status || 'Active',
  ).replace(
    /^./,
    (char) => char.toUpperCase(),
  );

  /**
   * Get the first available image URL.
   */
  const imageCandidates =
    getImageCandidates(item);

  const imageUrl =
    imageCandidates[0] || null;

  return {
    id: Number(
      item?.listing_id ??
        item?.id ??
        Date.now(),
    ),

    name:
      item?.product_name ||
      item?.name ||
      'Unnamed Product',

    category,

    price,

    unit,

    quantity,

    origin:
      typeof origin === 'string'
        ? origin
        : String(origin || ''),

    status,

    views: Number(
      item?.views ?? 0,
    ),

    orders: Number(
      item?.orders ?? 0,
    ),

    image:
      typeof item?.image === 'string'
        ? item.image
        : null,

    image_url: imageUrl,

    image_path:
      item?.image_path ||
      item?.imagePath ||
      null,

    raw: item,
  };
};

/* =========================================================
   STATUS COLOR
========================================================= */

const getStatusColor = (
  status: string,
) => {
  const colors: Record<
    string,
    string
  > = {
    Active: 'bg-[#22C55E]',
    'Low Stock': 'bg-[#F59E0B]',
    'Out of Stock': 'bg-red-500',
    Draft: 'bg-[#123C5C]',
    available: 'bg-[#22C55E]',
    inactive: 'bg-[#123C5C]',
  };

  return (
    colors[status] ||
    'bg-[#123C5C]'
  );
};

/* =========================================================
   PRODUCT IMAGE COMPONENT
========================================================= */

function ListingImage({
  candidates,
  alt,
  className = '',
}: {
  candidates?: string[];
  alt: string;
  className?: string;
}) {
  const safeCandidates =
    candidates || [];

  const [currentIndex, setCurrentIndex] =
    useState(0);

  const [imageFailed, setImageFailed] =
    useState(false);

  /**
   * Reset image state when the product/image changes.
   */
  useEffect(() => {
    setCurrentIndex(0);
    setImageFailed(false);
  }, [safeCandidates.join('|')]);

  const currentSrc =
    safeCandidates[currentIndex] ||
    null;

  /**
   * When one URL fails, automatically try
   * the next available image URL.
   */
  const handleImageError = () => {
    console.error(
      'HarborAI: Product image failed to load:',
      currentSrc,
    );

    if (
      currentIndex <
      safeCandidates.length - 1
    ) {
      setCurrentIndex(
        (previousIndex) =>
          previousIndex + 1,
      );

      return;
    }

    setImageFailed(true);
  };

  /**
   * Valid image URL.
   */
  if (
    currentSrc &&
    !imageFailed
  ) {
    return (
      <div
        className={`relative aspect-[4/3] w-full overflow-hidden bg-[#F5F1E5] ${className}`}
      >
        <img
          src={currentSrc}
          alt={alt}
          className="h-full w-full object-cover"
          loading="lazy"
          onError={
            handleImageError
          }
        />
      </div>
    );
  }

  /**
   * Fallback placeholder.
   */
  return (
    <div
      className={`flex aspect-[4/3] w-full items-center justify-center rounded-lg bg-[#0F9488]/5 text-[#0F9488] ${className}`}
      aria-label="No product image available"
    >
      <Package
        className="h-12 w-12"
        aria-hidden="true"
      />
    </div>
  );
}

/* =========================================================
   LISTING IMAGE URL HELPER
========================================================= */

const getListingImageCandidates = (
  listing?: Listing | null,
): string[] => {
  if (!listing) {
    return [];
  }

  return getImageCandidates(
    listing,
  );
};

/* =========================================================
   MAIN COMPONENT
========================================================= */

export default function MyListings() {
  const [listings, setListings] =
    useState<Listing[]>([]);

  const [listingSearch, setListingSearch] =
    useState('');

  const [selectedCategory, setSelectedCategory] =
    useState('all');

  const [isCreateOpen, setIsCreateOpen] =
    useState(false);

  const [isEditOpen, setIsEditOpen] =
    useState(false);

  const [editingListing, setEditingListing] =
    useState<Listing | null>(null);

  const [viewingListing, setViewingListing] =
    useState<Listing | null>(null);

  /* =======================================================
     FETCH LISTINGS
  ======================================================= */

  const fetchListings =
    async () => {
      try {
        const { request } =
          await import(
            '../../../../lib/api'
          );

        const data =
          await request(
            '/api/producer/dashboard',
          );

        console.log(
          'HarborAI listings API response:',
          data,
        );

        const rawItems =
          Array.isArray(
            data?.product_listings,
          )
            ? data.product_listings
            : [];

        const items =
          rawItems.map(
            normalizeListing,
          );

        /**
         * Debug the image URLs.
         */
        items.forEach(
          (listing) => {
            console.log(
              `HarborAI image URLs for "${listing.name}":`,
              getListingImageCandidates(
                listing,
              ),
              listing.raw,
            );
          },
        );

        setListings(items);
      } catch (err) {
        console.error(
          'Unable to load producer listings:',
          err,
        );

        setListings([]);
      }
    };

  /* =======================================================
     INITIAL LOAD
  ======================================================= */

  useEffect(() => {
    fetchListings();
  }, []);

  /* =======================================================
     FILTERS
  ======================================================= */

  const categories =
    Array.from(
      new Set(
        listings.map(
          (listing) =>
            listing.category,
        ),
      ),
    ).sort();

  const normalizedSearch =
    listingSearch
      .trim()
      .toLowerCase();

  const filteredListings =
    listings.filter(
      (listing) => {
        const matchesSearch = [
          listing.name,
          listing.category,
        ].some(
          (value) =>
            value
              .toLowerCase()
              .includes(
                normalizedSearch,
              ),
        );

        const matchesCategory =
          selectedCategory ===
            'all' ||
          listing.category ===
            selectedCategory;

        return (
          matchesSearch &&
          matchesCategory
        );
      },
    );

  /* =======================================================
     EDIT
  ======================================================= */

  const handleEdit = (
    listing: Listing,
  ) => {
    setEditingListing({
      ...listing,

      raw: {
        ...(listing.raw ?? {}),

        description:
          listing.raw
            ?.description ?? '',

        product_name:
          listing.name,

        category:
          listing.category,

        price:
          listing.price,

        unit:
          listing.unit,

        quantity:
          listing.quantity,

        origin:
          listing.origin ??
          listing.raw?.origin ??
          listing.raw?.product_origin ??
          listing.raw?.source ??
          listing.raw?.place_of_origin ??
          '',

        status:
          listing.status,

        /**
         * Keep all image information.
         */
        image_url:
          listing.image_url,

        image_path:
          listing.image_path,

        image:
          listing.image,
      },
    });

    setIsEditOpen(true);
  };

  /* =======================================================
     VIEW
  ======================================================= */

  const handleView = (
    listing: Listing,
  ) => {
    setViewingListing(
      listing,
    );
  };

  /* =======================================================
     DELETE
  ======================================================= */

  const handleDelete =
    async (
      id: number,
    ) => {
      if (
        !window.confirm(
          'Are you sure you want to delete this listing?',
        )
      ) {
        return;
      }

      try {
        const { request } =
          await import(
            '../../../../lib/api'
          );

        await request(
          `/api/producer/listings/${id}`,
          {
            method: 'DELETE',
          },
        );

        setListings(
          (current) =>
            current.filter(
              (listing) =>
                listing.id !==
                id,
            ),
        );
      } catch (err: any) {
        console.error(err);

        alert(
          err?.message ||
            'Delete listing failed',
        );
      }
    };

  /* =======================================================
     ADD / UPDATE STATE
  ======================================================= */

  const addListingToState = (
    item: Listing,
  ) => {
    setListings(
      (current) => {
        const exists =
          current.some(
            (entry) =>
              entry.id ===
              item.id,
          );

        if (!exists) {
          return [
            item,
            ...current,
          ];
        }

        return current.map(
          (entry) =>
            entry.id === item.id
              ? item
              : entry,
        );
      },
    );
  };

  /* =======================================================
     RENDER
  ======================================================= */

  return (
    <div className="space-y-6 p-6">

      {/* =====================================================
          PAGE HEADER
      ===================================================== */}

      <div className="flex items-center justify-between">

        <div>
          <h1 className="flex items-center gap-3 font-display text-2xl tracking-tight text-[#123C5C] sm:text-3xl">
            <Package className="h-8 w-8 text-[#22C55E]" />

            My Digital Stall
          </h1>

          <p className="mt-1 text-[#45586B]">
            Pamahalaan ang iyong product listings at inventory
          </p>
        </div>

        {/* CREATE BUTTON */}

        <Dialog
          open={isCreateOpen}
          onOpenChange={
            setIsCreateOpen
          }
        >
          <DialogTrigger
            asChild
          >
            <Button className="rounded-full bg-[#22C55E] font-bold hover:bg-[#15803D]">
              <Plus className="mr-2 h-4 w-4" />

              Magdagdag ng Listing
            </Button>
          </DialogTrigger>

          <DialogContent className="w-[98vw] max-w-5xl max-h-[92vh] overflow-y-auto">

            <DialogHeader>
              <DialogTitle>
                Gumawa ng Bagong Product Listing
              </DialogTitle>

              <DialogDescription>
                Magdagdag ng produkto sa iyong digital stall
              </DialogDescription>
            </DialogHeader>

            <ListingForm
              mode="create"
              onCancel={() =>
                setIsCreateOpen(
                  false,
                )
              }
              onSave={(item) => {
                addListingToState(
                  item,
                );

                setIsCreateOpen(
                  false,
                );
              }}
            />

          </DialogContent>
        </Dialog>

        {/* ===================================================
            VIEW PRODUCT DIALOG
            LANDSCAPE PRODUCT DETAIL LAYOUT
            Wide, compact, two-column product detail view.
        =================================================== */}

        <Dialog
          open={!!viewingListing}
          onOpenChange={(open) => {
            if (!open) {
              setViewingListing(null);
            }
          }}
        >
          <DialogContent
            className="w-[98vw] max-w-[1500px] max-h-[94vh] overflow-hidden border-0 bg-[#F8F7F3] p-0 shadow-2xl sm:rounded-2xl"
          >
            {viewingListing && (
              <div className="max-h-[94vh] overflow-y-auto">
                {/* =================================================
                    PRODUCT DETAIL HEADER
                ================================================= */}

                <div className="flex items-center justify-between border-b border-[#E7E1D0] bg-[#F8F7F3] px-5 py-3 sm:px-7">
                  <button
                    type="button"
                    onClick={() => setViewingListing(null)}
                    className="group inline-flex items-center gap-2 text-sm font-semibold text-[#7C7468] transition-colors hover:text-[#123C5C]"
                  >
                    <ArrowLeft className="h-4 w-4 transition-transform group-hover:-translate-x-0.5" />
                    Bumalik sa Listings
                  </button>

                  <div className="pr-8 text-right text-xs font-medium uppercase tracking-[0.16em] text-[#9A9287]">
                    Detalye ng Produkto
                  </div>
                </div>

                {/* =================================================
                    TWO-COLUMN PRODUCT DETAIL
                ================================================= */}

                <div className="grid lg:grid-cols-2 xl:grid-cols-[1.05fr_0.95fr] lg:min-h-[620px]">
                  {/* =================================================
                      LEFT: PRODUCT IMAGE + DESCRIPTION
                  ================================================= */}

                  <div className="border-b border-[#E7E1D0] bg-[#F8F7F3] p-6 sm:p-7 lg:border-b-0 lg:border-r lg:p-10 xl:p-12">
                    <div className="overflow-hidden rounded-xl bg-[#F1EEE6] shadow-sm">
                      <ListingImage
                        candidates={getListingImageCandidates(
                          viewingListing,
                        )}
                        alt={viewingListing.name}
                        className="aspect-[16/10] w-full rounded-none"
                      />
                    </div>

                    <div className="mt-8">
                      <p className="mb-2 text-xs font-bold uppercase tracking-[0.14em] text-[#8D857A]">
                        About This Item
                      </p>

                      <p className="max-w-3xl text-[15px] leading-8 text-[#45586B]">
                        {viewingListing.raw?.description?.trim() ||
                          `Fresh ${viewingListing.name} available from your digital stall.`}
                      </p>

                      <div className="mt-6 border-t border-[#E7E1D0] pt-5">
                        <div className="grid gap-4 sm:grid-cols-2">
                          <div>
                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-[#8D857A]">
                              Category
                            </p>
                            <p className="mt-1 font-semibold capitalize text-[#123C5C]">
                              {viewingListing.category}
                            </p>
                          </div>

                          <div>
                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-[#8D857A]">
                              Unit
                            </p>
                            <p className="mt-1 font-semibold text-[#123C5C]">
                              {viewingListing.unit}
                            </p>
                          </div>

                          <div className="sm:col-span-2">
                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-[#8D857A]">
                              Origin / Saan Nagmula
                            </p>
                            <p className="mt-1 font-semibold text-[#123C5C]">
                              {viewingListing.origin ||
                                viewingListing.raw?.origin ||
                                viewingListing.raw?.product_origin ||
                                viewingListing.raw?.source ||
                                viewingListing.raw?.place_of_origin ||
                                'Hindi pa nailalagay'}
                            </p>
                          </div>

                          <div>
                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-[#8D857A]">
                              Product Views
                            </p>
                            <p className="mt-1 font-semibold text-[#123C5C]">
                              {viewingListing.views.toLocaleString()}
                            </p>
                          </div>

                          <div>
                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-[#8D857A]">
                              Orders
                            </p>
                            <p className="mt-1 font-semibold text-[#123C5C]">
                              {viewingListing.orders.toLocaleString()}
                            </p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* =================================================
                      RIGHT: PRODUCT INFORMATION
                  ================================================= */}

                  <div className="bg-white p-6 sm:p-8 lg:p-10 xl:p-12">
                    <div className="w-full max-w-3xl min-w-0">
                      <p className="text-sm font-bold uppercase tracking-[0.14em] text-[#15803D]">
                        {viewingListing.category}
                      </p>

                      <h2 className="mt-3 break-words font-display text-3xl font-black uppercase leading-[1.05] tracking-tight text-[#2F281D] sm:text-4xl lg:text-5xl xl:text-6xl">
                        {viewingListing.name}
                      </h2>

                      <div className="mt-6 flex flex-wrap items-end gap-x-4 gap-y-2">
                        <span className="font-display text-4xl font-black tracking-tight text-[#15803D] sm:text-5xl lg:text-6xl">
                          ₱{viewingListing.price.toLocaleString()}
                        </span>

                        <span className="pb-1 text-lg text-[#8D857A]">
                          / {viewingListing.unit}
                        </span>
                      </div>

                      {/* PRODUCT META */}
                      <div className="mt-6 grid grid-cols-2 border-y border-[#E7E1D0]">
                        <div className="border-r border-[#E7E1D0] py-4 pr-4">
                          <p className="text-xs uppercase tracking-[0.1em] text-[#9A9287]">
                            Available
                          </p>
                          <p className="mt-1 text-base font-bold text-[#123C5C]">
                            {viewingListing.quantity.toLocaleString()}{" "}
                            {viewingListing.unit}
                          </p>
                        </div>

                        <div className="py-4 pl-4">
                          <p className="text-xs uppercase tracking-[0.1em] text-[#9A9287]">
                            Status
                          </p>
                          <div className="mt-1">
                            <Badge
                              className={`${getStatusColor(
                                viewingListing.status,
                              )} border-0 px-3 py-1 text-xs font-bold text-white`}
                            >
                              {viewingListing.status}
                            </Badge>
                          </div>
                        </div>
                      </div>

                      {/* INVENTORY */}
                      <div className="mt-6">
                        <p className="text-xs font-bold uppercase tracking-[0.14em] text-[#9A9287]">
                          Inventory
                        </p>

                        <div className="mt-2 flex items-center justify-between rounded-xl border border-[#E7E1D0] bg-[#F8F7F3] px-4 py-3">
                          <div>
                            <p className="text-sm text-[#7C7468]">
                              Available quantity
                            </p>
                            <p className="mt-1 text-2xl font-black text-[#123C5C]">
                              {viewingListing.quantity.toLocaleString()}
                              <span className="ml-2 text-base font-medium text-[#7C7468]">
                                {viewingListing.unit}
                              </span>
                            </p>
                          </div>

                          <Package className="h-8 w-8 text-[#15803D]" />
                        </div>
                      </div>

                      {/* PERFORMANCE */}
                      <div className="mt-5 grid grid-cols-2 gap-4">
                        <div className="rounded-xl border border-[#E7E1D0] bg-white px-4 py-3">
                          <p className="text-xs uppercase tracking-[0.1em] text-[#9A9287]">
                            Views
                          </p>
                          <p className="mt-1 text-xl font-black text-[#123C5C]">
                            {viewingListing.views.toLocaleString()}
                          </p>
                        </div>

                        <div className="rounded-xl border border-[#E7E1D0] bg-white px-4 py-3">
                          <p className="text-xs uppercase tracking-[0.1em] text-[#9A9287]">
                            Orders
                          </p>
                          <p className="mt-1 text-xl font-black text-[#123C5C]">
                            {viewingListing.orders.toLocaleString()}
                          </p>
                        </div>
                      </div>

                      {/* ACTIONS */}
                      <div className="mt-7 grid gap-4 sm:grid-cols-2">
                        <Button
                          type="button"
                          onClick={() => {
                            setViewingListing(null);
                            handleEdit(viewingListing);
                          }}
                          className="h-12 rounded-lg bg-[#1F7A2E] font-bold text-white shadow-sm hover:bg-[#176324]"
                        >
                          <Edit className="mr-2 h-4 w-4" />
                          I-edit ang Listing
                        </Button>

                        <Button
                          type="button"
                          variant="outline"
                          onClick={() => setViewingListing(null)}
                          className="h-12 rounded-lg border-[#D8D1C4] bg-white font-bold text-[#123C5C] hover:bg-[#F5F1E5]"
                        >
                          Isara
                        </Button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            )}
          </DialogContent>
        </Dialog>

      </div>

      {/* =====================================================
          SUMMARY CARDS
      ===================================================== */}

      <div className="grid gap-4 md:grid-cols-4">

        <Card className="border border-[#E7E1D0]">
          <CardContent className="p-4">
            <div className="text-2xl font-bold text-[#123C5C]">
              {listings.length}
            </div>

            <div className="text-sm text-[#45586B]">
              Kabuuang Listings
            </div>
          </CardContent>
        </Card>

        <Card className="border border-[#E7E1D0]">
          <CardContent className="p-4">

            <div className="text-2xl font-bold text-[#22C55E]">
              {
                listings.filter(
                  (listing) => {
                    const status =
                      String(
                        listing.status,
                      ).toLowerCase();

                    return (
                      status ===
                        'active' ||
                      status ===
                        'available'
                    );
                  },
                ).length
              }
            </div>

            <div className="text-sm text-[#45586B]">
              Active na Produkto
            </div>

          </CardContent>
        </Card>

        <Card className="border border-[#E7E1D0]">
          <CardContent className="p-4">

            <div className="text-2xl font-bold text-[#0F9488]">
              {listings.reduce(
                (sum, listing) =>
                  sum +
                  listing.views,
                0,
              )}
            </div>

            <div className="text-sm text-[#45586B]">
              Kabuuang Views
            </div>

          </CardContent>
        </Card>

        <Card className="border border-[#E7E1D0]">
          <CardContent className="p-4">

            <div className="text-2xl font-bold text-[#0E7490]">
              {listings.reduce(
                (sum, listing) =>
                  sum +
                  listing.orders,
                0,
              )}
            </div>

            <div className="text-sm text-[#45586B]">
              Kabuuang Orders
            </div>

          </CardContent>
        </Card>

      </div>

      {/* =====================================================
          MY DIGITAL STALL
      ===================================================== */}

      <Card className="overflow-hidden border-[#E7E1D0] bg-white shadow-sm">

        <CardHeader className="border-b border-[#E7E1D0]/70 bg-[#F5F1E5]/45">

          <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">

            <div>
              <CardTitle className="font-display text-2xl text-[#123C5C]">
                My Digital Stall
              </CardTitle>

              <CardDescription className="mt-2">
                Manage your products and listings.
              </CardDescription>
            </div>

          </div>

          {/* SEARCH + CATEGORY */}

          <div className="grid gap-3 pt-2 md:grid-cols-[minmax(0,1fr)_220px]">

            <div className="relative">

              <Search
                className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#45586B]"
                aria-hidden="true"
              />

              <Input
                value={
                  listingSearch
                }
                onChange={(event) =>
                  setListingSearch(
                    event.target
                      .value,
                  )
                }
                placeholder="Search products..."
                aria-label="Search listings"
                className="border-[#E7E1D0] bg-white pl-9 text-[#123C5C] focus-visible:ring-[#22C55E]"
              />

            </div>

            <Select
              value={
                selectedCategory
              }
              onValueChange={
                setSelectedCategory
              }
            >

              <SelectTrigger className="border-[#E7E1D0] bg-white text-[#123C5C] focus:ring-[#22C55E]">
                <SelectValue placeholder="All Categories" />
              </SelectTrigger>

              <SelectContent>

                <SelectItem value="all">
                  All Categories
                </SelectItem>

                {categories.map(
                  (category) => (
                    <SelectItem
                      key={
                        category
                      }
                      value={
                        category
                      }
                    >
                      {category}
                    </SelectItem>
                  ),
                )}

              </SelectContent>

            </Select>

          </div>

        </CardHeader>

        <CardContent className="p-4 sm:p-6">

          {/* NO LISTINGS */}

          {listings.length ===
          0 ? (

            <p className="py-8 text-center text-sm text-[#45586B]">
              Wala ka pang listings.
            </p>

          ) : filteredListings.length ===
            0 ? (

            /* NO SEARCH RESULTS */

            <p className="py-8 text-center text-sm text-[#45586B]">
              No products found.
            </p>

          ) : (

            /* PRODUCT GRID */

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">

              {filteredListings.map(
                (listing) => {

                  const imageCandidates =
                    getListingImageCandidates(
                      listing,
                    );

                  /**
                   * IMPORTANT DEBUG LOG.
                   *
                   * Open browser DevTools > Console
                   * to see the exact image URL.
                   */
                  console.log(
                    `HarborAI Digital Stall image for "${listing.name}":`,
                    imageCandidates,
                  );

                  return (
                    <Card
                      key={
                        listing.id
                      }
                      className="overflow-hidden border border-[#E7E1D0] bg-white shadow-sm transition-shadow hover:shadow-md"
                    >

                      {/* =================================================
                          PRODUCT IMAGE
                      ================================================= */}

                      {/* =================================================
                          CLICKABLE PRODUCT IMAGE
                          Clicking the image opens the product details.
                      ================================================= */}

                      <button
                        type="button"
                        onClick={() => handleView(listing)}
                        onKeyDown={(event) => {
                          if (
                            event.key === "Enter" ||
                            event.key === " "
                          ) {
                            event.preventDefault();
                            handleView(listing);
                          }
                        }}
                        className="group block w-full cursor-pointer border-0 bg-transparent p-0 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-[#22C55E] focus-visible:ring-offset-2"
                        aria-label={`View details for ${listing.name}`}
                      >
                        <div className="relative overflow-hidden">
                          <ListingImage
                            candidates={imageCandidates}
                            alt={listing.name}
                            className="rounded-none transition-transform duration-300 group-hover:scale-[1.03]"
                          />

                          <div className="pointer-events-none absolute inset-0 flex items-center justify-center bg-[#123C5C]/0 transition-all duration-300 group-hover:bg-[#123C5C]/35">
                            <div className="translate-y-2 rounded-full bg-white/95 px-4 py-2 text-sm font-bold text-[#123C5C] opacity-0 shadow-lg transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100">
                              Click to view details
                            </div>
                          </div>
                        </div>
                      </button>

                      {/* =================================================
                          PRODUCT HEADER
                      ================================================= */}

                      <CardHeader className="pb-3">

                        <div className="flex items-start justify-between gap-3">

                          <div className="min-w-0">

                            <CardTitle className="truncate text-lg text-[#123C5C]">
                              {
                                listing.name
                              }
                            </CardTitle>

                            <CardDescription className="mt-1">
                              {
                                listing.category
                              }
                            </CardDescription>

                          </div>

                          <Badge
                            className={`${getStatusColor(
                              listing.status,
                            )} shrink-0 text-white`}
                          >
                            {
                              listing.status
                            }
                          </Badge>

                        </div>

                      </CardHeader>

                      {/* =================================================
                          PRODUCT DETAILS
                      ================================================= */}

                      <CardContent className="space-y-4">

                        <div className="flex items-baseline gap-2">

                          <span className="text-xl font-bold text-[#22C55E]">
                            ₱
                            {listing.price.toLocaleString()}
                          </span>

                          <span className="text-sm text-[#45586B]">
                            per{' '}
                            {
                              listing.unit
                            }
                          </span>

                        </div>

                        <div className="flex items-center justify-between text-sm">

                          <span className="text-[#45586B]">
                            Available quantity
                          </span>

                          <span className="font-bold text-[#123C5C]">
                            {
                              listing.quantity
                            }{' '}
                            {
                              listing.unit
                            }
                          </span>

                        </div>

                        {/* ACTIONS */}

                        <div className="flex gap-2 border-t border-[#E7E1D0] pt-3">

                          <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="flex-1"
                            onClick={() =>
                              handleEdit(
                                listing,
                              )
                            }
                          >
                            <Edit className="mr-1 h-4 w-4" />

                            Edit
                          </Button>

                          <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            aria-label={`Delete ${listing.name}`}
                            onClick={() =>
                              handleDelete(
                                listing.id,
                              )
                            }
                          >
                            <Trash2 className="h-4 w-4 text-red-600" />
                          </Button>

                        </div>

                      </CardContent>

                    </Card>
                  );
                },
              )}

            </div>
          )}

        </CardContent>

      </Card>

      {/* =====================================================
          EDIT LISTING DIALOG
      ===================================================== */}

      <Dialog
        open={isEditOpen}
        onOpenChange={(open) => {

          setIsEditOpen(open);

          if (!open) {
            setEditingListing(
              null,
            );
          }

        }}
      >

        <DialogContent className="w-[98vw] max-w-5xl max-h-[92vh] overflow-y-auto">

          <DialogHeader>

            <DialogTitle>
              I-edit ang Product Listing
            </DialogTitle>

            <DialogDescription>
              I-update ang detalye ng listing at inventory
            </DialogDescription>

          </DialogHeader>

          {editingListing && (
            <ListingForm
              key={
                editingListing.id
              }
              mode="edit"
              existingListing={
                editingListing
              }
              onCancel={() => {
                setIsEditOpen(
                  false,
                );

                setEditingListing(
                  null,
                );
              }}
              onSave={async (
                item,
              ) => {

                addListingToState(
                  item,
                );

                await fetchListings();

                setEditingListing(
                  null,
                );

                setIsEditOpen(
                  false,
                );
              }}
            />
          )}

        </DialogContent>

      </Dialog>

      {/* =====================================================
          TIPS
      ===================================================== */}

      <Card className="border-[#0F9488]/30 bg-[#0F9488]/5">

        <CardContent className="p-6">

          <h3 className="mb-3 font-bold text-[#123C5C]">
            Tips para sa Mas Maayos na Listings
          </h3>

          <div className="grid gap-4 text-sm text-[#0B4842] md:grid-cols-2">

            <div className="flex items-start gap-2">

              <div className="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-[#0F9488] text-xs font-bold text-white">
                1
              </div>

              <span>
                Gumamit ng malinaw at descriptive na product names na nagpapakita ng quality
              </span>

            </div>

            <div className="flex items-start gap-2">

              <div className="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-[#0F9488] text-xs font-bold text-white">
                2
              </div>

              <span>
                Panatilihing competitive ang pricing batay sa AI insights
              </span>

            </div>

            <div className="flex items-start gap-2">

              <div className="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-[#0F9488] text-xs font-bold text-white">
                3
              </div>

              <span>
                Regular na i-update ang inventory para mapanatili ang tiwala ng buyers
              </span>

            </div>

            <div className="flex items-start gap-2">

              <div className="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-[#0F9488] text-xs font-bold text-white">
                4
              </div>

              <span>
                Banggitin ang certifications at quality standards
              </span>

            </div>

          </div>

        </CardContent>

      </Card>

    </div>
  );
}

/* ============================================================
   LISTING FORM
============================================================ */

function ListingForm({
  mode,
  existingListing,
  onCancel,
  onSave,
}: {
  mode: 'create' | 'edit';
  existingListing?: Listing | null;
  onCancel: () => void;
  onSave: (
    item: Listing,
  ) => void;
}) {

  const [
    productName,
    setProductName,
  ] = useState(
    existingListing?.name ||
      '',
  );

  const [
    category,
    setCategory,
  ] = useState(
    (
      existingListing?.category ||
      'grains'
    ).toLowerCase(),
  );

  const [
    description,
    setDescription,
  ] = useState(
    existingListing?.raw
      ?.description || '',
  );

  const [
    price,
    setPrice,
  ] = useState<
    number | ''
  >(
    existingListing?.price ??
      '',
  );

  const [
    unit,
    setUnit,
  ] = useState(
    existingListing?.unit ||
      'kg',
  );

  const [
    quantity,
    setQuantity,
  ] = useState<
    number | ''
  >(
    existingListing?.quantity ??
      '',
  );

  const [
    origin,
    setOrigin,
  ] = useState(
    existingListing?.origin ??
      existingListing?.raw?.origin ??
      existingListing?.raw?.product_origin ??
      existingListing?.raw?.source ??
      existingListing?.raw?.place_of_origin ??
      '',
  );

  const [
    imageFile,
    setImageFile,
  ] = useState<File | null>(
    null,
  );

  const [
    imagePreview,
    setImagePreview,
  ] = useState<string | null>(
    existingListing
      ? getListingImageCandidates(
          existingListing,
        )[0] || null
      : null,
  );

  /* ==========================================================
     AI LISTING ASSISTANT STATE
     The assistant creates listing suggestions from the
     product information entered by the producer. It does not
     require a separate AI service, so the feature remains
     usable even when no AI endpoint is configured yet.
  ========================================================== */

  const [
    aiSuggestions,
    setAiSuggestions,
  ] = useState<string[]>([]);

  const [
    aiDescription,
    setAiDescription,
  ] = useState('');

  const [
    aiPriceGuide,
    setAiPriceGuide,
  ] = useState('');

  const [
    aiMonthlyPrices,
    setAiMonthlyPrices,
  ] = useState<
    {
      month: string;
      low: number;
      high: number;
    }[]
  >([]);

  const [
    aiAnalyzed,
    setAiAnalyzed,
  ] = useState(false);

  const generateAIListingSuggestions =
    () => {
      const cleanProductName =
        productName.trim();

      const cleanOrigin =
        origin.trim();

      const cleanCategory =
        category.trim() || 'general';

      const cleanUnit =
        unit.trim() || 'kg';

      const hasImage =
        Boolean(imageFile || imagePreview);

      /*
       * July-September AI price estimate.
       *
       * The current listing price is used as the baseline because
       * this component does not currently receive a verified
       * historical market-price dataset from the backend.
       *
       * These are estimates for UI guidance, not official market
       * prices. The calculation is intentionally deterministic so
       * the same product/category produces the same guidance.
       */
      const basePrice =
        price !== '' && Number(price) > 0
          ? Number(price)
          : 0;

      const categoryFactors: Record<
        string,
        { low: number; high: number }[]
      > = {
        grains: [
          { low: 0.92, high: 1.02 },
          { low: 0.95, high: 1.06 },
          { low: 0.98, high: 1.10 },
        ],
        fish: [
          { low: 0.94, high: 1.05 },
          { low: 0.97, high: 1.09 },
          { low: 1.00, high: 1.12 },
        ],
        vegetables: [
          { low: 0.90, high: 1.04 },
          { low: 0.94, high: 1.08 },
          { low: 0.92, high: 1.06 },
        ],
        fruits: [
          { low: 0.93, high: 1.05 },
          { low: 0.96, high: 1.09 },
          { low: 1.00, high: 1.12 },
        ],
        livestock: [
          { low: 0.96, high: 1.04 },
          { low: 0.98, high: 1.07 },
          { low: 1.00, high: 1.09 },
        ],
      };

      const selectedFactors =
        categoryFactors[cleanCategory] ||
        [
          { low: 0.93, high: 1.05 },
          { low: 0.96, high: 1.08 },
          { low: 0.99, high: 1.10 },
        ];

      const months = [
        'July',
        'August',
        'September',
      ];

      const monthlyPrices =
        basePrice > 0
          ? months.map((month, index) => ({
              month,
              low: Math.round(
                basePrice *
                  selectedFactors[index].low,
              ),
              high: Math.round(
                basePrice *
                  selectedFactors[index].high,
              ),
            }))
          : [];

      setAiMonthlyPrices(
        monthlyPrices,
      );

      const displayName =
        cleanProductName ||
        cleanCategory.charAt(0).toUpperCase() +
          cleanCategory.slice(1);

      const categoryLabel =
        cleanCategory.charAt(0).toUpperCase() +
        cleanCategory.slice(1);

      const generatedDescription =
        `Fresh ${displayName} from ${
          cleanOrigin || 'the local agricultural community'
        }. ` +
        `Available for buyers through HarborAI. ` +
        `Product category: ${categoryLabel}. ` +
        `Sold per ${cleanUnit}. ` +
        `${
          quantity !== ''
            ? `Current available quantity: ${quantity} ${cleanUnit}.`
            : 'Please update the available quantity before publishing.'
        }`;

      const suggestions: string[] = [];

      if (cleanProductName) {
        suggestions.push(
          `Use "${cleanProductName}" as the main product title so buyers can identify the item quickly.`,
        );
      } else {
        suggestions.push(
          `Add a specific product name instead of only using the ${categoryLabel} category.`,
        );
      }

      if (cleanOrigin) {
        suggestions.push(
          `Highlight ${cleanOrigin} as the product source to give buyers clearer origin information.`,
        );
      } else {
        suggestions.push(
          'Add the product origin or source so buyers know where the product came from.',
        );
      }

      if (hasImage) {
        suggestions.push(
          'Product image detected. Use a clear, well-lit photo that shows the actual product.',
        );
      } else {
        suggestions.push(
          'Upload a clear product image to make the listing easier for buyers to evaluate.',
        );
      }

      if (
        price !== '' &&
        Number(price) > 0
      ) {
        suggestions.push(
          `Current price is ₱${Number(price).toLocaleString()} per ${cleanUnit}. Review the price regularly against local market conditions.`,
        );
      } else {
        suggestions.push(
          `Enter a selling price per ${cleanUnit} so buyers can compare your listing.`,
        );
      }

      if (
        quantity !== '' &&
        Number(quantity) > 0
      ) {
        suggestions.push(
          `Inventory is set to ${Number(quantity).toLocaleString()} ${cleanUnit}. Keep this updated as orders are completed.`,
        );
      }

      setAiDescription(
        generatedDescription,
      );

      setAiPriceGuide(
        monthlyPrices.length > 0
          ? `Estimated July-September range: ₱${monthlyPrices[0].low.toLocaleString()}-₱${monthlyPrices[2].high.toLocaleString()} per ${cleanUnit}. Use this as planning guidance and compare it with verified local market prices before publishing.`
          : `Enter a current price per ${cleanUnit} so HarborAI can calculate the July-September estimated range.`,
      );

      setAiSuggestions(
        suggestions.slice(0, 5),
      );

      setAiAnalyzed(true);
    };

  /* ==========================================================
     UPDATE FORM WHEN EDITING
  ========================================================== */

  useEffect(() => {

    if (!existingListing) {
      return;
    }

    setProductName(
      existingListing.name ||
        '',
    );

    setCategory(
      (
        existingListing.category ||
        'grains'
      ).toLowerCase(),
    );

    setDescription(
      existingListing.raw
        ?.description || '',
    );

    setPrice(
      existingListing.price ??
        '',
    );

    setUnit(
      existingListing.unit ||
        'kg',
    );

    setQuantity(
      existingListing.quantity ??
        '',
    );

    setOrigin(
      existingListing.origin ??
        existingListing.raw?.origin ??
        existingListing.raw?.product_origin ??
        existingListing.raw?.source ??
        existingListing.raw?.place_of_origin ??
        '',
    );

    setImageFile(null);

    setImagePreview(
      getListingImageCandidates(
        existingListing,
      )[0] || null,
    );

    setAiSuggestions([]);
    setAiDescription('');
    setAiPriceGuide('');
    setAiMonthlyPrices([]);
    setAiAnalyzed(false);

  }, [
    existingListing,
    mode,
  ]);

  /* ==========================================================
     LOCAL IMAGE PREVIEW
  ========================================================== */

  useEffect(() => {

    if (!imageFile) {
      return;
    }

    const objectUrl =
      URL.createObjectURL(
        imageFile,
      );

    setImagePreview(
      objectUrl,
    );

    return () => {
      URL.revokeObjectURL(
        objectUrl,
      );
    };

  }, [imageFile]);

  /* ==========================================================
     SUBMIT
  ========================================================== */

  const handleSubmit =
    async () => {

      if (
        !productName.trim() ||
        price === '' ||
        quantity === ''
      ) {
        alert(
          'Kumpletuhin ang product name, price, at quantity fields.',
        );

        return;
      }

      const numericPrice =
        Number(price);

      const numericQuantity =
        Number(quantity);

      if (
        Number.isNaN(
          numericPrice,
        ) ||
        Number.isNaN(
          numericQuantity,
        )
      ) {
        alert(
          'Dapat valid numbers ang price at quantity.',
        );

        return;
      }

      /* ======================================================
         BASIC PAYLOAD
      ====================================================== */

      const payload: Record<
        string,
        any
      > = {
        product_name:
          productName.trim(),

        category,

        price_per_unit:
          numericPrice,

        unit,

        quantity:
          numericQuantity,

        // Optional product origin/source.
        // Example: Brgy. Centro, Aparri, Cagayan
        origin: origin.trim(),

        description,

        status:
          existingListing?.status ||
          'Active',
      };

      /* ======================================================
         IMAGE UPLOAD
      ====================================================== */

      if (imageFile) {

        const form =
          new FormData();

        Object.entries(
          payload,
        ).forEach(
          ([key, value]) => {

            if (
              value !==
                undefined &&
              value !== null
            ) {
              form.append(
                key,
                String(value),
              );
            }

          },
        );

        /**
         * Laravel expects the image
         * under the "image" field.
         */
        form.append(
          'image',
          imageFile,
        );

        try {

          const { request } =
            await import(
              '../../../../lib/api'
            );

          const endpoint =
            mode === 'edit' &&
            existingListing
              ? `/api/producer/listings/${existingListing.id}`
              : '/api/producer/listings';

          /**
           * Laravel method spoofing.
           *
           * POST + _method=PUT is used
           * for multipart form uploads.
           */
          if (
            mode === 'edit'
          ) {
            form.append(
              '_method',
              'PUT',
            );
          }

          const data =
            await request(
              endpoint,
              {
                method: 'POST',
                body: form,
              },
            );

          console.log(
            'HarborAI listing save response:',
            data,
          );

          const responseData =
            data?.listing ||
            data?.data ||
            data;

          let nextItem =
            normalizeListing(
              responseData,
            );

          /**
           * If Laravel didn't return an image,
           * preserve the existing image.
           */
          if (
            !nextItem.image_url &&
            existingListing
          ) {
            nextItem =
              normalizeListing(
                {
                  ...existingListing.raw,

                  ...responseData,

                  image_url:
                    existingListing.image_url,

                  image_path:
                    existingListing.image_path,

                  image:
                    existingListing.image,

                  origin:
                    existingListing.origin ??
                    existingListing.raw?.origin ??
                    existingListing.raw?.product_origin ??
                    existingListing.raw?.source ??
                    existingListing.raw?.place_of_origin ??
                    origin,
                },
              );
          }

          onSave(
            nextItem,
          );

          alert(
            mode === 'edit'
              ? 'Listing updated'
              : 'Listing created',
          );

        } catch (
          err: any
        ) {

          console.error(
            'HarborAI listing save error:',
            err,
          );

          alert(
            err?.message ||
              (
                mode ===
                'edit'
                  ? 'Update listing failed'
                  : 'Create listing failed'
              ),
          );
        }

        return;
      }

      /* ======================================================
         SAVE WITHOUT NEW IMAGE
      ====================================================== */

      try {

        const { request } =
          await import(
            '../../../../lib/api'
          );

        const endpoint =
          mode === 'edit' &&
          existingListing
            ? `/api/producer/listings/${existingListing.id}`
            : '/api/producer/listings';

        const method =
          mode === 'edit'
            ? 'PUT'
            : 'POST';

        const data =
          await request(
            endpoint,
            {
              method,
              body: payload,
            },
          );

        console.log(
          'HarborAI listing update response:',
          data,
        );

        const responseData =
          data?.listing ||
          data?.data ||
          data;

        let nextItem =
          normalizeListing(
            responseData,
          );

        /**
         * IMPORTANT:
         *
         * If the backend does not send
         * the image during an update,
         * preserve the current image.
         */
        if (
          !nextItem.image_url &&
          existingListing
        ) {

          nextItem =
            normalizeListing(
              {
                ...existingListing.raw,

                ...responseData,

                image_url:
                  existingListing.image_url,

                image_path:
                  existingListing.image_path,

                image:
                  existingListing.image,

                origin:
                  existingListing.origin ??
                  existingListing.raw?.origin ??
                  existingListing.raw?.product_origin ??
                  existingListing.raw?.source ??
                  existingListing.raw?.place_of_origin ??
                  origin,
              },
            );
        }

        onSave(
          nextItem,
        );

        alert(
          mode === 'edit'
            ? 'Listing updated'
            : 'Listing created',
        );

      } catch (
        err: any
      ) {

        console.error(
          'HarborAI listing save error:',
          err,
        );

        alert(
          err?.message ||
            (
              mode === 'edit'
                ? 'Update listing failed'
                : 'Create listing failed'
            ),
        );
      }
    };

  /* ==========================================================
     FORM UI
  ========================================================== */

  return (
    <div className="space-y-4 py-4">

      {/* PRODUCT NAME + CATEGORY */}

      <div className="grid gap-4 md:grid-cols-2">

        <div>
          <Label htmlFor="productName">
            Product Name
          </Label>

          <Input
            id="productName"
            value={
              productName
            }
            onChange={(e) =>
              setProductName(
                e.target.value,
              )
            }
            placeholder="e.g., Premium Rice"
          />
        </div>

        <div>
          <Label htmlFor="category">
            Category
          </Label>

          <select
            id="category"
            value={
              category
            }
            onChange={(e) =>
              setCategory(
                e.target.value,
              )
            }
            className="block w-full rounded-lg border border-[#E7E1D0] p-2 focus:outline-none focus:ring-2 focus:ring-[#22C55E]"
          >
            <option value="grains">
              Grains
            </option>

            <option value="fish">
              Fish
            </option>

            <option value="vegetables">
              Vegetables
            </option>

            <option value="fruits">
              Fruits
            </option>

            <option value="livestock">
              Livestock
            </option>
          </select>
        </div>

      </div>

      {/* PRODUCT ORIGIN / SOURCE */}

      <div>

        <Label htmlFor="origin">
          Origin / Saan Nagmula ang Produkto
        </Label>

        <Input
          id="origin"
          value={origin}
          onChange={(e) =>
            setOrigin(e.target.value)
          }
          placeholder="Hal. Brgy. Centro, Aparri, Cagayan"
          className="border-[#E7E1D0] bg-white text-[#123C5C] focus-visible:ring-[#22C55E]"
        />

        <p className="mt-1 text-xs text-[#7C7468]">
          Ilagay kung saan nagmula, ginawa, o inani ang produkto.
        </p>

      </div>

      {/* DESCRIPTION */}

      <div>

        <Label htmlFor="description">
          Description
        </Label>

        <Textarea
          id="description"
          value={
            description
          }
          onChange={(e) =>
            setDescription(
              e.target.value,
            )
          }
          rows={3}
          placeholder="Ilarawan ang iyong produkto"
        />

      </div>

      {/* PRICE / UNIT / QUANTITY */}

      <div className="grid gap-4 md:grid-cols-3">

        <div>

          <Label htmlFor="price">
            Price
          </Label>

          <Input
            id="price"
            type="number"
            value={price}
            onChange={(e) =>
              setPrice(
                e.target.value ===
                  ''
                  ? ''
                  : Number(
                      e.target
                        .value,
                    ),
              )
            }
            placeholder="125"
          />

        </div>

        <div>

          <Label htmlFor="unit">
            Unit
          </Label>

          <select
            id="unit"
            value={unit}
            onChange={(e) =>
              setUnit(
                e.target.value,
              )
            }
            className="block w-full rounded-lg border border-[#E7E1D0] p-2 focus:outline-none focus:ring-2 focus:ring-[#22C55E]"
          >

            <option value="kg">
              Kilogram (kg)
            </option>

            <option value="g">
              Gram (g)
            </option>

            <option value="sack">
              Sack
            </option>

            <option value="piece">
              Piece
            </option>

          </select>

        </div>

        <div>

          <Label htmlFor="quantity">
            Available na Quantity
          </Label>

          <Input
            id="quantity"
            type="number"
            value={quantity}
            onChange={(e) =>
              setQuantity(
                e.target.value ===
                  ''
                  ? ''
                  : Number(
                      e.target
                        .value,
                    ),
              )
            }
            placeholder="500"
          />

        </div>

      </div>

      {/* =====================================================
          IMAGE UPLOAD
      ===================================================== */}

      <div>

        <Label htmlFor="imageInput">
          Product Image
        </Label>

        <input
          id="imageInput"
          type="file"
          accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
          className="mt-2"
          onChange={(e) =>
            setImageFile(
              e.target.files?.[0] ||
                null,
            )
          }
        />

        {/* IMAGE PREVIEW */}

        {imagePreview && (
          <div className="mt-3 max-w-xs">

            <ListingImage
              candidates={[
                imagePreview,
              ]}
              alt="Selected product preview"
            />

          </div>
        )}

        {!imagePreview && (
          <p className="mt-2 text-sm text-[#45586B]">
            Walang napiling image. HarborAI placeholder ang ipapakita.
          </p>
        )}

      </div>

      {/* =====================================================
          AI PRODUCT LISTING ASSISTANT
          Compact, fixed-size assistant with July-September
          estimated price guidance.
      ===================================================== */}

      <div className="w-full overflow-hidden rounded-2xl border border-[#B7E4C7] bg-gradient-to-br from-[#F0FDF4] via-white to-[#ECFDF5] shadow-sm">
        <div className="p-4 sm:p-5">

          <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex min-w-0 items-start gap-3">
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#15803D] text-white shadow-sm">
                <Sparkles
                  className="h-5 w-5"
                  aria-hidden="true"
                />
              </div>

              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  <h3 className="font-display text-base font-bold text-[#123C5C] sm:text-lg">
                    HarborAI Product Assistant
                  </h3>

                  <span className="rounded-full bg-[#DCFCE7] px-2 py-0.5 text-[9px] font-bold uppercase tracking-[0.12em] text-[#166534]">
                    AI Assistant
                  </span>
                </div>

                <p className="mt-1 text-xs leading-5 text-[#45586B] sm:text-sm">
                  I-automate ang listing at gumawa ng estimated
                  price range mula July hanggang September.
                </p>
              </div>
            </div>

            <Button
              type="button"
              onClick={
                generateAIListingSuggestions
              }
              className="h-9 shrink-0 rounded-full bg-[#123C5C] px-4 text-xs font-bold text-white hover:bg-[#0B2D45] sm:text-sm"
            >
              <Wand2
                className="mr-2 h-4 w-4"
                aria-hidden="true"
              />
              {aiAnalyzed
                ? 'I-refresh ang AI'
                : 'Generate AI'}
            </Button>
          </div>

          {!aiAnalyzed ? (
            <div className="mt-4 flex min-h-[74px] items-center rounded-xl border border-dashed border-[#A7D7B5] bg-white/80 px-4 py-3">
              <div className="min-w-0">
                <p className="text-sm font-semibold text-[#123C5C]">
                  Ready ang AI assistant.
                </p>
                <p className="mt-0.5 text-xs leading-5 text-[#45586B]">
                  Ilagay ang product name, category, price,
                  unit at quantity, pagkatapos pindutin ang
                  Generate AI.
                </p>
              </div>
            </div>
          ) : (
            <div className="mt-4 grid gap-3 lg:grid-cols-[1fr_1.15fr]">

              {/* AI DESCRIPTION / RECOMMENDATIONS */}
              <div className="min-w-0 rounded-xl border border-[#D8EAD9] bg-white p-4">
                <div className="flex items-center justify-between gap-2">
                  <div>
                    <p className="text-[10px] font-bold uppercase tracking-[0.12em] text-[#15803D]">
                      AI Listing Suggestions
                    </p>
                    <p className="mt-0.5 text-[11px] text-[#7C7468]">
                      Review bago i-save.
                    </p>
                  </div>

                  <Sparkles
                    className="h-4 w-4 shrink-0 text-[#22C55E]"
                    aria-hidden="true"
                  />
                </div>

                <div className="mt-3 rounded-lg bg-[#F5F1E5] p-3">
                  <p className="text-xs leading-5 text-[#45586B]">
                    {aiDescription}
                  </p>
                </div>

                <Button
                  type="button"
                  variant="outline"
                  onClick={() => {
                    if (aiDescription.trim()) {
                      setDescription(
                        aiDescription,
                      );
                    }
                  }}
                  className="mt-3 h-8 rounded-full border-[#B7D8C0] bg-white px-3 text-xs font-semibold text-[#15803D] hover:bg-[#F0FDF4]"
                >
                  Gamitin ang AI Description
                </Button>

                {aiSuggestions.length > 0 && (
                  <div className="mt-4 border-t border-[#E7E1D0] pt-3">
                    <p className="text-[10px] font-bold uppercase tracking-[0.1em] text-[#15803D]">
                      Quick recommendations
                    </p>

                    <div className="mt-2 grid gap-2 sm:grid-cols-2">
                      {aiSuggestions
                        .slice(0, 4)
                        .map(
                          (
                            suggestion,
                            index,
                          ) => (
                            <div
                              key={`${suggestion}-${index}`}
                              className="flex min-w-0 items-start gap-2 rounded-lg bg-[#F8FAF8] p-2"
                            >
                              <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#DCFCE7] text-[10px] font-bold text-[#15803D]">
                                {index + 1}
                              </span>

                              <p className="min-w-0 text-[11px] leading-4 text-[#45586B]">
                                {suggestion}
                              </p>
                            </div>
                          ),
                        )}
                    </div>
                  </div>
                )}
              </div>

              {/* JULY-SEPTEMBER PRICE RANGE */}
              <div className="min-w-0 rounded-xl border border-[#D8EAD9] bg-white p-4">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="text-[10px] font-bold uppercase tracking-[0.12em] text-[#15803D]">
                      AI Price Guide
                    </p>
                    <h4 className="mt-1 text-base font-bold text-[#123C5C]">
                      July - September
                    </h4>
                  </div>

                  <div className="rounded-lg bg-[#DCFCE7] px-2.5 py-1 text-[10px] font-bold text-[#166534]">
                    {unit}
                  </div>
                </div>

                {aiMonthlyPrices.length > 0 ? (
                  <>
                    <div className="mt-3 grid grid-cols-3 gap-2">
                      {aiMonthlyPrices.map(
                        (item) => (
                          <div
                            key={item.month}
                            className="min-w-0 rounded-lg border border-[#E7E1D0] bg-[#F8F7F3] px-2 py-3 text-center"
                          >
                            <p className="text-[10px] font-bold uppercase tracking-[0.08em] text-[#7C7468]">
                              {item.month}
                            </p>

                            <p className="mt-1 break-words text-sm font-black leading-tight text-[#15803D]">
                              ₱{item.low.toLocaleString()}
                            </p>

                            <div className="my-0.5 text-[10px] font-medium text-[#9A9287]">
                              to
                            </div>

                            <p className="break-words text-sm font-black leading-tight text-[#15803D]">
                              ₱{item.high.toLocaleString()}
                            </p>

                            <p className="mt-1 text-[9px] text-[#7C7468]">
                              per {unit}
                            </p>
                          </div>
                        ),
                      )}
                    </div>

                    <div className="mt-3 rounded-lg border border-[#FDE68A] bg-[#FFFBEB] p-3">
                      <p className="text-[11px] font-semibold leading-5 text-[#78350F]">
                        {aiPriceGuide}
                      </p>
                    </div>
                  </>
                ) : (
                  <div className="mt-3 rounded-lg border border-dashed border-[#E7E1D0] bg-[#F8F7F3] p-4 text-center">
                    <p className="text-xs font-semibold text-[#123C5C]">
                      Walang price estimate.
                    </p>
                    <p className="mt-1 text-[11px] leading-4 text-[#7C7468]">
                      Maglagay ng current price para
                      makagawa ng July-September range.
                    </p>
                  </div>
                )}
              </div>

            </div>
          )}

          <p className="mt-3 text-[10px] leading-4 text-[#7C7468]">
            Note: Ang July-September values ay AI-generated
            estimates batay sa current listing price at product
            category. Hindi ito verified historical market data.
            I-compare pa rin sa aktuwal na local market prices
            bago magtakda ng final selling price.
          </p>

        </div>
      </div>

      {/* BUTTONS */}

      <div className="flex justify-end gap-2 pt-4">

        <Button
          variant="outline"
          className="rounded-full"
          onClick={
            onCancel
          }
        >
          Kanselahin
        </Button>

        <Button
          className="rounded-full bg-[#22C55E] font-bold hover:bg-[#15803D]"
          onClick={
            handleSubmit
          }
        >
          {mode ===
          'edit'
            ? 'I-save ang Changes'
            : 'Gumawa ng Listing'}
        </Button>

      </div>

    </div>
  );
}
  