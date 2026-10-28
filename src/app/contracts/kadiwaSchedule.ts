const OFFSET = 8 * 60 * 60 * 1000;
/** datetime-local fields always represent Asia/Manila, independent of browser timezone. */
export function toManilaInput(date: Date): string { return new Date(date.getTime() + OFFSET).toISOString().slice(0, 16); }
export function fromManilaInput(value: string): string {
  if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value)) throw new Error('Enter a Philippine date and time.');
  const date = new Date(value + ':00+08:00');
  if (!Number.isFinite(date.getTime()) || toManilaInput(date) !== value) throw new Error('Invalid Philippine date and time.');
  return date.toISOString();
}
export function thirdMondayAndTuesday(year: number, month0: number) {
  if (!Number.isInteger(year) || year < 1900 || year > 9999 || !Number.isInteger(month0) || month0 < 0 || month0 > 11) throw new Error('Invalid month');
  const offset = (1 - new Date(Date.UTC(year, month0, 1)).getUTCDay() + 7) % 7;
  const monday = new Date(Date.UTC(year, month0, 15 + offset) - OFFSET);
  return {monday, tuesday: new Date(monday.getTime() + 86400000), opens: new Date(monday.getTime() - 27 * 86400000), closes: new Date(monday.getTime() - 86400000 - 60000)};
}
export function proposedSchedule(year: number, month0: number) {
  const d = thirdMondayAndTuesday(year, month0);
  return {starts_at: toManilaInput(new Date(d.monday.getTime() + 8 * 3600000)), ends_at: toManilaInput(new Date(d.tuesday.getTime() + 17 * 3600000)), opens_at: toManilaInput(d.opens), closes_at: toManilaInput(d.closes)};
}
