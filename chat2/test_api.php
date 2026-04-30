<?php
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Direct API test working',
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
