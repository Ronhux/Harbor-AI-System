<?php
/** Read-only: no Laravel HTTP/console kernel, migrations, seeds, files or secrets written. */
const QA_ROOT = 'C:/HarborAI_QA_PHASE5/app';
const QA_SCHEMA = 'harborai_uat_phase5_disposable';
function qaRequire(bool $ok, string $reason): void {if (!$ok) throw new RuntimeException($reason);}
function qaPath(string $path): string {return strtolower(rtrim(str_replace('\\','/',$path),'/'));}
function qaEnvironment(array $e): void {
    $expected=['APP_ENV'=>'testing','APP_DEBUG'=>'false','APP_URL'=>'http://127.0.0.1:8015','DB_CONNECTION'=>'mysql','DB_HOST'=>'127.0.0.1','DB_PORT'=>'3308','DB_DATABASE'=>QA_SCHEMA,'DB_USERNAME'=>'harborai_uat_phase5','MAIL_MAILER'=>'array','QUEUE_CONNECTION'=>'sync','BROADCAST_CONNECTION'=>'null','CACHE_STORE'=>'file','SESSION_DRIVER'=>'file','SESSION_COOKIE'=>'harborai_phase5_qa_session','LOG_CHANNEL'=>'single','FILESYSTEM_DISK'=>'local','BACKUP_ENABLED'=>'false','BACKUP_STORAGE_PERMISSIONS_APPROVED'=>'false','BACKUP_RECOVERY_DRILL_ENABLED'=>'false','AZURE_OPENAI_ENABLED'=>'false'];
    foreach($expected as $key=>$value)qaRequire(($e[$key]??null)===$value,'Unexpected or missing '.$key);
    foreach(['DB_URL','DB_SOCKET','SESSION_DOMAIN','APP_CONFIG_CACHE','APP_SERVICES_CACHE','APP_PACKAGES_CACHE','APP_ROUTES_CACHE','APP_EVENTS_CACHE','LARAVEL_STORAGE_PATH','APP_BASE_PATH','APP_PUBLIC_PATH','VIEW_COMPILED_PATH','LOG_STACK','LOG_SLACK_WEBHOOK_URL','AWS_ACCESS_KEY_ID','AWS_SECRET_ACCESS_KEY','AZURE_OPENAI_API_KEY','AZURE_OPENAI_ENDPOINT','POSTMARK_API_KEY','RESEND_API_KEY','SLACK_BOT_USER_OAUTH_TOKEN','MAIL_PASSWORD','BACKUP_ENCRYPTION_KEY','BACKUP_SECONDARY_ROOT','BACKUP_RECOVERY_PASSWORD'] as $key)qaRequire(empty($e[$key])||$e[$key]==='null','Forbidden override or external secret: '.$key);
    foreach($e as $key=>$value)if(preg_match('/TOKEN|SECRET|PASSWORD|API_KEY|ENDPOINT|WEBHOOK|_URL$/i',$key)&&!in_array($key,['APP_URL','DB_PASSWORD'],true))qaRequire(empty($value)||$value==='null','Unapproved external configuration: '.$key);
    qaRequire(!empty($e['DB_PASSWORD'])&&!str_contains($e['DB_PASSWORD'],'REPLACE'),'Dedicated DB password required');
    qaRequire(isset($e['APP_KEY'])&&str_starts_with($e['APP_KEY'],'base64:')&&strlen(base64_decode(substr($e['APP_KEY'],7),true)?:'')===32,'Independent QA app key required');
}
function qaGrants(array $grants): void {
    $scoped=false;
    foreach($grants as $grant){
        qaRequire(!str_contains($grant,'WITH GRANT OPTION'),'Grant option forbidden');
        if(str_starts_with($grant,'GRANT USAGE ON *.* TO '))continue;
        qaRequire((bool)preg_match('/^GRANT ([A-Z, ]+) ON (.+) TO /',$grant,$m),'Unsupported grant');
        qaRequire($m[2]==='`harborai\_uat\_phase5\_disposable`.*','Literal QA schema-only grants required');
        $actual=explode(', ',$m[1]);$needed=['SELECT','INSERT','UPDATE','DELETE','CREATE','DROP','REFERENCES','INDEX','ALTER','LOCK TABLES'];sort($actual);sort($needed);qaRequire($actual===$needed,'Unexpected privilege set');$scoped=true;
    }
    qaRequire($scoped,'Missing QA schema grant');
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')!==__FILE__)return;
try {
    $root=dirname(__DIR__);
    qaRequire(qaPath($root)===qaPath(QA_ROOT),'Run only from proposed separate QA copy: '.QA_ROOT);
    require $root.'/vendor/autoload.php';
    qaRequire(is_file($root.'/.env'),'QA .env missing');
    $env=Dotenv\Dotenv::parse(file_get_contents($root.'/.env'));qaEnvironment($env);
    foreach(getenv() as $key=>$value)if(array_key_exists($key,$env)||preg_match('/^(APP_|DB_|SESSION_|CACHE_|LOG_|MAIL_|QUEUE_|BROADCAST_|FILESYSTEM_|AWS_|AZURE_|BACKUP_|LARAVEL_)/',$key))qaRequire($value==='','Inherited environment overrides forbidden: '.$key);
    $active='C:/HarborAI_INTEGRATION_20261014/harborai-integrated';
    qaRequire(is_file($active.'/.env'),'Active key comparison unavailable');
    $protected=Dotenv\Dotenv::parse(file_get_contents($active.'/.env'));
    foreach(['APP_KEY','DB_PASSWORD'] as $key)qaRequire(!isset($protected[$key])||!hash_equals($protected[$key],$env[$key]),'Protected credential reused: '.$key);
    exec('powershell -NoProfile -File '.escapeshellarg(__DIR__.'/phase5-check-paths.ps1').' 2>&1',$pathOutput,$pathExit);
    qaRequire($pathExit===0,'Windows runtime/config path and hard-link verification failed');
    // Reject junctions/symlinks in ancestors and anywhere in writable runtime trees.
    // PowerShell companion preflight additionally checks Windows ReparsePoint attributes.
    foreach(['storage','public/storage','bootstrap/cache'] as $relative){
        $path=$root.'/'.$relative;qaRequire(is_dir($path)&&!is_link($path),'Missing/linked runtime directory: '.$relative);
        qaRequire(str_starts_with(qaPath(realpath($path)),qaPath(QA_ROOT).'/'),'Runtime path escaped QA copy');
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST) as $f)qaRequire(!$f->isLink(),'Linked runtime entry forbidden');
    }
    qaRequire(!is_file($root.'/public/hot'),'Dev hot file forbidden');
    foreach(array_merge([$root.'/bootstrap/cache/config.php',$root.'/bootstrap/cache/events.php'],glob($root.'/bootstrap/cache/routes*.php')?:[]) as $cache)qaRequire(!is_file($cache),'Config/route/event caches forbidden before preflight');
    $app=require $root.'/bootstrap/app.php';
    $app->bootstrapWith([Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,Illuminate\Foundation\Bootstrap\LoadConfiguration::class]);
    $c=$app['config'];
    qaRequire(qaPath($app->storagePath())===qaPath($root.'/storage')&&qaPath($app->publicPath())===qaPath($root.'/public'),'Application path override forbidden');
    qaRequire(hash_equals($env['DB_PASSWORD'],(string)$c->get('database.connections.mysql.password'))&&hash_equals($env['APP_KEY'],(string)$c->get('app.key')),'Resolved credential/key differs from reviewed environment');
    qaRequire(!$c->get('database.connections.mysql.url')&&!$c->get('database.connections.mysql.unix_socket')&&!$c->get('database.connections.mysql.read')&&!$c->get('database.connections.mysql.write'),'Resolved connection overrides forbidden');
    foreach(['database.default'=>'mysql','database.connections.mysql.host'=>'127.0.0.1','database.connections.mysql.port'=>'3308','database.connections.mysql.database'=>QA_SCHEMA,'database.connections.mysql.username'=>'harborai_uat_phase5','filesystems.disks.public.root'=>$root.'/public/storage','filesystems.disks.local.root'=>$root.'/storage/app/private','session.files'=>$root.'/storage/framework/sessions','session.cookie'=>'harborai_phase5_qa_session','cache.stores.file.path'=>$root.'/storage/framework/cache/data','view.compiled'=>$root.'/storage/framework/views','logging.channels.single.path'=>$root.'/storage/logs/laravel.log','mail.default'=>'array','queue.default'=>'sync','broadcasting.default'=>'null'] as $key=>$value)qaRequire(qaPath((string)$c->get($key))===qaPath($value),'Resolved configuration differs: '.$key);
    foreach(['backup.enabled','backup.recovery.enabled','backup.storage_permissions_approved','mao_workflows.enhanced_demands_enabled','mao_workflows.demand_publication_enabled','mao_workflows.sagip_saka_enabled'] as $key)qaRequire(!$c->get($key),'Restricted feature active: '.$key);
    $managerIds=[];
    foreach($argv as $arg)if(str_starts_with($arg,'--approved-kadiwa-id=')){
        $id=substr($arg,21);qaRequire(ctype_digit($id)&&(int)$id>0,'Invalid synthetic KADIWA ID');
        qaRequire(getenv('HARBORAI_QA_KADIWA_APPROVED')==='APPROVED-PHASE5-KADIWA-ASSIGNMENT','Separate KADIWA assignment approval required');$managerIds=[(int)$id];
    }
    qaRequire($c->get('mao_workflows.kadiwa_manager_user_ids')===$managerIds,'Copied demo or unapproved KADIWA authority assignment');
    foreach(['fca_reviewer_user_ids','demand_reviewer_user_ids','institutional_buyer_user_ids','kadiwa_inquiry_reviewer_user_ids'] as $key)qaRequire($c->get('mao_workflows.'.$key)===[],'Unapproved authority assignment: '.$key);
    if(in_array('--offline',$argv,true)){echo "OFFLINE CHECKS PASSED; database identity and grants NOT verified. Not authorization to migrate.\n";exit;}
    $pdo=new PDO('mysql:host=127.0.0.1;port=3308;dbname='.QA_SCHEMA,$env['DB_USERNAME'],$env['DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>3]);
    $id=$pdo->query('SELECT DATABASE() db,CURRENT_USER() account,@@port port,@@hostname hostname,@@version version,@@datadir datadir')->fetch(PDO::FETCH_ASSOC);
    qaRequire($id['db']===QA_SCHEMA&&$id['account']==='harborai_uat_phase5@127.0.0.1'&&(int)$id['port']===3308&&$id['hostname']==='Chaser'&&str_starts_with($id['version'],'10.6.23-MariaDB')&&qaPath($id['datadir'])===qaPath('C:/HarborAI_QA_PHASE5_DB/data'),'QA server identity mismatch');
    qaGrants($pdo->query('SHOW GRANTS FOR CURRENT_USER()')->fetchAll(PDO::FETCH_COLUMN));
    if($managerIds){$q=$pdo->prepare('SELECT email,user_type,is_active FROM users WHERE user_id=?');$q->execute([$managerIds[0]]);$u=$q->fetch(PDO::FETCH_ASSOC);qaRequire($u&&$u['email']==='phase5-kadiwa@example.test'&&$u['user_type']==='KADIWA Manager'&&(bool)$u['is_active'],'KADIWA ID does not belong to approved synthetic roster');}
    $tables=$pdo->query('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_ASSOC);
    foreach($tables as $t)qaRequire($t['ENGINE']==='InnoDB','Non-InnoDB table or view forbidden');
    foreach(['TRIGGERS'=>'TRIGGER_SCHEMA','ROUTINES'=>'ROUTINE_SCHEMA','EVENTS'=>'EVENT_SCHEMA'] as $table=>$column)qaRequire((int)$pdo->query("SELECT COUNT(*) FROM information_schema.$table WHERE $column=DATABASE()")->fetchColumn()===0,'Unapproved database executable object');
    if(in_array('--expect-empty',$argv,true))qaRequire(!$tables,'Initial schema is not empty');
    echo json_encode(['status'=>'READ_ONLY_PREFLIGHT_PASSED','identity'=>$id,'tables'=>count($tables),'root'=>QA_ROOT,'authorization'=>'Operator approval is still required for writes'],JSON_PRETTY_PRINT),PHP_EOL;
} catch(Throwable $e) {fwrite(STDERR,'BLOCKED: '.($e instanceof RuntimeException&&!($e instanceof PDOException)?$e->getMessage():'Inspection failed; raw credentials/driver errors suppressed').PHP_EOL);exit(1);}
