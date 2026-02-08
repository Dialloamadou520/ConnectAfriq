<?php
/**
 * ConnectAfriq - API Mentors
 * Endpoints: list, request, my-requests
 */

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        if ($method !== 'GET') sendError('Méthode non autorisée', 405);
        handleList();
        break;
    
    case 'request':
        if ($method !== 'POST') sendError('Méthode non autorisée', 405);
        handleRequest();
        break;
    
    case 'my-requests':
        if ($method !== 'GET') sendError('Méthode non autorisée', 405);
        handleMyRequests();
        break;
    
    default:
        sendError('Action non reconnue', 404);
}

/**
 * Liste des mentors
 */
function handleList() {
    $pdo = getDB();
    
    $stmt = $pdo->prepare("SELECT id, nom, role, avatar, keywords, bio FROM mentors WHERE is_active = TRUE ORDER BY nom");
    $stmt->execute();
    $mentors = $stmt->fetchAll();
    
    // Décoder les keywords JSON
    foreach ($mentors as &$m) {
        $m['keywords'] = json_decode($m['keywords'] ?? '[]', true);
    }
    
    sendSuccess($mentors);
}

/**
 * Demander un mentorat
 */
function handleRequest() {
    $auth = requireAuth();
    
    if ($auth['type'] !== 'jeune') {
        sendError('Seuls les jeunes diplômés peuvent demander un mentorat', 403);
    }
    
    $data = getRequestData();
    
    if (empty($data['mentor_id'])) {
        sendError('ID mentor requis');
    }
    
    $pdo = getDB();
    
    // Vérifier si le mentor existe
    $stmt = $pdo->prepare("SELECT id, nom FROM mentors WHERE id = ? AND is_active = TRUE");
    $stmt->execute([$data['mentor_id']]);
    $mentor = $stmt->fetch();
    
    if (!$mentor) {
        sendError('Mentor non trouvé', 404);
    }
    
    // Vérifier si une demande existe déjà
    $stmt = $pdo->prepare("SELECT id FROM demandes_mentorat WHERE user_id = ? AND mentor_id = ?");
    $stmt->execute([$auth['user_id'], $data['mentor_id']]);
    if ($stmt->fetch()) {
        sendError('Vous avez déjà fait une demande à ce mentor');
    }
    
    // Créer la demande
    $stmt = $pdo->prepare("
        INSERT INTO demandes_mentorat (user_id, mentor_id, message)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([
        $auth['user_id'],
        $data['mentor_id'],
        $data['message'] ?? null
    ]);
    
    // Ajouter des points
    $stmt = $pdo->prepare("UPDATE users SET points = points + 50 WHERE id = ?");
    $stmt->execute([$auth['user_id']]);
    
    // Récupérer les nouveaux points
    $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
    $stmt->execute([$auth['user_id']]);
    $points = $stmt->fetch()['points'];
    
    // Notification
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, titre, message)
        VALUES (?, 'Demande de mentorat', ?)
    ");
    $stmt->execute([
        $auth['user_id'],
        "Votre demande de mentorat à {$mentor['nom']} a été envoyée."
    ]);
    
    sendSuccess([
        'points' => $points
    ], 'Demande de mentorat envoyée', 201);
}

/**
 * Mes demandes de mentorat
 */
function handleMyRequests() {
    $auth = requireAuth();
    
    if ($auth['type'] !== 'jeune') {
        sendError('Réservé aux jeunes diplômés', 403);
    }
    
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        SELECT dm.*, m.nom as mentor_nom, m.role as mentor_role, m.avatar as mentor_avatar
        FROM demandes_mentorat dm
        JOIN mentors m ON dm.mentor_id = m.id
        WHERE dm.user_id = ?
        ORDER BY dm.created_at DESC
    ");
    $stmt->execute([$auth['user_id']]);
    $demandes = $stmt->fetchAll();
    
    sendSuccess($demandes);
}
