<?php
while (ob_get_level() > 0) @ob_end_clean();
if (function_exists('header_remove')) @header_remove();
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
@error_reporting(E_ALL);
header_register_callback(function() {
    if (function_exists('header_remove')) {
        header_remove('X-Powered-By');
    }
});

function sendJson($payload, $code = 200) {
    while (ob_get_level() > 0) @ob_end_clean();
    if (function_exists('header_remove')) @header_remove();
    @http_response_code($code);
    @header('Content-Type: application/json; charset=utf-8', true);
    @header('X-Content-Type-Options: nosniff', true);
    @header('Cache-Control: no-store, no-cache, must-revalidate', true);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $json = preg_replace('/^\xEF\xBB\xBF+/', '', (string)$json);
    echo trim($json);
    exit();
}

try {
    require_once __DIR__ . '/session_init.php';
    require_once __DIR__ . '/config.php';
} catch (Throwable $e) {
    sendJson(['success' => false, 'message' => 'Init error: ' . $e->getMessage()], 500);
}

if (!isset($_SESSION['user_id'])) {
    sendJson(['success' => false, 'message' => 'Non autorisé'], 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sendJson(['success' => false, 'message' => 'Méthode non autorisée'], 405);
}

$project_id = (int)($_POST['project_id'] ?? 0);
$pc_id = (int)($_POST['pc_id'] ?? 0);
$quantity_used = (int)($_POST['quantity_used'] ?? -1);

if ($project_id <= 0 || $pc_id <= 0 || $quantity_used < 0) {
    sendJson(['success' => false, 'message' => 'Paramètres invalides'], 400);
}

try {
    $pdo = getConnection();
} catch (Throwable $e) {
    sendJson(['success' => false, 'message' => 'DB error: ' . $e->getMessage()], 500);
}

try {
    $stmtCheck = $pdo->prepare("SELECT p.owner, pc.quantity_needed FROM project_components pc JOIN projects p ON p.id = pc.project_id WHERE pc.id = ? AND pc.project_id = ?");
    $stmtCheck->execute([$pc_id, $project_id]);
    $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        sendJson(['success' => false, 'message' => 'Ligne projet non trouvée'], 404);
    }
    if ((int)$row['owner'] !== (int)$_SESSION['user_id']) {
        sendJson(['success' => false, 'message' => 'Non autorisé (propriétaire)'], 403);
    }
    $needed = (int)$row['quantity_needed'];

    if ($quantity_used > $needed) {
        $quantity_used = $needed;
    }
    if ($quantity_used < 0) {
        $quantity_used = 0;
    }

    $stmt = $pdo->prepare("UPDATE project_components SET quantity_used = ? WHERE id = ? AND project_id = ?");
    $stmt->execute([$quantity_used, $pc_id, $project_id]);

    $progress = $needed > 0 ? min(100, round(($quantity_used / $needed) * 100, 1)) : 0;

    sendJson([
        'success' => true,
        'message' => 'Quantité mise à jour',
        'quantity_used' => $quantity_used,
        'quantity_needed' => $needed,
        'progress_percent' => $progress,
        'can_minus' => $quantity_used > 0,
        'can_plus' => $quantity_used < $needed,
    ], 200);
} catch (Throwable $e) {
    sendJson(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()], 500);
}
