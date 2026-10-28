<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
/** Limited token scope remains enforced even if its issuing workflow is later disabled. */
final class RestrictFcaOnboardingToken {
 public function handle(Request $request,Closure $next){
  // Inspect bearer scope before auth middleware; never rely on a cached guard user.
  $bearer=$request->bearerToken();$token=$bearer?\Laravel\Sanctum\PersonalAccessToken::findToken($bearer):null;
  if($token&&in_array('fca:onboarding',$token->abilities??[],true)){
    abort_unless(config('fca_president.workflow_enabled')===true&&$token->expires_at?->isFuture()&&$request->route()?->getControllerClass()===\App\Http\Controllers\FcaPresidentController::class,403);
  }
  return $next($request);
 }
}
