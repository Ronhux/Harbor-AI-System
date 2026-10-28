/** A later request invalidates earlier results, including their errors/finalizers. */
export class LatestRequest {
  private version=0;
  begin(){const version=++this.version;return ()=>version===this.version;}
  invalidate(){this.version++;}
}
