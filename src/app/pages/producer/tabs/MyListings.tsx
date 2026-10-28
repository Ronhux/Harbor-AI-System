import { DashboardText, useDashboardCopy } from '../../../i18n/DashboardCopy';
import { useLanguage } from '../../../i18n/useLanguage';
import { Fragment, useState, useEffect, useRef, type ReactNode } from 'react';
import {
  Plus,
  Edit,
  Trash2,
  Package,
  Search,
  ArrowLeft,
  ShoppingBag,
  Sparkles,
  Wand2,
  Loader2,
  Upload,
  X,
  AlertTriangle,
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
import ListingImageCarousel from '../../../components/dashboard/ListingImageCarousel';
import { resolveImageUrl, resolveListingImages } from '../../../../lib/image';
import { request } from '../../../../lib/api';

import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '../../../components/ui/select';

import { Label } from '../../../components/ui/label';
import { Textarea } from '../../../components/ui/textarea';
import FeedbackModal from '../../../components/ui/feedback-modal';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '../../../components/ui/alert-dialog';

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
  /** Backend-reported remaining stock, preferred over `quantity` when present. */
  quantityAvailable?: number | null;
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

type ProductAssistantPsaReference = {
  status?: string;
  source?: string;
  geography?: string;
  price_type?: string;
  currency?: string;
  unit?: string;
  observation_count?: number;
  minimum_price?: number | null;
  maximum_price?: number | null;
  average_price?: number | null;
  median_price?: number | null;
  first_observation_date?: string | null;
  latest_available_observation?: {
    date?: string | null;
    period?: string | null;
    price?: number | null;
  } | null;
};

type ProductAssistantAnalysis = {
  analysis_date?: string;
  input_context?: {product_name?: string; origin?: string | null};
  commodity_resolution?: {
    status?: string;
    canonical_name?: string | null;
    candidate_count?: number;
    source_coverage?: {
      status?: string;
      covered_series?: string[];
      unmapped_source_siblings?: string[];
      interpretation?: string;
    };
  };
  psa_reference?: ProductAssistantPsaReference;
  psa_reference_candidates?: Array<{
    commodity_label?: string;
    match_method?: string;
    match_confidence?: number;
    psa_reference?: ProductAssistantPsaReference;
    producer_price_comparison?: ProductAssistantAnalysis['producer_price_comparison'];
  }>;
  producer_price_comparison?: {
    status?: string;
    input_price?: number | null;
    input_currency?: string;
    input_unit?: string | null;
    normalized_price_per_kg?: number | null;
    reference_average_price?: number | null;
    difference_from_average?: number | null;
    difference_percent_from_average?: number | null;
    position_relative_to_range?: string | null;
  };
  harborai_listing_reference?: {
    scope?: string;
    active_listing_count?: number;
    active_listing_price_count?: number;
    minimum_active_listing_price?: number | null;
    maximum_active_listing_price?: number | null;
    average_active_listing_price?: number | null;
    price_currency?: string;
    price_unit?: string | null;
    active_listing_quantity_total?: number | null;
    quantity_unit?: string | null;
    quantity_status?: string;
  };
  supply_context?: {
    status?: string;
    quantity_total?: number | null;
    quantity_unit?: string | null;
    quantity_status?: string;
  };
  demand_context?: {
    status?: string;
    scope?: string;
    active_demand_request_count?: number;
    requested_quantity_total?: number | null;
    quantity_unit?: string | null;
    quantity_status?: string;
  };
  gross_listing_value?: {
    status?: string;
    amount?: number | null;
    currency?: string;
    is_profit?: boolean;
  };
  seasonality?: {
    status?: string;
    analysis_month?: number;
    analysis_month_name?: string;
    interpretation?: string;
    geographic_limitation?: string;
    references?: Array<{
      month?: number | null;
      period_label?: string | null;
      periods?: string[];
      rows?: Array<{
        month?: number | null;
        period_label?: string | null;
        season_status?: string;
        start_month?: number | null;
        end_month?: number | null;
        peak_period?: string | null;
        reference_date?: string | null;
      }>;
      season_status?: string;
      commodity_label?: string | null;
      commodity_category?: string | null;
      status?: string;
      geographic_scope?: string;
      source_name?: string | null;
      organization?: string | null;
      source_url?: string | null;
      source_reference?: string | null;
      source_label?: string | null;
      reference_date?: string | null;
      interpretation?: string;
    }>;
    candidates?: Array<{
      commodity_label: string;
      references?: Array<{
        status?: string;
        periods?: string[];
        geographic_scope?: string;
        source_name?: string;
        source_reference?: string;
        reference_date?: string | null;
        interpretation?: string;
      }>;
    }>;
  };
  quality_context?: {
    quality_grade?: string | null;
    price_adjustment_applied?: boolean;
    status?: string;
  };
  data_sufficiency?: {
    status?: string;
    basis?: string;
  };
  ai_interpretation?: string | null;
  ai_interpretation_status?: 'AVAILABLE' | 'UNAVAILABLE';
  ai_interpretation_unavailable?: boolean;
  ai_interpretation_reason?: string | null;
  /**
   * Deterministic, evidence-grounded reading. Always present when the analysis succeeded,
   * independently of whether the optional Azure provider answered.
   */
  interpretation_available?: boolean;  /** Compressed farmer-facing reading. Replaces the repeated paragraphs. */
  summary?: {
    summary?: string;
    word_count?: number;
    question?: string;
    key_facts?: Array<{ label: string; value: string }>;
    variety_candidates?: Array<{
      commodity_label: string;
      comparable_observation_count: number;
      geography?: string;
    }>;
  };
  interpretation?: {
    market_analysis_status?: string;
    seasonality_evidence_status?: string;
    price_evidence_status?: string;
    generative_ai_status?: string;
    interpretation_source?: string;
    origin?: { supplied?: string | null; is_aparri?: boolean };
    seasonality_narrative?: string;
    price_narrative?: string;
    geographic_narrative?: string;
    pricing_question?: string;
    interpretation?: string;
  };
};

/**
 * Progress messages shown while the real analysis request is in flight. They
 * describe the UI's own request state and never claim that a model is
 * generating anything; the deterministic cards still appear only when the
 * server has actually answered.
 */
const analysisProgressMessages = [
  'Thinking…',
  'Analyzing market data…',
  'Reviewing available price observations…',
  'Generating interpretation…',
] as const;

const formatAnalysisNumber = (value: number | null | undefined): string =>
  typeof value === 'number' && Number.isFinite(value)
    ? value.toLocaleString(undefined, { maximumFractionDigits: 4 })
    : '—';

const formatAnalysisMoney = (
  value: number | null | undefined,
  currency = 'PHP',
): string => {
  if (typeof value !== 'number' || !Number.isFinite(value)) return '—';
  const amount = value.toLocaleString(undefined, { maximumFractionDigits: 4 });
  return currency === 'PHP' ? `₱${amount}` : `${currency} ${amount}`;
};

const seasonalityStatusLabel = (status: string | undefined): string =>
  (status || 'INSUFFICIENT_DATA').replaceAll('_', ' ').toLowerCase();

/**
 * Reveals text progressively with a caret. The full text must already have been
 * received: this only paces how an existing response becomes visible, and it
 * never invents, delays, or extends a response the server did not send.
 */
function useTypewriterText(text: string, active: boolean) {
  const [revealed, setRevealed] = useState(text);
  const [done, setDone] = useState(false);

  useEffect(() => {
    if (!text) {
      setRevealed('');
      setDone(true);
      return;
    }
    if (!active) {
      // Reduced-motion / disabled path: show the received text in full.
      setRevealed(text);
      setDone(true);
      return;
    }

    setRevealed('');
    setDone(false);
    let index = 0;
    const reduced =
      typeof window !== 'undefined'
      && typeof window.matchMedia === 'function'
      && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduced) {
      setRevealed(text);
      setDone(true);
      return;
    }

    const timer = window.setInterval(() => {
      index += 2;
      setRevealed(text.slice(0, index));
      if (index >= text.length) {
        window.clearInterval(timer);
        setRevealed(text);
        setDone(true);
      }
    }, 12);

    return () => window.clearInterval(timer);
  }, [text, active]);

  return { revealed, done };
}

const renderAdvisoryInline = (text: string): ReactNode[] =>
  text.split(/(\*\*[^*]+\*\*)/g).filter(Boolean).map((part, index) => {
    if (part.startsWith('**') && part.endsWith('**')) {
      return <strong key={index} className="font-bold text-foreground">{part.slice(2, -2)}</strong>;
    }

    return <Fragment key={index}>{part}</Fragment>;
  });

