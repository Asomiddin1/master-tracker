<?php
// CORS va JSON sozlamalari
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

$host = "mysql-asomiddin320.alwaysdata.net";
$db   = "asomiddin320_master";
$user = "asomiddin320";
$pass = "2004Asom$"; // Alwaysdata MySQL parolingiz

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user,$pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $action =$_GET['action'] ?? '';

    // 1. Jadvallar ro'yxatini olish
    if ($action === 'tables') {
        $stmt =$pdo->query("SHOW TABLES");
        $tables =$stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(["status" => "success", "tables" => $tables]);
        exit;
    }

    // 2. Tanlangan jadvaldan ma'lumotlarni o'qish
    if ($action === 'query') {$table = $_GET['table'] ?? '';$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
        if ($limit < 1 || $limit > 500)$limit = 25;

        // SQL injection dan himoya: faqat harf, son va pastki chiziq
        if (!preg_match('/^[a-zA-Z0-9_]+$/',$table)) {
            throw new Exception("Noto'g'ri jadval nomi");
        }

        $stmt =$pdo->query("SELECT * FROM `$table` LIMIT $limit");
        $rows =$stmt->fetchAll();

        $columns = [];
        if (!empty($rows)) {
            $columns = array_keys($rows[0]);
        } else {
            // Jadval bo'sh bo'lsa ustun nomlarini alohida olamiz
            $colStmt =$pdo->query("DESCRIBE `$table`");
            $columns =$colStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        echo json_encode([
            "status" => "success",
            "columns" => $columns,
            "rows" => array_map('array_values', $rows),
            "total" => count($rows)
        ]);
        exit;
    }

    echo json_encode(["status" => "error", "message" => "Noma'lum amal"]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}