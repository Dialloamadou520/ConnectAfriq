<?php
/**
 * ConnectAfriq - API d'authentification
 * Endpoints: register, login, logout, me
 */

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        if ($method !== 'POST') sendError('Méthode non autorisée', 405);
        handleRegister();
        break;
    
    case 'register-entreprise':
        if ($method !== 'POST') sendError('Méthode non autorisée', 405);
        handleRegisterEntreprise();
        break;
    
    case 'login':
        if ($method !== 'POST') sendError('Méthode non autorisée', 405);
        handleLogin();
        break;
    
    case 'logout':
        if ($method !== 'POST') sendError('Méthode non autorisée', 405);
        handleLogout();
        break;
    
    case 'me':
        if ($method !== 'GET') sendError('Méthode non autorisée', 405);
        handleMe();
        break;
    
    case 'update-profile':
        if ($method !== 'POST') sendError('Méthode non autorisée', 405);
        handleUpdateProfile();
        break;
    
    default:
        sendError('Action non reconnue', 404);
}

/**
 * Inscription d'un jeune diplômé
 */
function handleRegister() {
    $data = getRequestData();
    
    // Validation des champs requis
    $required = ['prenom', 'nom', 'email', 'password'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendError("Le champ '$field' est requis");
        }
    }
    
    // Validation de l'email
    if (!isValidEmail($data['email'])) {
        sendError('Adresse email invalide');
    }
    
    // Validation du mot de passe
    if (strlen($data['password']) < 8) {
        sendError('Le mot de passe doit contenir au moins 8 caractères');
    }
    
    $pdo = getDB();
    
    // Vérifier si l'email existe déjà
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        sendError('Cette adresse email est déjà utilisée');
    }
    
    // Insérer le nouvel utilisateur
    $stmt = $pdo->prepare("
        INSERT INTO users (prenom, nom, email, password, telephone, ville, region, diplome, domaine, type_opportunite, competences, points, badges)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 100, '[]')
    ");
    
    $stmt->execute([
        $data['prenom'],
        $data['nom'],
        $data['email'],
        hashPassword($data['password']),
        $data['telephone'] ?? null,
        $data['ville'] ?? null,
        $data['region'] ?? null,
        $data['diplome'] ?? null,
        $data['domaine'] ?? null,
        $data['type_opportunite'] ?? null,
        $data['competences'] ?? null
    ]);
    
    $userId = $pdo->lastInsertId();
    
    // Créer une session
    $token = generateToken();
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_DURATION);
    
    $stmt = $pdo->prepare("
        INSERT INTO sessions (user_id, token, user_type, expires_at)
        VALUES (?, ?, 'jeune', ?)
    ");
    $stmt->execute([$userId, $token, $expiresAt]);
    
    // Créer une notification de bienvenue
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, titre, message)
        VALUES (?, 'Bienvenue sur ConnectAfriq !', 'Votre compte a été créé avec succès. Explorez les opportunités disponibles.')
    ");
    $stmt->execute([$userId]);
    
    sendSuccess([
        'token' => $token,
        'user' => [
            'id' => $userId,
            'prenom' => $data['prenom'],
            'nom' => $data['nom'],
            'email' => $data['email'],
            'points' => 100,
            'badges' => []
        ],
        'type' => 'jeune'
    ], 'Compte créé avec succès', 201);
}

/**
 * Inscription d'une entreprise
 */
function handleRegisterEntreprise() {
    $data = getRequestData();
    
    // Validation des champs requis
    $required = ['nom', 'email', 'password'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendError("Le champ '$field' est requis");
        }
    }
    
    // Validation de l'email
    if (!isValidEmail($data['email'])) {
        sendError('Adresse email invalide');
    }
    
    // Validation du mot de passe
    if (strlen($data['password']) < 8) {
        sendError('Le mot de passe doit contenir au moins 8 caractères');
    }
    
    $pdo = getDB();
    
    // Vérifier si l'email existe déjà
    $stmt = $pdo->prepare("SELECT id FROM entreprises WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        sendError('Cette adresse email est déjà utilisée');
    }
    
    // Générer un avatar à partir du nom
    $words = explode(' ', $data['nom']);
    $avatar = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : substr($words[0], 1, 1)));
    
    // Insérer la nouvelle entreprise
    $stmt = $pdo->prepare("
        INSERT INTO entreprises (nom, contact_nom, contact_poste, email, password, telephone, secteur, ville, description, avatar)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $data['nom'],
        $data['contact_nom'] ?? null,
        $data['contact_poste'] ?? null,
        $data['email'],
        hashPassword($data['password']),
        $data['telephone'] ?? null,
        $data['secteur'] ?? null,
        $data['ville'] ?? null,
        $data['description'] ?? null,
        $avatar
    ]);
    
    $entrepriseId = $pdo->lastInsertId();
    
    // Créer une session
    $token = generateToken();
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_DURATION);
    
    $stmt = $pdo->prepare("
        INSERT INTO sessions (entreprise_id, token, user_type, expires_at)
        VALUES (?, ?, 'entreprise', ?)
    ");
    $stmt->execute([$entrepriseId, $token, $expiresAt]);
    
    sendSuccess([
        'token' => $token,
        'entreprise' => [
            'id' => $entrepriseId,
            'nom' => $data['nom'],
            'email' => $data['email']
        ],
        'type' => 'entreprise'
    ], 'Entreprise enregistrée avec succès', 201);
}

/**
 * Connexion
 */
