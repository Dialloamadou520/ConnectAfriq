<?php
/**
 * ConnectAfriq - API Gamification
 * Endpoints: add-points, leaderboard, badges
 */

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'leaderboard';

switch ($action) {
    case 'add-points':
        if ($method !== 'POST') sendError('Méthode non autorisée', 405);
        handleAddPoints();
        break;
    
    case 'leaderboard':
        if ($method !== 'GET') sendError('Méthode non autorisée', 405);
        handleLeaderboard();
        break;
    
    case 'badges':
        if ($method !== 'GET') sendError('Méthode non autorisée', 405);
        handleBadges();
        break;
    
    default:
        sendError('Action non reconnue', 404);
}

/**
 * Ajouter des points (interne)
 */
function handleAddPoints() {
    $auth = requireAuth();
    
    if ($auth['type'] !== 'jeune') {
        sendError('Réservé aux jeunes diplômés', 403);
    }
    
    $data = getRequestData();
    $points = intval($data['points'] ?? 0);
    
    if ($points <= 0 || $points > 100) {
        sendError('Points invalides (1-100)');
    }
    
    $pdo = getDB();
    
    // Ajouter les points
    $stmt = $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
    $stmt->execute([$points, $auth['user_id']]);
    
    // Récupérer les infos mises à jour
    $stmt = $pdo->prepare("SELECT points, badges FROM users WHERE id = ?");
    $stmt->execute([$auth['user_id']]);
    $user = $stmt->fetch();
    
    // Vérifier les badges
    $badges = json_decode($user['badges'] ?? '[]', true);
    $newBadges = [];
    
    $badgeThresholds = [
        0 => ['points' => 100, 'name' => 'Premier Badge'],
        1 => ['points' => 200, 'name' => 'Étoile Montante'],
        2 => ['points' => 300, 'name' => 'En Feu'],
        3 => ['points' => 500, 'name' => 'Diamant']
    ];
    
    foreach ($badgeThresholds as $id => $badge) {
        if ($user['points'] >= $badge['points'] && !in_array($id, $badges)) {
            $badges[] = $id;
            $newBadges[] = $badge['name'];
        }
    }
    
    if (!empty($newBadges)) {
        $stmt = $pdo->prepare("UPDATE users SET badges = ? WHERE id = ?");
        $stmt->execute([json_encode($badges), $auth['user_id']]);
    }
    
    sendSuccess([
        'points' => $user['points'],
        'badges' => $badges,
        'new_badges' => $newBadges
    ]);
}

/**
 * Leaderboard
 */
function handleLeaderboard() {
    $pdo = getDB();
    
    $limit = min(intval($_GET['limit'] ?? 10), 50);
    
    $stmt = $pdo->prepare("
        SELECT id, prenom, nom, points, badges
        FROM users
        ORDER BY points DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $users = $stmt->fetchAll();
    
    // Formater
    $leaderboard = [];
    foreach ($users as $index => $u) {
        $leaderboard[] = [
            'rank' => $index + 1,
            'prenom' => $u['prenom'],
            'nom' => $u['nom'],
            'points' => $u['points'],
            'badges_count' => count(json_decode($u['badges'] ?? '[]', true))
        ];
    }
    
    sendSuccess($leaderboard);
}

/**
 * Liste des badges disponibles
 */
function handleBadges() {
    $badges = [
        [
            'id' => 0,
            'name' => 'Premier Badge',
            'icon' => '🏆',
            'description' => 'Atteindre 100 points',
            'points_required' => 100
        ],
        [
            'id' => 1,
            'name' => 'Étoile Montante',
            'icon' => '⭐',
            'description' => 'Atteindre 200 points',
            'points_required' => 200
        ],
        [
            'id' => 2,
            'name' => 'En Feu',
            'icon' => '🔥',
            'description' => 'Atteindre 300 points',
            'points_required' => 300
        ],
        [
            'id' => 3,
            'name' => 'Diamant',
            'icon' => '💎',
            'description' => 'Atteindre 500 points',
            'points_required' => 500
        ]
    ];
    
    sendSuccess($badges);
}
