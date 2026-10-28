import type { InstitutionalDemandItem } from '../../contracts/institutionalDemands';

export type PreviewActor = 'producer' | 'buyer' | 'reviewer';
export type PreviewModule = 'fca' | 'kadiwa' | 'demands';
export const previewModules: Record<PreviewActor, readonly PreviewModule[]> = {
  producer: ['fca', 'kadiwa', 'demands'], buyer: ['demands'], reviewer: ['fca', 'kadiwa', 'demands'],
};
export function selectPreviewModule(actor: PreviewActor, module: PreviewModule): PreviewModule {
  return previewModules[actor].includes(module) ? module : previewModules[actor][0];
}
export function isDraftDecimal(value: string, digits: number, scale: number, positive = false): boolean {
  return new RegExp(`^\\d{1,${digits}}(?:\\.\\d{1,${scale}})?$`).exec(value)?.[0] === value
    && (!positive || /[1-9]/.test(value));
}
export function quantityWithinRemaining(quantity: string, remaining: string): boolean {
  if (!isDraftDecimal(quantity, 9, 3, true) || !isDraftDecimal(remaining, 9, 3)) return false;
  const milli = (value: string) => { const [whole, fraction = ''] = value.split('.'); return BigInt(whole) * 1000n + BigInt(fraction.padEnd(3, '0')); };
  return milli(quantity) <= milli(remaining);
}
/** Mirrors basic prepared input constraints; not server eligibility or submission validation. */
export function demandItemIssues(items: readonly InstitutionalDemandItem[]): string[] {
  const issues: string[] = [];
  if (items.length < 1 || items.length > 20) issues.push('Use between 1 and 20 commodities.');
  const seen = new Set<string>();
  items.forEach((row, index) => {
    const label = `Commodity ${index + 1}`;
    if (!row.commodity.trim() || !row.category.trim() || !row.specifications.trim()) issues.push(`${label}: enter the name, category and specifications.`);
    if (!isDraftDecimal(row.quantity, 9, 3, true)) issues.push(`${label}: quantity must be positive, with up to 9 whole and 3 decimal digits.`);
    if (row.max_unit_price && !isDraftDecimal(row.max_unit_price, 10, 2)) issues.push(`${label}: price allows up to 10 whole and 2 decimal digits.`);
    const key = [row.commodity, row.category, row.variety ?? ''].map(value => value.trim().toLowerCase()).join('|');
    if (row.commodity.trim() && row.category.trim() && seen.has(key)) issues.push(`${label}: this commodity, category and variety already appear.`);
    seen.add(key);
  });
  return issues;
}
