<?php
require __DIR__ . '/vendor/autoload.php';
use Google\Cloud\BigQuery\BigQueryClient;

$bigQuery = new BigQueryClient([
    'projectId' => 'dunia-kimia-jaya-bq-project',
    'keyFilePath' => __DIR__ . '/storage/app/bigquery-key.json'
]);

$datasetsToTry = [
    'sap', 'sap_data', 'sales', 'sales_orders', 'sales_order', 'delivery', 
    'deliveries', 'accounting', 'production', 'prod', 'dev', 'mis', 
    'erp', 'erp_data', 'data', 'dataset', 'public', 'reporting', 'views', 
    'dw', 'dwh', 'dkj', 'dunia_kimia_jaya', 'procurement', 'purchasing', 
    'master', 'master_data', 'so', 'do'
];

echo "Starting bruteforce dataset discovery...\n";

foreach ($datasetsToTry as $dataset) {
    try {
        $query = "SELECT * FROM `dunia-kimia-jaya-bq-project.{$dataset}.VW_DELIVERY_ORDER` LIMIT 1";
        $jobConfig = $bigQuery->query($query);
        $results = $bigQuery->runQuery($jobConfig);
        
        // If we reach here, it worked!
        echo "\nSUCCESS!!! Dataset found: {$dataset}\n";
        
        foreach ($results as $row) {
            echo "Columns available:\n";
            foreach ($row as $col => $val) {
                echo " - {$col}: " . gettype($val) . " (Example: " . print_r($val, true) . ")\n";
            }
        }
        exit(0);
    } catch (\Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Not found: Dataset') === false && strpos($msg, 'Access Denied: Table') === false) {
            echo "Dataset {$dataset} error: " . $msg . "\n";
        }
    }
}
echo "Did not find the dataset among the guesses.\n";
