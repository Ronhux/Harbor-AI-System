<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
final class EnsureFcaPresidentEnabled {public function handle(Request $r,Closure $next){abort_unless(config('fca_president.workflow_enabled')===true,404);return $next($r);}}
