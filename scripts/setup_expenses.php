<?php
// Run from CLI only: php scripts/setup_expenses.php
// Preserve the existing login tables and data; do not run php/start.php again.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/php/path.php';
require ROOT_PATH . '/php/config.php';
$schema = file_get_contents(ROOT_PATH . '/database.sql');
$schema = substr($schema, strpos($schema, 'CREATE TABLE categories'));
$schema = str_replace('CREATE TABLE ', 'CREATE TABLE IF NOT EXISTS ', $schema);
foreach (explode(';', $schema) as $sql) {
    if (trim($sql) !== '') { $db_connection->query($sql); }
}
// Seed lookup names only. Never insert the team's sample expenses into real accounts.
$lookup = [
    'categories'=>['category_name',['Food','Transportation','Shopping','Entertainment','Bills','Healthcare']],
    'merchant'=>['merchant_name',['Walmart','Uber','Amazon','Netflix','Target','CVS']],
    'payment_method'=>['method_name',['Credit Card','Debit Card','Cash','Bank Transfer']],
    'currencies'=>['currency',['USD','CAD','EUR']],
];
foreach ($lookup as $table => [$column,$names]) {
    foreach ($names as $name) {
        $stmt=$db_connection->prepare("INSERT INTO $table ($column) SELECT ? WHERE NOT EXISTS (SELECT 1 FROM $table WHERE $column=?)");
        $stmt->bind_param('ss',$name,$name); $stmt->execute();
    }
}
echo "Expense and budget tables are ready; existing data was preserved.\n";
