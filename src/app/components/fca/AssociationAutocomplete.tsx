import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { Check, Loader2, Search, X } from 'lucide-react';
import { request } from '../../../lib/api';
import { useLanguage } from '../../i18n/useLanguage';

/**
 * Searchable association selector backed by the real FCA directory API.
 *
 * WHY THIS IS NOT A LOCAL LIST
 * ----------------------------
 * The previous page picked from a JSON file committed to the frontend. That list can drift from
 * the database and, worse, looks like an authoritative registry. This component queries
 * `GET /api/fca/associations`, which reads `fca_registry`, so once an authorized import lands the
 * same interface serves the live records with no code change.
 *
 * HONESTY
 * -------
 * Every row carries its real `directory_verification_status`. A listed president is a name in a
 * source directory, not a verified identity, and the component says so next to the selection. It
 * never labels a pending record as approved.
 */

/**
 * One association exactly as the PUBLIC search returns it.
 *
 * The public endpoint deliberately exposes only an identifier, a name and an address. It does
 * NOT return the listed president, a verification status or a provenance key, so none of
 * those exist on this type. A page that wanted to imply a record was confirmed has no field
 * with which to do it.
 */
export type AssociationRecord = {
  id: number;
  official_name: string;
  address: string;
};

type Props = {
  value: AssociationRecord | null;
  onChange: (record: AssociationRecord | null) => void;
  label?: string;
  disabled?: boolean;
};

const DEBOUNCE_MS = 220;

