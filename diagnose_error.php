<?php
require 'PHPs/db.php';
try {
    get_pdo(true);
    file_put_contents('error.txt', "Success: Connected.");
} catch (Exception $e) {
    file_put_contents('error.txt', "Error: " . $e->getMessage());
    exit(1);
}
