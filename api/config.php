<?php
/**
 * ConnectAfriq - Configuration de la base de données
 */

// Mode debug (désactiver en production)
define('DEBUG_MODE', true);

// Configuration de la base de données (utilise les variables d'environnement en production)
define('DB_TYPE', getenv('DB_TYPE') ?: 'mysql'); // 'mysql' ou 'pgsql'
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'connectafriq');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_PORT', getenv('DB_PORT') ?: (DB_TYPE === 'pgsql' ? '5432' : '3306'));
define('DB_CHARSET', 'utf8');

// Configuration de l'application
define('APP_NAME', 'ConnectAfriq');
define('APP_URL', 'http://localhost/ConnectAfriq');
define('SESSION_DURATION', 86400 * 7); // 7 jours

// Headers CORS pour les requêtes API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gestion des requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Connexion PDO à la base de données (supporte MySQL et PostgreSQL)
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            if (DB_TYPE === 'pgsql') {
                $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
            } else {
                $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            }
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Pour PostgreSQL, définir le charset
            if (DB_TYPE === 'pgsql') {
                $pdo->exec("SET NAMES 'UTF8'");
            }
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                sendError('Erreur DB: ' . $e->getMessage(), 500);
            }
            sendError('Erreur de connexion à la base de données', 500);
        }
    }
    
    return $pdo;
}

// Fonction pour envoyer une réponse JSON de succès
function sendSuccess($data = null, $message = 'Succès', $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Fonction pour envoyer une réponse JSON d'erreur
function sendError($message = 'Erreur', $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Fonction pour récupérer les données JSON de la requête
function getRequestData() {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    return $data ?? [];
}

// Fonction pour valider un email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Fonction pour hasher un mot de passe
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Fonction pour vérifier un mot de passe
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Fonction pour générer un token de session
function generateToken() {
    return bin2hex(random_bytes(32));
}

// Fonction pour vérifier l'authentification
function checkAuth() {
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? '';
    
    if (empty($token)) {
        return null;
    }
    
    // Retirer le préfixe "Bearer " si présent
    if (strpos($token, 'Bearer ') === 0) {
        $token = substr($token, 7);
    }
    
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT s.*, u.id as user_id, u.prenom, u.nom, u.email, u.points, u.badges
        FROM sessions s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.token = ? AND s.expires_at > NOW() AND s.user_type = 'jeune'
    ");
    $stmt->execute([$token]);
    $session = $stmt->fetch();
    
    if ($session) {
        return [
            'type' => 'jeune',
            'user_id' => $session['user_id'],
            'prenom' => $session['prenom'],
            'nom' => $session['nom'],
            'email' => $session['email'],
            'points' => $session['points'],
            'badges' => json_decode($session['badges'] ?? '[]', true)
        ];
    }
    
    // Vérifier si c'est une entreprise
    $stmt = $pdo->prepare("
        SELECT s.*, e.id as entreprise_id, e.nom, e.email
        FROM sessions s
        LEFT JOIN entreprises e ON s.entreprise_id = e.id
        WHERE s.token = ? AND s.expires_at > NOW() AND s.user_type = 'entreprise'
    ");
    $stmt->execute([$token]);
    $session = $stmt->fetch();
    
    if ($session) {
        return [
            'type' => 'entreprise',
            'entreprise_id' => $session['entreprise_id'],
            'nom' => $session['nom'],
            'email' => $session['email']
        ];
    }
    
    return null;
}

// Fonction pour exiger l'authentification
function requireAuth() {
    $auth = checkAuth();
    if (!$auth) {
        sendError('Non autorisé. Veuillez vous connecter.', 401);
    }
    return $auth;
}
