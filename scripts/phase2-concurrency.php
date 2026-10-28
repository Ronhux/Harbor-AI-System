<?php
/** Opt-in multi-process API races. No migration, database creation, reset or cleanup. */
require dirname(__DIR__).'/vendor/autoload.php';
use App\Models\{User,Producer,Buyer,RegisteredProducer,DemandRequest,ProductListing,Negotiation,Order,TransactionNotification};
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
function demand(bool $ok,string $message):void { if(!$ok)throw new RuntimeException($message); }
function bootQa(){
    demand(getenv('APP_ENV')==='testing' && getenv('HARBORAI_CONCURRENCY_APPROVED')==='1','BLOCKED: explicit disposable-database approval and testing environment are required.');
    \Tests\Support\FcaDatabaseGuard::verify('repeat');
    $app=require dirname(__DIR__).'/bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    demand(config('database.default')==='mysql' && DB::connection()->getDatabaseName()==='harborai_fca_regression_disposable','Resolved database guard failed.');
    foreach(DB::select("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'") as $row)demand($row->ENGINE==='InnoDB','InnoDB required for every fixture table.');
    demand(\Illuminate\Support\Facades\Schema::hasTable('demand_publication_reviews'),'A separately migrated disposable schema is required.');
    config(['cache.default'=>'array','queue.default'=>'sync','mail.default'=>'array','broadcasting.default'=>'null']);
    \Illuminate\Support\Facades\Http::preventStrayRequests();return $app;
}
function api($app,array $job):array {
    $app['auth']->forgetGuards();
    $server=['HTTP_ACCEPT'=>'application/json','CONTENT_TYPE'=>'application/json'];
    if($job['token']??null)$server['HTTP_AUTHORIZATION']='Bearer '.$job['token'];
    if(isset($job['idempotency_key']))$server['HTTP_IDEMPOTENCY_KEY']=$job['idempotency_key'];
    $request=Request::create($job['uri'],$job['method'],[],[],[],$server,json_encode($job['body']??[]));
    $kernel=$app->make(\Illuminate\Contracts\Http\Kernel::class);$response=$kernel->handle($request);$data=json_decode($response->getContent(),true)??[];$kernel->terminate($request,$response);
    return ['status'=>$response->getStatusCode(),'token_issued'=>!empty($data['token']),'data'=>$data['data']??null];
}
try {
    $app=bootQa();
    if(($argv[1]??'')==='--worker'){
        $jobPath=realpath($argv[2]??'');$root=realpath(storage_path('app/private/phase2-concurrency'));
        demand($jobPath && $root && str_starts_with($jobPath,$root.DIRECTORY_SEPARATOR),'Worker job outside QA artifact root.');
        $job=json_decode(file_get_contents($jobPath),true,512,JSON_THROW_ON_ERROR);file_put_contents($jobPath.'.ready','ready');$until=microtime(true)+15;
        while(!is_file(dirname($jobPath).'/release')){demand(microtime(true)<$until,'Barrier timeout.');usleep(20000);}
        $result=api($app,$job);file_put_contents($jobPath.'.result',json_encode(['status'=>$result['status'],'token_issued'=>$result['token_issued']]));exit;
    }
    demand(($argv[1]??'')==='--run','Use --run only against the already approved and migrated disposable instance.');
    $run=bin2hex(random_bytes(6));$artifactRoot=storage_path('app/private/phase2-concurrency/'.$run);mkdir($artifactRoot,0700,true);
    $counter=0;$newUser=function(string $role)use(&$counter,$run){$i=++$counter;$u=User::create(['email'=>"phase2-$run-$i@example.test",'first_name'=>'Synthetic','last_name'=>'Farmer','contact_number'=>'0919'.str_pad((string)random_int(1,9999999),7,'0',STR_PAD_LEFT),'password_hash'=>bcrypt('phase2-test-only'),'user_type'=>$role,'is_active'=>true]);app(\App\Services\Policies\PolicyAcceptanceService::class)->recordActiveAcceptances($u);return $u;};
    $admin=$newUser('Admin');$secondAdmin=$newUser('Admin');$farmer=$newUser('Farmer');$buyerUser=$newUser('Buyer');
    $registry=RegisteredProducer::create(['rsbsa_number'=>'02-15-05-001-'.str_pad((string)random_int(1,999999),6,'0',STR_PAD_LEFT),'first_name'=>'Synthetic','last_name'=>'Farmer','producer_type'=>'Farmer']);
    $producer=Producer::create(['user_id'=>$farmer->user_id,'registered_producer_id'=>$registry->registry_id,'rsbsa_number'=>$registry->rsbsa_number,'producer_type'=>'Farmer','verification_status'=>'Pending']);
    $buyer=Buyer::create(['user_id'=>$buyerUser->user_id,'organization_name'=>'Synthetic Phase 2 QA','verification_status'=>'Verified','buyer_type'=>'Institution']);
    $tokens=[];foreach([$admin,$secondAdmin,$farmer,$buyerUser] as $u)$tokens[$u->user_id]=$u->createToken('phase2-disposable-only')->plainTextToken;
    $job=fn(User $u,string $method,string $uri,array $body)=>['method'=>$method,'uri'=>$uri,'body'=>$body,'token'=>$tokens[$u->user_id]];
    $race=function(string $name,array $jobs)use($artifactRoot){$directory=$artifactRoot.'/'.$name;mkdir($directory,0700);$processes=[];$paths=[];foreach($jobs as $i=>$data){$path=$directory.'/job'.$i.'.json';file_put_contents($path,json_encode($data));$paths[]=$path;$process=new Process([PHP_BINARY,__FILE__,'--worker',$path],dirname(__DIR__));$process->setTimeout(30);$process->disableOutput();$process->start();$processes[]=$process;}
        $until=microtime(true)+20;while(count(array_filter($paths,fn($p)=>is_file($p.'.ready')))!==count($paths)){demand(microtime(true)<$until,'Workers did not reach barrier.');usleep(20000);}touch($directory.'/release');$results=[];foreach($processes as $i=>$process){$process->wait();demand($process->isSuccessful()&&is_file($paths[$i].'.result'),'Worker failed; no success claim.');$results[]=json_decode(file_get_contents($paths[$i].'.result'),true);unlink($paths[$i]);}return $results;};
    $out=[];$path='/api/admin/producers/'.$producer->producer_id.'/verification';
    $r=$race('producer-decision',[$job($admin,'PATCH',$path,['verification_status'=>'Verified']),$job($secondAdmin,'PATCH',$path,['verification_status'=>'Rejected','reason'=>'Synthetic concurrency decision only.'])]);$codes=array_column($r,'status');sort($codes);demand($codes===[200,409],'Producer race must have exactly one winner.');demand(TransactionNotification::where('recipient_user_id',$farmer->user_id)->count()===1,'Duplicate farmer notice.');$out['producer-decision']=$r;
    // Separate fresh applicant for login-versus-approval; terminal states are never reopened.
    $loginUser=$newUser('Farmer');$loginProducer=Producer::create(['user_id'=>$loginUser->user_id,'rsbsa_number'=>$registry->rsbsa_number,'producer_type'=>'Farmer','verification_status'=>'Pending']);
    $loginJob=['method'=>'POST','uri'=>'/api/login','body'=>['identifier'=>$loginUser->contact_number,'password'=>'phase2-test-only']];
    $r=$race('login-during-review',[$job($admin,'PATCH','/api/admin/producers/'.$loginProducer->producer_id.'/verification',['verification_status'=>'Verified']),$loginJob]);demand($r[0]['status']===200 && in_array($r[1]['status'],[200,403],true) && $r[1]['token_issued']===($r[1]['status']===200),'Login/approval result inconsistent.');$out['login-during-review']=$r;
    $demand=DemandRequest::create(['buyer_id'=>$buyer->buyer_id,'product_name'=>'Synthetic rice','quantity_needed'=>5,'unit'=>'kg','status'=>'Active','deadline'=>now()->addDays(10)]);$pending=$demand->publicationReviews()->create(['actor_user_id'=>$buyerUser->user_id,'decision'=>'Pending Review']);
    $path='/api/admin/demands/'.$demand->request_id.'/review';$r=$race('demand-decision',[$job($admin,'POST',$path,['decision'=>'Approved','expected_review_id'=>$pending->id]),$job($secondAdmin,'POST',$path,['decision'=>'Rejected','reason'=>'Synthetic review reason.','expected_review_id'=>$pending->id])]);$codes=array_column($r,'status');sort($codes);demand($codes===[200,409]&&$demand->publicationReviews()->count()===2,'Demand review race inconsistent.');demand(TransactionNotification::where('recipient_user_id',$buyerUser->user_id)->count()===1,'Duplicate demand decision notice.');$out['demand-decision']=$r;
    $producer->refresh();$verified=$loginProducer;
    $listing=ProductListing::create(['producer_id'=>$verified->producer_id,'product_name'=>'Synthetic QA rice','quantity_available'=>100,'quantity'=>100,'current_price_per_unit'=>20,'price_per_unit'=>20,'unit_of_measure'=>'kg','unit'=>'kg','status'=>'Active']);
    $verifiedToken=$loginUser->createToken('phase2-disposable-only')->plainTextToken;
    $created=api($app,$job($buyerUser,'POST','/api/buyer/negotiations',['listing_id'=>$listing->listing_id,'quantity'=>5,'unit_price'=>20]));demand($created['status']===201,'Negotiation fixture creation failed.');$id=$created['data']['id'];
    $confirm=['method'=>'POST','uri'=>'/api/producer/negotiations/'.$id.'/respond','token'=>$verifiedToken,'body'=>['action'=>'confirm','version'=>1]];
    $r=$race('repeated-confirmation',[$confirm,$confirm]);demand(array_column($r,'status')===[200,200],'Confirmation retries failed.');$orders=Order::where('negotiation_id',$id)->get();demand($orders->count()===1,'Duplicate negotiation order.');demand((float)$listing->fresh()->quantity_available===95.0,'Inventory reserved more than once.');$out['repeated-confirmation']=$r;
    $order=$orders->sole();$cancel=['method'=>'PATCH','uri'=>'/api/producer/orders/'.$order->order_id,'token'=>$verifiedToken,'body'=>['status'=>'Cancelled']];$reject=$cancel;$reject['body']=['status'=>'Rejected'];$r=$race('order-terminal-change',[$cancel,$reject]);$codes=array_column($r,'status');sort($codes);demand($codes===[200,409]&&(float)$listing->fresh()->quantity_available===100.0,'Order race duplicated inventory release.');$out['order-terminal-change']=$r;
    $payload=['product_name'=>'Synthetic separate submissions','quantity_needed'=>1,'unit'=>'kg','deadline'=>now()->addDays(10)->toDateString()];$before=DemandRequest::where('buyer_id',$buyer->buyer_id)->count();$submit=$job($buyerUser,'POST','/api/buyer/demands',$payload);$r=$race('separate-buyer-submissions',[$submit,$submit]);demand(array_column($r,'status')===[201,201]&&DemandRequest::where('buyer_id',$buyer->buyer_id)->count()===$before+2,'Distinct POST submission contract changed.');$out['separate-buyer-submissions']=$r;
    demand(\App\Services\Demand\BuyerDemandSubmission::available(),'Reviewed idempotency schema required for keyed race.');
    $before=DemandRequest::where('buyer_id',$buyer->buyer_id)->count();$key=(string)\Illuminate\Support\Str::uuid();$submit['idempotency_key']=$key;
    $r=$race('same-key-buyer-submission',[$submit,$submit]);$codes=array_column($r,'status');sort($codes);
    demand($codes===[200,201]&&DemandRequest::where('buyer_id',$buyer->buyer_id)->count()===$before+1,'Keyed submission must create one record.');
    demand(DB::table('buyer_demand_submissions')->where('buyer_id',$buyer->buyer_id)->where('submission_key',$key)->count()===1,'Duplicate replay-key row.');$out['same-key-buyer-submission']=$r;
    // Keep synthetic artifacts for inspection; revoke only the tokens created for these users.
    foreach([$admin,$secondAdmin,$farmer,$buyerUser,$loginUser] as $u)$u->tokens()->delete();
    file_put_contents($artifactRoot.'/results.json',json_encode($out,JSON_PRETTY_PRINT));echo json_encode(['result'=>'passed','run'=>$run,'races'=>$out,'note'=>'Keyless POSTs remain distinct; same-key submissions must replay one persisted demand. Synthetic records retained in disposable database only.'],JSON_PRETTY_PRINT),PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'BLOCKED/FAILED: '.$e->getMessage().PHP_EOL);exit(1);}
