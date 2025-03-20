<?php
/**
 * Fichier des fonctions principales de nginx-manager
 * 
 * Ce fichier contient toutes les fonctions utilitaires nécessaires au fonctionnement
 * de l'application nginx-manager, notamment les fonctions de gestion des fichiers,
 * des configurations Nginx et des opérations système.
 * 
 * @author Votre nom
 * @version 1.0
 */

// Définition des chemins par défaut pour la configuration Nginx
// Ces variables sont déclarées comme globales pour pouvoir être utilisées dans toutes les fonctions
$nginxSitesAvailable = '/etc/nginx/sites-available';
$nginxSitesEnabled = '/etc/nginx/sites-enabled';

/**
 * Écrit un message de débogage dans un fichier journal
 * 
 * Cette fonction permet de journaliser les messages de débogage pendant le
 * fonctionnement de l'application. Utile pour le dépannage.
 * 
 * @param string $message Le message à journaliser
 * @return void
 */
function debug_log($message) {
    // Définir le chemin du fichier journal
    $logFile = '/tmp/nginx-manager-error.log';
    
    // Formater le message avec la date et l'heure
    $formattedMessage = date('[Y-m-d H:i:s]') . " $message\n";
    
    // Écrire le message dans le fichier journal
    file_put_contents($logFile, $formattedMessage, FILE_APPEND);
}

/**
 * Sanitize un chemin de fichier ou de répertoire
 * 
 * Cette fonction nettoie un chemin pour éviter les injections ou les problèmes de sécurité,
 * en éliminant les caractères potentiellement dangereux.
 * 
 * @param string $path Le chemin à nettoyer
 * @return string Le chemin nettoyé
 */
function sanitizePath($path) {
    // Supprimer les espaces en début et fin de chaîne
    $path = trim($path);
    
    // Supprimer les séquences de caractères potentiellement dangereuses
    $path = str_replace(['../', '..\\', './', '.\\', '&&', ';', '|'], '', $path);
    
    // Supprimer tout ce qui n'est pas un caractère de chemin valide
    $path = preg_replace('/[^a-zA-Z0-9\/\._-]/', '', $path);
    
    return $path;
}

/**
 * Sanitize un nom de fichier
 * 
 * Cette fonction nettoie un nom de fichier pour éviter les problèmes de sécurité,
 * en supprimant les caractères non autorisés dans un nom de fichier.
 * 
 * @param string $filename Le nom de fichier à nettoyer
 * @return string Le nom de fichier nettoyé
 */
function sanitizeFileName($filename) {
    // Supprimer les espaces en début et fin de chaîne
    $filename = trim($filename);
    
    // Supprimer les caractères non autorisés dans un nom de fichier
    $filename = preg_replace('/[^a-zA-Z0-9\._-]/', '', $filename);
    
    return $filename;
}

/**
 * Explore un répertoire de manière récursive pour trouver tous les sous-répertoires
 * 
 * Cette fonction parcourt un répertoire et tous ses sous-répertoires pour créer
 * une liste complète de la structure des dossiers.
 * 
 * @param string $baseDir Le répertoire de base à explorer
 * @return array Un tableau contenant tous les chemins de répertoires trouvés
 */
function scanDirectoryRecursive($baseDir) {
    debug_log("Début du scan récursif pour $baseDir");
    
    // Initialiser le tableau des résultats avec le répertoire de base
    $result = [$baseDir];
    
    // Vérifier que le répertoire existe et est accessible
    if (!is_dir($baseDir) || !is_readable($baseDir)) {
        debug_log("Erreur: $baseDir n'est pas un répertoire valide ou n'est pas accessible en lecture");
        return $result;
    }
    
    try {
        // Créer un nouvel objet RecursiveDirectoryIterator
        $dirIterator = new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS);
        
        // Créer un nouvel objet RecursiveIteratorIterator
        $iterator = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::SELF_FIRST);
        
        // Parcourir tous les éléments
        foreach ($iterator as $item) {
            // Ne prendre que les répertoires
            if ($item->isDir()) {
                $path = $item->getPathname();
                
                // Exclure le répertoire nginx-manager lui-même pour éviter les problèmes d'accès
                if (strpos($path, '/var/www/html/nginx-manager') === false) {
                    $result[] = $path;
                }
            }
        }
    } catch (Exception $e) {
        // En cas d'erreur, journaliser l'exception
        debug_log("Exception lors du scan récursif: " . $e->getMessage());
    }
    
    debug_log("Fin du scan récursif, " . count($result) . " répertoires trouvés");
    return $result;
}

/**
 * Récupère la liste des configurations Nginx existantes
 * 
 * Cette fonction lit les fichiers de configuration dans sites-available
 * et extrait les informations pertinentes (nom, chemin racine, port).
 * 
 * @return array Un tableau contenant les configurations Nginx trouvées
 */
