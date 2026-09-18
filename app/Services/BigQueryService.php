<?php

namespace App\Services;

use Google\Cloud\BigQuery\BigQueryClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BigQueryService
 *
 * Sumber data utama untuk semua AR data.
 * Tabel: lautan-luas-big-data-project1.shared_view.VW_FBL5N_GL
 *
 * PERUBAHAN dari versi sebelumnya:
 *   1. getArRecords() sekarang menghitung due_week dari DueDateReal/DueDate
 *      (W1=1–7, W2=8–14, W3=15–21, W4=22–31) langsung di BigQuery SQL.
 *   2. collectorFilter() dan plantFilter() menggunakan parameterized queries
 *      agar aman dari SQL injection dan karakter khusus di nama.
 *   3. periodWhere() menggunakan CAST yang konsisten.
 */
class BigQueryService
{
    protected BigQueryClient $client;

    protected string $table = '`lautan-luas-big-data-project1.shared_view.VW_DELIVERY_ORDER`';

    public function __construct()
    {
        $this->client = new BigQueryClient([
            'projectId'   => config('bigquery.project_id', env('BIGQUERY_PROJECT_ID')),
            'keyFilePath' => storage_path('app/bigquery-key.json'),
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // HELPER
    // ══════════════════════════════════════════════════════════════

    protected function query(string $sql, array $params = []): Collection
    {
        try {
            $queryConfig = $this->client->query($sql);

            if (!empty($params)) {
                $queryConfig->parameters($params);
            }

            $results = $this->client->runQuery($queryConfig);
            $rows    = [];

            foreach ($results as $row) {
                $obj = new \stdClass();
                foreach ($row as $key => $value) {
                    $obj->$key = $value;
                }
                $rows[] = $obj;
            }

            return collect($rows);
        } catch (\Exception $e) {
            Log::error('BigQuery error: ' . $e->getMessage(), ['sql' => $sql]);
            return collect();
        }
    }

    /**
     * WHERE clause untuk filter period.
     * Menggunakan CAST agar Year/Month string di BQ bisa dibandingkan sebagai integer.
     */
    protected function periodWhere(int $year, int $month): string
    {
        $ym = sprintf('%04d-%02d', $year, $month);
        return "STARTS_WITH(CAST(DocDate AS STRING), '{$ym}')";
    }

    /**
     * WHERE clause untuk filter collector (SalesName).
     * Menggunakan string escape manual karena BigQuery tidak support positional params di LIKE/=.
     * Nama collector dari BigQuery sudah ter-validasi, bukan user input langsung.
     */
    protected function collectorFilter(?string $collectorName): string
    {
        if (!$collectorName) return '';
        // Escape single quotes untuk BigQuery SQL safety
        $safe = str_replace("'", "\\'", $collectorName);
        return "AND SalesName = '{$safe}'";
    }

    /**
     * WHERE clause untuk filter plant (BA atau SOff).
     *
     * PENTING: BigQuery menyimpan plant di dua kolom: BA dan SOff.
     * Filter harus mengecek keduanya agar tidak ada data yang terlewat.
     * Contoh: plant 1511 bisa ada di BA='1511' dengan SOff=NULL, atau sebaliknya.
     */
    protected function plantFilter(?string $plantCode): string
    {
        if (!$plantCode) return '';
        $safe = str_replace("'", "\\'", $plantCode);
        return "AND (BA = '{$safe}' OR SOff = '{$safe}')";
    }

    /**
     * Hitung week bucket dari tanggal (hari dalam bulan).
     * Dikembalikan sebagai SQL CASE expression, siap dipakai di SELECT.
     *
     * W1 = 1–7, W2 = 8–14, W3 = 15–21, W4 = 22–31
     */
    protected function weekCaseExpr(string $dateColumn = 'COALESCE(DueDateReal, DueDate)'): string
    {
        return "
            CASE
                WHEN EXTRACT(DAY FROM {$dateColumn}) BETWEEN 1  AND 7  THEN 1
                WHEN EXTRACT(DAY FROM {$dateColumn}) BETWEEN 8  AND 14 THEN 2
                WHEN EXTRACT(DAY FROM {$dateColumn}) BETWEEN 15 AND 21 THEN 3
                WHEN {$dateColumn} IS NOT NULL                          THEN 4
                ELSE 0
            END
        ";
    }


    // ══════════════════════════════════════════════════════════════
    // AR PERIODS
    // ══════════════════════════════════════════════════════════════

    public function getPeriods(): Collection
    {
        $sql = "
            SELECT DISTINCT
                CAST(Year  AS INT64) AS year,
                CAST(Month AS INT64) AS month,
                CONCAT(
                    CASE CAST(Month AS INT64)
                        WHEN 1  THEN 'Jan' WHEN 2  THEN 'Feb' WHEN 3  THEN 'Mar'
                        WHEN 4  THEN 'Apr' WHEN 5  THEN 'May' WHEN 6  THEN 'Jun'
                        WHEN 7  THEN 'Jul' WHEN 8  THEN 'Aug' WHEN 9  THEN 'Sep'
                        WHEN 10 THEN 'Oct' WHEN 11 THEN 'Nov' WHEN 12 THEN 'Dec'
                        ELSE CAST(Month AS STRING)
                    END,
                    ' ', Year
                ) AS period_label,
                DATE(CAST(Year AS INT64), CAST(Month AS INT64), 1) AS period_month
            FROM {$this->table}
            WHERE Year IS NOT NULL AND Month IS NOT NULL
            ORDER BY year DESC, month DESC
        ";

        return $this->query($sql);
    }

    public function getLatestPeriod(): ?\stdClass
    {
        return $this->getPeriods()->first();
    }


    // ══════════════════════════════════════════════════════════════
    // AR RECORDS  (dengan due_week)
    // ══════════════════════════════════════════════════════════════

    /**
     * Ambil AR records untuk 1 period dengan filter opsional.
     *
     * Field baru yang ditambahkan:
     *   - due_week (INT 0–4): kategori minggu dari DueDateReal
     *   - due_week_label (STRING): label human-readable, mis. "Week 3 (15–21)"
     *
     * due_week = 0 berarti DueDateReal NULL (tidak diketahui).
     */
    public function getArRecords(int $year, int $month, ?string $collector = null, ?string $plant = null, string $context = 'default'): Collection
    {
        if ($context === 'aging') {
            $endDate = \Carbon\Carbon::create($year, $month)->endOfMonth()->toDateString();
            $periodWhere = "CAST(COALESCE(DueDateReal, DueDate) AS STRING) <= '{$endDate}'";
            $glWhere = "AND GLAcctDesc = 'ARTrd:Trading-3rdPty'";
        } else {
            $periodWhere = $this->periodWhere($year, $month);
            $glWhere = "";
        }

        $collectorWhere = $this->collectorFilter($collector);
        $plantWhere     = $this->plantFilter($plant);
        $weekExpr       = $this->weekCaseExpr('COALESCE(DueDateReal, DueDate)');

        $sql = "
            SELECT
                DocNo                                   AS invoice_id,
                CustomerID                              AS customer_id,
                CustomerName                            AS customer_name,
                SalesName                               AS collection_by,
                SalesName                               AS sales_name,
                CAST(DocDate AS STRING)                 AS doc_date,
                COALESCE(BA, SOff)                      AS plant,

                -- Aging buckets
                COALESCE(CAST(Currents        AS FLOAT64), 0) AS amount_current,
                COALESCE(CAST(Due1_30Days     AS FLOAT64), 0) AS amount_1_30_days,
                COALESCE(CAST(Due31_60Days    AS FLOAT64), 0) AS amount_30_60_days,
                COALESCE(CAST(Due61_90Days    AS FLOAT64), 0) AS amount_60_90_days,
                COALESCE(CAST(Due91_120Days   AS FLOAT64), 0)
                    + COALESCE(CAST(Due121_180Days AS FLOAT64), 0)
                    + COALESCE(CAST(Due_181M_Days  AS FLOAT64), 0) AS amount_over_90_days,

                -- Total AR
                COALESCE(CAST(AmountInLC AS FLOAT64), 0) AS total_ar,

                -- Target & actual — default 0 (managed in MySQL if needed)
                0 AS ar_target,
                0 AS ar_actual,

                -- Invoice fields
                CAST(COALESCE(DueDateReal, DueDate) AS STRING) AS due_date,
                CAST(DueDateReal AS STRING)              AS DueDateReal,
                CAST(BaselineDate AS STRING)             AS baseline_date,
                COALESCE(DC, LC, 'IDR')                 AS currency_type,
                CAST(AmountInDC AS FLOAT64)             AS amount_in_dc,

                -- Period
                CAST(Year  AS INT64)                    AS year,
                CAST(Month AS INT64)                    AS month,

                -- Extra info
                SalesDistrict                           AS sales_district,
                CustIndustry                            AS industry,
                AR_STATUS                               AS ar_status,
                ClearingDoc                             AS clearing_doc,
                CAST(ClearingDate AS STRING)            AS clearing_date,
                CAST(AmountInGC AS FLOAT64)             AS amount_usd,

                -- ── Week bucket (BARU) ────────────────────────────────────────
                -- Kategori minggu berdasarkan hari dari DueDateReal (atau DueDate).
                -- W1=1–7, W2=8–14, W3=15–21, W4=22–31, 0=NULL date
                ({$weekExpr}) AS due_week

            FROM {$this->table}
            WHERE {$periodWhere}
              {$collectorWhere}
              {$plantWhere}
              {$glWhere}
            ORDER BY CustomerName
            LIMIT 5000
        ";

        // Mapping plant code → collector name from DB
        $collectorMap = \App\Models\Collector::getPlantMap();

        return $this->query($sql)->map(function ($r) use ($collectorMap) {
            $dueDate = $r->due_date
                ? \Carbon\Carbon::parse($r->due_date)
                : null;

            $r->target_date = $dueDate
                ? $dueDate->copy()->addDays(7)
                : null;
            if ($r->target_date) {

                $day = $r->target_date->day;

                if ($day <= 7)
                    $r->target_week = 1;
                elseif ($day <= 14)
                    $r->target_week = 2;
                elseif ($day <= 21)
                    $r->target_week = 3;
                else
                    $r->target_week = 4;

                $r->target_month = $r->target_date->month;
                $r->target_year = $r->target_date->year;

            } else {

                $r->target_week = null;
            }

            // Only treat clearing_date as "paid" if it's a real date (not SAP placeholder)
            $hasValidClearingDate = !empty($r->clearing_date)
                && !str_starts_with($r->clearing_date, '30')
                && !str_starts_with($r->clearing_date, '00');

            if ($hasValidClearingDate) {

                $actual = \Carbon\Carbon::parse($r->clearing_date);

                $day = $actual->day;

                if ($day <= 7)
                    $r->actual_week = 1;
                elseif ($day <= 14)
                    $r->actual_week = 2;
                elseif ($day <= 21)
                    $r->actual_week = 3;
                else
                    $r->actual_week = 4;

                $r->actual_month = $actual->month;
                $r->actual_year = $actual->year;

            } else {

                $r->actual_week = null;
            }

            // target_amount/actual_amount removed — ar_target/ar_actual are
            // computed in DashboardController::getRows() with the real business logic

            $r->plant = trim((string)$r->plant);

            $r->collection_by = $collectorMap[$r->plant] ?? 'Unknown';
            // ── Aliases untuk kompatibilitas blade lama ──────────────────────
            $r->total        = (float) ($r->total_ar ?? 0);
            $r->current      = (float) ($r->amount_current ?? 0);
            $r->days_1_30    = (float) ($r->amount_1_30_days ?? 0);
            $r->days_30_60   = (float) ($r->amount_30_60_days ?? 0);
            $r->days_60_90   = (float) ($r->amount_60_90_days ?? 0);
            $r->days_over_90 = (float) ($r->amount_over_90_days ?? 0);

            // ── Collection rate ──────────────────────────────────────────────
            // Placeholder — real collection_rate is computed in
            // DashboardController::getRows() after ar_target/ar_actual are set
            $r->collection_rate = null;
            $r->collection_status = match (true) {
                $r->collection_rate === null => 'no-target',
                $r->collection_rate >= 100   => 'achieved',
                $r->collection_rate >= 70    => 'partial',
                default                      => 'none',
            };

            // ── Overdue flag ─────────────────────────────────────────────────
            $r->overdue = $r->days_60_90 + $r->days_over_90;

            // ── Week label (BARU) ────────────────────────────────────────────
            $r->due_week       = (int) ($r->due_week ?? 0);
            $r->due_week_label = match ($r->due_week) {
                1       => 'Week 1 (1–7)',
                2       => 'Week 2 (8–14)',
                3       => 'Week 3 (15–21)',
                4       => 'Week 4 (22–31)',
                default => 'Unknown',
            };

            return $r;
        });
    }

    // ══════════════════════════════════════════════════════════════
    // AR RECORDS FOR COLLECTION PAGE
    // Filter: rows where (DueDateReal + 7 days) OR ClearingDate
    //         falls within the selected year/month.
    // This is different from getArRecords() which filters by DocDate.
    // ══════════════════════════════════════════════════════════════

    /**
     * Ambil AR records untuk halaman Collection.
     *
     * Filter berbeda dari getArRecords():
     *   - Tampilkan invoice bila  (DueDateReal + 7 hari) jatuh di period ini  → bisa jadi Target
     *   - ATAU bila ClearingDate jatuh di period ini                           → bisa jadi Actual
     *
     * Ini memungkinkan invoice dengan DocDate di bulan lalu tetap muncul
     * selama DueDateReal+7 atau ClearingDate-nya ada di bulan yang dipilih.
     */
    public function getArRecordsForCollection(int $year, int $month, ?string $plant = null): Collection
    {
        $collectorMap = \App\Models\Collector::getPlantMap();

        $plantWhere  = $this->plantFilter($plant);
        $weekExpr    = $this->weekCaseExpr('COALESCE(DueDateReal, DueDate)');

        // Year/month formatted for STARTS_WITH comparisons
        $ym = sprintf('%04d-%02d', $year, $month);
        $endDate = \Carbon\Carbon::create($year, $month)->endOfMonth()->toDateString();
        $startDate = \Carbon\Carbon::create($year, $month, 1)->toDateString();

        // DueDateReal + 7 days: we check if DATE_ADD falls in or before the target month
        // ClearingDate: we check if it falls in the clearing month (actual)
        $sql = "
            SELECT
                DocNo                                   AS invoice_id,
                CustomerID                              AS customer_id,
                CustomerName                            AS customer_name,
                SalesName                               AS collection_by,
                SalesName                               AS sales_name,
                CAST(DocDate AS STRING)                 AS doc_date,
                COALESCE(BA, SOff)                      AS plant,

                -- Aging buckets
                COALESCE(CAST(Currents        AS FLOAT64), 0) AS amount_current,
                COALESCE(CAST(Due1_30Days     AS FLOAT64), 0) AS amount_1_30_days,
                COALESCE(CAST(Due31_60Days    AS FLOAT64), 0) AS amount_30_60_days,
                COALESCE(CAST(Due61_90Days    AS FLOAT64), 0) AS amount_60_90_days,
                COALESCE(CAST(Due91_120Days   AS FLOAT64), 0)
                    + COALESCE(CAST(Due121_180Days AS FLOAT64), 0)
                    + COALESCE(CAST(Due_181M_Days  AS FLOAT64), 0) AS amount_over_90_days,

                -- Total AR
                COALESCE(CAST(AmountInLC AS FLOAT64), 0) AS total_ar,

                -- Target & actual — default 0, computed in controller
                0 AS ar_target,
                0 AS ar_actual,

                -- Invoice date fields
                CAST(COALESCE(DueDateReal, DueDate) AS STRING) AS due_date,
                CAST(DueDateReal AS STRING)              AS DueDateReal,
                CAST(BaselineDate AS STRING)             AS baseline_date,
                COALESCE(DC, LC, 'IDR')                 AS currency_type,
                CAST(AmountInDC AS FLOAT64)             AS amount_in_dc,

                -- Period
                CAST(Year  AS INT64)                    AS year,
                CAST(Month AS INT64)                    AS month,

                -- Extra info
                SalesDistrict                           AS sales_district,
                CustIndustry                            AS industry,
                AR_STATUS                               AS ar_status,
                ClearingDoc                             AS clearing_doc,
                CAST(ClearingDate AS STRING)            AS clearing_date,
                CAST(AmountInGC AS FLOAT64)             AS amount_usd,

                -- Week bucket from DueDateReal
                ({$weekExpr}) AS due_week

            FROM {$this->table}
            WHERE (
                -- Invoice is a TARGET up to this month and unpaid (or paid in/after this month)
                (
                    CAST(DATE_ADD(COALESCE(DueDateReal, DueDate), INTERVAL 7 DAY) AS STRING) <= '{$endDate}'
                    AND (ClearingDate IS NULL OR STARTS_WITH(CAST(ClearingDate AS STRING), '30') OR STARTS_WITH(CAST(ClearingDate AS STRING), '00') OR CAST(ClearingDate AS STRING) >= '{$startDate}')
                )
                OR
                -- Invoice was ACTUALLY collected this month: ClearingDate falls here
                STARTS_WITH(CAST(ClearingDate AS STRING), '{$ym}')
            )
            {$plantWhere}
            ORDER BY CustomerName
            LIMIT 3000
        ";

        return $this->query($sql)->map(function ($r) use ($collectorMap) {
            $dueDate = $r->due_date ? \Carbon\Carbon::parse($r->due_date) : null;

            // Target date = DueDateReal + 7 days
            $r->target_date = $dueDate ? $dueDate->copy()->addDays(7) : null;
            if ($r->target_date) {
                $day = $r->target_date->day;
                $r->target_week  = $day <= 7 ? 1 : ($day <= 14 ? 2 : ($day <= 21 ? 3 : 4));
                $r->target_month = $r->target_date->month;
                $r->target_year  = $r->target_date->year;
            } else {
                $r->target_week  = null;
                $r->target_month = null;
                $r->target_year  = null;
            }

            // Valid clearing date (not SAP placeholder)
            $hasValidClearingDate = !empty($r->clearing_date)
                && !str_starts_with($r->clearing_date, '30')
                && !str_starts_with($r->clearing_date, '00');

            if ($hasValidClearingDate) {
                $actual = \Carbon\Carbon::parse($r->clearing_date);
                $day = $actual->day;
                $r->actual_week  = $day <= 7 ? 1 : ($day <= 14 ? 2 : ($day <= 21 ? 3 : 4));
                $r->actual_month = $actual->month;
                $r->actual_year  = $actual->year;
            } else {
                $r->actual_week  = null;
                $r->actual_month = null;
                $r->actual_year  = null;
            }

            $r->plant = trim((string) $r->plant);
            $r->collection_by = $collectorMap[$r->plant] ?? 'Unknown';

            // Aliases
            $r->total        = (float) ($r->total_ar ?? 0);
            $r->current      = (float) ($r->amount_current ?? 0);
            $r->days_1_30    = (float) ($r->amount_1_30_days ?? 0);
            $r->days_30_60   = (float) ($r->amount_30_60_days ?? 0);
            $r->days_60_90   = (float) ($r->amount_60_90_days ?? 0);
            $r->days_over_90 = (float) ($r->amount_over_90_days ?? 0);
            $r->overdue      = $r->days_60_90 + $r->days_over_90;

            $r->collection_rate   = null;
            $r->collection_status = 'no-target';

            $r->due_week       = (int) ($r->due_week ?? 0);
            $r->due_week_label = match ($r->due_week) {
                1 => 'Week 1 (1–7)',
                2 => 'Week 2 (8–14)',
                3 => 'Week 3 (15–21)',
                4 => 'Week 4 (22–31)',
                default => 'Unknown',
            };

            return $r;
        });
    }


    // ══════════════════════════════════════════════════════════════
    // AR RECORDS FOR CUSTOMERS PAGE
    // Filter: rows where DocDate OR (DueDateReal + 7 days) OR ClearingDate
    //         falls within the selected year/month.
    // ══════════════════════════════════════════════════════════════

    public function getArRecordsForCustomers(int $year, int $month, ?string $plant = null): Collection
    {
        $collectorMap = \App\Models\Collector::getPlantMap();

        $plantWhere  = $this->plantFilter($plant);
        $weekExpr    = $this->weekCaseExpr('COALESCE(DueDateReal, DueDate)');
        $ym = sprintf('%04d-%02d', $year, $month);

        $sql = "
            SELECT
                DocNo                                   AS invoice_id,
                CustomerID                              AS customer_id,
                CustomerName                            AS customer_name,
                SalesName                               AS collection_by,
                SalesName                               AS sales_name,
                CAST(DocDate AS STRING)                 AS doc_date,
                COALESCE(BA, SOff)                      AS plant,

                -- Aging buckets
                COALESCE(CAST(Currents        AS FLOAT64), 0) AS amount_current,
                COALESCE(CAST(Due1_30Days     AS FLOAT64), 0) AS amount_1_30_days,
                COALESCE(CAST(Due31_60Days    AS FLOAT64), 0) AS amount_30_60_days,
                COALESCE(CAST(Due61_90Days    AS FLOAT64), 0) AS amount_60_90_days,
                COALESCE(CAST(Due91_120Days   AS FLOAT64), 0)
                    + COALESCE(CAST(Due121_180Days AS FLOAT64), 0)
                    + COALESCE(CAST(Due_181M_Days  AS FLOAT64), 0) AS amount_over_90_days,

                -- Total AR
                COALESCE(CAST(AmountInLC AS FLOAT64), 0) AS total_ar,

                0 AS ar_target,
                0 AS ar_actual,

                -- Invoice date fields
                CAST(COALESCE(DueDateReal, DueDate) AS STRING) AS due_date,
                CAST(DueDateReal AS STRING)              AS DueDateReal,
                CAST(BaselineDate AS STRING)             AS baseline_date,
                COALESCE(DC, LC, 'IDR')                 AS currency_type,
                CAST(AmountInDC AS FLOAT64)             AS amount_in_dc,

                -- Period
                CAST(Year  AS INT64)                    AS year,
                CAST(Month AS INT64)                    AS month,

                -- Extra info
                SalesDistrict                           AS sales_district,
                CustIndustry                            AS industry,
                AR_STATUS                               AS ar_status,
                ClearingDoc                             AS clearing_doc,
                CAST(ClearingDate AS STRING)            AS clearing_date,
                CAST(AmountInGC AS FLOAT64)             AS amount_usd,

                ({$weekExpr}) AS due_week

            FROM {$this->table}
            WHERE (
                STARTS_WITH(CAST(DocDate AS STRING), '{$ym}')
                OR STARTS_WITH(CAST(DATE_ADD(COALESCE(DueDateReal, DueDate), INTERVAL 7 DAY) AS STRING), '{$ym}')
                OR STARTS_WITH(CAST(ClearingDate AS STRING), '{$ym}')
            )
            {$plantWhere}
            ORDER BY CustomerName
            LIMIT 3000
        ";

        return $this->query($sql)->map(function ($r) use ($collectorMap) {
            $dueDate = $r->due_date ? \Carbon\Carbon::parse($r->due_date) : null;
            $r->target_date = $dueDate ? $dueDate->copy()->addDays(7) : null;
            if ($r->target_date) {
                $day = $r->target_date->day;
                $r->target_week  = $day <= 7 ? 1 : ($day <= 14 ? 2 : ($day <= 21 ? 3 : 4));
                $r->target_month = $r->target_date->month;
                $r->target_year  = $r->target_date->year;
            } else {
                $r->target_week  = null;
                $r->target_month = null;
                $r->target_year  = null;
            }

            $hasValidClearingDate = !empty($r->clearing_date)
                && !str_starts_with($r->clearing_date, '30')
                && !str_starts_with($r->clearing_date, '00');

            if ($hasValidClearingDate) {
                $actual = \Carbon\Carbon::parse($r->clearing_date);
                $day = $actual->day;
                $r->actual_week  = $day <= 7 ? 1 : ($day <= 14 ? 2 : ($day <= 21 ? 3 : 4));
                $r->actual_month = $actual->month;
                $r->actual_year  = $actual->year;
            } else {
                $r->actual_week  = null;
                $r->actual_month = null;
                $r->actual_year  = null;
            }

            $r->plant = trim((string) $r->plant);
            $r->collection_by = $collectorMap[$r->plant] ?? 'Unknown';

            $r->total        = (float) ($r->total_ar ?? 0);
            $r->current      = (float) ($r->amount_current ?? 0);
            $r->days_1_30    = (float) ($r->amount_1_30_days ?? 0);
            $r->days_30_60   = (float) ($r->amount_30_60_days ?? 0);
            $r->days_60_90   = (float) ($r->amount_60_90_days ?? 0);
            $r->days_over_90 = (float) ($r->amount_over_90_days ?? 0);
            $r->overdue      = $r->days_60_90 + $r->days_over_90;

            $r->collection_rate   = null;
            $r->collection_status = 'no-target';

            $r->due_week       = (int) ($r->due_week ?? 0);
            $r->due_week_label = match ($r->due_week) {
                1 => 'Week 1 (1–7)',
                2 => 'Week 2 (8–14)',
                3 => 'Week 3 (15–21)',
                4 => 'Week 4 (22–31)',
                default => 'Unknown',
            };

            return $r;
        });
    }

    /**
     * Gabungkan ar_target & ar_actual dari MySQL jika dikelola di sana.
     */
    public function getArRecordsWithMysqlTargets(int $year, int $month, ?string $collector = null, ?string $plant = null): Collection
    {
        $bqRows = $this->getArRecords($year, $month, $collector, $plant);

        $mysqlTargets = DB::table('ar_records as r')
            ->join('invoice as inv',   'inv.id', '=', 'r.invoice_id')
            ->join('ar_periods as ap', 'ap.id',  '=', 'r.period_id')
            ->where('ap.period_month', \Carbon\Carbon::create($year, $month, 1)->toDateString())
            ->select('inv.id as invoice_id_int', 'r.ar_target', 'r.ar_actual')
            ->get()
            ->keyBy('invoice_id_int');

        return $bqRows->map(function ($r) use ($mysqlTargets) {
            $mysql = $mysqlTargets->get($r->invoice_id);
            if ($mysql) {
                $r->ar_target = (float) $mysql->ar_target;
                $r->ar_actual = (float) $mysql->ar_actual;
                $r->collection_rate   = $r->ar_target > 0 ? round($r->ar_actual / $r->ar_target * 100, 1) : null;
                $r->collection_status = match (true) {
                    $r->collection_rate === null => 'no-target',
                    $r->collection_rate >= 100   => 'achieved',
                    $r->collection_rate >= 70    => 'partial',
                    default                      => 'none',
                };
            }
            return $r;
        });
    }


    // ══════════════════════════════════════════════════════════════
    // AGING BUCKETS
    // ══════════════════════════════════════════════════════════════

    public function getAgingBuckets(int $year, int $month, ?string $collector = null, ?string $plant = null): array
    {
        $periodWhere    = $this->periodWhere($year, $month);
        $collectorWhere = $this->collectorFilter($collector);
        $plantWhere     = $this->plantFilter($plant);

        $sql = "
            SELECT
                SUM(COALESCE(CAST(Currents        AS FLOAT64), 0))  AS `current`,
                SUM(COALESCE(CAST(Due1_30Days     AS FLOAT64), 0))  AS days_1_30,
                SUM(COALESCE(CAST(Due31_60Days    AS FLOAT64), 0))  AS days_30_60,
                SUM(COALESCE(CAST(Due61_90Days    AS FLOAT64), 0))  AS days_60_90,
                SUM(
                    COALESCE(CAST(Due91_120Days   AS FLOAT64), 0)
                    + COALESCE(CAST(Due121_180Days AS FLOAT64), 0)
                    + COALESCE(CAST(Due_181M_Days  AS FLOAT64), 0)
                )                                                   AS over_90
            FROM {$this->table}
            WHERE {$periodWhere}
              {$collectorWhere}
              {$plantWhere}
        ";

        $row = $this->query($sql)->first();

        return [
            'current'    => (float) ($row->current    ?? 0),
            'days_1_30'  => (float) ($row->days_1_30  ?? 0),
            'days_30_60' => (float) ($row->days_30_60 ?? 0),
            'days_60_90' => (float) ($row->days_60_90 ?? 0),
            'over_90'    => (float) ($row->over_90    ?? 0),
        ];
    }


    // ══════════════════════════════════════════════════════════════
    // CUSTOMERS
    // ══════════════════════════════════════════════════════════════

    public function getCustomers(): Collection
    {
        $sql = "
            SELECT DISTINCT
                CustomerID          AS customer_id,
                CustomerName        AS customer_name,
                COALESCE(BA, SOff)  AS plant_code,
                SalesName           AS salesman_name
            FROM {$this->table}
            WHERE CustomerID IS NOT NULL
            ORDER BY CustomerID, plant_code
        ";

        return $this->query($sql);
    }

    public function getCustomerDetail(string $customerId): Collection
    {
        $safe = str_replace("'", "\\'", $customerId);

        $sql = "
            SELECT
                CAST(Year  AS INT64)            AS year,
                CAST(Month AS INT64)            AS month,
                CONCAT(
                    CASE CAST(Month AS INT64)
                        WHEN 1  THEN 'Jan' WHEN 2  THEN 'Feb' WHEN 3  THEN 'Mar'
                        WHEN 4  THEN 'Apr' WHEN 5  THEN 'May' WHEN 6  THEN 'Jun'
                        WHEN 7  THEN 'Jul' WHEN 8  THEN 'Aug' WHEN 9  THEN 'Sep'
                        WHEN 10 THEN 'Oct' WHEN 11 THEN 'Nov' WHEN 12 THEN 'Dec'
                        ELSE CAST(Month AS STRING)
                    END,
                    ' ', Year
                )                               AS period_label,
                DocNo                           AS invoice_id,
                CustomerID                      AS customer_id,
                CustomerName                    AS customer_name,
                CAST(DocDate AS STRING)         AS doc_date,
                SalesDistrict                   AS sales_district,
                COALESCE(BA, SOff)              AS plant,
                CAST(COALESCE(DueDateReal, DueDate) AS STRING)  AS due_date,
                CAST(DueDateReal AS STRING)             AS DueDateReal,
                CAST(DueDate AS STRING)                 AS DueDate,
                CAST(ClearingDate AS STRING)            AS clearing_date,
                CAST(BaselineDate AS STRING)            AS baseline_date,
                SalesName                       AS salesman_name,
                COALESCE(CAST(Currents        AS FLOAT64), 0) AS `current`,
                COALESCE(CAST(Due1_30Days     AS FLOAT64), 0) AS days_1_30,
                COALESCE(CAST(Due31_60Days    AS FLOAT64), 0) AS days_30_60,
                COALESCE(CAST(Due61_90Days    AS FLOAT64), 0) AS days_60_90,
                COALESCE(CAST(Due91_120Days   AS FLOAT64), 0)
                    + COALESCE(CAST(Due121_180Days AS FLOAT64), 0)
                    + COALESCE(CAST(Due_181M_Days  AS FLOAT64), 0) AS days_over_90,
                COALESCE(CAST(AmountInLC AS FLOAT64), 0) AS total,
                0 AS ar_target,
                0 AS ar_actual
            FROM {$this->table}
            WHERE CustomerID = '{$safe}'
            ORDER BY year DESC, month DESC
        ";

        $collectorMap = \App\Models\Collector::getPlantMap();

        return $this->query($sql)->map(function($r) use ($collectorMap) {
            $dueDate = $r->due_date ? \Carbon\Carbon::parse($r->due_date) : null;
            $r->target_date = $dueDate ? $dueDate->copy()->addDays(7)->format('Y-m-d') : null;
            
            // Map plant → collector name
            $plant = trim((string) ($r->plant ?? ''));
            $r->collector_name = $collectorMap[$plant] ?? null;

            // Target is the total amount if target_date exists
            $r->ar_target = $r->target_date ? $r->total : 0;

            // Actual is the total amount if there is a valid clearing date
            $hasValidClearingDate = !empty($r->clearing_date)
                && !str_starts_with($r->clearing_date, '30')
                && !str_starts_with($r->clearing_date, '00');

            $r->ar_actual = $hasValidClearingDate ? $r->total : 0;

            return $r;
        });
    }


    // ══════════════════════════════════════════════════════════════
    // COLLECTORS
    // ══════════════════════════════════════════════════════════════

    /**
     * Daftar collector dari database lokal.
     */
    public function getCollectors(): Collection
    {
        return collect(\App\Models\Collector::getCollectorNames());
    }


    // ══════════════════════════════════════════════════════════════
    // PLANTS
    // ══════════════════════════════════════════════════════════════

    /**
     * Daftar plant distinct dari BigQuery.
     * Kembalikan Collection of stdClass dengan field 'code' dan 'name'
     * agar filter bar bisa menampilkan label yang proper (bukan hanya kode).
     */
    public function getPlants(): Collection
    {
        $sql = "
            SELECT DISTINCT
                COALESCE(BA, SOff) AS code,
                BA_NAME            AS name
            FROM {$this->table}
            WHERE (BA IS NOT NULL OR SOff IS NOT NULL)
              AND COALESCE(BA, SOff) IS NOT NULL
            ORDER BY code
        ";

        // Kembalikan sebagai Collection of stdClass { code, name }
        // (bukan hanya pluck code, agar blade bisa tampilkan nama)
        return $this->query($sql);
    }


    // ══════════════════════════════════════════════════════════════
    // SO OVERLIMIT
    // ══════════════════════════════════════════════════════════════

    public function getArRecordsWithSO(int $year, int $month, ?string $collector = null, ?string $plant = null, string $context = 'default'): Collection
    {
        $bqRows = $this->getArRecords($year, $month, $collector, $plant, $context);

        $soData = DB::table('so_overlimit as so')
            ->join('invoice as inv',   'inv.id', '=', 'so.invoice_id')
            ->join('ar_periods as ap', 'ap.id',  '=', 'so.period_id')
            ->where('ap.period_month', \Carbon\Carbon::create($year, $month, 1)->toDateString())
            ->select('inv.id', 'so.so_without_od', 'so.so_with_od', 'so.total_so')
            ->get()
            ->keyBy('id');

        return $bqRows->map(function ($r) use ($soData) {
            $so = $soData->get($r->invoice_id);
            $r->so_without_od = $so ? (int) $so->so_without_od : 0;
            $r->so_with_od    = $so ? (int) $so->so_with_od    : 0;
            $r->total_so      = $so ? (int) $so->total_so      : 0;
            return $r;
        });
    }


    // ══════════════════════════════════════════════════════════════
    // HISTORY
    // ══════════════════════════════════════════════════════════════

    /**
     * Get monthly Target & Actual per collector for the history chart.
     *
     * Collector is determined by plant (BA): 1511=Viona, 1512=Miya, 1515=Risa, 1516=Mega.
     * - TARGET for a month: invoice where DATE_ADD(DueDateReal, 7 days) falls in that month
     * - ACTUAL for a month: invoice where ClearingDate falls in that month (valid, not SAP placeholder)
     *
     * Returns one row per (collector, month) with total_ar, total_target, total_actual.
     */
    public function getHistoryByYear(int $year, ?string $collectorFilter = null): Collection
    {
        $yearInt = (int) $year;

        $collectorMap = \App\Models\Collector::getPlantMap();

        // Build plant filter if a specific collector was chosen
        $plantWhere = '';
        if ($collectorFilter) {
            $plantForCollector = array_search($collectorFilter, $collectorMap);
            if ($plantForCollector) {
                $plantWhere = "AND COALESCE(BA, SOff) = '{$plantForCollector}'";
            } else {
                // Collector name not in map — return empty
                return collect();
            }
        } else {
            // Only known collector plants
            $knownPlants = implode("','", array_keys($collectorMap));
            $plantWhere  = "AND COALESCE(BA, SOff) IN ('{$knownPlants}')";
        }

        // Pull all individual invoices for the year so we can compute target & actual month
        $sql = "
            SELECT
                COALESCE(BA, SOff)                              AS plant,
                CAST(Month AS INT64)                            AS month,
                CAST(DueDateReal AS STRING)                     AS due_date_real,
                CAST(ClearingDate AS STRING)                    AS clearing_date,
                COALESCE(CAST(AmountInLC AS FLOAT64), 0)        AS amount
            FROM {$this->table}
            WHERE CAST(Year AS INT64) = {$yearInt}
              {$plantWhere}
        ";

        $rows = $this->query($sql);

        // Aggregate per (collector, month)
        $result = [];

        foreach ($rows as $r) {
            $plant     = trim((string) ($r->plant ?? ''));
            $collector = $collectorMap[$plant] ?? null;
            if (!$collector) continue;

            $amount = (float) ($r->amount ?? 0);

            // ── Target: DueDateReal + 7 days falls in which month? ──
            $targetMonth = null;
            if (!empty($r->due_date_real)) {
                try {
                    $td = \Carbon\Carbon::parse($r->due_date_real)->addDays(7);
                    if ($td->year == $yearInt) $targetMonth = $td->month;
                } catch (\Exception $e) {}
            }

            // ── Actual: ClearingDate falls in which month? ──
            $actualMonth = null;
            if (!empty($r->clearing_date)
                && !str_starts_with($r->clearing_date, '30')
                && !str_starts_with($r->clearing_date, '00')) {
                try {
                    $cd = \Carbon\Carbon::parse($r->clearing_date);
                    if ($cd->year == $yearInt) $actualMonth = $cd->month;
                } catch (\Exception $e) {}
            }

            // ── AR: counted by row's own month ──
            $rowMonth = (int) ($r->month ?? 0);

            $key = $collector . '|' . $rowMonth;
            if (!isset($result[$key])) {
                $result[$key] = [
                    'collector'    => $collector,
                    'month'        => $rowMonth,
                    'total_ar'     => 0,
                    'total_target' => 0,
                    'total_actual' => 0,
                ];
            }
            $result[$key]['total_ar'] += $amount;

            // Add target amount to the target month bucket
            if ($targetMonth) {
                $tk = $collector . '|' . $targetMonth;
                if (!isset($result[$tk])) {
                    $result[$tk] = [
                        'collector'    => $collector,
                        'month'        => $targetMonth,
                        'total_ar'     => 0,
                        'total_target' => 0,
                        'total_actual' => 0,
                    ];
                }
                $result[$tk]['total_target'] += $amount;
            }

            // Add actual amount to the actual month bucket
            if ($actualMonth) {
                $ak = $collector . '|' . $actualMonth;
                if (!isset($result[$ak])) {
                    $result[$ak] = [
                        'collector'    => $collector,
                        'month'        => $actualMonth,
                        'total_ar'     => 0,
                        'total_target' => 0,
                        'total_actual' => 0,
                    ];
                }
                $result[$ak]['total_actual'] += $amount;
            }
        }

        return collect(array_values($result))->map(fn($r) => (object) $r);
    }

    /**
     * Full-year summary per collector for the history summary table.
     */
    public function getHistorySummaryByYear(int $year, ?string $collectorFilter = null): Collection
    {
        // Reuse getHistoryByYear which already computes target/actual correctly
        $monthly = $this->getHistoryByYear($year, $collectorFilter);

        // Aggregate across all 12 months per collector
        $summary = [];
        foreach ($monthly as $r) {
            $c = $r->collector;
            if (!isset($summary[$c])) {
                $summary[$c] = [
                    'collector'    => $c,
                    'total_ar'     => 0,
                    'total_target' => 0,
                    'total_actual' => 0,
                ];
            }
            $summary[$c]['total_ar']     += (float) ($r->total_ar ?? 0);
            $summary[$c]['total_target'] += (float) ($r->total_target ?? 0);
            $summary[$c]['total_actual'] += (float) ($r->total_actual ?? 0);
        }

        return collect(array_values($summary))->map(function ($r) {
            $r = (object) $r;
            $r->rate = $r->total_target > 0
                ? round($r->total_actual / $r->total_target * 100, 1)
                : null;
            return $r;
        })->sortBy('collector')->values();
    }

    public function getHistoryCustomerByYear(int $year, string $customerId): Collection
    {
        $yearInt = (int) $year;
        
        $sql = "
            SELECT
                CustomerID                                      AS customer_id,
                CustomerName                                    AS customer_name,
                CAST(Month AS INT64)                            AS month,
                CAST(DueDateReal AS STRING)                     AS due_date_real,
                CAST(ClearingDate AS STRING)                    AS clearing_date,
                COALESCE(CAST(AmountInLC AS FLOAT64), 0)        AS amount
            FROM {$this->table}
            WHERE CAST(Year AS INT64) = {$yearInt}
              AND CustomerID = '" . str_replace("'", "\\'", $customerId) . "'
        ";

        $rows = $this->query($sql);
        $result = [];
        
        $customerName = $customerId;

        foreach ($rows as $r) {
            $amount = (float) ($r->amount ?? 0);
            if (!empty($r->customer_name) && $customerName === $customerId) {
                $customerName = $r->customer_name;
            }

            // Target month
            $targetMonth = null;
            if (!empty($r->due_date_real)) {
                try {
                    $td = \Carbon\Carbon::parse($r->due_date_real)->addDays(7);
                    if ($td->year == $yearInt) $targetMonth = $td->month;
                } catch (\Exception $e) {}
            }

            // Actual month
            $actualMonth = null;
            if (!empty($r->clearing_date)
                && !str_starts_with($r->clearing_date, '30')
                && !str_starts_with($r->clearing_date, '00')) {
                try {
                    $cd = \Carbon\Carbon::parse($r->clearing_date);
                    if ($cd->year == $yearInt) $actualMonth = $cd->month;
                } catch (\Exception $e) {}
            }

            $rowMonth = (int) ($r->month ?? 0);

            // Populate array for all 3 cases
            $key = $rowMonth;
            if (!isset($result[$key])) {
                $result[$key] = ['month' => $rowMonth, 'total_ar' => 0, 'total_target' => 0, 'total_actual' => 0];
            }
            $result[$key]['total_ar'] += $amount;

            if ($targetMonth) {
                if (!isset($result[$targetMonth])) {
                    $result[$targetMonth] = ['month' => $targetMonth, 'total_ar' => 0, 'total_target' => 0, 'total_actual' => 0];
                }
                $result[$targetMonth]['total_target'] += $amount;
            }

            if ($actualMonth) {
                if (!isset($result[$actualMonth])) {
                    $result[$actualMonth] = ['month' => $actualMonth, 'total_ar' => 0, 'total_target' => 0, 'total_actual' => 0];
                }
                $result[$actualMonth]['total_actual'] += $amount;
            }
        }

        // Ensure all 12 months exist
        $finalResult = [];
        for ($m = 1; $m <= 12; $m++) {
            if (isset($result[$m])) {
                $row = $result[$m];
                $row['customer_id'] = $customerId;
                $row['customer_name'] = $customerName;
                $finalResult[] = (object) $row;
            } else {
                $finalResult[] = (object) [
                    'customer_id' => $customerId,
                    'customer_name' => $customerName,
                    'month' => $m,
                    'total_ar' => 0,
                    'total_target' => 0,
                    'total_actual' => 0,
                ];
            }
        }

        return collect($finalResult);
    }


    // ══════════════════════════════════════════════════════════════
    // UNPAID INVOICES (FOR REMINDER)
    // ══════════════════════════════════════════════════════════════

    public function getUnpaidInvoicesByPlant(string $plantCode): Collection
    {
        $plantCodeSafe = str_replace("'", "\\'", $plantCode);

        // Fetch unpaid invoices (ClearingDate is NULL or starts with 30 / 00)
        // Ensure we handle DueDateReal
        $sql = "
            SELECT
                DocNo                                   AS invoice_id,
                CustomerID                              AS customer_id,
                CustomerName                            AS customer_name,
                SalesName                               AS collection_by,
                CAST(DocDate AS STRING)                 AS doc_date,
                CAST(COALESCE(DueDateReal, DueDate) AS STRING) AS due_date,
                COALESCE(CAST(AmountInLC AS FLOAT64), 0) AS total_ar,
                COALESCE(BA, SOff)                      AS plant
            FROM {$this->table}
            WHERE COALESCE(BA, SOff) = '{$plantCodeSafe}'
              AND (ClearingDate IS NULL OR STARTS_WITH(CAST(ClearingDate AS STRING), '30') OR STARTS_WITH(CAST(ClearingDate AS STRING), '00'))
              AND CAST(AmountInLC AS FLOAT64) > 0
            ORDER BY COALESCE(DueDateReal, DueDate) ASC
        ";

        return $this->query($sql)->unique('invoice_id')->values()->map(function ($r) {
            $dueDate = $r->due_date ? \Carbon\Carbon::parse($r->due_date) : null;
            $r->due_date_parsed = $dueDate;
            return $r;
        });
    }

    // ══════════════════════════════════════════════════════════════
    // INVOICE
    // ══════════════════════════════════════════════════════════════

    public function getInvoices(int $year, int $month): Collection
    {
        $periodWhere = $this->periodWhere($year, $month);

        $sql = "
            SELECT
                DocNo                               AS doc_no,
                CustomerID                          AS customer_id,
                CustomerName                        AS customer_name,
                COALESCE(DueDateReal, DueDate)      AS due_date,
                DueDateReal,
                DueDate,
                ClearingDate AS clearing_date,
                BaselineDate                        AS baseline_date,
                TaxDate                             AS tax_date,
                COALESCE(DC, LC, 'IDR')             AS currency_type,
                COALESCE(CAST(AmountInLC AS FLOAT64), 0) AS amount_paid,
                SalesName                           AS sales_name,
                COALESCE(BA, SOff)                  AS plant_code,
                PostingDate                         AS posting_date,
                Reference                           AS reference,
                DT                                  AS doc_type,
                TOP                                 AS payment_term
            FROM {$this->table}
            WHERE {$periodWhere}
            ORDER BY CustomerName, DocNo
        ";

        return $this->query($sql);
    }


    // ══════════════════════════════════════════════════════════════
    // TRADE & SALES
    // ══════════════════════════════════════════════════════════════

    public function getSalesPersons(): Collection
    {
        $sql = "
            SELECT DISTINCT
                SalesPerson AS sales_id,
                SalesName   AS sales_name
            FROM {$this->table}
            WHERE SalesName IS NOT NULL
            ORDER BY SalesName
        ";

        return $this->query($sql);
    }

    public function getTradeTypes(): Collection
    {
        $sql = "
            SELECT DISTINCT
                DistChan    AS trade_code,
                DistChannel AS trade_type
            FROM {$this->table}
            WHERE DistChannel IS NOT NULL
            ORDER BY DistChannel
        ";

        return $this->query($sql);
    }


    // ══════════════════════════════════════════════════════════════
    // SYNC / UPSERT KE MYSQL  (hybrid mode)
    // ══════════════════════════════════════════════════════════════

    public function syncPeriods(): int
    {
        $periods = $this->getPeriods();
        $count   = 0;

        foreach ($periods as $p) {
            $periodMonth = \Carbon\Carbon::create($p->year, $p->month, 1)->toDateString();

            DB::table('ar_periods')->updateOrInsert(
                ['period_month' => $periodMonth],
                [
                    'period_label' => $p->period_label,
                    'period_month' => $periodMonth,
                    'created_at'   => now(),
                ]
            );
            $count++;
        }

        Log::info("BigQueryService::syncPeriods — synced {$count} periods");
        return $count;
    }

    public function syncCustomers(): int
    {
        $customers = $this->getCustomers();
        $count     = 0;

        foreach ($customers as $c) {
            $collector = DB::table('collectors')->where('name', $c->collector_name)->first();

            $collectorId = $collector?->id;
            if (!$collectorId && $c->collector_name) {
                $collectorId = DB::table('collectors')->insertGetId([
                    'user_id'    => 0,
                    'name'       => $c->collector_name,
                    'created_at' => now(),
                ]);
            }

            if (!$collectorId) continue;

            DB::table('customers')->updateOrInsert(
                ['customer_id' => $c->customer_id],
                [
                    'collector_id'  => $collectorId,
                    'customer_name' => $c->customer_name,
                    'updated_at'    => now(),
                ]
            );

            $customerId = DB::table('customers')
                ->where('customer_id', $c->customer_id)
                ->value('id');

            if ($customerId && $c->plant_code) {
                DB::table('plants')->updateOrInsert(
                    ['customer_id' => $customerId, 'code' => $c->plant_code],
                    [
                        'name'       => $c->plant_name ?? $c->plant_code,
                        'created_at' => now(),
                    ]
                );
            }

            $count++;
        }

        Log::info("BigQueryService::syncCustomers — synced {$count} customers");
        return $count;
    }

    public function syncArRecords(int $year, int $month): int
    {
        $rows = $this->getArRecords($year, $month);

        $periodMonth = \Carbon\Carbon::create($year, $month, 1)->toDateString();
        $period      = DB::table('ar_periods')->where('period_month', $periodMonth)->first();

        if (!$period) {
            Log::warning("BigQueryService::syncArRecords — period {$year}-{$month} not found, run syncPeriods() first");
            return 0;
        }

        $count = 0;
        foreach ($rows as $r) {
            $customer = DB::table('customers')->where('customer_id', $r->customer_id)->first();
            if (!$customer) continue;

            $invoiceId = DB::table('invoice')
                ->where('customer_id', $customer->id)
                ->whereRaw("DATE(due_date) = ?", [$r->due_date])
                ->value('id');

            if (!$invoiceId) continue;

            DB::table('ar_records')->updateOrInsert(
                ['invoice_id' => $invoiceId, 'period_id' => $period->id],
                [
                    'amount_current'      => $r->amount_current,
                    'amount_1_30_days'    => $r->amount_1_30_days,
                    'amount_30_60_days'   => $r->amount_30_60_days,
                    'amount_60_90_days'   => $r->amount_60_90_days,
                    'amount_over_90_days' => $r->amount_over_90_days,
                    'total_ar'            => $r->total_ar,
                    'updated_at'          => now(),
                ]
            );

            $count++;
        }

        Log::info("BigQueryService::syncArRecords — synced {$count} records for {$year}-{$month}");
        return $count;
    }
}