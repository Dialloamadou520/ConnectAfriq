<?php
/**
 * ConnectAfriq - API Entreprises
 * Endpoints: list, get, search
 */

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        if ($method !== 'GET') sendError('Méthode non autorisée', 405);
        handleList();
        break;
    
    case 'get':
        if ($method !== 'GET') sendError('Méthode non autorisée', 405);
        handleGet();
        break;
    
    case 'search':
        if ($method !== 'GET') sendError('Méthode non autorisée', 405);
        handleSearch();
        break;
    
    case 'offres':
        if ($method !== 'GET') sendError('Méthode non autorisée', 405);
        handleOffres();
        break;
    
    default:
        sendError('Action non reconnue', 404);
}

/**
 * Liste toutes les entreprises
 */
function handleList() {
    $pdo = getDB();
    
    $secteur = $_GET['secteur'] ?? null;
    $ville = $_GET['ville'] ?? null;
    
    $sql = "SELECT id, nom, email, secteur, ville, description, couleur_bg, avatar, contact_nom FROM entreprises WHERE is_active = TRUE";
    $params = [];
    
    if ($secteur && $secteur !== 'tous') {
        $sql .= " AND secteur = ?";
        $params[] = $secteur;
    }
    
    if ($ville) {
        $sql .= " AND ville LIKE ?";
        $params[] = "%$ville%";
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $entreprises = $stmt->fetchAll();
    
    // Ajouter les offres et tags pour chaque entreprise
    foreach ($entreprises as &$e) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM offres WHERE entreprise_id = ? AND is_active = TRUE");
        $stmt->execute([$e['id']]);
        $e['offres_count'] = $stmt->fetch()['count'];
        
        // Générer les tags
        $stmt = $pdo->prepare("SELECT type_contrat, duree, ville FROM offres WHERE entreprise_id = ? AND is_active = TRUE LIMIT 1");
        $stmt->execute([$e['id']]);
        $offre = $stmt->fetch();
        
        $e['tags'] = [];
        if ($offre) {
            if ($offre['duree']) $e['tags'][] = $offre['type_contrat'] . ' ' . $offre['duree'];
            $e['tags'][] = ucfirst($e['secteur']);
            $e['tags'][] = $e['ville'];
        } else {
            $e['tags'] = [ucfirst($e['secteur']), $e['ville'], 'Recrutement'];
        }
        
        $e['type'] = $offre ? $offre['type_contrat'] : 'Emploi';
        $e['bg'] = $e['couleur_bg'];
        unset($e['couleur_bg']);
    }
    
    sendSuccess($entreprises);
}

/**
 * Récupérer une entreprise par ID
 */
function handleGet() {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        sendError('ID entreprise requis');
    }
    
    $pdo = getDB();
    
    $stmt = $pdo->prepare("SELECT * FROM entreprises WHERE id = ? AND is_active = TRUE");
    $stmt->execute([$id]);
    $entreprise = $stmt->fetch();
    
    if (!$entreprise) {
        sendError('Entreprise non trouvée', 404);
    }
    
    // Récupérer les offres
    $stmt = $pdo->prepare("SELECT * FROM offres WHERE entreprise_id = ? AND is_active = TRUE ORDER BY created_at DESC");
    $stmt->execute([$id]);
    $offres = $stmt->fetchAll();
    
    // Masquer le mot de passe
    unset($entreprise['password']);
    
    sendSuccess([
        'entreprise' => $entreprise,
        'offres' => $offres
    ]);
}

/**
 * Rechercher des entreprises
 */
function handleSearch() {
    $query = $_GET['q'] ?? '';
    
    if (strlen($query) < 2) {
        sendError('La recherche doit contenir au moins 2 caractères');
    }
    
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        SELECT id, nom, secteur, ville, description, couleur_bg as bg, avatar
        FROM entreprises 
        WHERE is_active = TRUE 
        AND (nom LIKE ? OR description LIKE ? OR secteur LIKE ? OR ville LIKE ?)
        ORDER BY nom
        LIMIT 20
    ");
    
    $searchTerm = "%$query%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $entreprises = $stmt->fetchAll();
    
    sendSuccess($entreprises);
}

/**
 * Liste des offres d'une entreprise ou toutes les offres
 */
function handleOffres() {
    $pdo = getDB();
    
    $entrepriseId = $_GET['entreprise_id'] ?? null;
    $type = $_GET['type'] ?? null;
    
    $sql = "
        SELECT o.*, e.nom as entreprise_nom, e.avatar, e.couleur_bg as bg, e.ville as entreprise_ville
        FROM offres o
        JOIN entreprises e ON o.entreprise_id = e.id
        WHERE o.is_active = TRUE AND e.is_active = TRUE
    ";
    $params = [];
    
    if ($entrepriseId) {
        $sql .= " AND o.entreprise_id = ?";
        $params[] = $entrepriseId;
    }
    
    if ($type) {
        $sql .= " AND o.type_contrat = ?";
        $params[] = $type;
    }
    
    $sql .= " ORDER BY o.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $offres = $stmt->fetchAll();
    
    sendSuccess($offres);
}
