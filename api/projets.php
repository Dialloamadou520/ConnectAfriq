<?php
/**
 * ConnectAfriq - API Projets Collaboratifs
 * Endpoints: list, create, join, my-projects
 */

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        if ($method !== 'GET') sendError('Méthode non autorisée', 405);
        handleList();
        break;
    
    case 'create':
        if ($method !== 'POST') sendError('Méthode non autorisée', 405);
        handleCreate();
        break;
    
    case 'join':
        if ($method !== 'POST') sendError('Méthode non autorisée', 405);
        handleJoin();
        break;
    
    case 'my-projects':
        if ($method !== 'GET') sendError('Méthode non autorisée', 405);
        handleMyProjects();
        break;
    
    default:
        sendError('Action non reconnue', 404);
}

/**
 * Liste des projets
 */
function handleList() {
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        SELECT p.*, u.prenom as createur_prenom, u.nom as createur_nom
        FROM projets p
        JOIN users u ON p.createur_id = u.id
        WHERE p.is_active = TRUE
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
    $projets = $stmt->fetchAll();
    
    sendSuccess($projets);
}

/**
 * Créer un projet
 */
function handleCreate() {
    $auth = requireAuth();
    
    if ($auth['type'] !== 'jeune') {
        sendError('Seuls les jeunes diplômés peuvent créer des projets', 403);
    }
    
    $data = getRequestData();
    
    if (empty($data['titre'])) {
        sendError('Titre du projet requis');
    }
    
    $pdo = getDB();
    
    // Créer le projet
    $stmt = $pdo->prepare("
        INSERT INTO projets (titre, description, createur_id, membres_max)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['titre'],
        $data['description'] ?? null,
        $auth['user_id'],
        $data['membres_max'] ?? 5
    ]);
    
    $projetId = $pdo->lastInsertId();
    
    // Ajouter le créateur comme membre
    $stmt = $pdo->prepare("INSERT INTO projet_membres (projet_id, user_id) VALUES (?, ?)");
    $stmt->execute([$projetId, $auth['user_id']]);
    
    // Ajouter des points
    $stmt = $pdo->prepare("UPDATE users SET points = points + 100 WHERE id = ?");
    $stmt->execute([$auth['user_id']]);
    
    // Récupérer les nouveaux points
    $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
    $stmt->execute([$auth['user_id']]);
    $points = $stmt->fetch()['points'];
    
    sendSuccess([
        'projet_id' => $projetId,
        'points' => $points
    ], 'Projet créé avec succès', 201);
}

/**
 * Rejoindre un projet
 */
function handleJoin() {
    $auth = requireAuth();
    
    if ($auth['type'] !== 'jeune') {
        sendError('Seuls les jeunes diplômés peuvent rejoindre des projets', 403);
    }
    
    $data = getRequestData();
    
    if (empty($data['projet_id'])) {
        sendError('ID projet requis');
    }
    
    $pdo = getDB();
    
    // Vérifier si le projet existe et n'est pas plein
    $stmt = $pdo->prepare("SELECT * FROM projets WHERE id = ? AND is_active = TRUE");
    $stmt->execute([$data['projet_id']]);
    $projet = $stmt->fetch();
    
    if (!$projet) {
        sendError('Projet non trouvé', 404);
    }
    
    if ($projet['membres_actuels'] >= $projet['membres_max']) {
        sendError('Ce projet est complet');
    }
    
    // Vérifier si déjà membre
    $stmt = $pdo->prepare("SELECT id FROM projet_membres WHERE projet_id = ? AND user_id = ?");
    $stmt->execute([$data['projet_id'], $auth['user_id']]);
    if ($stmt->fetch()) {
        sendError('Vous êtes déjà membre de ce projet');
    }
    
    // Ajouter comme membre
    $stmt = $pdo->prepare("INSERT INTO projet_membres (projet_id, user_id) VALUES (?, ?)");
    $stmt->execute([$data['projet_id'], $auth['user_id']]);
    
    // Mettre à jour le nombre de membres
    $stmt = $pdo->prepare("UPDATE projets SET membres_actuels = membres_actuels + 1 WHERE id = ?");
    $stmt->execute([$data['projet_id']]);
    
    // Ajouter des points
    $stmt = $pdo->prepare("UPDATE users SET points = points + 80 WHERE id = ?");
    $stmt->execute([$auth['user_id']]);
    
    // Récupérer les nouveaux points
    $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
    $stmt->execute([$auth['user_id']]);
    $points = $stmt->fetch()['points'];
    
    // Notification
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, titre, message)
        VALUES (?, 'Projet rejoint', ?)
    ");
    $stmt->execute([
        $auth['user_id'],
        "Vous avez rejoint le projet \"{$projet['titre']}\"."
    ]);
    
    sendSuccess([
        'points' => $points
    ], 'Vous avez rejoint le projet', 201);
}

/**
 * Mes projets
 */
function handleMyProjects() {
    $auth = requireAuth();
    
    if ($auth['type'] !== 'jeune') {
        sendError('Réservé aux jeunes diplômés', 403);
    }
    
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        SELECT p.*, pm.joined_at
        FROM projets p
        JOIN projet_membres pm ON p.id = pm.projet_id
        WHERE pm.user_id = ? AND p.is_active = TRUE
        ORDER BY pm.joined_at DESC
    ");
    $stmt->execute([$auth['user_id']]);
    $projets = $stmt->fetchAll();
    
    sendSuccess($projets);
}
