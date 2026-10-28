import { useEffect, useRef, useState } from 'react';
import { ChevronLeft, ChevronRight, Package } from 'lucide-react';
import { resolveListingImages } from '../../../lib/image';

type ListingImageSource = {
  images?: unknown;
  image_url?: unknown;
  image_path?: unknown;
};

type ListingImageCarouselProps = {
  listing: ListingImageSource | null | undefined;
  alt: string;
};

function CarouselImage({ src, alt }: { src: string; alt: string }) {
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    setFailed(false);
  }, [src]);

  if (failed) {
    return (
      <div
        role="img"
        aria-label={`${alt} could not be loaded`}
        className="flex aspect-[16/10] w-full items-center justify-center bg-[#0F9488]/10 text-[#0F9488]"
      >
        <Package className="h-12 w-12" aria-hidden="true" />
      </div>
    );
  }

  return (
    <img
      src={src}
      alt={alt}
      className="aspect-[16/10] w-full object-cover"
      onError={() => setFailed(true)}
    />
  );
}

/** Shared looping carousel for producer listing photos. */
export default function ListingImageCarousel({ listing, alt }: ListingImageCarouselProps) {
  const imageCandidates = resolveListingImages(listing).map((image) => image.url);
  const imageCount = imageCandidates.length;
  const imageSignature = imageCandidates.join('|');
  const [viewingImageIndex, setViewingImageIndex] = useState(0);
  const [trackIndex, setTrackIndex] = useState(imageCount > 1 ? 1 : 0);
  const [settling, setSettling] = useState(false);
  const settleTo = useRef<number | null>(null);
  const settleTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const clearSettleTimer = () => {
    if (settleTimer.current !== null) {
      window.clearTimeout(settleTimer.current);
      settleTimer.current = null;
    }
  };

  const cancelSettle = () => {
    settleTo.current = null;
    clearSettleTimer();
  };

  const applySettle = (target: number) => {
    settleTo.current = null;
    clearSettleTimer();
    setSettling(true);
    setTrackIndex(target);
  };

  const armSettleFallback = (target: number) => {
    clearSettleTimer();
    settleTimer.current = window.setTimeout(() => {
      settleTimer.current = null;
      if (settleTo.current === target) applySettle(target);
    }, 400);
  };

  useEffect(() => {
    cancelSettle();
    setViewingImageIndex(0);
    setTrackIndex(imageCount > 1 ? 1 : 0);
    setSettling(false);
  }, [imageSignature, imageCount]);

  useEffect(() => {
    if (settling) setSettling(false);
  }, [settling]);

  useEffect(() => () => clearSettleTimer(), []);

  const showNext = () => {
    if (imageCount < 2) return;
    cancelSettle();
    setSettling(false);

    if (viewingImageIndex >= imageCount - 1) {
      setViewingImageIndex(0);
      settleTo.current = 1;
      setTrackIndex(imageCount + 1);
      armSettleFallback(1);
      return;
    }

    const nextIndex = viewingImageIndex + 1;
    setViewingImageIndex(nextIndex);
    setTrackIndex(nextIndex + 1);
  };

  const showPrev = () => {
    if (imageCount < 2) return;
    cancelSettle();
    setSettling(false);

    if (viewingImageIndex <= 0) {
      setViewingImageIndex(imageCount - 1);
      settleTo.current = imageCount;
      setTrackIndex(0);
      armSettleFallback(imageCount);
      return;
    }

    const previousIndex = viewingImageIndex - 1;
    setViewingImageIndex(previousIndex);
    setTrackIndex(previousIndex + 1);
  };

  const handleTrackTransitionEnd = (event: React.TransitionEvent<HTMLDivElement>) => {
    if (event.target !== event.currentTarget || event.propertyName !== 'transform') return;
    if (settleTo.current !== null) applySettle(settleTo.current);
  };

  const slides = imageCount > 1
    ? [imageCandidates[imageCount - 1], ...imageCandidates, imageCandidates[0]]
    : imageCandidates;

  const realIndexAt = (position: number) => {
    if (imageCount < 2) return position;
    if (position === 0) return imageCount - 1;
    if (position === imageCount + 1) return 0;
    return position - 1;
  };

  return (
    <div className="min-w-0">
      <div className="overflow-hidden rounded-xl bg-[#F1EEE6] shadow-sm">
        {imageCount === 0 ? (
          <div
            role="img"
            aria-label={`${alt} has no uploaded image`}
            className="flex aspect-[16/10] w-full items-center justify-center text-[#0F9488]"
          >
            <Package className="h-16 w-16" aria-hidden="true" />
          </div>
        ) : (
          <div
            className={`flex w-full ${settling ? '' : 'transition-transform duration-300 ease-out'}`}
            style={{ transform: `translateX(-${trackIndex * 100}%)` }}
            onTransitionEnd={handleTrackTransitionEnd}
          >
            {slides.map((url, position) => (
              <div
                key={`${position}-${url}`}
                aria-hidden={position !== trackIndex}
                className="w-full shrink-0"
              >
                <CarouselImage
                  src={url}
                  alt={`${alt}, image ${realIndexAt(position) + 1} of ${imageCount}`}
                />
              </div>
            ))}
          </div>
        )}
      </div>

      {imageCount > 1 && (
        <div className="mt-3 flex items-center justify-between gap-3">
          <button
            type="button"
            className="inline-flex min-h-11 items-center gap-1 rounded-full border border-[#D8D3C4] px-3 py-2 text-sm font-bold text-foreground"
            onClick={showPrev}
            aria-label="Previous product image"
          >
            <ChevronLeft className="h-4 w-4" aria-hidden="true" />
            Previous
          </button>
          <span className="text-sm text-muted-foreground" aria-live="polite">
            {viewingImageIndex + 1} / {imageCount}
          </span>
          <button
            type="button"
            className="inline-flex min-h-11 items-center gap-1 rounded-full border border-[#D8D3C4] px-3 py-2 text-sm font-bold text-foreground"
            onClick={showNext}
            aria-label="Next product image"
          >
            Next
            <ChevronRight className="h-4 w-4" aria-hidden="true" />
          </button>
        </div>
      )}
    </div>
  );
}
