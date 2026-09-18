<?php
require __DIR__ . '/vendor/autoload.php';
use Google\Cloud\BigQuery\BigQueryClient;

// Use the service account key, but query a different project's dataset
$bigQuery = new BigQueryClient([
    'projectId' => 'dunia-kimia-jaya-bq-project',
    'keyFilePath' => __DIR__ . '/storage/app/bigquery-key.json'
]);

echo "Querying lautan-luas-big-data-project1.shared_view.VW_DELIVERY_ORDER...\n\n";

try {
    $query = "SELECT * FROM `lautan-luas-big-data-project1.shared_view.VW_DELIVERY_ORDER` LIMIT 5";
    $jobConfig = $bigQuery->query($query);
    $results = $bigQuery->runQuery($jobConfig);

    $rowCount = 0;
    foreach ($results as $row) {
        $rowCount++;
        echo "=== ROW {$rowCount} ===\n";
        foreach ($row as $col => $val) {
            if (is_array($val) || is_object($val)) {
                $val = json_encode($val);
            }
            echo "  {$col}: {$val}\n";
        }
        echo "\n";
    }

    if ($rowCount === 0) {
        echo "No rows returned.\n";
    } else {
        echo "Total rows shown: {$rowCount}\n";
    }

    // Also get total count
    $countQuery = "SELECT COUNT(*) as total FROM `lautan-luas-big-data-project1.shared_view.VW_DELIVERY_ORDER`";
    $countConfig = $bigQuery->query($countQuery);
    $countResults = $bigQuery->runQuery($countConfig);
    foreach ($countResults as $r) {
        echo "\nTotal rows in table: " . $r['total'] . "\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
