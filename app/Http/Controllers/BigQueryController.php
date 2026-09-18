<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class BigQueryController extends Controller
{
    public function sync()
    {
        try {
            // Call the artisan command we created
            Artisan::call('bigquery:sync');
            $output = Artisan::output();
            Log::info("BigQuery Sync triggered from Web UI. Output: " . $output);
            
            return redirect()->back()->with('success', 'Data berhasil diperbarui dari BigQuery!');
        } catch (\Exception $e) {
            Log::error("BigQuery Sync failed: " . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal memperbarui data dari BigQuery: ' . $e->getMessage());
        }
    }
}
