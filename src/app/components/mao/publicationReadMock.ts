import type {PublicationRead} from '../../contracts/institutionalPublication';
import {validatePublicationRead} from '../../contracts/institutionalPublication';
import publicationFixtures from './publicationReadFixtures';
export type PublicationScenario='owner_draft'|'owner_pending'|'non_owner'|'unknown_reviewer'|'inactive'|'stale'|'missing_version'|'offers_unknown'|'official'|'documents'|'malformed'|'unavailable'|'network';
export const publicationScenarios:Record<PublicationScenario,string>={owner_draft:'Eligible buyer owned draft',owner_pending:'Owned pending review',non_owner:'Other institution',unknown_reviewer:'Configured reviewer without read policy',inactive:'Inactive owner',stale:'Stale expected version',missing_version:'Expected version missing',offers_unknown:'Retained offers unverified',official:'Official publication rules blocked',documents:'Private documents not validated',malformed:'Malformed capability response',unavailable:'Record unavailable',network:'Network interruption'};
export type PublicationOutcome={kind:'record';record:PublicationRead}|{kind:'error';message:string};
export async function publicationMock(scenario:PublicationScenario,reviewerContext=false):Promise<PublicationOutcome>{
 await new Promise(resolve=>setTimeout(resolve,160));
 if(scenario==='network'||scenario==='unavailable')return {kind:'error',message:`Synthetic ${scenario}; no operation, retry or draft replacement.`};
 try{
   if(!(scenario in publicationScenarios))throw new Error();
   const key=reviewerContext?'unknown_reviewer':scenario;
   const raw=structuredClone((publicationFixtures as Record<string,unknown>)[key]);
   return {kind:'record',record:validatePublicationRead(raw,102)};
 }catch{return {kind:'error',message:'Unsupported or malformed synthetic capability response rejected.'};}
}