function getNginxConfigurations() {
    global $nginxSitesAvailable;
    
    $configs = [];
    debug_log("Lecture des configurations dans $nginxSitesAvailable");
    
    // Vérifier que le répertoire existe et est accessible
    if (!is_dir($nginxSitesAvailable) || !is_readable($nginxSitesAvailable)) {
        debug_log("Erreur: $nginxSitesAvailable n'est pas un répertoire valide ou n'est pas accessible en lecture");
        return $configs;
    }
    
    // Parcourir les fichiers du répertoire
    $files = scandir($nginxSitesAvailable);
    
    foreach ($files as $file) {
        // Ignorer les entrées spéciales (. et ..) et les fichiers cachés
        if ($file === '.' || $file === '..' || substr($file, 0, 1) === '.') {
            continue;
        }
        
        // Construire le chemin complet du fichier
        $configFile = "$nginxSitesAvailable/$file";
        
        // Vérifier que c'est un fichier et qu'il est lisible
        if (is_file($configFile) && is_readable($configFile)) {
            // Lire le contenu du fichier
            $content = file_get_contents($configFile);
            
            // Extraire le chemin racine (root) à l'aide d'une expression régulière
            $rootPattern = '/root\s+([^;]+);/';
            $rootMatches = [];
            preg_match($rootPattern, $content, $rootMatches);
            $root = isset($rootMatches[1]) ? trim($rootMatches[1]) : '';
            
            // Extraire le port à l'aide d'une expression régulière
            $portPattern = '/listen\s+(\d+);/';
            $portMatches = [];
            preg_match($portPattern, $content, $portMatches);
            $port = isset($portMatches[1]) ? $portMatches[1] : '';
            
            // Ajouter la configuration au tableau des résultats
            $configs[] = [
                'name' => $file,
                'root' => $root,
                'port' => $port
            ];
        }
    }
    
    debug_log("Lecture terminée, " . count($configs) . " configurations trouvées");
    return $configs;
}

/**
 * Génère et enregistre une configuration Nginx pour un répertoire spécifique
 * 
 * Cette fonction crée un nouveau fichier de configuration Nginx avec les paramètres fournis,
 * puis crée un lien symbolique pour l'activer.
 * 
 * @param string $path Le chemin du répertoire pour lequel créer la configuration
 * @param int $port Le port sur lequel le serveur doit écouter
 * @param int $autoindex Activer (1) ou désactiver (0) l'autoindex
 * @param string $custom404 Chemin d'une page 404 personnalisée (optionnel)
 * @return array Résultat de l'opération (succès/échec et message)
 */
function saveNginxConfig($path, $port, $autoindex, $custom404 = '') {
    global $nginxSitesAvailable, $nginxSitesEnabled;
    
    debug_log("Enregistrement d'une configuration pour $path sur le port $port");
    
    // Sanitizer les entrées pour plus de sécurité
    $path = sanitizePath($path);
    $port = filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    $autoindex = $autoindex ? 'on' : 'off';
    $custom404 = !empty($custom404) ? sanitizePath($custom404) : '';
    
    // Vérifier que le chemin existe et est un répertoire
    if (!is_dir($path)) {
        debug_log("Erreur: $path n'est pas un répertoire valide");
        return ['success' => false, 'message' => 'Le chemin spécifié n\'est pas un répertoire valide.'];
    }
    
    // Générer un nom de fichier basé sur le chemin
    $configName = createConfigName($path);
    $configPath = "$nginxSitesAvailable/$configName";
    
    // Vérifier si le port est déjà utilisé
    if (isPortInUse($port, $configName)) {
        debug_log("Erreur: Le port $port est déjà utilisé par une autre configuration");
        return ['success' => false, 'message' => "Le port $port est déjà utilisé par une autre configuration."];
    }
    
    // Générer le contenu de la configuration
    $configContent = generateNginxConfig($path, $port, $autoindex, $custom404);
    
    // Écrire la configuration dans le fichier
    $writeResult = file_put_contents($configPath, $configContent);
    if ($writeResult === false) {
        debug_log("Erreur: Impossible d'écrire la configuration dans $configPath");
        return ['success' => false, 'message' => 'Impossible d\'écrire le fichier de configuration.'];
    }
    
    // Créer le lien symbolique pour activer la configuration
    $linkTarget = "$nginxSitesEnabled/$configName";
    
    // Supprimer le lien s'il existe déjà
    if (file_exists($linkTarget)) {
        unlink($linkTarget);
    }
    
    // Créer le nouveau lien
    $linkResult = symlink($configPath, $linkTarget);
    if (!$linkResult) {
        debug_log("Erreur: Impossible de créer le lien symbolique de $configPath vers $linkTarget");
        return ['success' => false, 'message' => 'Impossible de créer le lien symbolique pour activer la configuration.'];
    }
    
    debug_log("Configuration enregistrée avec succès dans $configPath et activée via $linkTarget");
    return ['success' => true];
}

