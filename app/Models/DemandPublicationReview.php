<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DemandPublicationReview extends Model {
    protected $fillable = ['demand_request_id', 'actor_user_id', 'decision', 'reason'];
}
