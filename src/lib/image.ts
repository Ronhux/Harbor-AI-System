/**
 * Shared resolver for Laravel public-storage image values.
 *
 * The API returns absolute URLs built from APP_URL (for example
 * `http://localhost/storage/product_images/rice.jpg`). The HarborAI frontend is
 * served from a different origin than APP_URL points at, so those absolute URLs
 * 404 and every card falls back to its placeholder icon. Resolving to a
 * root-relative `/storage/...` path keeps the images on the origin that actually
 * serves Laravel, which is what the producer pages already do.
 */

const absoluteUrlPattern = /^(https?:)?\/\//i;

function laravelBaseUrl(): string {
  const configured = String(import.meta.env.VITE_API_URL ?? '').trim();

  if (configured) return configured.replace(/\/+$/, '').replace(/\/api$/i, '');

  return '';
}

/**
 * Turns any image value returned by the API into a browser-loadable URL,
 * or an empty string when the value cannot address a real image.
 */
export function resolveImageUrl(value: unknown): string {
  if (value === null || value === undefined) return '';

  let candidate = value;

  // Some endpoints return { url } / { path } style objects.
  if (typeof candidate === 'object') {
    const record = candidate as Record<string, unknown>;
    candidate = record.url ?? record.path ?? record.image_url ?? record.imagePath ?? '';
  }

  if (typeof candidate !== 'string') return '';

  let path = candidate.trim();
  if (!path) return '';

  if (path.startsWith('blob:') || path.startsWith('data:')) return path;

  path = path.replace(/\\/g, '/');

  // Absolute URL from the API: keep only the path so it resolves against the
  // current origin instead of whatever host APP_URL happens to name.
  if (absoluteUrlPattern.test(path)) {
    try {
      const parsed = new URL(path, typeof window === 'undefined' ? 'http://localhost' : window.location.origin);
      return `${laravelBaseUrl()}${parsed.pathname}${parsed.search}`;
    } catch {
      return '';
    }
  }

  path = path.replace(/^\/+/, '');
  path = path.replace(/^storage\/app\/public\//i, '');
  path = path.replace(/^app\/public\//i, '');
  path = path.replace(/^public\//i, '');
  path = path.replace(/^storage\//i, '');

  if (!path) return '';

  // Laravel API endpoints are not static assets; never fold them into /storage.
  if (path.startsWith('api/')) return `${laravelBaseUrl()}/${path}`;

  return `${laravelBaseUrl()}/storage/${path}`;
}

/**
 * Collects every usable image URL for a listing, primary image first, dropping
 * duplicates and entries that cannot be resolved.
 */
export function resolveListingImages(
  listing: { images?: unknown; image_url?: unknown; image_path?: unknown } | null | undefined,
): { id?: number | string; url: string; is_primary?: boolean }[] {
  if (!listing) return [];

  const seen = new Set<string>();
  const collected: { id?: number | string; url: string; is_primary?: boolean }[] = [];

  const push = (raw: unknown, id?: number | string) => {
    const url = resolveImageUrl(raw);
    if (!url || seen.has(url)) return;
    seen.add(url);
    collected.push({ id, url });
  };

  const entries = Array.isArray(listing.images) ? listing.images : [];
  entries.forEach((entry: any) => {
    if (!entry) return;
    push(entry.url ?? entry.path, entry.id ?? undefined);
  });

  push(listing.image_url);
  push(listing.image_path);

  if (collected.length > 0) {
    const entriesWithId = entries as any[];
    const primaryIndex = entriesWithId.findIndex((entry) => entry && entry.is_primary);
    const primaryUrl = primaryIndex >= 0 ? resolveImageUrl(entriesWithId[primaryIndex]?.url ?? entriesWithId[primaryIndex]?.path) : '';
    if (primaryUrl) {
      const at = collected.findIndex((image) => image.url === primaryUrl);
      if (at > 0) collected.unshift(...collected.splice(at, 1));
    }
    collected[0].is_primary = true;
  }

  return collected;
}

export default { resolveImageUrl, resolveListingImages };