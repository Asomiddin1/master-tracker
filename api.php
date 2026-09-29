<?php
// CORS va JSON sarlavhalari
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$host = "mysql-asomiddin320.alwaysdata.net";
$db   = "asomiddin320_master";
$user = "asomiddin320";
$pass = "2004Asom$"; // <-- Alwaysdata MariaDB parolingizni kiriting

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user,$pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Progress jadvali mavjud bo'lmasa, avtomatik yaratish
    $pdo->exec("CREATE TABLE IF NOT EXISTS `jlpt_progress` (
        `item_key` VARCHAR(64) PRIMARY KEY,
        `done` TINYINT(1) DEFAULT 1,
        `completed_at` VARCHAR(32) NOT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $action =$_GET['action'] ?? '';

    // 1. Bazadan barcha progressni olish (GET)
    if ($action === 'get_progress') {
        $stmt =$pdo->query("SELECT item_key, done, completed_at FROM `jlpt_progress`");
        $rows = $stmt->fetchAll();$state = [];
        foreach ($rows as$r) {
            $state[$r['item_key']] = [
                'done' => (bool)$r['done'],
                'completedAt' => $r['completed_at']
            ];
        }
        echo json_encode(["status" => "success", "state" => $state]);
        exit;
    }

    // 2. Progressni bazaga avtomatik saqlash yoki o'chirish (POST)
    if ($action === 'toggle_progress' && $_SERVER['REQUEST_METHOD'] === 'POST') {$input = json_decode(file_get_contents('php://input'), true);
        $key = trim($input['key'] ?? '');
        $done = !empty($input['done']);
        $completedAt = trim($input['completedAt'] ?? '');

        if (!$key) {
            throw new Exception("Kalit parametri topilmadi");
        }

        if ($done) {
            $stmt =$pdo->prepare("REPLACE INTO `jlpt_progress` (`item_key`, `done`, `completed_at`) VALUES (?, 1, ?)");
            $stmt->execute([$key,$completedAt]);
        } else {
            $stmt =$pdo->prepare("DELETE FROM `jlpt_progress` WHERE `item_key` = ?");
            $stmt->execute([$key]);
        }

        echo json_encode(["status" => "success"]);
        exit;
    }

    // 3. Barcha progressni tozalash (POST)
    if ($action === 'reset_progress' && $_SERVER['REQUEST_METHOD'] === 'POST') {$pdo->exec("TRUNCATE TABLE `jlpt_progress`");
        echo json_encode(["status" => "success"]);
        exit;
    }

    // 4. Bazadagi barcha jadvallar ro'yxatini olish (GET)
    if ($action === 'tables') {
        $stmt =$pdo->query("SHOW TABLES");
        $tables =$stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(["status" => "success", "tables" => $tables]);
        exit;
    }

    // 5. Tanlangan jadval ma'lumotlarini ko'rish (GET)
    if ($action === 'query') {$table = $_GET['table'] ?? '';$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
        if ($limit < 1 || $limit > 500)$limit = 25;

        if (!preg_match('/^[a-zA-Z0-9_]+$/',$table)) {
            throw new Exception("Xavfsiz bo'lmagan jadval nomi!");
        }

        $stmt =$pdo->query("SELECT * FROM `$table` LIMIT $limit");
        $rows =$stmt->fetchAll();

        $columns = [];
        if (!empty($rows)) {
            $columns = array_keys($rows[0]);
        } else {
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

    echo json_encode(["status" => "error", "message" => "Noma'lum buyruq"]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}