<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Quotation;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Dummy Users for all roles
        User::firstOrCreate(['email' => 'sales@test.com'], ['name' => 'Sales Person', 'password' => bcrypt('password'), 'role' => 'sales']);
        User::firstOrCreate(['email' => 'csr@test.com'], ['name' => 'CSR Admin', 'password' => bcrypt('password'), 'role' => 'csr']);
        User::firstOrCreate(['email' => 'ppic@test.com'], ['name' => 'PPIC Manager', 'password' => bcrypt('password'), 'role' => 'ppic']);
        User::firstOrCreate(['email' => 'security@test.com'], ['name' => 'Security Guard', 'password' => bcrypt('password'), 'role' => 'security']);
        User::firstOrCreate(['email' => 'delivery@test.com'], ['name' => 'Delivery Driver', 'password' => bcrypt('password'), 'role' => 'delivery']);

        $dummyItems1 = json_encode([
            ['code_material' => 'MTL-001', 'nama_material' => 'Pipa Besi 4inch', 'quantity' => 10, 'harga' => 150000],
            ['code_material' => 'MTL-002', 'nama_material' => 'Semen Gresik', 'quantity' => 50, 'harga' => 65000],
        ]);

        $dummyItems2 = json_encode([
            ['code_material' => 'MTL-003', 'nama_material' => 'Cat Tembok 5kg', 'quantity' => 20, 'harga' => 120000],
        ]);

        $dummyItems3 = json_encode([
            ['code_material' => 'MTL-004', 'nama_material' => 'Besi Beton 10mm', 'quantity' => 100, 'harga' => 75000],
            ['code_material' => 'MTL-005', 'nama_material' => 'Kawat Bendrat', 'quantity' => 5, 'harga' => 25000],
        ]);

        // 2. Create Dummy Quotations (Pretending they came from Salesforce)
        Quotation::create([
            'quotation_number' => 'QT-1001',
            'customer_name' => 'PT Makmur Jaya',
            'requested_delivery_date' => date('Y-m-d', strtotime('+3 days')),
            'ppic_status' => 'pending',
            'items' => $dummyItems1,
        ]);

        Quotation::create([
            'quotation_number' => 'QT-1002',
            'customer_name' => 'CV Abadi Sentosa',
            'requested_delivery_date' => date('Y-m-d', strtotime('+5 days')),
            'ppic_status' => 'pending',
            'items' => $dummyItems2,
        ]);
        
        Quotation::create([
            'quotation_number' => 'QT-1003',
            'customer_name' => 'Toko Global',
            'requested_delivery_date' => date('Y-m-d', strtotime('+1 days')),
            'ppic_status' => 'pending',
            'items' => $dummyItems3,
        ]);
    }
}
