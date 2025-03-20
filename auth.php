<?php
/**
 * Fichier de gestion de l'authentification
 * 
 * Ce fichier gère l'authentification des utilisateurs, notamment l'authentification
 * root nécessaire pour effectuer des modifications sur les configurations Nginx.
 * Il vérifie si l'utilisateur est connecté et fournit les fonctions nécessaires
 * pour la connexion et la vérification d'authentification.
 * 
 * @author Votre nom
 * @version 1.0
 */

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifie si l'utilisateur est authentifié
 * 
 * Cette fonction vérifie si l'utilisateur est connecté en regardant
 * la variable de session 'authenticated'.
 * 
 * @return bool Retourne true si l'utilisateur est authentifié, false sinon
 */
function isAuthenticated() {
    // Vérifier si l'utilisateur est authentifié via la session
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

/**
 * Vérifie les identifiants de connexion
 * 
 * Cette fonction vérifie si le nom d'utilisateur et le mot de passe fournis
 * correspondent à un utilisateur valide. Pour l'instant, seul l'utilisateur root
 * est autorisé à se connecter.
 * 
 * @param string $username Le nom d'utilisateur
 * @param string $password Le mot de passe
 * @return bool Retourne true si les identifiants sont valides, false sinon
 */
function verifyCredentials($username, $password) {
    // Pour l'instant, seul l'utilisateur root est autorisé
    if ($username !== 'root') {
        return false;
    }
    
    // Vérifier le mot de passe root en utilisant le fichier shadow
    return verifyRootPassword($password);
}

/**
 * Vérifie si le mot de passe root est correct
 * 
 * Cette fonction utilise la commande système 'su' pour vérifier
 * si le mot de passe root est valide.
 * 
 * @param string $password Le mot de passe à vérifier
 * @return bool Retourne true si le mot de passe est correct, false sinon
 */
function verifyRootPassword($password) {
    // Échapper le mot de passe pour éviter les injections
    $escapedPassword = escapeshellarg($password);
    
    // Créer une commande test pour vérifier le mot de passe
    // La commande "true" ne fait rien mais permet de tester l'authentification
    $command = "echo $escapedPassword | su -c 'true' 2>/dev/null";
    
    // Exécuter la commande et vérifier le code de retour
    exec($command, $output, $returnVar);
    
    // Si le code de retour est 0, le mot de passe est correct
    return $returnVar === 0;
}

/**
 * Connecte l'utilisateur et stocke ses informations dans la session
 * 
 * Cette fonction marque l'utilisateur comme authentifié et stocke son nom
 * d'utilisateur et mot de passe dans la session pour une utilisation ultérieure.
 * Le mot de passe est nécessaire pour exécuter des commandes en tant que root.
 * 
 * @param string $username Le nom d'utilisateur
 * @param string $password Le mot de passe
 * @return void
 */
function loginUser($username, $password) {
    // Stocker les informations d'authentification dans la session
    $_SESSION['authenticated'] = true;
    $_SESSION['username'] = $username;
    
    // Pour des raisons de sécurité, le mot de passe est également stocké pour
    // permettre l'exécution de commandes en tant que root
    $_SESSION['root_password'] = $password;
    
    // Définir une durée de validité pour la session (2 heures)
    $_SESSION['auth_time'] = time();
    $_SESSION['auth_expiry'] = time() + (2 * 3600); // 2 heures
}

/**
 * Vérifie si la session d'authentification a expiré
 * 
 * Cette fonction vérifie si la session d'authentification a dépassé
 * sa durée de validité.
 * 
 * @return bool Retourne true si la session a expiré, false sinon
 */
function isSessionExpired() {
    // Vérifier si le temps d'expiration est défini et s'il est dépassé
    if (isset($_SESSION['auth_expiry']) && time() > $_SESSION['auth_expiry']) {
        return true;
    }
    return false;
}

/**
 * Déconnecte l'utilisateur en détruisant sa session
 * 
 * Cette fonction supprime toutes les données de session, y compris
 * les informations d'authentification.
 * 
 * @return void
 */
function logoutUser() {
    // Supprimer toutes les variables de session
    $_SESSION = array();
    
    // Détruire le cookie de session
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    
    // Détruire la session
    session_destroy();
}

// Vérifier automatiquement si la session a expiré
if (isAuthenticated() && isSessionExpired()) {
    logoutUser();
    // Rediriger vers la page de connexion si la session a expiré
    if (!strpos($_SERVER['PHP_SELF'], 'login.php')) {
        header('Location: login.php?expired=1');
        exit;
    }
} 