export default function AssociationAutocomplete({ value, onChange, label, disabled = false }: Props) {
  const { lang } = useLanguage();
  const tr = (en: string, fil: string) => (lang === 'fil' ? fil : en);
  const [term, setTerm] = useState('');
  const [rows, setRows] = useState<AssociationRecord[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(0);
  const boxRef = useRef<HTMLDivElement | null>(null);
  const listId = useId();

  // Debounced query. An empty term returns the first page so the list is useful immediately
  // rather than showing an empty box the applicant must guess their way out of.
  useEffect(() => {
    let cancelled = false;
    const handle = setTimeout(async () => {
      setLoading(true);
      setError('');
      try {
        const query = term.trim() ? `?q=${encodeURIComponent(term.trim())}` : '';
        const result = await request(`/api/fca/associations${query}`) as { data?: AssociationRecord[] };
        if (!cancelled) {
          setRows(result?.data ?? []);
          setActive(0);
        }
      } catch (e) {
        if (!cancelled) {
          setError(publicErrorMessage(e));
          setRows([]);
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    }, DEBOUNCE_MS);

    return () => { cancelled = true; clearTimeout(handle); };
  }, [term]);

  // Close on outside click.
  useEffect(() => {
    if (!open) return;
    const onDown = (event: MouseEvent) => {
      if (boxRef.current && !boxRef.current.contains(event.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', onDown);
    return () => document.removeEventListener('mousedown', onDown);
  }, [open]);

  const selectedLabel = useMemo(
    () => (value ? `${value.official_name} — ${value.address}` : ''),
    [value],
  );

  const clear = () => {
    onChange(null);
    setTerm('');
    setOpen(true);
  };

  const choose = (row: AssociationRecord) => {
    onChange(row);
    setTerm('');
    setOpen(false);
  };

  const onKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'ArrowDown') { event.preventDefault(); setOpen(true); setActive((i) => Math.min(i + 1, rows.length - 1)); }
    else if (event.key === 'ArrowUp') { event.preventDefault(); setActive((i) => Math.max(i - 1, 0)); }
    else if (event.key === 'Enter') {
      if (open && rows[active]) { event.preventDefault(); choose(rows[active]); }
    } else if (event.key === 'Escape') { setOpen(false); }
  };

  return (
    <div className="space-y-2">
      <label className="block text-sm font-semibold text-foreground" htmlFor={`${listId}-input`}>
        {label ?? tr('Association or cooperative', 'Samahan o kooperatiba')}
      </label>

      {value ? (
        <div className="flex items-start gap-2 rounded-xl border-2 border-[#0F9488] bg-[#F0FDFA] p-3">
          <Check className="mt-0.5 h-4 w-4 shrink-0 text-[#0F9488]" aria-hidden="true" />
          <div className="min-w-0 flex-1">
            <p className="font-semibold text-foreground">{value.official_name}</p>
            <p className="text-sm text-muted-foreground">{value.address}</p>
            <p className="mt-1 text-xs font-medium text-[#B45309]">
              {tr(
                'Directory record awaiting independent confirmation. Selecting it does not verify you or the association.',
                'Rekord ng direktoryo na naghihintay ng malayang kumpirmasyon. Ang pagpili nito ay hindi beripikado sa iyo o sa samahan.',
              )}
            </p>
          </div>
          <button type="button" onClick={clear} disabled={disabled}
            className="shrink-0 rounded border border-[#B9CBD2] px-2 py-1 text-xs font-semibold text-foreground disabled:opacity-50"
            aria-label="Clear selected association">
            <X className="h-3.5 w-3.5" aria-hidden="true" />
          </button>
        </div>
      ) : (
        <div className="relative" ref={boxRef}>
          <div className="relative">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
            <input
              id={`${listId}-input`}
              type="text"
              role="combobox"
              aria-expanded={open}
              aria-controls={listId}
              aria-autocomplete="list"
              autoComplete="off"
              disabled={disabled}
              value={term}
              placeholder={tr('Start typing an association or place', 'Magsimulang pagtaon ng samahan o lugar')}
              onChange={(e) => { setTerm(e.target.value); setOpen(true); }}
              onFocus={() => setOpen(true)}
              onKeyDown={onKeyDown}
              className="w-full rounded-xl border-2 border-[#D8E4E8] bg-card py-2.5 pl-9 pr-9 text-foreground outline-none focus:border-[#0F9488]"
            />
            {loading && <Loader2 className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-[#0F9488]" aria-hidden="true" />}
          </div>

          {open && (
            <ul id={listId} role="listbox"
              className="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-[#D8E4E8] bg-card shadow-lg">
              {error && <li className="px-3 py-2 text-sm text-red-700">{error}</li>}
              {!error && rows.length === 0 && !loading && (
                <li className="px-3 py-3 text-sm text-muted-foreground">
                  {tr(
                    `No association matches “${term}”. Check the spelling or search by barangay.`,
                    `Walang samahang tumutugma sa “${term}”. Suriin ang baybay, o maghanap ayon sa barangay.`,
                  )}
                </li>
              )}
              {rows.map((row, index) => (
                <li key={row.id} role="option" aria-selected={index === active}
                  onMouseEnter={() => setActive(index)}
                  onMouseDown={(e) => { e.preventDefault(); choose(row); }}
                  className={`cursor-pointer px-3 py-2 ${index === active ? 'bg-[#F0FDFA]' : ''}`}>
                  <p className="text-sm font-semibold text-foreground">{row.official_name}</p>
                  <p className="text-xs text-muted-foreground">{row.address}</p>

                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  );
}

/**
 * Turns any failure into one short, plain sentence.
 *
 * The previous version surfaced the raw backend message, which is how an applicant came to see
 * "Unauthenticated." for an operation that is deliberately open to everyone. Framework text such
 * as "Too Many Attempts" or a JSON exception is no more useful, so none of it reaches the UI.
 */
function publicErrorMessage(failure: unknown): string {
  const raw = failure instanceof Error ? failure.message : String(failure ?? '');
  const text = raw.toLowerCase();

  if (text.includes('too many') || text.includes('429')) {
    return 'Too many searches in a short time. Please wait a moment and try again.';
  }
  if (text.includes('unauthenticated') || text.includes('unauthorized') || text.includes('401')) {
    return 'The association list is temporarily unavailable. Please try again in a moment.';
  }
  if (text.includes('validation') || text.includes('422')) {
    return 'Enter a shorter search term.';
  }
  if (text.includes('network') || text.includes('failed to fetch') || text.includes('load')) {
    return 'Could not reach the association list. Check your connection and try again.';
  }

  return 'The association list is temporarily unavailable. Please try again in a moment.';
}
