/** Inactive interpretation of Laravel status/message/errors, not new server error codes. */
export interface PreparedFailure {kind:'validation'|'authentication'|'forbidden'|'missing'|'conflict'|'unavailable'|'unexpected';status:number;message:string;fields:Record<string,string[]>;unmappedFieldCount:number;automaticRetry:false}
export function normalizePreparedFailure(status:number,raw:unknown,knownFields:readonly string[]=[]):PreparedFailure {
  const categories:Record<number,PreparedFailure['kind']>={401:'authentication',403:'forbidden',404:'missing',409:'conflict',422:'validation',503:'unavailable'};
  const result:PreparedFailure={kind:categories[status]??'unexpected',status,message:'Synthetic operation failed; local draft and notes retained.',fields:{},unmappedFieldCount:0,automaticRetry:false};
  if(!Number.isSafeInteger(status)||status<400||status>599)throw new Error('Malformed prepared error status.');
  if(!raw||typeof raw!=='object'||Array.isArray(raw))throw new Error('Malformed prepared error envelope.');
  const body=raw as Record<string,unknown>;
  if(Object.keys(body).some(key=>!['message','errors'].includes(key))||typeof body.message!=='string'||body.message.length>4000)throw new Error('Unsupported prepared error envelope.');
  // Never pass arbitrary server diagnostic text/SQL/identifiers to a display or log.
  const messages:Record<PreparedFailure['kind'],string>={validation:'Synthetic server validation rejected the operation.',authentication:'Synthetic authentication failed.',forbidden:'Synthetic permission denied; restricted data must be withheld.',missing:'Synthetic record unavailable.',conflict:'Synthetic conflict; compare current versions and retained operation intent. The server supplies no stable conflict subtype.',unavailable:'Synthetic dependency or policy is unavailable.',unexpected:'Synthetic unexpected response; no automatic retry.'};
  result.message=messages[result.kind];
  if(body.errors!==undefined){if(status!==422||!body.errors||typeof body.errors!=='object'||Array.isArray(body.errors))throw new Error('Malformed prepared field errors.');
    const entries=Object.entries(body.errors);if(entries.length>100)throw new Error('Excessive prepared field errors.');
    for(const [key,value] of entries){if(!Array.isArray(value)||value.length<1||value.length>10||value.some(v=>typeof v!=='string'||v.length>1000))throw new Error('Malformed prepared field error.');
      if(knownFields.includes(key))result.fields[key]=['Server validation rejected this field (synthetic).'];else result.unmappedFieldCount++;
    }
  }
  return result;
}
/** Replay success uses the ordinary success DTO. No undocumented replay flag is invented. */
export const preparedReplayNotice='A matching retained key/payload may return the current record; a response alone does not prove replay. No automatic retry or new key is generated.';
