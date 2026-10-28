type StorageLike=Pick<Storage,'getItem'|'setItem'|'removeItem'>;
type Pending={hash:string;key:string};
/** Retain only a fingerprint/UUID per account until a successful server acknowledgement. */
export class DemandSubmissionRetry {
  private memory=new Map<string,Pending>();
  private storage?:StorageLike;
  private generate:()=>string;
  constructor(storage?:StorageLike,generate:()=>string=()=>crypto.randomUUID()){this.storage=storage;this.generate=generate;}
  private store():StorageLike|undefined {try{return this.storage??(typeof sessionStorage==='undefined'?undefined:sessionStorage);}catch{return undefined;}}
  async keyFor(account:string,payload:Record<string,unknown>):Promise<string>{
    const ordered=Object.fromEntries(Object.entries(payload).sort(([a],[b])=>a.localeCompare(b)));
    const digest=await crypto.subtle.digest('SHA-256',new TextEncoder().encode(JSON.stringify(ordered)));
    const hash=Array.from(new Uint8Array(digest),b=>b.toString(16).padStart(2,'0')).join('');
    let prior=this.memory.get(account);
    if(!prior){try{const value=JSON.parse(this.store()?.getItem('harbor:demand-retry:'+account)??'null');if(value&&typeof value.hash==='string'&&typeof value.key==='string'&&/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(value.key))prior=value;}catch{/* In-memory retry remains available. */}}
    const pending=prior?.hash===hash?prior:{hash,key:this.generate()};this.memory.set(account,pending);
    try{this.store()?.setItem('harbor:demand-retry:'+account,JSON.stringify(pending));}catch{/* Restricted browser storage. */}
    return pending.key;
  }
  complete(account:string){this.memory.delete(account);try{this.store()?.removeItem('harbor:demand-retry:'+account);}catch{/* Restricted storage. */}}
}
