<?php
/**
 * ConnectAfriq - API Événements
 * Endpoints: list, register, my-events
 */

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        if ($method !== 'GET') sendError('Méthode non autorisée', 405);
        handleList();
        break;
    
    case 'register':
        if ($method !== 'POST') sendError('Méthode non autorisée', 405);
        handleRegister();
        break;
    
    case 'my-events':
        if ($method !== 'GET') sendError('Méthode non autorisée', 405);
        handleMyEvents();
        break;
    
    default:
        sendError('Action non reconnue', 404);
}

/**
 * Liste des événements
 */
function handleList() {
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        SELECT * FROM evenements 
        WHERE is_active = TRUE AND date_evenement > NOW()
        ORDER BY date_evenement ASC
    ");
    $stmt->execute();
    $evenements = $stmt->fetchAll();
    
    // Formater les dates
    foreach ($evenements as &$e) {
        $date = new DateTime($e['date_evenement']);
        $e['date_formatted'] = $date->format('d M Y - H\h');
    }
    
    sendSuccess($evenements);
}

/**
 * S'inscrire à un événement
 */
function handleRegister() {
    $auth = requireAuth();
    
    if ($auth['type'] !== 'jeune') {
        sendError('Seuls les jeunes diplômés peuvent s\'inscrire aux événements', 403);
    }
    
    $data = getRequestData();
    
    if (empty($data['evenement_id'])) {
        sendError('ID événement requis');
    }
    
    $pdo = getDB();
    
    // Vérifier si l'événement existe
    $stmt = $pdo->prepare("SELECT * FROM evenements WHERE id = ? AND is_active = TRUE AND date_evenement > NOW()");
    $stmt->execute([$data['evenement_id']]);
    $evenement = $stmt->fetch();
    
    if (!$evenement) {
        sendError('Événement non trouvé ou passé', 404);
    }
    
    // Vérifier si déjà inscrit
    $stmt = $pdo->prepare("SELECT id FROM inscriptions_evenements WHERE user_id = ? AND evenement_id = ?");
    $stmt->execute([$auth['user_id'], $data['evenement_id']]);
    if ($stmt->fetch()) {
        sendError('Vous êtes déjà inscrit à cet événement');
    }
    
    // Inscrire
    $stmt = $pdo->prepare("INSERT INTO inscriptions_evenements (user_id, evenement_id) VALUES (?, ?)");
    $stmt->execute([$auth['user_id'], $data['evenement_id']]);
    
    // Ajouter des points
    $points = $evenement['points_gagnes'];
    $stmt = $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
    $stmt->execute([$points, $auth['user_id']]);
    
    // Récupérer les nouveaux points
    $stmt = $pdo->prepare("SELECT points, badges FROM users WHERE id = ?");
    $stmt->execute([$auth['user_id']]);
    $user = $stmt->fetch();
    
    // Vérifier les badges
    $badges = json_decode($user['badges'] ?? '[]', true);
    $newBadges = [];
    
    if ($user['points'] >= 100 && !in_array(0, $badges)) {
        $badges[] = 0;
        $newBadges[] = 'Premier Badge';
    }
    if ($user['points'] >= 200 && !in_array(1, $badges)) {
        $badges[] = 1;
        $newBadges[] = 'Étoile Montante';
    }
    if ($user['points'] >= 300 && !in_array(2, $badges)) {
        $badges[] = 2;
        $newBadges[] = 'En Feu';
    }
    if ($user['points'] >= 500 && !in_array(3, $badges)) {
        $badges[] = 3;
        $newBadges[] = 'Diamant';
    }
    
    if (!empty($newBadges)) {
        $stmt = $pdo->prepare("UPDATE users SET badges = ? WHERE id = ?");
        $stmt->execute([json_encode($badges), $auth['user_id']]);
    }
    
    // Notification
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, titre, message)
        VALUES (?, 'Inscription confirmée', ?)
    ");
    $stmt->execute([
        $auth['user_id'],
        "Vous êtes inscrit à \"{$evenement['titre']}\"."
    ]);
    
    sendSuccess([
        'points' => $user['points'],
        'points_gagnes' => $points,
        'badges' => $badges,
        'new_badges' => $newBadges
    ], 'Inscription réussie', 201);
}

/**
 * Mes événements
 */
function handleMyEvents() {
    $auth = requireAuth();
    
    if ($auth['type'] !== 'jeune') {
        sendError('Réservé aux jeunes diplômés', 403);
    }
    
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        SELECT e.*, ie.created_at as inscription_date
        FROM evenements e
        JOIN inscriptions_evenements ie ON e.id = ie.evenement_id
        WHERE ie.user_id = ?
        ORDER BY e.date_evenement ASC
    ");
    $stmt->execute([$auth['user_id']]);
    $evenements = $stmt->fetchAll();
    
    sendSuccess($evenements);
}
