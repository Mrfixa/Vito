<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\BusinessManagement\Database\Seeders\BusinessManagementDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);
        $this->call(AdminUserWalletSeeder::class);
        $this->call(BusinessManagementDatabaseSeeder::class);
    }
}
