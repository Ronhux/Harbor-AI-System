type Notice={title?:unknown;body?:unknown;navigation?:{type?:unknown;record_id?:unknown}|null};
type Copy=(text:string,values?:unknown[])=>string;
/** Translate fixed server envelopes; embedded names and applicant remarks stay verbatim. */
export function notificationBody(item:Notice,copy:Copy):string {
  const body=typeof item.body==='string'?item.body:'';
  const decision=/^Demand decision #(\d+) for request #(\d+): (Approved|Rejected)\. ([\s\S]*)$/.exec(body);
  if(item.navigation?.type==='buyer_demand'&&decision&&Number(decision[2])===item.navigation.record_id)return copy('Demand decision #{0} for request #{1}: {2}. {3}',[decision[1],decision[2],copy(decision[3]),decision[4]]);
  const queue=/^Demand request #(\d+) for ([\s\S]+) has entered the administrative review queue\. Open the demand review queue to view the requesting institution and its commercial terms\.$/.exec(body);
  if(item.navigation?.type==='demand_review'&&queue&&Number(queue[1])===item.navigation.record_id)return copy('Demand request #{0} for {1} is awaiting review. Open Demand Approvals to view its details.',[queue[1],queue[2]]);
  const rejection=/^Your Producer registration was not approved\. Reason: ([\s\S]*) Please contact the Municipal Agriculture Office for clarification\.$/.exec(body);
  if(item.title==='Producer registration not approved'&&rejection)return copy('Your Producer registration was not approved. Reason: {0} Please contact the Municipal Agriculture Office for clarification.',[rejection[1]]);
  return copy(body);
}
