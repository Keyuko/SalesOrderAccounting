<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Google\Cloud\BigQuery\BigQueryClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncBigQueryData extends Command
{
    protected $signature = 'bigquery:sync';
    protected $description = 'Sync Delivery Order data from BigQuery into local database';

    public function handle()
    {
        $this->info("=== Starting BigQuery Sync ===");
        $startTime = now();

        $keyFilePath = storage_path('app/bigquery-key.json');
        if (!file_exists($keyFilePath)) {
            $this->error("BigQuery Key file not found at: {$keyFilePath}");
            return 1;
        }

        try {
            $bigQuery = new BigQueryClient([
                'projectId' => 'dunia-kimia-jaya-bq-project',
                'keyFilePath' => $keyFilePath
            ]);

            // Step 1: Fetch all data from BigQuery
            $this->info("Fetching data from BigQuery...");
            $query = "SELECT * FROM `lautan-luas-big-data-project1.shared_view.VW_DELIVERY_ORDER`";
            $jobConfig = $bigQuery->query($query);
            $queryResults = $bigQuery->runQuery($jobConfig);

            $rows = [];
            foreach ($queryResults as $row) {
                // FIX: BigQuery returns column names in whatever case the view
                // defines them in (in this project that's UPPERCASE, e.g.
                // COMPANY_CODE, DELIVERY_ORDER_NUMBER), but the rest of this
                // command reads lowercase keys like 'company_code'. That
                // mismatch meant every lookup below silently missed and fell
                // back to null/''  -- the sync "succeeded" but created zero
                // Sales/Delivery Orders. Normalizing keys to lowercase here
                // fixes that regardless of the actual casing BigQuery returns.
                $normalized = [];
                foreach ($row as $key => $value) {
                    $normalized[strtolower($key)] = $this->stringifyValue($value);
                }
                $rows[] = $normalized;
            }
            $this->info("Fetched " . count($rows) . " rows from BigQuery.");

            if (count($rows) > 0) {
                $this->line("Sample row keys: " . implode(', ', array_keys($rows[0])));
            }

            // Step 2: Clear existing BQ data (full refresh)
            $this->info("Clearing old bq_delivery_orders data...");
            DB::table('bq_delivery_orders')->truncate();

            // Step 3: Insert raw BQ data in chunks
            $this->info("Inserting raw BQ data...");
            $chunks = array_chunk($rows, 500);
            foreach ($chunks as $chunkIndex => $chunk) {
                $insertData = [];
                foreach ($chunk as $row) {
                    $insertData[] = [
                        'company_code' => $row['company_code'] ?? null,
                        'business_area' => $row['business_area'] ?? null,
                        'delivery_order_number' => $row['delivery_order_number'] ?? '',
                        'sales_order_number' => $row['sales_order_number'] ?? null,
                        'estimated_goods_issue_date' => $this->parseDate($row['estimated_goods_issue_date'] ?? null),
                        'actual_goods_issue_date' => $this->parseDate($row['actual_goods_issue_date'] ?? null),
                        'sold_to_party' => $row['sold_to_party'] ?? null,
                        'ship_to_party' => $row['ship_to_party'] ?? null,
                        'material_number' => $row['material_number'] ?? null,
                        'batch_number' => $row['batch_number'] ?? null,
                        'storage_location' => $row['storage_location'] ?? null,
                        'quantity' => $row['quantity'] ?? null,
                        'salesperson_number' => $row['salesperson_number'] ?? null,
                        'cost_value_in_local_currency' => $row['cost_value_in_local_currency'] ?? null,
                        'profit_center' => $row['profit_center'] ?? null,
                        'fbl3n_posting_date' => $this->parseDate($row['fbl3n_posting_date'] ?? null),
                        'goods_value_in_local_currency' => $row['goods_value_in_local_currency'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                DB::table('bq_delivery_orders')->insert($insertData);
                $this->line("  Chunk " . ($chunkIndex + 1) . "/" . count($chunks) . " inserted.");
            }

            // Step 4: Populate sales_orders from unique SO numbers
            $this->info("Populating sales_orders...");
            $uniqueSOs = DB::table('bq_delivery_orders')
                ->select('sales_order_number')
                ->whereNotNull('sales_order_number')
                ->where('sales_order_number', '!=', '')
                ->distinct()
                ->pluck('sales_order_number');

            $soCount = 0;
            foreach ($uniqueSOs as $soNumber) {
                $exists = DB::table('sales_orders')->where('so_number', $soNumber)->exists();
                if (!$exists) {
                    // Get the first BQ row for this SO to extract some metadata
                    $bqRow = DB::table('bq_delivery_orders')
                        ->where('sales_order_number', $soNumber)
                        ->first();

                    DB::table('sales_orders')->insert([
                        'quotation_id' => null,
                        'so_number' => $soNumber,
                        'delivery_date' => $bqRow->estimated_goods_issue_date ?? $bqRow->actual_goods_issue_date ?? now()->toDateString(),
                        'location' => $bqRow->storage_location ?? '-',
                        'notes' => 'Imported from BigQuery (Company: ' . ($bqRow->company_code ?? '-') . ', Business Area: ' . ($bqRow->business_area ?? '-') . ')',
                        'ppic_status' => 'approved',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $soCount++;
                }
            }
            $this->info("  {$soCount} new Sales Orders created.");

            // Step 5: Populate delivery_orders from unique DO numbers
            $this->info("Populating delivery_orders...");
            $uniqueDOs = DB::table('bq_delivery_orders')
                ->select('delivery_order_number', 'sales_order_number', 'estimated_goods_issue_date', 'actual_goods_issue_date', 'storage_location', 'company_code', 'business_area')
                ->whereNotNull('delivery_order_number')
                ->where('delivery_order_number', '!=', '')
                ->groupBy('delivery_order_number', 'sales_order_number', 'estimated_goods_issue_date', 'actual_goods_issue_date', 'storage_location', 'company_code', 'business_area')
                ->get();

            $doCount = 0;
            foreach ($uniqueDOs as $doRow) {
                $exists = DB::table('delivery_orders')->where('do_number', $doRow->delivery_order_number)->exists();
                if (!$exists) {
                    // Find linked SO
                    $soId = null;
                    if ($doRow->sales_order_number) {
                        $so = DB::table('sales_orders')->where('so_number', $doRow->sales_order_number)->first();
                        $soId = $so ? $so->id : null;
                    }

                    $doId = DB::table('delivery_orders')->insertGetId([
                        'sales_order_id' => $soId,
                        'do_number' => $doRow->delivery_order_number,
                        'delivery_date' => $doRow->estimated_goods_issue_date ?? $doRow->actual_goods_issue_date ?? now()->toDateString(),
                        'location' => $doRow->storage_location ?? '-',
                        'notes' => 'Imported from BigQuery',
                        'ppic_status' => 'approved',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Step 6: Create a delivery record for tracking
                    $deliveryExists = DB::table('deliveries')->where('delivery_order_id', $doId)->exists();
                    if (!$deliveryExists) {
                        DB::table('deliveries')->insert([
                            'delivery_order_id' => $doId,
                            'driver_name' => null,
                            'gps_imei' => null,
                            'status' => 'pending',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $doCount++;
                }
            }
            $this->info("  {$doCount} new Delivery Orders created.");

            $elapsed = now()->diffInSeconds($startTime);
            $this->info("=== BigQuery Sync completed in {$elapsed}s ===");
            Log::info("BigQuery Sync completed. {$soCount} SOs, {$doCount} DOs created in {$elapsed}s.");

            return 0;

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error("BigQuery Sync failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * FIX: the raw BigQuery client (unlike BigQueryService::query()) returns
     * scalar values as native types/objects (e.g. Date/Timestamp objects),
     * not strings. Casting them safely here prevents strtotime()/date()
     * from choking on non-string values downstream.
     */
    private function stringifyValue($value)
    {
        if ($value === null) {
            return null;
        }
        if (is_object($value) && method_exists($value, 'get')) {
            // BigQuery's Date/Timestamp wrapper objects return a DateTime
            // (or DateTimeImmutable) instance from get(), not a string.
            // DateTime has no __toString(), so casting it directly throws
            // "Object of class DateTime could not be converted to string".
            $inner = $value->get();
            if ($inner instanceof \DateTimeInterface) {
                return $inner->format('Y-m-d H:i:s');
            }
            return is_scalar($inner) ? (string) $inner : (string) json_encode($inner);
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_object($value) || is_array($value)) {
            return (string) json_encode($value);
        }
        return $value;
    }

    private function parseDate($value)
    {
        if (empty($value) || is_array($value) || (is_object($value) && empty((array)$value))) {
            return null;
        }
        try {
            return date('Y-m-d', strtotime($value));
        } catch (\Exception $e) {
            return null;
        }
    }
}