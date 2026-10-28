<?php
namespace Tests\Unit;
use Tests\Support\FcaDatabaseGuard;
use PHPUnit\Framework\TestCase;
use LogicException;
class FcaDatabaseGuardTest extends TestCase {
 private string $privileges='SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER, LOCK TABLES';
 public function test_literal_escaped_schema_grants_are_accepted():void {FcaDatabaseGuard::verifySchemaGrants(['GRANT USAGE ON *.* TO synthetic', 'GRANT '.$this->privileges.' ON `harborai\_fca\_regression\_disposable`.* TO synthetic']);$this->addToAssertionCount(1);}
 public function test_unescaped_schema_underscores_are_rejected_as_wildcards():void {$this->expectException(LogicException::class);FcaDatabaseGuard::verifySchemaGrants(['GRANT '.$this->privileges.' ON `harborai_fca_regression_disposable`.* TO synthetic']);}
 public function test_usage_cannot_hide_global_grant_option():void {$this->expectException(LogicException::class);FcaDatabaseGuard::verifySchemaGrants(['GRANT USAGE ON *.* TO synthetic WITH GRANT OPTION']);}
 public function test_disaster_recovery_schema_is_not_a_concurrency_target():void {$this->expectException(LogicException::class);FcaDatabaseGuard::verifySchemaGrants(['GRANT '.$this->privileges.' ON `harborai\_recovery\_drill`.* TO synthetic']);}
}
