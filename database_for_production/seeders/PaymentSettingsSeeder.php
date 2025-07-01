<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'payment_bank_id',
                'value' => '970436',
                'description' => 'Vietcombank Bank ID for VietQR'
            ],
            [
                'key' => 'payment_account_no',
                'value' => '0971000032314',
                'description' => 'Bank account number for payments'
            ],
            [
                'key' => 'payment_account_name',
                'value' => 'TRUONG VAN DO',
                'description' => 'Bank account holder name'
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }
    }
}
