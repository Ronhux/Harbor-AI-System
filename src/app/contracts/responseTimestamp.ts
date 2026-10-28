/** Accepted source formats only. Preserve spelling/offset; no agreed display timezone is implied. */
export function responseTimestamp(raw: unknown): number {
  if (typeof raw !== 'string' || raw.length>40) return NaN;
  const m=/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2})(?:\.(\d{1,6}))?)?(Z|[+-]\d{2}:\d{2})?)?$/.exec(raw);
  if(!m||m[0]!==raw)return NaN;
  const p=m.slice(1,7).map(v=>Number(v??0)),base=Date.UTC(p[0],p[1]-1,p[2],p[3],p[4],p[5]),d=new Date(base);
  if(d.getUTCFullYear()!==p[0]||d.getUTCMonth()+1!==p[1]||d.getUTCDate()!==p[2]||d.getUTCHours()!==p[3]||d.getUTCMinutes()!==p[4]||d.getUTCSeconds()!==p[5])return NaN;
  const zone=m[8];let offset=0;
  if(zone&&zone!=='Z'){const h=Number(zone.slice(1,3)),min=Number(zone.slice(4));if(h>14||min>59||(h===14&&min!==0))return NaN;offset=(zone[0]==='+'?1:-1)*(h*60+min)*60000;}
  return base-offset+Number((m[7]??'').padEnd(3,'0').slice(0,3));
}
export const hasExplicitOffset=(value:string)=>/(Z|[+-]\d{2}:\d{2})$/.test(value);
/** Comparing a local wall time to an offset timestamp would invent a timezone. */
export function comparableSchedule(values:unknown[]):boolean {
  return values.every(v=>typeof v==='string'&&Number.isFinite(responseTimestamp(v)))&&new Set(values.map(v=>hasExplicitOffset(v as string))).size===1;
}
