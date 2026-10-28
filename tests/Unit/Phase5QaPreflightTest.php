<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/scripts/phase5-qa-preflight.php';
class Phase5QaPreflightTest extends TestCase {
    private function env():array {
        $e=\Dotenv\Dotenv::parse(file_get_contents(dirname(__DIR__,2).'/scripts/phase5-qa.env.example'));
        $e['APP_KEY']='base64:'.base64_encode(str_repeat('x',32));$e['DB_PASSWORD']='synthetic-test-only-password';return $e;
    }
    public function test_expected_qa_configuration_is_accepted():void {\qaEnvironment($this->env());$this->addToAssertionCount(1);}
    public function test_protected_database_wrong_port_and_backup_activation_are_rejected():void {
        foreach(['DB_DATABASE'=>'harborai_integrated_dev','DB_PORT'=>'3306','DB_USERNAME'=>'root','BACKUP_ENABLED'=>'true','APP_ENV'=>'local'] as $key=>$value){
            $e=$this->env();$e[$key]=$value;try{\qaEnvironment($e);$this->fail('Unsafe configuration accepted');}catch(\RuntimeException $ex){$this->assertStringContainsString($key,$ex->getMessage());}
        }
    }
    public function test_shared_path_and_external_secret_overrides_are_rejected():void {
        foreach(['LARAVEL_STORAGE_PATH','APP_CONFIG_CACHE','DB_URL','AWS_SECRET_ACCESS_KEY','SMS_API_KEY'] as $key){$e=$this->env();$e[$key]='unsafe';try{\qaEnvironment($e);$this->fail('Override accepted');}catch(\RuntimeException $ex){$this->assertStringContainsString($key,$ex->getMessage());}}
    }
    private function grant(string $scope):string {return 'GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER, LOCK TABLES ON '.$scope.' TO `harborai_uat_phase5`@`127.0.0.1`';}
    public function test_only_literal_qa_schema_grants_are_accepted():void {\qaGrants(['GRANT USAGE ON *.* TO `harborai_uat_phase5`@`127.0.0.1`',$this->grant('`harborai\_uat\_phase5\_disposable`.*')]);$this->addToAssertionCount(1);}
    public function test_wildcard_global_concurrency_recovery_and_delegable_grants_are_rejected():void {
        foreach(['`harborai_uat_phase5_disposable`.*','*.*','`harborai\_fca\_regression\_disposable`.*','`harborai\_recovery\_drill`.*'] as $scope){try{\qaGrants([$this->grant($scope)]);$this->fail('Unsafe grants accepted');}catch(\RuntimeException $ex){$this->assertStringContainsString('QA schema',$ex->getMessage());}}
        $this->expectException(\RuntimeException::class);\qaGrants(['GRANT USAGE ON *.* TO `test`@`127.0.0.1` WITH GRANT OPTION']);
    }
}
