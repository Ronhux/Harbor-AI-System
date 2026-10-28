<?php
namespace Tests\Feature;
use App\Models\{User,Producer,RegisteredProducer,TransactionNotification};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class FarmerRegistrationReviewTest extends TestCase
{
    use RefreshDatabase;
    private int $sequence=0;
    private function user(string $role): User {
        $i=++$this->sequence;
        return User::create(['email'=>"review-$i@example.test",'first_name'=>'Synthetic','last_name'=>'Farmer','contact_number'=>sprintf('0918%07d',$i),'password_hash'=>bcrypt('test-secret'),'user_type'=>$role,'is_active'=>true]);
    }
    private function applicant(): array {
        $registry=RegisteredProducer::create(['rsbsa_number'=>'02-15-05-001-000777','first_name'=>'Synthetic','last_name'=>'Farmer','sex'=>'Male','barangay'=>'Synthetic Centro','producer_type'=>'Farmer']);
        $this->postJson('/api/register',['first_name'=>'Synthetic','last_name'=>'Farmer','contact_number'=>'09179990777','password'=>'test-secret','user_type'=>'producer','producer_type'=>'Farmer','rsbsa_number'=>$registry->rsbsa_number,'sex'=>'Male','barangay'=>$registry->barangay,'terms_accepted'=>true,'privacy_acknowledged'=>true,'terms_version'=>config('policies.terms.version'),'privacy_policy_version'=>config('policies.privacy_policy.version')])->assertCreated()->assertJsonPath('verification_status','Pending')->assertJsonPath('token',null);
        $producer=Producer::firstOrFail();return [$producer->user,$producer];
    }
    private function url(Producer $p): string {return '/api/admin/producers/'.$p->producer_id.'/verification';}
    private function login(User $user) { $this->app['auth']->forgetGuards(); return $this->postJson('/api/login',['identifier'=>$user->contact_number,'password'=>'test-secret']); }
    public function test_registered_farmer_is_pending_then_approved_with_one_audit_notice_and_login(): void {
        [$user,$p]=$this->applicant();
        $this->login($user)->assertForbidden()->assertJsonPath('registration_status','Pending')->assertJsonMissingPath('token');
        $this->assertDatabaseCount('personal_access_tokens',0);
        $admin=$this->user('Admin');
        $this->actingAs($admin,'sanctum')->patchJson($this->url($p),['verification_status'=>'Verified'])->assertOk();
        $this->patchJson($this->url($p),['verification_status'=>'Verified'])->assertOk();
        $this->assertSame('Verified',$p->fresh()->verification_status);
        $this->assertSame(1,DB::table('admin_activity_logs')->where('action','Approved producer verification')->count());
        $this->assertSame(1,TransactionNotification::where('recipient_user_id',$user->user_id)->where('title','Producer registration approved')->count());
        $token=$this->login($user)->assertOk()->assertJsonPath('user.verification_status','Verified')->json('token');
        $this->app['auth']->forgetGuards();$this->withToken($token)->getJson('/api/producer/dashboard')->assertOk();
        $this->withToken($token)->getJson('/api/notifications')->assertOk()->assertJsonPath('unread_count',1);
        $this->assertSame($user->user_id,$p->fresh()->user_id);
    }
    public function test_rejection_reason_requires_credentials_and_never_grants_a_token(): void {
        [$user,$p]=$this->applicant();$admin=$this->user('Admin');
        $this->actingAs($admin,'sanctum')->patchJson($this->url($p),['verification_status'=>'Rejected'])->assertUnprocessable();
        foreach(['          ','short','!!!!!!!!!!'] as $reason) $this->patchJson($this->url($p),['verification_status'=>'Rejected','reason'=>$reason])->assertUnprocessable();
        $legacy=$user->createToken('legacy')->plainTextToken;
        $reason='Please clarify the submitted identity information.';
        $this->patchJson($this->url($p),['verification_status'=>'Rejected','reason'=>$reason,'internal_notes'=>'Never disclose this internal note'])->assertOk();
        $this->patchJson($this->url($p),['verification_status'=>'Rejected','reason'=>$reason])->assertOk();
        $this->patchJson($this->url($p),['verification_status'=>'Verified'])->assertStatus(409);
        $this->patchJson($this->url($p),['verification_status'=>'Pending'])->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens',0);
        $response=$this->login($user)->assertForbidden()->assertJsonPath('registration_status','Rejected')->assertJsonPath('applicant_reason',$reason)->assertJsonMissingPath('token')->assertJsonMissingPath('user');
        $this->assertStringNotContainsString('Never disclose',$response->getContent());
        $this->assertStringNotContainsString($p->rsbsa_number,$response->getContent());
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $this->postJson('/api/login',['identifier'=>$user->contact_number,'password'=>'wrong'])->assertUnauthorized()->assertJsonMissingPath('applicant_reason');
        $this->postJson('/api/login',['identifier'=>$user->contact_number])->assertUnprocessable()->assertJsonMissingPath('applicant_reason');
        $this->assertSame(1,TransactionNotification::where('recipient_user_id',$user->user_id)->where('title','Producer registration not approved')->count());
        $audit=DB::table('admin_activity_logs')->where('action','Rejected producer verification')->sole();
        $this->assertSame($admin->user_id,$audit->user_id);$this->assertNotNull($audit->created_at);
        $this->assertSame($reason,json_decode($audit->details,true)['applicant_reason']);
        $this->app['auth']->forgetGuards();$this->withToken($legacy)->getJson('/api/producer/dashboard')->assertUnauthorized();
    }
    public function test_wrong_roles_inactive_admin_and_registry_mismatch_cannot_approve(): void {
        [$owner,$p]=$this->applicant();
        foreach(['Buyer','Farmer'] as $role){$actor=$this->user($role);$this->actingAs($actor,'sanctum')->patchJson($this->url($p),['verification_status'=>'Verified'])->assertForbidden();}
        $admin=$this->user('Admin');$admin->update(['is_active'=>false]);
        $this->actingAs($admin,'sanctum')->patchJson($this->url($p),['verification_status'=>'Verified'])->assertForbidden();
        $admin->update(['is_active'=>true]);$owner->update(['first_name'=>'Different']);
        $this->actingAs($admin,'sanctum')->patchJson($this->url($p),['verification_status'=>'Verified'])->assertUnprocessable();
        $this->assertSame('Pending',$p->fresh()->verification_status);
        $this->assertSame(0,TransactionNotification::where('recipient_user_id',$owner->user_id)->count());
    }
    public function test_inbox_failure_rolls_back_decision_audit_and_safe_retry(): void {
        [$owner,$p]=$this->applicant();$admin=$this->user('Admin');
        TransactionNotification::creating(fn()=>throw new \RuntimeException('Synthetic notification failure'));
        try {$this->actingAs($admin,'sanctum')->patchJson($this->url($p),['verification_status'=>'Verified'])->assertStatus(500);}finally{TransactionNotification::flushEventListeners();}
        $this->assertSame('Pending',$p->fresh()->verification_status);
        $this->assertSame(0,DB::table('admin_activity_logs')->where('action','Approved producer verification')->count());
        $this->patchJson($this->url($p),['verification_status'=>'Verified'])->assertOk();
        $this->assertSame(1,TransactionNotification::where('recipient_user_id',$owner->user_id)->where('title','Producer registration approved')->count());
        $this->patchJson($this->url($p),['verification_status'=>'Rejected','reason'=>'A conflicting synthetic decision'])->assertStatus(409);
    }
}
