<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class WorkspaceSessionTest extends TestCase {
 use RefreshDatabase;
 public function test_session_requires_authentication():void {$this->getJson('/api/session')->assertUnauthorized();}
 public function test_session_reports_authenticated_role_without_private_fields():void {
  foreach(['Buyer','KADIWA Manager'] as $role){$user=User::create(['first_name'=>'Synthetic','last_name'=>'Session','email'=>uniqid().'@example.test','password_hash'=>bcrypt('test-only'),'user_type'=>$role,'is_active'=>true]);
   $this->actingAs($user)->getJson('/api/session')->assertOk()->assertExactJson(['user_type'=>$role])->assertHeader('Cache-Control','no-store, private');}
 }
}