function handleLogin() {
    $data = getRequestData();
    
    if (empty($data['email']) || empty($data['password'])) {
        sendError('Email et mot de passe requis');
    }
    
    $pdo = getDB();
    $type = $data['type'] ?? 'jeune';
    
    if ($type === 'jeune') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch();
        
        if (!$user || !verifyPassword($data['password'], $user['password'])) {
            sendError('Email ou mot de passe incorrect', 401);
        }
        
        // Créer une session
        $token = generateToken();
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_DURATION);
        
        $stmt = $pdo->prepare("
            INSERT INTO sessions (user_id, token, user_type, expires_at)
            VALUES (?, ?, 'jeune', ?)
        ");
        $stmt->execute([$user['id'], $token, $expiresAt]);
        
        sendSuccess([
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'prenom' => $user['prenom'],
                'nom' => $user['nom'],
                'email' => $user['email'],
                'points' => $user['points'],
                'badges' => json_decode($user['badges'] ?? '[]', true)
            ],
            'type' => 'jeune'
        ], 'Connexion réussie');
        
    } else {
        $stmt = $pdo->prepare("SELECT * FROM entreprises WHERE email = ?");
        $stmt->execute([$data['email']]);
        $entreprise = $stmt->fetch();
        
        if (!$entreprise || !verifyPassword($data['password'], $entreprise['password'])) {
            sendError('Email ou mot de passe incorrect', 401);
        }
        
        // Créer une session
        $token = generateToken();
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_DURATION);
        
        $stmt = $pdo->prepare("
            INSERT INTO sessions (entreprise_id, token, user_type, expires_at)
            VALUES (?, ?, 'entreprise', ?)
        ");
        $stmt->execute([$entreprise['id'], $token, $expiresAt]);
        
        sendSuccess([
            'token' => $token,
            'entreprise' => [
                'id' => $entreprise['id'],
                'nom' => $entreprise['nom'],
                'email' => $entreprise['email']
            ],
            'type' => 'entreprise'
        ], 'Connexion réussie');
    }
}

/**
 * Déconnexion
 */
function handleLogout() {
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? '';
    
    if (strpos($token, 'Bearer ') === 0) {
        $token = substr($token, 7);
    }
    
    if (!empty($token)) {
        $pdo = getDB();
        $stmt = $pdo->prepare("DELETE FROM sessions WHERE token = ?");
        $stmt->execute([$token]);
    }
    
    sendSuccess(null, 'Déconnexion réussie');
}

/**
 * Récupérer les infos de l'utilisateur connecté
 */
function handleMe() {
    $auth = requireAuth();
    
    $pdo = getDB();
    
    if ($auth['type'] === 'jeune') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$auth['user_id']]);
        $user = $stmt->fetch();
        
        // Compter les candidatures
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM candidatures WHERE user_id = ?");
        $stmt->execute([$auth['user_id']]);
        $candidatures = $stmt->fetch()['count'];
        
        // Récupérer les notifications non lues
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$auth['user_id']]);
        $notifications = $stmt->fetchAll();
        
        sendSuccess([
            'user' => [
                'id' => $user['id'],
                'prenom' => $user['prenom'],
                'nom' => $user['nom'],
                'email' => $user['email'],
                'telephone' => $user['telephone'],
                'ville' => $user['ville'],
                'region' => $user['region'],
                'diplome' => $user['diplome'],
                'domaine' => $user['domaine'],
                'type_opportunite' => $user['type_opportunite'],
                'competences' => $user['competences'],
                'points' => $user['points'],
                'badges' => json_decode($user['badges'] ?? '[]', true)
            ],
            'stats' => [
                'candidatures' => $candidatures
            ],
            'notifications' => $notifications,
            'type' => 'jeune'
        ]);
        
    } else {
        $stmt = $pdo->prepare("SELECT * FROM entreprises WHERE id = ?");
        $stmt->execute([$auth['entreprise_id']]);
        $entreprise = $stmt->fetch();
        
        // Compter les candidatures reçues
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM candidatures WHERE entreprise_id = ?");
        $stmt->execute([$auth['entreprise_id']]);
        $candidatures = $stmt->fetch()['count'];
        
        sendSuccess([
            'entreprise' => [
                'id' => $entreprise['id'],
                'nom' => $entreprise['nom'],
                'email' => $entreprise['email'],
                'contact_nom' => $entreprise['contact_nom'],
                'contact_poste' => $entreprise['contact_poste'],
                'telephone' => $entreprise['telephone'],
                'secteur' => $entreprise['secteur'],
                'ville' => $entreprise['ville'],
                'description' => $entreprise['description']
            ],
            'stats' => [
                'candidatures_recues' => $candidatures
            ],
            'type' => 'entreprise'
        ]);
    }
}

/**
 * Mettre à jour le profil
 */
function handleUpdateProfile() {
    $auth = requireAuth();
    $data = getRequestData();
    
    $pdo = getDB();
    
    if ($auth['type'] === 'jeune') {
        $fields = [];
        $values = [];
        
        $allowedFields = ['prenom', 'nom', 'telephone', 'ville', 'region', 'diplome', 'domaine', 'type_opportunite', 'competences'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            sendError('Aucun champ à mettre à jour');
        }
        
        $values[] = $auth['user_id'];
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        sendSuccess(null, 'Profil mis à jour avec succès');
        
    } else {
        $fields = [];
        $values = [];
        
        $allowedFields = ['nom', 'contact_nom', 'contact_poste', 'telephone', 'secteur', 'ville', 'description'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            sendError('Aucun champ à mettre à jour');
        }
        
        $values[] = $auth['entreprise_id'];
        $sql = "UPDATE entreprises SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        sendSuccess(null, 'Profil entreprise mis à jour avec succès');
    }
}
