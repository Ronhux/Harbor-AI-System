import type {DemandDetailRead} from './institutionalDemandReads';
import {validateDemandDetail} from '../components/mao/institutionalReadValidation';
export interface PublicationRead {
 scope:'owner'|'restricted';execution_enabled:false;demand:DemandDetailRead|null;
 capabilities:{view_own_demand:boolean;view_private_draft:boolean;edit_own_demand:boolean;request_publication_review:boolean;view_reviewer_data:false;perform_reviewer_decision:false;request_publication:false;publish:false};
 version:{current:number|null;expected:number|null;matches:boolean|null};review_read_authority:'not_established';publication_authority:'not_established';blockers:string[];
}
export const publicationBlockers=['review_read_scope_not_established','expected_version_required','version_conflict','retained_offer_state_unverified','retained_offers_prevent_revision','official_publication_rules_unapproved','private_document_validation_unavailable','publication_deadline_and_owner_require_fresh_server_check'] as const;
/** Strict prepared owner-read projection validation; never an authorization or execution API. */
export function validatePublicationRead(raw:unknown,id:number):PublicationRead {
 const fail=()=>{throw new Error('Publication read rejected; no draft or notes replaced.');};
 const record=(v:unknown,keys:string[])=>{if(!v||typeof v!=='object'||Array.isArray(v)||Object.keys(v).length!==keys.length||Object.keys(v).some(k=>!keys.includes(k)))return fail();return v as Record<string,unknown>;};
 const v=record(raw,['scope','execution_enabled','demand','capabilities','version','review_read_authority','publication_authority','blockers']);
 if(!['owner','restricted'].includes(v.scope as string)||v.execution_enabled!==false||v.review_read_authority!=='not_established'||v.publication_authority!=='not_established')return fail();
 const c=record(v.capabilities,['view_own_demand','view_private_draft','edit_own_demand','request_publication_review','view_reviewer_data','perform_reviewer_decision','request_publication','publish']);
 if(Object.values(c).some(value=>typeof value!=='boolean')||['view_reviewer_data','perform_reviewer_decision','request_publication','publish'].some(key=>c[key]!==false))return fail();
 const version=record(v.version,['current','expected','matches']);
 if(!Array.isArray(v.blockers)||v.blockers.length>8||v.blockers.some(b=>!publicationBlockers.includes(b))||new Set(v.blockers).size!==v.blockers.length)return fail();
 if(v.scope==='restricted'){
   if(v.demand!==null||Object.values(c).some(Boolean)||Object.values(version).some(value=>value!==null)||v.blockers.length!==1||v.blockers[0]!=='review_read_scope_not_established')return fail();
 }else{
   const demand=validateDemandDetail(v.demand,'buyer_owner',id);
   if(version.current!==demand.demand.mao_version||c.view_own_demand!==true||c.view_private_draft!==(demand.demand.mao_state==='draft'))return fail();
   if(version.expected!==null&&(typeof version.expected!=='number'||!Number.isSafeInteger(version.expected)||version.expected<1))return fail();
   const matched=version.expected===null?null:version.current===version.expected;
   if(version.matches!==matched||c.request_publication_review!==(matched===true&&demand.demand.mao_state==='draft'))return fail();
   if(c.edit_own_demand&&(matched!==true||!['draft','pending_review','open','under_review'].includes(demand.demand.mao_state)||v.blockers.includes('retained_offer_state_unverified')||v.blockers.includes('retained_offers_prevent_revision')))return fail();
   if(!v.blockers.includes('review_read_scope_not_established')||!v.blockers.includes('publication_deadline_and_owner_require_fresh_server_check'))return fail();
   if(matched!==true&&!v.blockers.includes(matched===null?'expected_version_required':'version_conflict'))return fail();
 }
 return structuredClone(v) as unknown as PublicationRead;
}
