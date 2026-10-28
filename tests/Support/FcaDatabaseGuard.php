<?php
namespace Tests\Support;
use LogicException;
use PDO;
final class FcaDatabaseGuard {
 public static function verify(string $mode): array {
  $fail=static function(string $why): never {throw new LogicException('FCA database guard: '.$why);};
  if(!in_array($mode,['initial','repeat'],true))$fail('Explicit initial/repeat mode required.');
  $root=dirname(__DIR__,2);
  if(is_file($root.'/bootstrap/cache/config.php') || getenv('APP_CONFIG_CACHE'))$fail('Cached configuration forbidden.');
  foreach(['DB_CONNECTION'=>'mysql','DB_HOST'=>'127.0.0.1','DB_PORT'=>'3307','DB_DATABASE'=>'harborai_fca_regression_disposable','DB_USERNAME'=>'harborai_fca_regression'] as $key=>$expected)if(getenv($key)!==$expected)$fail('Unexpected '.$key);
  if(!getenv('DB_PASSWORD') || getenv('DB_URL') || getenv('DB_SOCKET'))$fail('Password required; URL/socket overrides forbidden.');
  $app=require $root.'/bootstrap/app.php';
  $app->bootstrapWith([\Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,\Illuminate\Foundation\Bootstrap\LoadConfiguration::class]);
  $config=$app['config'];$c=$config->get('database.connections.mysql');
  if($config->get('database.default')!=='mysql')$fail('Resolved default differs.');
  foreach(['driver'=>'mysql','host'=>'127.0.0.1','port'=>'3307','database'=>'harborai_fca_regression_disposable','username'=>'harborai_fca_regression','password'=>getenv('DB_PASSWORD')] as $k=>$v)if((string)($c[$k]??'')!==$v)$fail('Resolved '.$k.' differs.');
  if(!empty($c['url'])||!empty($c['unix_socket']))$fail('Resolved URL/socket forbidden.');
  $pdo=new PDO('mysql:host=127.0.0.1;port=3307;dbname=harborai_fca_regression_disposable',$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $id=$pdo->query('SELECT DATABASE() db,CURRENT_USER() account,@@port port,@@hostname hostname,@@version version,@@datadir dir')->fetch(PDO::FETCH_ASSOC);
  if($id['db']!==$c['database']||$id['account']!=='harborai_fca_regression@127.0.0.1'||(int)$id['port']!==3307||$id['hostname']!=='Chaser'||!str_starts_with($id['version'],'10.6.23-MariaDB')||$id['dir']!=='/home/harboraitest/fca-isolated-data/')$fail('Server identity mismatch.');
  self::verifySchemaGrants($pdo->query('SHOW GRANTS FOR CURRENT_USER()')->fetchAll(PDO::FETCH_COLUMN));
  $tables=$pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='harborai_fca_regression_disposable'")->fetchAll(PDO::FETCH_COLUMN);
  if($mode==='initial' && count($tables)!==0)$fail('Initial schema must be empty.');
  if($mode==='repeat' && $tables){
   $expected=['migrations'];$names=[];
   foreach(glob($root.'/database/migrations/*.php') as $file){$names[]=basename($file,'.php');preg_match_all('/Schema::create\(\s*[\x27\x22]([^\x27\x22]+)[\x27\x22]/',file_get_contents($file),$m);$expected=array_merge($expected,$m[1]);}
   if(array_diff($tables,$expected)||!in_array('migrations',$tables,true))$fail('Unexpected populated schema.');
   $applied=$pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
   if(array_diff($applied,$names))$fail('Unknown migration history.');
  }
  return ['identity'=>$id,'tables'=>count($tables),'mode'=>$mode,'grants'=>'Exact schema-only migration/testing privileges verified'];
 }
 public static function verifySchemaGrants(array $grants): void {
  $scoped=false;
  foreach($grants as $grant){
   if(str_contains($grant,'WITH GRANT OPTION'))throw new LogicException('FCA database guard: Grant option forbidden.');
   if(str_starts_with($grant,'GRANT USAGE ON *.* TO '))continue;
   if(!preg_match('/^GRANT ([A-Z, ]+) ON (.+) TO /',$grant,$m) || $m[2] !== '`harborai\_fca\_regression\_disposable`.*')throw new LogicException('FCA database guard: Literal escaped test schema grants required.');
   $privileges=explode(', ',$m[1]);$required=['SELECT','INSERT','UPDATE','DELETE','CREATE','DROP','REFERENCES','INDEX','ALTER','LOCK TABLES'];sort($privileges);sort($required);
   if($privileges!==$required)throw new LogicException('FCA database guard: Unexpected privilege set.');$scoped=true;
  }
  if(!$scoped)throw new LogicException('FCA database guard: Missing schema grants.');
 }
}
