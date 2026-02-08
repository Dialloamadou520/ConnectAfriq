<?php
/**
 * ConnectAfriq - API Candidatures
 * Endpoints: apply, list, update-status
 */

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'apply':
        if ($method !== 'POST') sendError('Méthode non autorisée', 405);
        handleApply();
        break;
    
    case 'list':
        if ($method !== 'GET') sendError('Méthode non autorisée', 405);
        handleList();
        break;
    
    case 'update-status':
        if ($method !== 'POST') sendError('Méthode non autorisée', 405);
        handleUpdateStatus();
        break;
    
    default:
        sendError('Action non reconnue', 404);
}

/**
 * Postuler à une entreprise/offre
 */
function handleApply() {
    $auth = requireAuth();
    
    if ($auth['type'] !== 'jeune') {
        sendError('Seuls les jeunes diplômés peuvent postuler', 403);
    }
    
    $data = getRequestData();
    
    if (empty($data['entreprise_id'])) {
        sendError('ID entreprise requis');
    }
    
    $pdo = getDB();
    
    // Vérifier si l'entreprise existe
    $stmt = $pdo->prepare("SELECT id, nom FROM entreprises WHERE id = ? AND is_active = TRUE");
    $stmt->execute([$data['entreprise_id']]);
    $entreprise = $stmt->fetch();
    
    if (!$entreprise) {
        sendError('Entreprise non trouvée', 404);
    }
    
    // Vérifier si une candidature existe déjà
    $stmt = $pdo->prepare("SELECT id FROM candidatures WHERE user_id = ? AND entreprise_id = ?");
    $stmt->execute([$auth['user_id'], $data['entreprise_id']]);
    if ($stmt->fetch()) {
        sendError('Vous avez déjà postulé à cette entreprise');
    }
    
    // Créer la candidature
    $stmt = $pdo->prepare("
        INSERT INTO candidatures (user_id, entreprise_id, offre_id, message)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $auth['user_id'],
        $data['entreprise_id'],
        $data['offre_id'] ?? null,
        $data['message'] ?? null
    ]);
    
    // Ajouter des points
    $stmt = $pdo->prepare("UPDATE users SET points = points + 20 WHERE id = ?");
    $stmt->execute([$auth['user_id']]);
    
    // Récupérer les nouveaux points
    $stmt = $pdo->prepare("SELECT points, badges FROM users WHERE id = ?");
    $stmt->execute([$auth['user_id']]);
    $user = $stmt->fetch();
    
    // Vérifier et débloquer les badges
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
    
    // Créer une notification
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, titre, message)
        VALUES (?, 'Candidature envoyée', ?)
    ");
    $stmt->execute([
        $auth['user_id'],
        "Votre candidature pour {$entreprise['nom']} a été envoyée avec succès."
    ]);
    
    sendSuccess([
        'points' => $user['points'],
        'badges' => $badges,
        'new_badges' => $newBadges
    ], 'Candidature envoyée avec succès', 201);
}

/**
 * Liste des candidatures
 */
function handleList() {
    $auth = requireAuth();
    
    $pdo = getDB();
    
    if ($auth['type'] === 'jeune') {
        // Candidatures de l'utilisateur
        $stmt = $pdo->prepare("
            SELECT c.*, e.nom as entreprise_nom, e.avatar, e.secteur, o.titre as offre_titre
            FROM candidatures c
            JOIN entreprises e ON c.entreprise_id = e.id
            LEFT JOIN offres o ON c.offre_id = o.id
            WHERE c.user_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$auth['user_id']]);
        
    } else {
        // Candidatures reçues par l'entreprise
        $stmt = $pdo->prepare("
            SELECT c.*, u.prenom, u.nom, u.email, u.diplome, u.domaine, u.competences, o.titre as offre_titre
            FROM candidatures c
            JOIN users u ON c.user_id = u.id
            LEFT JOIN offres o ON c.offre_id = o.id
            WHERE c.entreprise_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$auth['entreprise_id']]);
    }
    
    $candidatures = $stmt->fetchAll();
    
    sendSuccess($candidatures);
}

/**
 * Mettre à jour le statut d'une candidature (pour entreprises)
 */
function handleUpdateStatus() {
    $auth = requireAuth();
    
    if ($auth['type'] !== 'entreprise') {
        sendError('Réservé aux entreprises', 403);
    }
    
    $data = getRequestData();
    
    if (empty($data['candidature_id']) || empty($data['statut'])) {
        sendError('ID candidature et statut requis');
    }
    
    $validStatuts = ['en_attente', 'vue', 'acceptee', 'refusee'];
    if (!in_array($data['statut'], $validStatuts)) {
        sendError('Statut invalide');
    }
    
    $pdo = getDB();
    
    // Vérifier que la candidature appartient à cette entreprise
    $stmt = $pdo->prepare("SELECT * FROM candidatures WHERE id = ? AND entreprise_id = ?");
    $stmt->execute([$data['candidature_id'], $auth['entreprise_id']]);
    $candidature = $stmt->fetch();
    
    if (!$candidature) {
        sendError('Candidature non trouvée', 404);
    }
    
    // Mettre à jour le statut
    $stmt = $pdo->prepare("UPDATE candidatures SET statut = ? WHERE id = ?");
    $stmt->execute([$data['statut'], $data['candidature_id']]);
    
    // Notifier l'utilisateur
    $messages = [
        'vue' => 'Votre candidature a été consultée.',
        'acceptee' => 'Félicitations ! Votre candidature a été acceptée.',
        'refusee' => 'Votre candidature n\'a pas été retenue cette fois.'
    ];
    
    if (isset($messages[$data['statut']])) {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, titre, message)
            VALUES (?, 'Mise à jour de candidature', ?)
        ");
        $stmt->execute([$candidature['user_id'], $messages[$data['statut']]]);
        
        // Points bonus si acceptée
        if ($data['statut'] === 'acceptee') {
            $stmt = $pdo->prepare("UPDATE users SET points = points + 100 WHERE id = ?");
            $stmt->execute([$candidature['user_id']]);
        }
    }
    
    sendSuccess(null, 'Statut mis à jour');
}
