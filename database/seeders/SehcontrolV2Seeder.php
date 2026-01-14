<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\Subscription;
use App\Models\DevicePairing;
use Carbon\Carbon;

class SehcontrolV2Seeder extends Seeder
{
    public function run(): void
    {
        $customer = Customer::firstOrCreate(
            ['email' => 'recepcion@sehuacho.com'],
            ['name' => 'Cliente Demo']
        );

        Subscription::create([
            'customer_id' => $customer->id,
            'status' => 'active',
            'starts_on' => Carbon::now()->toDateString(),
            'ends_on' => Carbon::now()->addDays(60)->toDateString(),
            'max_devices' => 2,
        ]);

        DevicePairing::create([
            'customer_id' => $customer->id,
            'pair_code' => 'PAIR-123456',
            'status' => 'pending',
        ]);
    }
}