/**
 * Crée un nom de fichier de configuration basé sur le chemin du répertoire
 * 
 * Cette fonction génère un nom de fichier unique et sécurisé pour
 * une configuration Nginx basée sur le chemin du répertoire.
 * 
 * @param string $path Le chemin du répertoire
 * @return string Le nom du fichier de configuration
 */
function createConfigName($path) {
    // Remplacer les caractères spéciaux par des tirets
    $name = preg_replace('/[^a-zA-Z0-9]/', '-', $path);
    
    // Supprimer les tirets multiples
    $name = preg_replace('/-+/', '-', $name);
    
    // Supprimer les tirets en début et fin de chaîne
    $name = trim($name, '-');
    
    // S'assurer que le nom ne dépasse pas une longueur raisonnable
    if (strlen($name) > 50) {
        $name = substr($name, 0, 50);
    }
    
    // Ajouter un suffixe unique basé sur le timestamp pour éviter les collisions
    $name .= '-' . time();
    
    return $name;
}

/**
 * Vérifie si un port est déjà utilisé par une autre configuration
 * 
 * Cette fonction parcourt les configurations existantes pour vérifier
 * si le port spécifié est déjà utilisé.
 * 
 * @param int $port Le port à vérifier
 * @param string $excludeConfig Nom de configuration à exclure de la vérification
 * @return bool True si le port est déjà utilisé, False sinon
 */
function isPortInUse($port, $excludeConfig = '') {
    $configs = getNginxConfigurations();
    
    foreach ($configs as $config) {
        // Ne pas vérifier la configuration à exclure (utile lors des mises à jour)
        if ($config['name'] === $excludeConfig) {
            continue;
        }
        
        // Vérifier si le port correspond
        if ($config['port'] == $port) {
            return true;
        }
    }
    
    return false;
}

/**
 * Génère le contenu d'un fichier de configuration Nginx
 * 
 * Cette fonction crée le contenu complet d'un fichier de configuration Nginx
 * avec les paramètres spécifiés.
 * 
 * @param string $path Le chemin du répertoire à servir
 * @param int $port Le port d'écoute
 * @param string $autoindex 'on' ou 'off' pour l'autoindex
 * @param string $custom404 Chemin d'une page 404 personnalisée (optionnel)
 * @return string Le contenu de la configuration
 */
function generateNginxConfig($path, $port, $autoindex, $custom404 = '') {
    // Début du bloc server
    $config = "server {\n";
    $config .= "    listen $port;\n";
    $config .= "    server_name localhost;\n\n";
    
    // Définition du répertoire racine
    $config .= "    root $path;\n";
    $config .= "    index index.html index.htm index.php;\n\n";
    
    // Configuration de l'autoindex
    $config .= "    autoindex $autoindex;\n\n";
    
    // Configuration de la page 404 personnalisée si spécifiée
    if (!empty($custom404) && file_exists($custom404)) {
        $config .= "    error_page 404 $custom404;\n\n";
    }
    
    // Configuration pour les fichiers PHP
    $config .= "    location ~ \.php$ {\n";
    $config .= "        include snippets/fastcgi-php.conf;\n";
    $config .= "        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;\n";
    $config .= "    }\n\n";
    
    // Configuration pour les fichiers .htaccess
    $config .= "    location ~ /\.ht {\n";
    $config .= "        deny all;\n";
    $config .= "    }\n";
    
    // Fin du bloc server
    $config .= "}\n";
    
    return $config;
}

/**
 * Supprime une configuration Nginx existante
 * 
 * Cette fonction supprime le fichier de configuration et son lien symbolique,
 * désactivant ainsi le site.
 * 
 * @param string $configName Le nom du fichier de configuration à supprimer
 * @return array Résultat de l'opération (succès/échec et message)
 */
