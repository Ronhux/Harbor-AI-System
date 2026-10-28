import { useCallback } from 'react';
import { useLanguage } from './useLanguage';
import { en } from './en';
import { fil } from './fil';
import { dashboardFil, dashboardEn } from './dashboardCopyMessages';
function collect(a:unknown,b:unknown,result:Record<string,string>) {
  if(!a||!b||typeof a!=='object'||typeof b!=='object')return;
  for(const [key,value] of Object.entries(a)){
    const other=(b as Record<string,unknown>)[key];
    if(typeof value==='string'&&typeof other==='string')result[value]=other;
    else collect(value,other,result);
  }
}
const messages:Record<string,string>={};collect(en,fil,messages);Object.assign(messages,dashboardFil);
/** Call only for source-authored interface copy, never applicant/product content. */
export function translateDashboardCopy(text:string,lang:string):string{return lang==='fil'?messages[text]??text:dashboardEn[text]??text;}
export function useDashboardCopy(){const {lang}=useLanguage();return useCallback((text:string,values?:unknown[])=>{const translated=translateDashboardCopy(text,lang);return values?translated.replace(/\{(\d+)\}/g,(_,index)=>String(values[Number(index)]??'')):translated;},[lang]);}
export function DashboardText({text}:{text:string}){const copy=useDashboardCopy();return copy(text);}
