<?php

namespace Database\Seeders;

use App\Models\CompanyProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanyProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (DB::table('company_profiles')->where('id', CompanyProfile::SINGLETON_ID)->exists()) {
            return;
        }

        DB::table('company_profiles')->insert([
            'id' => CompanyProfile::SINGLETON_ID,
            'commercial_name' => 'Mi empresa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