const renderAiAdvisory = (markdown: string): ReactNode[] => {
  type Block =
    | { type: 'heading'; text: string }
    | { type: 'paragraph'; text: string }
    | { type: 'list'; items: string[] };

  const blocks: Block[] = [];
  let paragraphLines: string[] = [];
  let listItems: string[] = [];
  const flushParagraph = () => {
    if (paragraphLines.length > 0) {
      blocks.push({ type: 'paragraph', text: paragraphLines.join(' ') });
      paragraphLines = [];
    }
  };
  const flushList = () => {
    if (listItems.length > 0) {
      blocks.push({ type: 'list', items: listItems });
      listItems = [];
    }
  };

  for (const rawLine of markdown.split(/\r?\n/)) {
    const line = rawLine.trim();
    if (!line) {
      flushParagraph();
      flushList();
      continue;
    }

    const heading = line.match(/^#{1,3}\s+(.+)$/);
    if (heading) {
      flushParagraph();
      flushList();
      blocks.push({ type: 'heading', text: heading[1] });
      continue;
    }

    const bullet = line.match(/^[-*]\s+(.+)$/);
    if (bullet) {
      flushParagraph();
      listItems.push(bullet[1]);
      continue;
    }

    flushList();
    paragraphLines.push(line);
  }
  flushParagraph();
  flushList();

  return blocks.map((block, index) => {
    if (block.type === 'heading') {
      return (
        <h5 key={`heading-${index}`} className="pt-2 text-sm font-bold text-foreground first:pt-0">
          {renderAdvisoryInline(block.text)}
        </h5>
      );
    }
    if (block.type === 'list') {
      return (
        <ul key={`list-${index}`} className="list-disc space-y-1 pl-5 text-sm leading-6 text-muted-foreground marker:text-[#0F9488]">
          {block.items.map((item, itemIndex) => (
            <li key={itemIndex}>{renderAdvisoryInline(item)}</li>
          ))}
        </ul>
      );
    }

    return (
      <p key={`paragraph-${index}`} className="text-sm leading-6 text-muted-foreground">
        {renderAdvisoryInline(block.text)}
      </p>
    );
  });
};

const pricePositionText = (position?: string | null): string => {
  switch (position) {
    case 'BELOW_REFERENCE_RANGE':
      return 'Your proposed price is below the available PSA reference range.';
    case 'WITHIN_REFERENCE_RANGE':
      return 'Your proposed price is within the available PSA reference range.';
    case 'ABOVE_REFERENCE_RANGE':
      return 'Your proposed price is above the available PSA reference range.';
    case 'NO_REFERENCE_AVAILABLE':
      return 'No comparable PSA reference is available for this product.';
    case 'MULTIPLE_REFERENCES':
      return 'Several PSA series are available; review each variety separately below.';
    default:
      return 'A price position is not available for the supplied context.';
  }
};

const sufficiencyText = (status?: string, basis?: string): string => {
  switch (status) {
    case 'HIGH':
      return 'Verified reference data and a compatible price comparison are available.';
    case 'MODERATE':
      if (basis === 'MULTIPLE_PSA_SERIES_REQUIRE_VARIETY_SELECTION') {
        return 'Several PSA series have verified data; review each variety separately without combining their prices.';
      }
      return 'Reference data is available, but the price comparison is incomplete.';
    case 'LIMITED':
      return 'HarborAI platform context is available without a matching PSA reference.';
    default:
      return 'Not enough verified market information is available for this product yet.';
  }
};

const producerPriceStatusText = (status?: string): string => {
  switch (status) {
    case 'PRICE_NOT_PROVIDED':
      return 'Add a proposed price to compare it with a compatible PSA reference.';
    case 'UNIT_INCOMPATIBLE':
      return 'The entered unit cannot be compared with the available PSA reference.';
    case 'CURRENCY_INCOMPATIBLE':
      return 'The entered currency cannot be compared with the available PSA reference.';
    case 'NO_REFERENCE_AVAILABLE':
      return 'No comparable PSA reference is available for this product.';
    case 'MULTIPLE_REFERENCES':
      return 'Several PSA series are available; review each variety separately below.';
    case 'COMPARABLE':
      return '';
    default:
      return 'A price comparison is not available for the supplied context.';
  }
};

/* =========================================================
   IMAGE URL RESOLUTION
   resolveImageUrl and resolveListingImages are imported from
   the shared lib/image module, which resolves API image values
   against the origin that serves Laravel. No local duplicate is
   defined here so the shared resolver stays the single source of
   truth and the module compiles without a duplicate identifier.
   ========================================================= */

const MAX_LISTING_IMAGES = 10;

/* =========================================================
   IMAGE URL RESOLVER
   The shared resolveImageUrl / resolveListingImages imported
   from lib/image above are used directly. This local duplicate
   definition was removed so the module no longer declares two
   conflicting resolveImageUrl bindings (TS2440).
========================================================= */

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
  const uploadedImages = resolveListingImages(item?.raw ?? item);
  if (uploadedImages.length > 0) {
    return uploadedImages.map((image) => image.url);
  }

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

type EditableListingImage = {
  id?: number;
  url: string;
  isLegacy: boolean;
};

const getEditableListingImages = (
  listing: Listing | null | undefined,
): EditableListingImage[] => {
  if (!listing) return [];

  const rawImages = Array.isArray(listing.raw?.images)
    ? listing.raw.images
    : [];
  const images: EditableListingImage[] = rawImages
    .map((image: any) => {
      const url = resolveImageUrl(image?.url ?? image?.path);
      if (!url) return null;

      return {
        id: image?.id == null ? undefined : Number(image.id),
        url,
        isLegacy: image?.id == null,
      } satisfies EditableListingImage;
    })
    .filter((image: EditableListingImage | null): image is EditableListingImage => Boolean(image));
  const knownUrls = new Set(images.map((image) => image.url));

  getListingImageCandidates(listing).forEach((url) => {
    if (!knownUrls.has(url)) {
      images.push({ url, isLegacy: true });
      knownUrls.add(url);
    }
  });

  return images;
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
    item?.availability_badge || item?.status || 'Active',
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
  const copy = useDashboardCopy();
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
        className={`relative aspect-[4/3] w-full overflow-hidden bg-background ${className}`}
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
      aria-label={copy("No product image available")}
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

export default function MyListings({ focusAssistant = false }: { focusAssistant?: boolean }) {
  const copy = useDashboardCopy();
  const [listings, setListings] =
    useState<Listing[]>([]);

  const [listingSearch, setListingSearch] =
    useState('');

  const [selectedCategory, setSelectedCategory] =
    useState('all');

  // Active shows what is still purchasable; History keeps sold-out and
  // non-active listings visible without deleting anything.
  const [listingView, setListingView] = useState<'active' | 'history'>('active');

  const [isCreateOpen, setIsCreateOpen] =
    useState(false);

  const [listingFeedback, setListingFeedback] =
    useState<'created' | 'updated' | 'deleted' | null>(null);

  const [deleteTarget, setDeleteTarget] =
    useState<Listing | null>(null);

  const [deleteBusy, setDeleteBusy] =
    useState(false);

  const [deleteError, setDeleteError] =
    useState('');

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

  useEffect(() => {
    if (focusAssistant) setIsCreateOpen(true);
  }, [focusAssistant]);

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

  /*
   | Availability split.
   |
   | The remaining quantity is read from the backend's own `quantity_available`
   | / `quantity` fields, which ProducerController already treats as the single
   | source of truth: NegotiationController reserves that stock when an order is
   | created and only returns it when an order is Cancelled or Rejected, so a
   | zero here means the stock was genuinely consumed. Nothing is deleted and no
   | status is written; a sold-out listing only moves between these two views.
   */
  const isActiveListing = (listing: Listing): boolean => {
    const status = (listing.status || '').trim().toLowerCase();
    const quantity = Number(
      listing.quantityAvailable ?? listing.quantity ?? 0,
    );

    return (
      status === 'active' || status === 'available'
    ) && Number.isFinite(quantity) && quantity > 0;
  };

  const activeListings = filteredListings.filter(
    isActiveListing,
  );
  const listingHistory = filteredListings.filter(
    (listing) => !isActiveListing(listing),
  );
  const shownListings =
    listingView === 'active'
      ? activeListings
      : listingHistory;

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

        quantityAvailable:
          Number(
            listing.raw?.quantity_available ??
              listing.quantity ??
              0,
          ),

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

  const handleDelete = (listing: Listing) => {
    setDeleteError('');
    setDeleteTarget(listing);
  };

  const confirmDelete = async () => {
    if (!deleteTarget || deleteBusy) return;

    const id = deleteTarget.id;
    setDeleteError('');
    setDeleteBusy(true);

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
        setDeleteTarget(null);
        setListingFeedback('deleted');
      } catch (err: any) {
        console.error(err);
        setDeleteError(err?.message || 'Delete listing failed. Please try again.');
      } finally {
        setDeleteBusy(false);
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
    <div className="space-y-6 p-4 sm:p-6">

      <FeedbackModal
        open={listingFeedback !== null}
        tone="success"
        title={
          listingFeedback === 'created'
            ? copy("Listing Successfully Created!")
            : listingFeedback === 'updated'
              ? copy("Listing Successfully Updated!")
              : copy("Listing Deleted Successfully!")
        }
        description={
          listingFeedback === 'created'
            ? copy("Your product listing has been added successfully.")
            : listingFeedback === 'updated'
              ? copy("Your product listing has been updated successfully.")
              : copy("Your product listing has been deleted.")
        }
        onClose={() => setListingFeedback(null)}
        onCloseLabel={copy("Done")}
      />

      <AlertDialog
        open={!!deleteTarget}
        onOpenChange={(open) => {
          if (!open && !deleteBusy) {
            setDeleteTarget(null);
            setDeleteError('');
          }
        }}
      >
        <AlertDialogContent className="w-[calc(100vw-2rem)] rounded-2xl border-border sm:max-w-md">
          <button
            type="button"
            aria-label={copy("Close delete confirmation")}
            disabled={deleteBusy}
            onClick={() => {
              if (deleteBusy) return;
              setDeleteTarget(null);
              setDeleteError('');
            }}
            className="absolute right-4 top-4 flex h-9 w-9 items-center justify-center rounded-full text-muted-foreground transition hover:bg-background hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0F9488] disabled:opacity-50"
          >
            <X className="h-4 w-4" aria-hidden="true" />
          </button>
          <AlertDialogHeader>
            <AlertDialogTitle className="flex items-start gap-3 text-foreground">
              <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600">
                <AlertTriangle className="h-5 w-5" aria-hidden="true" />
              </span>
              <span className="pt-1"><DashboardText text="Are you sure you want to delete this listing?" /></span>
            </AlertDialogTitle>
            <AlertDialogDescription className="leading-6 text-muted-foreground"><DashboardText text="This action cannot be undone. Deleting this listing will permanently remove it from your listings." /></AlertDialogDescription>
          </AlertDialogHeader>

          {deleteError && (
            <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-800">
              {deleteError}
            </p>
          )}

          <AlertDialogFooter className="gap-2 sm:gap-2">
            <AlertDialogCancel disabled={deleteBusy}><DashboardText text="Cancel" /></AlertDialogCancel>
            <AlertDialogAction
              disabled={deleteBusy}
              className="bg-red-600 text-white hover:bg-red-700 focus-visible:ring-red-600"
              onClick={(event) => {
                event.preventDefault();
                void confirmDelete();
              }}
            >
              {deleteBusy ? copy("Deleting...") : copy("Yes, Delete Listing")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* =====================================================
          PAGE HEADER
      ===================================================== */}

      <div className="flex items-center justify-between">

        <div>
          <h1 className="flex items-center gap-3 font-display text-2xl tracking-tight text-foreground sm:text-3xl">
            <Package className="h-8 w-8 text-[#22C55E]" /><DashboardText text="My Digital Stall" /></h1>

          <p className="mt-1 text-muted-foreground"><DashboardText text="Pamahalaan ang iyong product listings at inventory" /></p>
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
            <Button className="hidden shrink-0 rounded-full bg-[#22C55E] font-bold hover:bg-[#15803D] lg:inline-flex">
              <Plus className="mr-2 h-4 w-4" /><DashboardText text="Magdagdag ng Listing" /></Button>
          </DialogTrigger>

          <DialogContent className="flex max-h-[92dvh] w-[calc(100vw-1rem)] flex-col gap-0 overflow-hidden p-0 sm:w-[calc(100vw-2rem)] sm:max-w-6xl">

            <DialogHeader className="shrink-0 border-b border-border bg-card px-5 py-4 sm:px-6">
              <DialogTitle><DashboardText text="Gumawa ng Bagong Product Listing" /></DialogTitle>

              <DialogDescription><DashboardText text="Magdagdag ng produkto sa iyong digital stall" /></DialogDescription>
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
                setListingFeedback('created');
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
            className="w-[96vw] max-w-[1200px] max-h-[min(90vh,900px)] overflow-hidden border-0 bg-card p-0 shadow-2xl sm:max-w-[1200px] sm:rounded-2xl sm:p-0"
          >
            {viewingListing && (
              <div className="max-h-[min(90vh,900px)] overflow-y-auto overscroll-contain [scrollbar-gutter:stable]">
                {/* =================================================
                    PRODUCT DETAIL HEADER
                ================================================= */}

                <div className="flex items-center justify-between border-b border-border bg-card px-5 py-3 sm:px-7">
                  <button
                    type="button"
                    onClick={() => setViewingListing(null)}
                    className="group inline-flex items-center gap-2 text-sm font-semibold text-muted-foreground transition-colors hover:text-foreground"
                  >
                    <ArrowLeft className="h-4 w-4 transition-transform group-hover:-translate-x-0.5" /><DashboardText text="Bumalik sa Listings" /></button>

                  <div className="pr-8 text-right text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground"><DashboardText text="Detalye ng Produkto" /></div>
                </div>

                {/* =================================================
                    TWO-COLUMN PRODUCT DETAIL
                ================================================= */}

                <div className="grid lg:grid-cols-2 xl:grid-cols-[1.05fr_0.95fr] lg:min-h-[620px]">
                  {/* =================================================
                      LEFT: PRODUCT IMAGE + DESCRIPTION
                  ================================================= */}

                  <div className="border-b border-border bg-card p-6 sm:p-7 lg:border-b-0 lg:border-r lg:p-10 xl:p-12">
                    <ListingImageCarousel
                      key={viewingListing.id}
                      listing={viewingListing.raw ?? viewingListing}
                      alt={viewingListing.name}
                    />

                    <div className="mt-8">
                      <p className="mb-2 text-xs font-bold uppercase tracking-[0.14em] text-muted-foreground"><DashboardText text="About This Item" /></p>

                      <p className="max-w-3xl text-[15px] leading-8 text-muted-foreground">
                        {viewingListing.raw?.description?.trim() ||
                          copy("Fresh {0} available from your digital stall.", [viewingListing.name])}
                      </p>

                      <div className="mt-6 border-t border-border pt-5">
                        <div className="grid gap-4 sm:grid-cols-2">
                          <div>
                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-muted-foreground"><DashboardText text="Category" /></p>
                            <p className="mt-1 font-semibold capitalize text-foreground">
                              {viewingListing.category}
                            </p>
                          </div>

                          <div>
                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-muted-foreground"><DashboardText text="Unit" /></p>
                            <p className="mt-1 font-semibold text-foreground">
                              {viewingListing.unit}
                            </p>
                          </div>

                          <div className="sm:col-span-2">
                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-muted-foreground"><DashboardText text="Origin / Saan Nagmula" /></p>
                            <p className="mt-1 font-semibold text-foreground">
                              {viewingListing.origin ||
                                viewingListing.raw?.origin ||
                                viewingListing.raw?.product_origin ||
                                viewingListing.raw?.source ||
                                viewingListing.raw?.place_of_origin ||
                                copy("Hindi pa nailalagay")}
                            </p>
                          </div>

                          <div>
                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-muted-foreground"><DashboardText text="Product Views" /></p>
                            <p className="mt-1 font-semibold text-foreground">
                              {viewingListing.views.toLocaleString()}
                            </p>
                          </div>

                          <div>
                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-muted-foreground"><DashboardText text="Orders" /></p>
                            <p className="mt-1 font-semibold text-foreground">
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

                  <div className="bg-card p-6 sm:p-8 lg:p-10 xl:p-12">
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

                        <span className="pb-1 text-lg text-muted-foreground">
                          / {viewingListing.unit}
                        </span>
                      </div>

                      {/* PRODUCT META */}
                      <div className="mt-6 grid grid-cols-2 border-y border-border">
                        <div className="border-r border-border py-4 pr-4">
                          <p className="text-xs uppercase tracking-[0.1em] text-muted-foreground"><DashboardText text="Available" /></p>
                          <p className="mt-1 text-base font-bold text-foreground">
                            {viewingListing.quantity.toLocaleString()}{" "}
                            {viewingListing.unit}
                          </p>
                        </div>

                        <div className="py-4 pl-4">
                          <p className="text-xs uppercase tracking-[0.1em] text-muted-foreground"><DashboardText text="Status" /></p>
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
                        <p className="text-xs font-bold uppercase tracking-[0.14em] text-muted-foreground"><DashboardText text="Inventory" /></p>

                        <div className="mt-2 flex items-center justify-between rounded-xl border border-border bg-card px-4 py-3">
                          <div>
                            <p className="text-sm text-muted-foreground"><DashboardText text="Available quantity" /></p>
                            <p className="mt-1 text-2xl font-black text-foreground">
                              {viewingListing.quantity.toLocaleString()}
                              <span className="ml-2 text-base font-medium text-muted-foreground">
                                {viewingListing.unit}
                              </span>
                            </p>
                          </div>

                          <Package className="h-8 w-8 text-[#15803D]" />
                        </div>
                      </div>

                      {/* PERFORMANCE */}
                      <div className="mt-5 grid grid-cols-2 gap-4">
                        <div className="rounded-xl border border-border bg-card px-4 py-3">
                          <p className="text-xs uppercase tracking-[0.1em] text-muted-foreground"><DashboardText text="Views" /></p>
                          <p className="mt-1 text-xl font-black text-foreground">
                            {viewingListing.views.toLocaleString()}
                          </p>
                        </div>

                        <div className="rounded-xl border border-border bg-card px-4 py-3">
                          <p className="text-xs uppercase tracking-[0.1em] text-muted-foreground"><DashboardText text="Orders" /></p>
                          <p className="mt-1 text-xl font-black text-foreground">
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
                          <Edit className="mr-2 h-4 w-4" /><DashboardText text="I-edit ang Listing" /></Button>

                        <Button
                          type="button"
                          variant="outline"
                          onClick={() => setViewingListing(null)}
                          className="h-12 rounded-lg border-[#D8D1C4] bg-card font-bold text-foreground hover:bg-background"
                        ><DashboardText text="Isara" /></Button>
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

      <div className="grid grid-cols-2 gap-2.5 sm:gap-4 md:grid-cols-4">

        <Card className="border border-border">
          <CardContent className="p-3 sm:p-4">
            <div className="text-xl font-bold text-foreground sm:text-2xl">
              {listings.length}
            </div>

            <div className="text-sm text-muted-foreground"><DashboardText text="Kabuuang Listings" /></div>
          </CardContent>
        </Card>

        <Card className="border border-border">
          <CardContent className="p-3 sm:p-4">

            <div className="text-xl font-bold text-[#22C55E] sm:text-2xl">
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

            <div className="text-sm text-muted-foreground"><DashboardText text="Active na Produkto" /></div>

          </CardContent>
        </Card>

        <Card className="border border-border">
          <CardContent className="p-3 sm:p-4">

            <div className="text-xl font-bold text-[#0F9488] sm:text-2xl">
              {listings.reduce(
                (sum, listing) =>
                  sum +
                  listing.views,
                0,
              )}
            </div>

            <div className="text-sm text-muted-foreground"><DashboardText text="Kabuuang Views" /></div>

          </CardContent>
        </Card>

        <Card className="border border-border">
          <CardContent className="p-3 sm:p-4">

            <div className="text-xl font-bold text-[#0E7490] sm:text-2xl">
              {listings.reduce(
                (sum, listing) =>
                  sum +
                  listing.orders,
                0,
              )}
            </div>

            <div className="text-sm text-muted-foreground"><DashboardText text="Kabuuang Orders" /></div>

          </CardContent>
        </Card>

      </div>

      {/* =====================================================
          MY DIGITAL STALL
      ===================================================== */}

      <Card className="overflow-hidden border-border bg-card shadow-sm">

        <CardHeader className="border-b border-border/70 bg-background/45">

          <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">

            <div>
              <CardTitle className="font-display text-2xl text-foreground"><DashboardText text="My Digital Stall" /></CardTitle>

              <CardDescription className="mt-2"><DashboardText text="Manage your products and listings." /></CardDescription>
            </div>

          </div>

          {/* ACTIVE / HISTORY VIEW SWITCH */}

          <div
            role="tablist"
            aria-label={copy("Listing view")}
            className="inline-flex w-full max-w-md rounded-full border border-border bg-card p-1"
          >
            {([
              ['active', 'Active Listings', activeListings.length],
              ['history', 'Listing History', listingHistory.length],
            ] as const).map(([value, label, count]) => (
              <button
                key={value}
                type="button"
                role="tab"
                aria-selected={listingView === value}
                onClick={() => setListingView(value)}
                className={`min-h-11 flex-1 rounded-full px-4 text-sm font-bold transition-colors ${
                  listingView === value
                    ? 'bg-[#123C5C] text-white'
                    : 'text-muted-foreground hover:bg-background'
                }`}
              >
                {label}
                <span className="ml-2 opacity-80">{count}</span>
              </button>
            ))}
          </div>

          <p className="text-xs text-muted-foreground">
            {listingView === 'active'
              ? copy("Products with remaining available quantity. Sold-out products move to Listing History and are never deleted.")
              : copy("Sold-out and no-longer-active listings. These records are kept for order history, reports, and review.")}
          </p>

          {/* SEARCH + CATEGORY */}

          <div className="grid gap-3 pt-2 md:grid-cols-[minmax(0,1fr)_220px]">

            <div className="relative">

              <Search
                className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
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
                placeholder={copy("Search products...")}
                aria-label={copy("Search listings")}
                className="border-border bg-card pl-9 text-foreground focus-visible:ring-[#22C55E]"
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

              <SelectTrigger className="border-border bg-card text-foreground focus:ring-[#22C55E]">
                <SelectValue placeholder={copy("All Categories")} />
              </SelectTrigger>

              <SelectContent>

                <SelectItem value="all"><DashboardText text="All Categories" /></SelectItem>

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

            <div className="flex flex-col items-center justify-center gap-3 px-4 py-10 text-center sm:py-14">

              <div
                aria-hidden="true"
                className="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#22C55E]/12 text-[#15803D] sm:h-16 sm:w-16"
              >
                <ShoppingBag className="h-7 w-7 sm:h-8 sm:w-8" />
              </div>

              <div>
                <p className="font-display text-lg text-foreground sm:text-xl"><DashboardText text="Wala ka pang listings" /></p>

                <p className="mx-auto mt-1.5 max-w-sm text-sm text-muted-foreground"><DashboardText text="Magdagdag ng produkto sa iyong digital stall para makita ito ng mga institutional buyers." /></p>
              </div>

              <Button
                type="button"
                onClick={() => {
                  setEditingListing(null);
                  setIsCreateOpen(true);
                }}
                className="mt-1 rounded-full bg-[#22C55E] px-5 font-bold text-white hover:bg-[#15803D]"
              >
                <Plus className="mr-1.5 h-4 w-4" aria-hidden="true" /><DashboardText text="Add Listing" /></Button>

            </div>

          ) : filteredListings.length ===
            0 ? (

            /* NO SEARCH RESULTS */

            <div className="flex flex-col items-center justify-center gap-3 px-4 py-10 text-center sm:py-14">

              <div
                aria-hidden="true"
                className="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#0F9488]/12 text-[#0D706B] sm:h-16 sm:w-16"
              >
                <Search className="h-7 w-7 sm:h-8 sm:w-8" />
              </div>

              <div>
                <p className="font-display text-lg text-foreground sm:text-xl"><DashboardText text="No products found" /></p>

                <p className="mx-auto mt-1.5 max-w-sm text-sm text-muted-foreground"><DashboardText text="Walang listing na tumugma sa iyong search o napiling kategorya. Subukan ang ibang keyword." /></p>
              </div>

            </div>

          ) : shownListings.length ===
            0 ? (

            /* EMPTY VIEW, OTHER VIEW STILL HAS RECORDS */

            <div className="py-8 text-center">
              <p className="text-sm text-muted-foreground">
                {listingView === 'active'
                  ? copy("No active products with remaining stock.")
                  : copy("No completed or inactive listings yet.")}
              </p>
              <button
                type="button"
                onClick={() => setListingView(listingView === 'active' ? 'history' : 'active')}
                className="mt-2 text-sm font-semibold text-[#0F9488] underline"
              >
                {listingView === 'active'
                  ? copy("View listing history ({0})", [listingHistory.length])
                  : copy("Back to active listings ({0})", [activeListings.length])}
              </button>
            </div>

          ) : (

            /* PRODUCT GRID — marketplace style.
               Two cards per row from 320px upward, widening to four on
               very large desktop screens. The Active/History split above
               keeps sold-out listings visible without deleting them. */

            <div className="grid grid-cols-2 gap-2.5 sm:gap-4 lg:grid-cols-3 2xl:grid-cols-4">

              {shownListings.map(
                (listing) => {

                  const imageCandidates =
                    getListingImageCandidates(
                      listing,
                    );

                  return (
                    <article
                      key={listing.id}
                      className="group flex flex-col overflow-hidden rounded-xl border border-border bg-card shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md focus-within:ring-2 focus-within:ring-[#22C55E] motion-reduce:transition-none motion-reduce:hover:translate-y-0"
                    >

                      {/* =================================================
                          PRODUCT IMAGE — square, upper portion of card
                          Tapping opens the full product detail dialog.
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
                        className="relative block aspect-square w-full cursor-pointer overflow-hidden border-0 bg-background p-0 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#22C55E]"
                        aria-label={copy("View details for {0}", [listing.name])}
                      >
                        <ListingImage
                          candidates={imageCandidates}
                          alt={listing.name}
                          className="h-full w-full transition-transform duration-300 group-hover:scale-[1.04]"
                        />

                        {/* Status pill sits on the image so it never steals
                            a row of text space on narrow screens. */}
                        <span
                          className={`
                            pointer-events-none
                            absolute
                            left-1.5
                            top-1.5
                            inline-flex
                            max-w-[calc(100%-0.75rem)]
                            items-center
                            truncate
                            rounded-full
                            px-1.5
                            py-0.5
                            text-[0.6rem]
                            font-bold
                            uppercase
                            tracking-wide
                            text-white
                            shadow-sm
                            ${getStatusColor(
                              listing.status,
                            )}
                          `}
                        >
                          {listing.status}
                        </span>
                      </button>

                      {/* =================================================
                          PRODUCT DETAILS — compact card body
                      ================================================= */}

                      <div className="flex flex-1 flex-col p-2 sm:p-2.5">

                        <h3 className="line-clamp-2 min-h-[2.1rem] text-[0.8rem] font-semibold leading-[1.05rem] text-foreground sm:text-sm">
                          {listing.name}
                        </h3>

                        <div className="mt-1 flex flex-wrap items-baseline gap-x-1">
                          <span className="text-sm font-bold leading-none text-[#22C55E] sm:text-base">
                            ₱{listing.price.toLocaleString()}
                          </span>
                          <span className="text-[0.65rem] leading-none text-muted-foreground sm:text-xs">
                            / {listing.unit}
                          </span>
                        </div>

                        <p className="mt-1 text-[0.65rem] leading-tight text-muted-foreground sm:text-xs">
                          {listing.quantity} {listing.unit}{' '}<DashboardText text="available" /></p>

                        <dl className="mt-2 grid grid-cols-2 gap-1 text-xs text-muted-foreground">{[['Reserved','quantity_reserved'],['Sold / committed','quantity_sold'],['Delivered','quantity_delivered'],['Returned / cancelled','quantity_returned_cancelled']].map(([label,key])=><div key={key}><dt>{label}</dt><dd className="font-semibold">{listing.raw?.[key] == null ? '—' : Number(listing.raw[key]).toLocaleString()} {listing.unit}</dd></div>)}</dl><p className="mt-1 text-[10px] text-muted-foreground"><DashboardText text="Delivery does not confirm payment." /></p>
                        {/* ACTIONS — compact, always reachable */}

                        <div className="mt-2 flex gap-1 border-t border-border pt-2">

                          <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                              handleEdit(
                                listing,
                              )
                            }
                            className="h-7 min-h-7 flex-1 rounded-md px-1 text-[0.7rem] font-semibold"
                          >
                            <Edit className="mr-1 h-3.5 w-3.5" aria-hidden="true" /><DashboardText text="Edit" /></Button>

                          <Button
                            type="button"
                            variant="ghost"
                            aria-label={copy("Delete {0}", [listing.name])}
                            onClick={() =>
                              handleDelete(
                                listing,
                              )
                            }
                            className="h-7 min-h-7 shrink-0 rounded-md px-1.5"
                          >
                            <Trash2 className="h-3.5 w-3.5 text-red-600" />
                          </Button>

                        </div>

                      </div>

                    </article>
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

        <DialogContent className="flex max-h-[92dvh] w-[calc(100vw-1rem)] flex-col gap-0 overflow-hidden p-0 sm:w-[calc(100vw-2rem)] sm:max-w-5xl">

          <DialogHeader className="shrink-0 border-b border-border bg-card px-5 py-4 sm:px-6">

            <DialogTitle><DashboardText text="I-edit ang Product Listing" /></DialogTitle>

            <DialogDescription><DashboardText text="I-update ang detalye ng listing at inventory" /></DialogDescription>

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
              onSave={(item) => {
                addListingToState(
                  item,
                );

                setEditingListing(
                  null,
                );

                setIsEditOpen(
                  false,
                );

                setListingFeedback('updated');
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

          <h3 className="mb-3 font-bold text-foreground"><DashboardText text="Tips para sa Mas Maayos na Listings" /></h3>

          <div className="grid gap-4 text-sm text-[#0B4842] md:grid-cols-2">

            <div className="flex items-start gap-2">

              <div className="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-[#0F9488] text-xs font-bold text-white">
                1
              </div>

              <span><DashboardText text="Gumamit ng malinaw at descriptive na product names na nagpapakita ng quality" /></span>

            </div>

            <div className="flex items-start gap-2">

              <div className="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-[#0F9488] text-xs font-bold text-white">
                2
              </div>

              <span><DashboardText text="Panatilihing competitive ang pricing batay sa AI insights" /></span>

            </div>

            <div className="flex items-start gap-2">

              <div className="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-[#0F9488] text-xs font-bold text-white">
                3
              </div>

              <span><DashboardText text="Regular na i-update ang inventory para mapanatili ang tiwala ng buyers" /></span>

            </div>

            <div className="flex items-start gap-2">

              <div className="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-[#0F9488] text-xs font-bold text-white">
                4
              </div>

              <span><DashboardText text="Banggitin ang certifications at quality standards" /></span>

            </div>

          </div>

        </CardContent>

      </Card>

      {/* =====================================================
          FLOATING ADD LISTING BUTTON (mobile only)

          Opens the SAME create dialog used by the desktop header
          action, so no listing form is duplicated. Positioned above the
          dashboard's bottom navigation bar and clear of the safe-area
          inset, and kept below dialog z-index layers.
      ===================================================== */}

      <Button
        type="button"
        onClick={() => {
          setEditingListing(null);
          setIsCreateOpen(true);
        }}
        aria-label={copy("Add Listing")}
        title={copy("Add Listing")}
        className="
          fixed
          bottom-[calc(env(safe-area-inset-bottom)+4.75rem)]
          right-4
          z-40
          flex
          h-14
          w-14
          items-center
          justify-center
          rounded-full
          bg-[#22C55E]
          p-0
          text-white
          shadow-xl
          transition-transform
          duration-200
          hover:bg-[#15803D]
          hover:shadow-2xl
          active:scale-95
          focus-visible:outline-none
          focus-visible:ring-2
          focus-visible:ring-[#22C55E]
          focus-visible:ring-offset-2
          focus-visible:ring-offset-[#F5F1E5]
          motion-reduce:transition-none
          sm:bottom-[calc(env(safe-area-inset-bottom)+4.25rem)]
          sm:right-6
          lg:hidden
        "
      >
        <Plus className="h-6 w-6" aria-hidden="true" />
      </Button>

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
  ) => void | Promise<void>;
}) {
  const copy = useDashboardCopy();

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

  const [imageFiles, setImageFiles] = useState<File[]>([]);
  const [imagePreviewUrls, setImagePreviewUrls] = useState<string[]>([]);
  const [imageError, setImageError] = useState('');
  const [removedImageIds, setRemovedImageIds] = useState<number[]>([]);
  const [removeLegacyImage, setRemoveLegacyImage] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const saveLock = useRef(false);
  const existingImages = mode === 'edit' && existingListing
    ? getEditableListingImages(existingListing)
    : [];
  const visibleExistingImages = existingImages.filter((image) =>
    image.id != null
      ? !removedImageIds.includes(image.id)
      : !(image.isLegacy && removeLegacyImage),
  );
  const totalImageCount = visibleExistingImages.length + imageFiles.length;

  /* ==========================================================
     PRODUCT ASSISTANT STATE
     Market facts come from the authenticated deterministic
     backend endpoint. Listing text remains a separate helper.
  ========================================================== */

  const [
    aiSuggestions,
    setAiSuggestions,
  ] = useState<string[]>([]);

  const [
    aiDescription,
    setAiDescription,
  ] = useState('');

  const [listingTextGenerated, setListingTextGenerated] = useState(false);

  const [analysisResult, setAnalysisResult] =
    useState<ProductAssistantAnalysis | null>(null);

  const [analysisSnapshot, setAnalysisSnapshot] =
    useState('');

  const [analysisLoading, setAnalysisLoading] =
    useState(false);

  const [analysisError, setAnalysisError] =
    useState('');

  // Rotates only while the real request is pending. There is no artificial
  // delay: the interval is cleared the moment the server answers, and it never
  // claims the model is producing text before a response has arrived.
  const [analysisProgressStep, setAnalysisProgressStep] = useState(0);
  const analysisInFlight = useRef(false);

  // Text that has already been received from the API and is being revealed.
  const [aiReveal, setAiReveal] = useState('');
  const { revealed: aiRevealedText, done: aiRevealDone } = useTypewriterText(aiReveal, Boolean(aiReveal));

  useEffect(() => {
    if (!analysisLoading) {
      setAnalysisProgressStep(0);
      return;
    }
    const timer = window.setInterval(
      () => setAnalysisProgressStep((step) => (step + 1) % analysisProgressMessages.length),
      1600,
    );
    return () => window.clearInterval(timer);
  }, [analysisLoading]);

  const {lang:analysisLanguage}=useLanguage();
  const trAnalysis=(en:string,fil:string)=>analysisLanguage==='fil'?fil:en;
  const [analysisMonth, setAnalysisMonth] = useState(() => new Intl.DateTimeFormat('sv-SE', {timeZone:'Asia/Manila', year:'numeric', month:'2-digit'}).format(new Date()));
  const analysisInput = {
    analysis_date: /^\d{4}-\d{2}$/.test(analysisMonth) ? analysisMonth + '-01' : null,
    product_name: productName.trim() || null,
    category: category || null,
    origin: origin.trim() || null,
    unit: unit || null,
    quantity: quantity === '' ? null : Number(quantity),
    producer_price: price === '' ? null : Number(price),
  };
  const currentAnalysisSnapshot = JSON.stringify(analysisInput);
  const analysisIsStale = Boolean(
    analysisResult && analysisSnapshot !== currentAnalysisSnapshot,
  );

  const generateListingTextSuggestions =
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
        imageFiles.length > 0 || visibleExistingImages.length > 0;

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

      setAiSuggestions(
        suggestions.slice(0, 5),
      );

      setListingTextGenerated(true);
    };

  const analyzeMarket = async () => {
    // Guards duplicate submissions from rapid clicks and from the Enter key
    // while the previous request is still open.
    if (analysisInFlight.current) return;

    if (
      (quantity !== '' && !Number.isFinite(Number(quantity))) ||
      (price !== '' && !Number.isFinite(Number(price)))
    ) {
      setAnalysisError('Enter a valid quantity and price before requesting analysis.');
      return;
    }

    const submittedInput = { ...analysisInput };
    const submittedSnapshot = JSON.stringify(submittedInput);
    analysisInFlight.current = true;
    setAnalysisError('');
    setAnalysisProgressStep(0);
    setAnalysisLoading(true);

    try {
      const response = await request(
        '/api/producer/product-assistant/analyze',
        { method: 'POST', body: submittedInput },
      );

      if (!response?.data || typeof response.data !== 'object' || Array.isArray(response.data)) {
        setAnalysisError('The analysis response was unavailable.');
        return;
      }

      const received = response.data as ProductAssistantAnalysis;
      setAnalysisResult(received);
      setAnalysisSnapshot(submittedSnapshot);
      // The interpretation is revealed progressively only after it has been
      // received. When the provider did not answer, ai_interpretation is null
      // and the existing deterministic-only state is shown instead.
      setAiReveal(received.ai_interpretation ?? '');
    } catch (error: any) {
      const status = Number(error?.status);
      const validationMessages = Object.values(error?.errors || {})
        .flat()
        .filter((message): message is string => typeof message === 'string');

      if (status === 401) {
        setAnalysisError('Your session may have expired. Sign in again to request market analysis.');
      } else if (status === 403) {
        setAnalysisError('This account is not authorized to request producer market analysis.');
      } else if (status === 422) {
        setAnalysisError(validationMessages.length
          ? validationMessages.join(' ')
          : 'Review the product, category, unit, quantity, and price values, then try again.');
      } else if (status >= 500) {
        setAnalysisError('Market analysis is temporarily unavailable.');
      } else {
        setAnalysisError('Unable to reach market analysis right now.');
      }
    } finally {
      analysisInFlight.current = false;
      setAnalysisLoading(false);
    }
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

    setImageFiles([]);
    setImageError('');
    setRemovedImageIds([]);
    setRemoveLegacyImage(false);

    setAiSuggestions([]);
    setAiDescription('');
    setListingTextGenerated(false);
    setAnalysisResult(null);
    setAnalysisSnapshot('');
    setAnalysisError('');

  }, [
    existingListing,
    mode,
  ]);

  /* ==========================================================
     LOCAL IMAGE PREVIEW
  ========================================================== */

  useEffect(() => {
    const previewUrls = imageFiles.map((file) => URL.createObjectURL(file));
    setImagePreviewUrls(previewUrls);

    return () => {
      previewUrls.forEach((url) => URL.revokeObjectURL(url));
    };
  }, [imageFiles]);

  /* ==========================================================
     SUBMIT
  ========================================================== */

  const handleSubmit =
    async () => {

      if (saveLock.current || isSaving) return;

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

      saveLock.current = true;
      setIsSaving(true);

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

      if (mode === 'edit') {
        payload.remove_image_ids = removedImageIds;
        payload.remove_legacy_image = removeLegacyImage;
      }

      const hasRequestedImageRemoval =
        removedImageIds.length > 0 || removeLegacyImage;

      /* ======================================================
         IMAGE UPLOAD
      ====================================================== */

      if (imageFiles.length > 0) {

        const form =
          new FormData();

        Object.entries(
          payload,
        ).forEach(
          ([key, value]) => {

            if (key === 'remove_image_ids') {
              (value as number[]).forEach((imageId) => {
                form.append('remove_image_ids[]', String(imageId));
              });
              return;
            }

            if (key === 'remove_legacy_image') {
              if (value) form.append(key, '1');
              return;
            }

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

        imageFiles.forEach((file) => {
          form.append('images[]', file);
        });

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
            existingListing &&
            !hasRequestedImageRemoval
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

          await onSave(
            nextItem,
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
        } finally {
          saveLock.current = false;
          setIsSaving(false);
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
          existingListing &&
          !hasRequestedImageRemoval
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

        await onSave(
          nextItem,
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
      } finally {
        saveLock.current = false;
        setIsSaving(false);
      }
    };

  /* ==========================================================
     FORM UI
  ========================================================== */

  return (
    <div className="flex min-h-0 flex-1 flex-col">
      <div className="min-h-0 flex-1 space-y-5 overflow-y-auto overscroll-contain px-4 py-4 [scrollbar-gutter:stable] sm:px-6">

      {/* PRODUCT NAME + CATEGORY */}

      <div className="grid gap-4 md:grid-cols-2">

        <div>
          <Label htmlFor="productName"><DashboardText text="Product Name" /></Label>

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
            placeholder={copy("e.g., Premium Rice")}
          />
        </div>

        <div>
          <Label htmlFor="category"><DashboardText text="Category" /></Label>

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
            className="block w-full rounded-lg border border-border p-2 focus:outline-none focus:ring-2 focus:ring-[#22C55E]"
          >
            <option value="grains"><DashboardText text="Grains" /></option>

            <option value="fish"><DashboardText text="Fish" /></option>

            <option value="vegetables"><DashboardText text="Vegetables" /></option>

            <option value="fruits"><DashboardText text="Fruits" /></option>

            <option value="livestock"><DashboardText text="Livestock" /></option>
          </select>
        </div>

      </div>

      {/* PRODUCT ORIGIN / SOURCE */}

      <div>

        <Label htmlFor="origin"><DashboardText text="Origin / Saan Nagmula ang Produkto" /></Label>

        <Input
          id="origin"
          value={origin}
          onChange={(e) =>
            setOrigin(e.target.value)
          }
          placeholder={copy("Hal. Brgy. Centro, Aparri, Cagayan")}
          className="border-border bg-card text-foreground focus-visible:ring-[#22C55E]"
        />

        <p className="mt-1 text-xs text-muted-foreground"><DashboardText text="Ilagay kung saan nagmula, ginawa, o inani ang produkto." /></p>

      </div>

      {/* DESCRIPTION */}

      <div>

        <Label htmlFor="description"><DashboardText text="Description" /></Label>

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
          placeholder={copy("Ilarawan ang iyong produkto")}
        />

      </div>

      {/* PRICE / UNIT / QUANTITY */}

      <div className="grid gap-4 md:grid-cols-3">

        <div>

          <Label htmlFor="price"><DashboardText text="Price" /></Label>

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

          <Label htmlFor="unit"><DashboardText text="Unit" /></Label>

          <select
            id="unit"
            value={unit}
            onChange={(e) =>
              setUnit(
                e.target.value,
              )
            }
            className="block w-full rounded-lg border border-border p-2 focus:outline-none focus:ring-2 focus:ring-[#22C55E]"
          >

            <option value="kg"><DashboardText text="Kilogram (kg)" /></option>

            <option value="g"><DashboardText text="Gram (g)" /></option>

            <option value="sack"><DashboardText text="Sack" /></option>

            <option value="piece"><DashboardText text="Piece" /></option>

          </select>

        </div>

        <div>

          <Label htmlFor="quantity"><DashboardText text="Available na Quantity" /></Label>

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

      <section
        aria-labelledby="listing-images-title"
        className="rounded-2xl border border-[#D8D2C3] bg-[#FBFAF6] p-4 sm:p-5"
      >
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 id="listing-images-title" className="font-bold text-foreground"><DashboardText text="Upload Product Images (Up to 10)" /></h3>
            <p className="mt-1 text-xs leading-5 text-muted-foreground"><DashboardText text="Choose one or more JPG, PNG, or WebP photos. Each image may be up to 5 MB." /></p>
          </div>
          <span aria-live="polite" className="rounded-full bg-[#E7F4E8] px-3 py-1 text-xs font-bold text-[#166534]">
            {totalImageCount}/{MAX_LISTING_IMAGES}{' '}<DashboardText text="images uploaded." /></span>
        </div>

        <input
          id={mode === 'create' ? 'createListingImages' : 'editListingImages'}
          type="file"
          accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
          multiple
          disabled={totalImageCount >= MAX_LISTING_IMAGES}
          className="peer sr-only"
          onChange={(event) => {
            const input = event.currentTarget;
            const selectedFiles = Array.from(input.files ?? []);
            input.value = '';

            if (selectedFiles.length === 0) return;

            const remainingSlots = Math.max(0, MAX_LISTING_IMAGES - totalImageCount);
            const acceptedFiles = selectedFiles.slice(0, remainingSlots);

            setImageError(
              selectedFiles.length > remainingSlots
                ? 'A listing can have up to 10 images. The remaining available slots were added.'
                : '',
            );

            if (acceptedFiles.length > 0) {
              setImageFiles((current) => [...current, ...acceptedFiles]);
            }
          }}
        />

        <label
          htmlFor={mode === 'create' ? 'createListingImages' : 'editListingImages'}
          aria-disabled={totalImageCount >= MAX_LISTING_IMAGES}
          className={`mt-4 flex min-h-24 cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-4 py-5 text-center transition sm:flex-row sm:text-left ${
            totalImageCount >= MAX_LISTING_IMAGES
              ? 'cursor-not-allowed border-[#D8D2C3] bg-[#F1EFE9] text-muted-foreground'
              : 'border-[#8FC39A] bg-card text-foreground hover:border-[#15803D] hover:bg-[#F0FDF4] peer-focus-visible:ring-2 peer-focus-visible:ring-[#0F9488]'
          }`}
        >
          <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#DCFCE7] text-[#15803D]">
            <Upload className="h-5 w-5" aria-hidden="true" />
          </span>
          <span>
            <span className="block text-sm font-bold">
              {totalImageCount >= MAX_LISTING_IMAGES ? copy("Image limit reached") : copy("Select product images")}
            </span>
            <span className="mt-1 block text-xs text-muted-foreground"><DashboardText text="Add photos in more than one selection, up to 10 total." /></span>
          </span>
        </label>

        {imageError && (
          <p role="alert" className="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
            {imageError}
          </p>
        )}

        {totalImageCount > 0 ? (
          <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            {visibleExistingImages.map((image, index) => (
              <div key={`existing-${image.id ?? image.url}-${index}`} className="relative overflow-hidden rounded-xl border border-border bg-card p-2">
                <ListingImage candidates={[image.url]} alt={`Existing product image ${index + 1}`} />
                <button
                  type="button"
                  aria-label={copy("Remove saved image {0}", [index + 1])}
                  onClick={() => {
                    if (image.id != null) {
                      setRemovedImageIds((current) =>
                        current.includes(image.id!) ? current : [...current, image.id!],
                      );
                    } else if (image.isLegacy) {
                      setRemoveLegacyImage(true);
                    }
                    setImageError('');
                  }}
                  className="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-red-600 text-white shadow-md transition hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2"
                >
                  <X className="h-4 w-4" aria-hidden="true" />
                </button>
                <span className="mt-2 block truncate text-xs font-semibold text-muted-foreground"><DashboardText text="Saved image" />{' '}{index + 1}</span>
              </div>
            ))}

            {imageFiles.map((file, index) => (
              <div key={`${file.name}-${file.lastModified}-${index}`} className="relative overflow-hidden rounded-xl border border-border bg-card p-2">
                {imagePreviewUrls[index] && (
                  <ListingImage
                    candidates={[imagePreviewUrls[index]]}
                    alt={`Selected product image ${index + 1}`}
                  />
                )}
                <button
                  type="button"
                  aria-label={copy("Remove {0}", [file.name])}
                  onClick={() => {
                    setImageFiles((current) => current.filter((_, fileIndex) => fileIndex !== index));
                    setImageError('');
                  }}
                  className="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-red-600 text-white shadow-md transition hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2"
                >
                  <X className="h-4 w-4" aria-hidden="true" />
                </button>
                <span className="mt-2 block truncate text-xs font-semibold text-muted-foreground">{file.name}</span>
              </div>
            ))}
          </div>
        ) : (
          <p className="mt-3 text-sm text-muted-foreground"><DashboardText text="Photos help buyers inspect your product. HarborAI will show a placeholder when no image is added." /></p>
        )}
      </section>

      {/* Product Assistant: deterministic market analysis plus the existing optional listing-text helper. */}
      <section
        id="product-assistant"
        aria-labelledby="product-assistant-title"
        className="w-full rounded-2xl border border-[#B7E4C7] bg-gradient-to-br from-[#F0FDF4] via-white to-[#ECFDF5] shadow-sm"
      >
        <div className="p-4 sm:p-5">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex min-w-0 items-start gap-3">
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#15803D] text-white shadow-sm">
                <Sparkles className="h-5 w-5" aria-hidden="true" />
              </div>
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  <h3 id="product-assistant-title" className="font-display text-base font-bold text-foreground sm:text-lg">
                    HarborAI Product Assistant
                  </h3>
                  <span className="rounded-full bg-[#DCFCE7] px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.1em] text-[#166534]"><DashboardText text="Market analysis" /></span>
                </div>
                <p className="mt-1 text-sm leading-5 text-muted-foreground">
                  {trAnalysis('Compare historical regional prices and seasonal references for your product.','Ihambing ang makasaysayang presyo sa rehiyon at mga sanggunian sa panahon ng produkto.')}
                </p>
              </div>
            </div>

            <div className="flex flex-col gap-2 sm:flex-row lg:shrink-0">
              <Button
                type="button"
                onClick={analyzeMarket}
                disabled={analysisLoading}
                aria-label={copy("Analyze market information for this listing")}
                aria-busy={analysisLoading}
                className="h-10 rounded-full bg-[#123C5C] px-5 text-sm font-bold text-white hover:bg-[#0B2D45]"
              >
                {analysisLoading ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" aria-hidden="true" />
                    {analysisProgressMessages[analysisProgressStep]}
                  </>
                ) : (
                  <>
                    <Wand2 className="mr-2 h-4 w-4" aria-hidden="true" />
                    {analysisResult ? trAnalysis('Analyze again','Suriin muli') : trAnalysis('Analyze Product','Suriin ang produkto')}
                  </>
                )}
              </Button>
              <Button
                type="button"
                variant="outline"
                onClick={generateListingTextSuggestions}
                className="h-10 rounded-full border-[#B7D8C0] bg-card px-4 text-sm font-semibold text-[#15803D] hover:bg-[#F0FDF4]"
              >
                <Sparkles className="mr-2 h-4 w-4" aria-hidden="true" /><DashboardText text="Generate listing text" /></Button>
            </div>
          </div>

          <label className="mt-3 block text-sm font-medium text-foreground">{trAnalysis('Analysis month','Buwan ng pagsusuri')}<input type="month" className="mt-1 block max-w-full rounded-lg border p-2" value={analysisMonth} onChange={e => setAnalysisMonth(e.target.value)} /></label>
          {analysisError && (
            <p role="alert" className="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm leading-5 text-red-900">
              {analysisError}{' '}<DashboardText text="Your listing form remains available." /></p>
          )}

          {analysisIsStale && (
            <p role="status" aria-live="polite" className="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm leading-5 text-amber-950"><DashboardText text="The form values have changed since this analysis. These results reflect the earlier values; analyze again to refresh them. Your entered price has not been changed." /></p>
          )}

          {analysisLoading && (
            <div role="status" aria-live="polite" className="mt-4 rounded-lg border border-[#B7D8C0] bg-card px-3 py-3 text-sm text-foreground">
              <p className="flex items-center gap-2 font-semibold">
                <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                {analysisProgressMessages[analysisProgressStep]}
              </p>
              <p className="mt-1 text-xs text-muted-foreground"><DashboardText text="Figures come from the deterministic backend analysis; any AI interpretation is advisory and optional. You can continue editing the listing." /></p>
            </div>
          )}

          {!analysisResult && !analysisLoading && (
            <div className="mt-4 rounded-xl border border-dashed border-[#A7D7B5] bg-card/80 px-4 py-4">
              <p className="text-sm font-semibold text-foreground"><DashboardText text="Market assistance is ready." /></p>
              <p className="mt-1 text-sm leading-5 text-muted-foreground"><DashboardText text="Enter the product details, then choose Analyze Market to request backend analysis. Results provide reference information and do not set or validate your listing price." /></p>
            </div>
          )}

          {analysisResult && (
            <div className="mt-4 space-y-3">
              <div className="grid min-w-0 gap-3 sm:grid-cols-2">
                <section className="rounded-xl border bg-card p-4"><h4 className="font-bold text-foreground">{trAnalysis('Product Information','Impormasyon ng produkto')}</h4><p className="mt-2 text-sm">{analysisResult.commodity_resolution?.canonical_name || productName} · {analysisResult.analysis_date}</p><p className="text-sm">{formatAnalysisMoney(analysisResult.producer_price_comparison?.input_price)} / {analysisResult.producer_price_comparison?.input_unit || unit}</p><p className="text-sm">{analysisResult.input_context?.origin || trAnalysis('Origin not specified','Hindi tinukoy ang pinagmulan')}</p></section>
                <section className="rounded-xl border bg-card p-4"><h4 className="font-bold text-foreground">{trAnalysis('Market Evidence','Batayan ng presyo')}</h4><p className="mt-2 text-sm">{(analysisResult.psa_reference?.observation_count??0)>0 ? formatAnalysisMoney(analysisResult.psa_reference?.minimum_price)+' – '+formatAnalysisMoney(analysisResult.psa_reference?.maximum_price)+' / '+(analysisResult.psa_reference?.unit||'unit not specified') : trAnalysis('No matching positive price observations.','Walang katugmang positibong tala ng presyo.')}</p><p className="text-xs">{analysisResult.psa_reference?.geography || 'Region II / Cagayan Valley'} · {analysisResult.psa_reference?.first_observation_date || '—'} – {analysisResult.psa_reference?.latest_available_observation?.date || '—'}</p><p className="mt-2 text-xs">{trAnalysis('Historical regional evidence, not current Aparri prices.','Makasaysayang datos sa rehiyon, hindi kasalukuyang presyo sa Aparri.')}</p></section>
                <section className="rounded-xl border bg-card p-4"><h4 className="font-bold text-foreground">{trAnalysis('Seasonality Analysis','Pagsusuri ng panahon')}</h4><p className="mt-2 text-sm">{analysisResult.seasonality?.analysis_month_name}: {analysisResult.seasonality?.interpretation || trAnalysis('No matching seasonal reference.','Walang katugmang sanggunian.')}</p><p className="mt-2 text-xs">{analysisResult.seasonality?.references?.map(r=>r.source_label || r.source_name).filter(Boolean).join('; ')}</p><p className="mt-2 text-xs">{trAnalysis('Derived seasonal indicators are not MAO-validated Aparri production data and contain no prices.','Ang hinangong palatandaan ay hindi datos ng produksyon sa Aparri na pinatotohanan ng MAO at walang presyo.')}</p></section>
                <section className="rounded-xl border bg-card p-4"><h4 className="font-bold text-foreground">{trAnalysis('Geographic Considerations','Pagsasaalang-alang sa lokasyon')}</h4><p className="mt-2 text-sm">{analysisResult.input_context?.origin && !/aparri/i.test(analysisResult.input_context.origin) ? trAnalysis('Origin is outside the stated Aparri context or needs confirmation. Transport, handling and local market conditions may differ; no price difference is inferred.','Nasa labas ng Aparri o kailangang kumpirmahin ang pinagmulan. Maaaring magkaiba ang transportasyon at lokal na kondisyon; walang ipinapalagay na diperensya sa presyo.') : trAnalysis('Regional observations do not establish a local Aparri price. Check actual transport, quality and handling costs.','Hindi nagtatakda ng presyo sa Aparri ang datos ng rehiyon. Suriin ang aktuwal na transportasyon, kalidad at paghawak.')}</p></section>
              </div>
              {/* Compressed default reading: one paragraph plus the supporting facts. The full
    narratives, every variety series and all provenance remain available in the
    collapsed evidence section further down, so nothing is lost by not repeating it. */}
<section className="rounded-xl border border-teal-200 bg-teal-50 p-4">
  <h4 className="font-bold">{trAnalysis('Market Interpretation', 'Pagpapaliwanag ng Merkado')}</h4>
  <p className="mt-2 text-sm leading-6 text-foreground">
    {analysisResult.summary?.summary || analysisResult.interpretation?.interpretation}
  </p>
  <p className="mt-2 text-xs text-muted-foreground">
    {analysisResult.summary?.word_count
      ? trAnalysis(analysisResult.summary.word_count + ' words', analysisResult.summary.word_count + ' salita')
      : ''}
    {analysisResult.interpretation?.interpretation_source
      ? ' · ' + trAnalysis('Deterministic analysis, not Azure-generated', 'Batay sa datos, hindi ginawa ng Azure')
      : ''}
  </p>
</section>

{analysisResult.summary?.key_facts?.length ? (
  <section className="rounded-xl border border-[#D8EAD9] bg-card p-4">
    <h4 className="font-bold text-foreground">{trAnalysis('Key Market Facts', 'Pangunahing datos ng merkado')}</h4>
    <dl className="mt-2 grid gap-2 sm:grid-cols-2">
      {analysisResult.summary.key_facts.map((fact) => (
        <div key={fact.label} className="rounded-lg border border-[#E7EDF0] bg-background p-2">
          <dt className="text-[11px] font-bold uppercase tracking-wide text-muted-foreground">{fact.label}</dt>
          <dd className="text-sm text-foreground">{fact.value}</dd>
        </div>
      ))}
    </dl>
    {analysisResult.summary.variety_candidates?.length ? (
      <details className="mt-3">
        <summary className="cursor-pointer text-xs font-semibold text-foreground">
          {trAnalysis(
            'Variety-specific historical series (' + analysisResult.summary.variety_candidates.length + ')',
            'Serye ng espesipikong varieties (' + analysisResult.summary.variety_candidates.length + ')',
          )}
        </summary>
        <ul className="mt-2 space-y-1">
          {analysisResult.summary.variety_candidates.map((v) => (
            <li key={v.commodity_label} className="text-xs text-muted-foreground">
              {v.commodity_label} — {v.comparable_observation_count}{' '}<DashboardText text="comparable observation(s)" />{' '}{v.geography ? ' · ' + v.geography : ''}
            </li>
          ))}
        </ul>
        <p className="mt-2 text-[11px] text-[#B45309]">
          {trAnalysis(
            'These are separate variety series. HarborAI does not merge them, because averaging different varieties would mislead you.',
            'Separate ang mga seryeng ito ng bawat variety. Hindi ito pinagsasama ng HarborAI, sapagkat maling impormasyon ang maaaring manggaling sa pag-average ng iba’t ibang variety.',
          )}
        </p>
      </details>
    ) : null}
  </section>
) : null}

{/* Four independent statuses. A failure in one never blanks the others. */}
<div className="flex flex-wrap gap-2" role="list" aria-label={trAnalysis('Analysis status', 'Estatus ng pagsusuri')}>
  {([
    ['Market analysis', 'Pagsusuri ng merkado', analysisResult.interpretation?.market_analysis_status],
    ['Seasonality evidence', 'Ebidensya ng panahon', analysisResult.interpretation?.seasonality_evidence_status],
    ['Price evidence', 'Ebidensya ng presyo', analysisResult.interpretation?.price_evidence_status],
    ['Generative AI', 'Generative AI', analysisResult.interpretation?.generative_ai_status],
  ] as const).map(([en, fil, status]) => (
    <span key={en} role="listitem" className="inline-flex items-center gap-1.5 rounded-full border border-[#D8EAD9] bg-card px-2.5 py-1 text-[11px] font-semibold text-foreground">
      <span aria-hidden="true" className={'h-1.5 w-1.5 rounded-full ' + (status === 'AVAILABLE' ? 'bg-[#15803D]' : status === 'UNAVAILABLE' || status === 'INSUFFICIENT_DATA' ? 'bg-[#B45309]' : 'bg-[#64748B]')} />
      {trAnalysis(en, fil)}: {status ?? trAnalysis('unavailable', 'hindi available')}
    </span>
  ))}
</div>
              <details className="rounded-xl border bg-card p-3"><summary className="cursor-pointer font-semibold">{trAnalysis('Detailed evidence and source limitations','Detalyadong ebidensya at limitasyon')}</summary>
              {analysisResult.analysis_date && (
                <p className="text-xs text-muted-foreground"><DashboardText text="Analysis date:" />{' '}{analysisResult.analysis_date}</p>
              )}
              <p className="text-sm font-semibold text-foreground"><DashboardText text="Commodity / variety:" />{' '}{analysisResult.commodity_resolution?.canonical_name || productName || copy("Not specified")}</p>
              <section aria-labelledby="ai-advisory-title" className="min-w-0 rounded-xl border border-[#B7D8C0] bg-gradient-to-br from-[#F0FDF4] to-white p-4 sm:p-5">
                <div className="flex flex-wrap items-center gap-2">
                  <Sparkles className="h-4 w-4 text-[#15803D]" aria-hidden="true" />
                  <h4 id="ai-advisory-title" className="text-sm font-bold text-foreground"><DashboardText text="Pricing Consideration" /></h4>
                  <span className="rounded-full bg-[#DCFCE7] px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.1em] text-[#166534]"><DashboardText text="Reference only" /></span>
                </div>
                {analysisResult.ai_interpretation ? (
                  <div className="mt-3 space-y-2" aria-busy={!aiRevealDone}>
                    {renderAiAdvisory(aiRevealedText)}
                    {!aiRevealDone && (
                      <p className="flex items-center gap-2 text-xs text-muted-foreground">
                        <Loader2 className="h-3.5 w-3.5 animate-spin" aria-hidden="true" />
                        {analysisProgressMessages[analysisProgressStep]}
                      </p>
                    )}
                  </div>
                ) : (
                  <p className="mt-3 text-sm leading-6 text-muted-foreground">
                    {analysisResult.interpretation?.seasonality_narrative || analysisResult.seasonality?.interpretation || copy("No matching seasonal reference is available.")}{' '}
                    {analysisResult.interpretation?.price_narrative}{' '}
                    {analysisResult.interpretation?.pricing_question}
                  </p>
                )}
              </section>

              <div className="grid min-w-0 gap-3 sm:grid-cols-2">
                <section aria-labelledby="producer-price-title" className="min-w-0 rounded-xl border border-[#D8EAD9] bg-card p-4">
                  <h4 id="producer-price-title" className="text-sm font-bold text-foreground"><DashboardText text="Your proposed price" /></h4>
                  <p className="mt-2 text-xl font-black text-foreground">
                    {analysisResult.producer_price_comparison?.input_price == null
                      ? copy("No price supplied")
                      : `${formatAnalysisMoney(analysisResult.producer_price_comparison.input_price, analysisResult.producer_price_comparison.input_currency)}${analysisResult.producer_price_comparison.input_unit ? ` / ${analysisResult.producer_price_comparison.input_unit}` : ''}`}
                  </p>
                  <p className="mt-2 text-sm leading-5 text-muted-foreground">
                    {analysisResult.producer_price_comparison?.position_relative_to_range
                      ? pricePositionText(analysisResult.producer_price_comparison.position_relative_to_range)
                      : producerPriceStatusText(analysisResult.producer_price_comparison?.status)}
                  </p>
                  {analysisResult.producer_price_comparison?.difference_from_average != null && (
                    <p className="mt-3 border-t border-border pt-3 text-xs leading-5 text-muted-foreground"><DashboardText text="Difference from the PSA average:" />{' '}{formatAnalysisMoney(analysisResult.producer_price_comparison.difference_from_average, analysisResult.producer_price_comparison.input_currency)}{' '}<DashboardText text="per kg" />{' '}{analysisResult.producer_price_comparison.difference_percent_from_average != null
                        ? ` (${formatAnalysisNumber(analysisResult.producer_price_comparison.difference_percent_from_average)}%)`
                        : ''}.
                    </p>
                  )}
                  <p className="mt-3 text-xs leading-5 text-muted-foreground"><DashboardText text="This is advisory context only. You control the final listing price." /></p>
                </section>

                <section aria-labelledby="harborai-listings-title" className="min-w-0 rounded-xl border border-[#D8EAD9] bg-card p-4">
                  <h4 id="harborai-listings-title" className="text-sm font-bold text-foreground"><DashboardText text="HarborAI current listings" /></h4>
                  <p className="mt-2 text-sm text-muted-foreground"><DashboardText text="Active listings:" /><strong>{formatAnalysisNumber(analysisResult.harborai_listing_reference?.active_listing_count)}</strong>
                    {' · '}{' '}<DashboardText text="Compatible prices:" /><strong>{formatAnalysisNumber(analysisResult.harborai_listing_reference?.active_listing_price_count)}</strong>
                  </p>
                  {(analysisResult.harborai_listing_reference?.active_listing_price_count ?? 0) > 0 && (
                    <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                      <div><dt className="text-xs text-muted-foreground"><DashboardText text="Price range" /></dt><dd className="mt-1 font-semibold text-foreground">{formatAnalysisMoney(analysisResult.harborai_listing_reference?.minimum_active_listing_price)} – {formatAnalysisMoney(analysisResult.harborai_listing_reference?.maximum_active_listing_price)} {analysisResult.harborai_listing_reference?.price_unit}</dd></div>
                      <div><dt className="text-xs text-muted-foreground"><DashboardText text="Average listing price" /></dt><dd className="mt-1 font-semibold text-foreground">{formatAnalysisMoney(analysisResult.harborai_listing_reference?.average_active_listing_price)} {analysisResult.harborai_listing_reference?.price_unit}</dd></div>
                    </dl>
                  )}
                  <p className="mt-3 border-t border-border pt-3 text-xs leading-5 text-muted-foreground"><DashboardText text="Available listing supply:" />{' '}{analysisResult.supply_context?.quantity_total == null
                      ? copy("not available for compatible units")
                      : `${formatAnalysisNumber(analysisResult.supply_context.quantity_total)} ${analysisResult.supply_context.quantity_unit || ''}`}
                  </p>
                  {analysisResult.harborai_listing_reference?.scope && (
                    <p className="mt-2 text-xs leading-5 text-muted-foreground">{analysisResult.harborai_listing_reference.scope}.</p>
                  )}
                  <p className="mt-2 text-xs leading-5 text-muted-foreground"><DashboardText text="Platform listings are separate from PSA reference observations and are not official market prices." /></p>
                </section>

                <section aria-labelledby="buyer-demand-title" className="min-w-0 rounded-xl border border-[#D8EAD9] bg-card p-4">
                  <h4 id="buyer-demand-title" className="text-sm font-bold text-foreground"><DashboardText text="Active buyer requests on HarborAI" /></h4>
                  <p className="mt-2 text-sm text-muted-foreground"><DashboardText text="Active requests:" /><strong>{formatAnalysisNumber(analysisResult.demand_context?.active_demand_request_count)}</strong>
                  </p>
                  <p className="mt-2 text-sm leading-5 text-muted-foreground"><DashboardText text="Requested quantity:" />{' '}{analysisResult.demand_context?.requested_quantity_total == null
                      ? copy("not available for compatible units")
                      : `${formatAnalysisNumber(analysisResult.demand_context.requested_quantity_total)} ${analysisResult.demand_context.quantity_unit || ''}`}
                  </p>
                  {analysisResult.demand_context?.scope && (
                    <p className="mt-3 border-t border-border pt-3 text-xs leading-5 text-muted-foreground">{analysisResult.demand_context.scope}.</p>
                  )}
                </section>
              </div>

                <details aria-labelledby="psa-reference-title" className="min-w-0 rounded-xl border border-[#D8EAD9] bg-card p-4">
                  <summary id="psa-reference-title" className="cursor-pointer text-sm font-bold text-foreground"><DashboardText text="PSA Reference Data:" />{' '}
                    {analysisResult.psa_reference?.source || 'PSA OpenSTAT'} — {analysisResult.psa_reference?.geography || 'Region II / Cagayan Valley'}
                  </summary>
                  <p className="mt-1 text-xs text-muted-foreground">
                    {analysisResult.psa_reference?.price_type || copy("Price type not specified")}{' '}<DashboardText text="reference ·" />{' '}{analysisResult.psa_reference?.unit || copy("Unit not specified")}
                  </p>
                  {(analysisResult.psa_reference_candidates?.length ?? 0) > 1 && (
                    <p className="mt-3 text-xs leading-5 text-muted-foreground"><DashboardText text="These are separate Region II reference series. Statistics are not combined; choose the variety that best matches your product." /></p>
                  )}
                  {(analysisResult.commodity_resolution?.source_coverage?.unmapped_source_siblings?.length ?? 0) > 0 && (
                    <p className="mt-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-950"><DashboardText text="Partial source coverage: PSA publishes further series for this product that HarborAI has no registered commodity reference for (" />{' '}{analysisResult.commodity_resolution?.source_coverage?.unmapped_source_siblings?.join('; ')}{' '}<DashboardText text="). Only the matched series above are represented; nothing is combined with or replaced by an unmapped series." /></p>
                  )}
                  <div className="mt-3 max-h-80 space-y-3 overflow-y-auto overscroll-contain" role="region" aria-label={copy("PSA variety references")} tabIndex={0}>
                    {(analysisResult.psa_reference_candidates?.length
                      ? analysisResult.psa_reference_candidates
                      : [{
                        commodity_label: analysisResult.commodity_resolution?.canonical_name || 'No matching commodity series',
                        psa_reference: analysisResult.psa_reference,
                      }]
                    ).map((series, index) => {
                      const reference = series.psa_reference;
                      const hasObservations = (reference?.observation_count ?? 0) > 0;
                      return (
                        <article
                          key={`${series.commodity_label || 'series'}-${index}`}
                          className="rounded-lg border border-border bg-[#FEFEFC] p-3"
                        >
                          {series.commodity_label && (
                            <h5 className="text-sm font-semibold text-foreground">{series.commodity_label}</h5>
                          )}
                          {hasObservations ? (
                            <>
                              <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                <div><dt className="text-xs text-muted-foreground"><DashboardText text="Verified observations" /></dt><dd className="mt-1 font-semibold text-foreground">{formatAnalysisNumber(reference?.observation_count)}</dd></div>
                                <div><dt className="text-xs text-muted-foreground"><DashboardText text="Observed range" /></dt><dd className="mt-1 font-semibold text-foreground">{formatAnalysisMoney(reference?.minimum_price)} – {formatAnalysisMoney(reference?.maximum_price)}</dd></div>
                                <div><dt className="text-xs text-muted-foreground"><DashboardText text="Average" /></dt><dd className="mt-1 font-semibold text-foreground">{formatAnalysisMoney(reference?.average_price)}</dd></div>
                                <div><dt className="text-xs text-muted-foreground"><DashboardText text="Median" /></dt><dd className="mt-1 font-semibold text-foreground">{formatAnalysisMoney(reference?.median_price)}</dd></div>
                              </dl>
                              {reference?.first_observation_date && (
                                <p className="mt-3 text-xs leading-5 text-muted-foreground"><DashboardText text="Observation history begins" />{' '}{reference.first_observation_date}.
                                </p>
                              )}
                              {reference?.latest_available_observation && (
                                <p className="mt-3 border-t border-border pt-3 text-xs leading-5 text-muted-foreground"><DashboardText text="Latest observation:" />{' '}{reference.latest_available_observation.period || reference.latest_available_observation.date || copy("date unavailable")} — {formatAnalysisMoney(reference.latest_available_observation.price)}.
                                </p>
                              )}
                              {series.producer_price_comparison && (
                                <p className="mt-3 text-xs leading-5 text-muted-foreground">
                                  {series.producer_price_comparison.position_relative_to_range
                                    ? pricePositionText(series.producer_price_comparison.position_relative_to_range)
                                    : producerPriceStatusText(series.producer_price_comparison.status)}
                                </p>
                              )}
                            </>
                          ) : (
                            <p className="mt-2 text-sm leading-5 text-muted-foreground"><DashboardText text="No verified PSA observations are available for this series. No price estimate is shown." /></p>
                          )}
                        </article>
                      );
                    })}
                  </div>
                </details>


              <section aria-labelledby="product-seasonality-title" className="rounded-xl border border-[#D8EAD9] bg-card p-4 sm:p-5">
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <div>
                    <h4 id="product-seasonality-title" className="text-sm font-bold uppercase tracking-wide text-foreground"><DashboardText text="Seasonality" /></h4>
                    <p className="mt-1 text-xs text-muted-foreground"><DashboardText text="Analysis month:" />{' '}{analysisResult.seasonality?.analysis_month_name || copy("Not available")}
                    </p>
                  </div>
                  <span className="rounded-full bg-background px-3 py-1 text-xs font-bold capitalize text-foreground">
                    {seasonalityStatusLabel(analysisResult.seasonality?.status)}
                  </span>
                </div>

                <p className="mt-3 text-sm leading-6 text-muted-foreground">
                  {analysisResult.seasonality?.interpretation
                    || copy("No verified seasonality reference is currently available for this product. HarborAI will not infer a season without supporting reference data.")}
                </p>

                {(analysisResult.seasonality?.references?.length ?? 0) > 0 ? (
                  <div className="mt-4 space-y-3">
                    {analysisResult.seasonality?.references?.map((reference, index) => (
                      <article key={`${reference.source_name || reference.source_reference || 'seasonality'}-${reference.geographic_scope || 'scope'}-${index}`} className="rounded-lg border border-border bg-[#FEFEFC] p-3">
                        <div className="grid gap-3 sm:grid-cols-2">
                          <div>
                            <p className="text-xs font-bold uppercase text-muted-foreground"><DashboardText text="Typical documented period" /></p>
                            <p className="mt-1 text-sm font-semibold text-foreground">
                              {reference.periods?.length ? reference.periods.join('; ') : copy("Period not specified")}
                            </p>
                          </div>
                          <div>
                            <p className="text-xs font-bold uppercase text-muted-foreground"><DashboardText text="Current status" /></p>
                            <p className="mt-1 text-sm font-semibold capitalize text-foreground">{seasonalityStatusLabel(reference.status)}</p>
                          </div>
                        </div>
                        {reference.rows?.some((row) => row.season_status || row.peak_period) && (
                          <ul className="mt-2 space-y-1 text-xs leading-5 text-muted-foreground">
                            {reference.rows.filter((row) => row.season_status || row.peak_period).map((row, rowIndex) => (
                              <li key={`${row.season_status || 'period'}-${rowIndex}`}>
                                {row.season_status
                                  ? reference.commodity_category?.toLowerCase() === 'fish'
                                    ? copy("Source-recorded fishery term: {0}", [seasonalityStatusLabel(row.season_status)])
                                    : copy("{0} period", [seasonalityStatusLabel(row.season_status)])
                                  : copy("Documented period")}
                                {row.peak_period ? copy(" · stated peak: {0}", [row.peak_period]) : ''}
                                {row.reference_date ? copy(" · reference date: {0}", [row.reference_date]) : ''}
                              </li>
                            ))}
                          </ul>
                        )}
                        <p className="mt-3 text-xs leading-5 text-muted-foreground"><DashboardText text="Geographic basis:" />{' '}{reference.geographic_scope || copy("Not specified")}.
                        </p>
                        <p className="mt-1 text-xs leading-5 text-muted-foreground"><DashboardText text="Source:" />{' '}{reference.organization ? `${reference.organization} — ` : ''}{reference.source_name || reference.source_label || copy("Official reference")}
                          {reference.source_reference ? ` · ${reference.source_reference}` : ''}
                          {reference.reference_date ? copy(" · reference date {0}", [reference.reference_date]) : ''}
                          {reference.source_url ? <> · <a href={reference.source_url} target="_blank" rel="noreferrer" className="underline"><DashboardText text="View source" /></a></> : ''}
                        </p>
                        {reference.interpretation && (
                          <p className="mt-2 text-xs leading-5 text-muted-foreground">{reference.interpretation}</p>
                        )}
                      </article>
                    ))}
                  </div>
                ) : analysisResult.seasonality?.candidates?.length ? (
                  <div className="mt-3 text-sm text-muted-foreground">
                    <p><DashboardText text="Matched varieties are kept separate; no seasonal status was selected." /></p>
                    <ul className="mt-2 ml-5 list-disc">
                      {analysisResult.seasonality.candidates.map((candidate) => (
                        <li key={candidate.commodity_label}>{candidate.commodity_label}: {candidate.references?.length ? copy("separate verified reference available") : copy("no verified seasonality reference")}</li>
                      ))}
                    </ul>
                  </div>
                ) : null}

                <p className="mt-3 border-t border-border pt-3 text-xs leading-5 text-muted-foreground">
                  {analysisResult.seasonality?.geographic_limitation || copy("Product quality, grade, and localized farm conditions are not represented.")}
                  {' '}<DashboardText text="Product quality and grade are not represented by seasonality data; no seasonal price adjustment is applied." /></p>
              </section>

              <footer className="rounded-xl border border-[#D8EAD9] bg-card px-4 py-3">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                  <p id="data-availability-title" className="text-xs leading-5 text-muted-foreground">
                    <strong className="text-foreground"><DashboardText text="Data availability:" />{' '}{analysisResult.data_sufficiency?.status || 'INSUFFICIENT_DATA'}.</strong>
                    {' '}{sufficiencyText(analysisResult.data_sufficiency?.status, analysisResult.data_sufficiency?.basis)}
                  </p>
                  {analysisResult.gross_listing_value?.amount != null && (
                    <p className="shrink-0 text-xs leading-5 text-muted-foreground sm:text-right"><DashboardText text="Gross listing value:" />{' '}{formatAnalysisMoney(analysisResult.gross_listing_value.amount, analysisResult.gross_listing_value.currency)}{' '}<DashboardText text="(not profit)" /></p>
                  )}
                </div>
                <p className="mt-2 border-t border-border pt-2 text-xs leading-5 text-muted-foreground"><DashboardText text="PSA observations describe the stated source geography, period and measurement unit; they are not live Aparri prices. HarborAI listing and buyer-request data is separate platform context; listing prices are not official market prices." />{' '}{analysisResult.seasonality?.status === 'INSUFFICIENT_DATA'
                    ? copy("No verified seasonality reference is available for this product.")
                    : analysisResult.seasonality?.status === 'AMBIGUOUS' || analysisResult.seasonality?.status === 'MULTIPLE_REFERENCES'
                      ? copy("Multiple verified seasonality references remain separate; no single seasonal conclusion is selected.")
                      : copy("Seasonality context is based on {0} for {1}.", [analysisResult.seasonality?.references?.map((reference) => reference.source_name).filter(Boolean).join(', ') || 'verified reference data', analysisResult.seasonality?.references?.map((reference) => reference.geographic_scope).filter(Boolean).join('; ') || 'the documented geographic scope'])}
                  {' '}<DashboardText text="Product quality, grade, and localized farm conditions are not represented; no seasonal price adjustment or validated quality-price adjustment is applied. You control the final listing price." /></p>
              </footer>
              </details>
            </div>
          )}

          {listingTextGenerated && (
            <section aria-labelledby="listing-text-helper-title" className="mt-4 rounded-xl border border-[#D8EAD9] bg-card p-4">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <h4 id="listing-text-helper-title" className="text-sm font-bold text-foreground"><DashboardText text="Optional listing text helper" /></h4>
                  <p className="mt-1 text-xs text-muted-foreground"><DashboardText text="Review and edit this text before saving." /></p>
                </div>
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => {
                    if (aiDescription.trim()) setDescription(aiDescription);
                  }}
                  className="h-9 rounded-full border-[#B7D8C0] bg-card px-4 text-xs font-semibold text-[#15803D] hover:bg-[#F0FDF4]"
                ><DashboardText text="Use this description" /></Button>
              </div>
              <p className="mt-3 rounded-lg bg-background p-3 text-sm leading-6 text-muted-foreground">{aiDescription}</p>
              {aiSuggestions.length > 0 && (
                <ul className="mt-3 grid gap-2 text-sm leading-5 text-muted-foreground sm:grid-cols-2">
                  {aiSuggestions.slice(0, 4).map((suggestion, index) => (
                    <li key={`${suggestion}-${index}`} className="rounded-lg bg-[#F8FAF8] p-3">{suggestion}</li>
                  ))}
                </ul>
              )}
            </section>
          )}
        </div>
      </section>

      </div>

      {/* BUTTONS */}

      <div className="mt-auto flex shrink-0 flex-col-reverse gap-2 border-t border-border bg-card px-4 py-3 sm:flex-row sm:justify-end sm:px-6">

        <Button
          type="button"
          disabled={isSaving}
          variant="outline"
          className="w-full rounded-full sm:w-auto"
          onClick={
            onCancel
          }
        ><DashboardText text="Kanselahin" /></Button>

        <Button
          type="button"
          disabled={isSaving}
          className="w-full rounded-full bg-[#22C55E] font-bold hover:bg-[#15803D] sm:w-auto"
          onClick={
            handleSubmit
          }
        >
          {isSaving
            ? copy("Sine-save...")
            : mode === 'edit'
              ? copy("I-save ang Changes")
              : copy("Gumawa ng Listing")}
        </Button>

      </div>

    </div>
  );
}
