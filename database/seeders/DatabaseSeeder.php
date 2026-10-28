<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
/** Source-publication seed entry: no default users, passwords or personal registry data. */
class DatabaseSeeder extends Seeder { public function run():void { $this->call(AiReferenceDataSeeder::class); } }
