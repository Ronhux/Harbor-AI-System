<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionNotification extends Model
{
    protected $fillable = ['recipient_user_id','negotiation_id','order_id','title','body','read_at'];
    protected $casts = ['read_at' => 'datetime'];
    protected $appends = ['navigation'];

    /** Explicit relationship metadata for the UI. No title matching or database rewrite. */
    public function getNavigationAttribute(): ?array
    {
        if ($this->order_id) return ['type' => 'order', 'record_id' => (int) $this->order_id];
        if ($this->negotiation_id) return ['type' => 'negotiation', 'record_id' => (int) $this->negotiation_id];
        if (preg_match('/\ADemand decision #([1-9][0-9]*) for request #([1-9][0-9]*): (Approved|Rejected)\. /u', (string) $this->body, $match)
            && DemandPublicationReview::whereKey((int) $match[1])->where('demand_request_id', (int) $match[2])->where('decision', $match[3])->exists()
            && DemandRequest::whereKey((int) $match[2])->whereHas('buyer', fn ($q) => $q->where('user_id', $this->recipient_user_id))->exists()) {
            return ['type' => 'buyer_demand', 'record_id' => (int) $match[2]];
        }
        // Legacy admin notices store their demand identity in this fixed server-generated
        // envelope. Decode that identifier, not arbitrary title/keyword text. Keep access
        // scoped to the original recipient and verify that the referenced demand exists.
        if (preg_match('/\ADemand request #([1-9][0-9]*) for .+ has entered the administrative review queue\. Open the demand review queue to view the requesting institution and its commercial terms\.\z/s', (string) $this->body, $match)
            && User::whereKey($this->recipient_user_id)->where('user_type', 'Admin')->where('is_active', true)->exists()
            && DemandRequest::whereKey((int) $match[1])->exists()) {
            return ['type' => 'demand_review', 'record_id' => (int) $match[1]];
        }
        return null;
    }
}