function deleteNginxConfig($configName) {
    global $nginxSitesAvailable, $nginxSitesEnabled;
    
    debug_log("Suppression de la configuration $configName");
    
    // Sanitizer le nom de fichier pour plus de sécurité
    $configName = sanitizeFileName($configName);
    
    // Construire les chemins complets
    $configPath = "$nginxSitesAvailable/$configName";
    $linkPath = "$nginxSitesEnabled/$configName";
    
    // Vérifier que le fichier existe
    if (!file_exists($configPath)) {
        debug_log("Erreur: $configPath n'existe pas");
        return ['success' => false, 'message' => 'La configuration spécifiée n\'existe pas.'];
    }
    
    // Supprimer le lien symbolique s'il existe
    if (file_exists($linkPath)) {
        $unlinkResult = unlink($linkPath);
        if (!$unlinkResult) {
            debug_log("Erreur: Impossible de supprimer le lien symbolique $linkPath");
            return ['success' => false, 'message' => 'Impossible de désactiver la configuration.'];
        }
    }
    
    // Supprimer le fichier de configuration
    $deleteResult = unlink($configPath);
    if (!$deleteResult) {
        debug_log("Erreur: Impossible de supprimer le fichier $configPath");
        return ['success' => false, 'message' => 'Impossible de supprimer le fichier de configuration.'];
    }
    
    debug_log("Configuration $configName supprimée avec succès");
    return ['success' => true];
}

/**
 * Met à jour le port d'une configuration Nginx existante
 * 
 * Cette fonction modifie le port d'écoute dans un fichier de configuration Nginx existant.
 * 
 * @param string $configName Nom de la configuration
 * @param int $newPort Nouveau port à utiliser
 * @return array Résultat de l'opération
 */
function updateNginxConfigPort($configName, $newPort) {
    global $nginxSitesAvailable;
    
    debug_log("Mise à jour du port pour la configuration $configName vers $newPort");
    
    // Vérifier que le fichier existe
    $configFile = "$nginxSitesAvailable/$configName";
    if (!file_exists($configFile)) {
        debug_log("Erreur: $configFile n'existe pas");
        return ['success' => false, 'message' => 'Configuration non trouvée.'];
    }
    
    // Vérifier si le port est déjà utilisé par une autre configuration
    if (isPortInUse($newPort, $configName)) {
        debug_log("Erreur: Le port $newPort est déjà utilisé par une autre configuration");
        return ['success' => false, 'message' => "Le port $newPort est déjà utilisé par une autre configuration."];
    }
    
    // Lire le contenu actuel
    $content = file_get_contents($configFile);
    if ($content === false) {
        debug_log("Erreur: Impossible de lire le contenu de $configFile");
        return ['success' => false, 'message' => 'Impossible de lire la configuration.'];
    }
    
    // Remplacer le port dans la directive "listen"
    $pattern = '/listen\s+(\d+);/';
    $replacement = "listen $newPort;";
    $newContent = preg_replace($pattern, $replacement, $content);
    
    // Vérifier si un remplacement a été effectué
    if ($newContent === $content) {
        debug_log("Erreur: Aucune directive de port trouvée ou le port est déjà configuré à $newPort");
        return ['success' => false, 'message' => 'Aucune directive de port trouvée ou le port est déjà configuré.'];
    }
    
    // Écrire le nouveau contenu
    $result = file_put_contents($configFile, $newContent);
    if ($result === false) {
        debug_log("Erreur: Impossible d'écrire dans $configFile");
        return ['success' => false, 'message' => 'Impossible d\'écrire la nouvelle configuration.'];
    }
    
    debug_log("Port de la configuration $configName mis à jour avec succès vers $newPort");
    return ['success' => true];
}

/**
 * Recharge la configuration Nginx pour appliquer les modifications
 * 
 * Cette fonction exécute la commande 'nginx -t' pour tester la configuration,
 * puis 'systemctl reload nginx' pour l'appliquer si le test est réussi.
 * 
 * @return array Résultat de l'opération (succès/échec et message)
 */
function reloadNginx() {
    debug_log("Rechargement de la configuration Nginx");
    
    // Tester la configuration avant de la recharger
    $testOutput = [];
    $testReturnVar = 0;
    
    exec('nginx -t 2>&1', $testOutput, $testReturnVar);
    
    // Si le test échoue, retourner l'erreur
    if ($testReturnVar !== 0) {
        $errorMessage = implode("\n", $testOutput);
        debug_log("Erreur lors du test de la configuration: $errorMessage");
        return ['success' => false, 'message' => "Erreur lors du test de la configuration: $errorMessage"];
    }
    
    // Recharger la configuration
    $reloadOutput = [];
    $reloadReturnVar = 0;
    
    exec('systemctl reload nginx 2>&1', $reloadOutput, $reloadReturnVar);
    
    // Si le rechargement échoue, retourner l'erreur
    if ($reloadReturnVar !== 0) {
        $errorMessage = implode("\n", $reloadOutput);
        debug_log("Erreur lors du rechargement de Nginx: $errorMessage");
        return ['success' => false, 'message' => "Erreur lors du rechargement de Nginx: $errorMessage"];
    }
    
    debug_log("Configuration Nginx rechargée avec succès");
    return ['success' => true];
} 