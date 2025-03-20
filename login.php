<?php
/**
 * Page de connexion du gestionnaire de configuration Nginx
 * 
 * Cette page gère le formulaire de connexion et le processus d'authentification.
 * L'utilisateur doit se connecter en tant que root pour accéder à l'application.
 * 
 * @author Votre nom
 * @version 1.0
 */

// Inclure le fichier d'authentification
require_once 'auth.php';

// Initialiser les variables
$error = '';
$expired = false;

// Vérifier si l'utilisateur est déjà authentifié
if (isAuthenticated()) {
    // Rediriger vers la page principale si l'utilisateur est déjà connecté
    header('Location: index.php');
    exit;
}

// Vérifier si la session a expiré
if (isset($_GET['expired']) && $_GET['expired'] == 1) {
    $expired = true;
}

// Traiter le formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les identifiants du formulaire
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Vérifier que les champs ne sont pas vides
    if (empty($username) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        // Vérifier les identifiants
        if (verifyCredentials($username, $password)) {
            // Connecter l'utilisateur et stocker les informations en session
            loginUser($username, $password);
            
            // Rediriger vers la page principale
            header('Location: index.php');
            exit;
        } else {
            // Afficher un message d'erreur si les identifiants sont incorrects
            $error = 'Identifiants invalides. Veuillez réessayer.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Gestionnaire de Configuration Nginx</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Conteneur principal centré avec responsive design -->
    <div class="min-h-screen flex items-center justify-center px-4">
        <!-- Carte de connexion -->
        <div class="max-w-md w-full bg-white rounded-lg shadow-md p-8">
            <!-- En-tête de la carte -->
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-800">Gestionnaire de Configuration Nginx</h1>
                <p class="text-gray-600 mt-2">Connexion administrateur</p>
            </div>
            
            <!-- Affichage des messages d'erreur -->
            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Affichage du message de session expirée -->
            <?php if ($expired): ?>
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                    Votre session a expiré. Veuillez vous reconnecter.
                </div>
            <?php endif; ?>
            
            <!-- Formulaire de connexion -->
            <form method="post" class="space-y-6">
                <!-- Champ nom d'utilisateur -->
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="root">
                </div>
                
                <!-- Champ mot de passe -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                    <input type="password" id="password" name="password" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                
                <!-- Bouton de connexion -->
                <div>
                    <button type="submit"
                            class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Se connecter
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 