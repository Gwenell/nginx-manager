<?php
session_start();
require_once 'auth.php';
require_once 'functions.php';

// Vérifier l'authentification
if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

// Message de démarrage pour le débogage
debug_log("Début du chargement de l'application nginx-manager");

// Paramètres de base
$baseDir = '/var/www/html';
$nginxSitesAvailable = '/etc/nginx/sites-available';
$nginxSitesEnabled = '/etc/nginx/sites-enabled';

// Traitement du formulaire
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save_config':
                // Récupérer et valider les données du formulaire
                $selectedPath = isset($_POST['path']) ? sanitizePath($_POST['path']) : '';
                $port = isset($_POST['port']) ? filter_var($_POST['port'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) : '';
                $autoindex = isset($_POST['autoindex']) ? (int)$_POST['autoindex'] : 0;
                $custom404 = isset($_POST['custom_404']) ? sanitizePath($_POST['custom_404']) : '';
                
                if (empty($selectedPath) || empty($port)) {
                    $message = 'Veuillez fournir un chemin et un port valides.';
                    $messageType = 'error';
                } else {
                    // Générer et enregistrer la configuration Nginx
                    $result = saveNginxConfig($selectedPath, $port, $autoindex, $custom404);
                    if ($result['success']) {
                        $message = 'Configuration enregistrée avec succès.';
                        $messageType = 'success';
                    } else {
                        $message = 'Erreur lors de l\'enregistrement de la configuration: ' . $result['message'];
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'delete_config':
                $configName = isset($_POST['config_name']) ? sanitizeFileName($_POST['config_name']) : '';
                if (empty($configName)) {
                    $message = 'Nom de configuration invalide.';
                    $messageType = 'error';
                } else if ($configName === 'default') {
                    $message = 'La configuration par défaut ne peut pas être supprimée.';
                    $messageType = 'error';
                } else {
                    $result = deleteNginxConfig($configName);
                    if ($result['success']) {
                        $message = 'Configuration supprimée avec succès.';
                        $messageType = 'success';
                    } else {
                        $message = 'Erreur lors de la suppression de la configuration: ' . $result['message'];
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'update_port':
                $configName = isset($_POST['config_name']) ? sanitizeFileName($_POST['config_name']) : '';
                $newPort = isset($_POST['new_port']) ? filter_var($_POST['new_port'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) : '';
                
                if (empty($configName) || empty($newPort)) {
                    $message = 'Veuillez fournir un nom de configuration et un port valides.';
                    $messageType = 'error';
                } else if ($configName === 'default') {
                    $message = 'La configuration par défaut ne peut pas être modifiée.';
                    $messageType = 'error';
                } else {
                    $result = updateNginxConfigPort($configName, $newPort);
                    if ($result['success']) {
                        $message = 'Port de la configuration mis à jour avec succès.';
                        $messageType = 'success';
                    } else {
                        $message = 'Erreur lors de la mise à jour du port: ' . $result['message'];
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'reload_nginx':
                $result = reloadNginx();
                if ($result['success']) {
                    $message = 'Configuration Nginx rechargée avec succès.';
                    $messageType = 'success';
                } else {
                    $message = 'Erreur lors du rechargement de Nginx: ' . $result['message'];
                    $messageType = 'error';
                }
                break;
        }
    }
}

debug_log("Début du scan des répertoires");
// Récupérer les listes de répertoires et configurations
$directories = scanDirectoryRecursive('/var/www/html');
debug_log("Fin du scan des répertoires, " . count($directories) . " répertoires trouvés");
$configs = getNginxConfigurations();

// Organiser les répertoires en structure arborescente
$directoryTree = [];
foreach ($directories as $dir) {
    // Ignorer les chemins qui ne commencent pas par /var/www/html
    if (strpos($dir, '/var/www/html') !== 0) {
        continue;
    }
    
    // Pour l'affichage, ne montrer que la partie après /var/www/html/
    $displayPath = $dir === '/var/www/html' ? '/ (racine)' : str_replace('/var/www/html/', '', $dir);
    
    // Stocker les informations nécessaires pour chaque répertoire
    $directoryTree[] = [
        'path' => $dir,
        'display' => $displayPath,
        'level' => $dir === '/var/www/html' ? 0 : substr_count($displayPath, '/') + 1,
        'parent' => $dir === '/var/www/html' ? null : dirname($dir)
    ];
}

// Trier les répertoires pour que les parents apparaissent avant les enfants
usort($directoryTree, function($a, $b) {
    return strcmp($a['path'], $b['path']);
});

debug_log("Rendu de la page");
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionnaire de Configuration Nginx</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            overflow: hidden;
        }
        .main-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            padding: 1rem;
        }
        .content-container {
            flex: 1;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            overflow: hidden;
            min-height: 0;
        }
        .panel {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }
        .panel-header {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .panel-body {
            flex: 1;
            overflow: auto;
            padding: 1rem;
        }
        .directory-selector {
            height: 200px;
            overflow: auto;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            display: flex;
            flex-direction: column;
        }
        .directory-list-container {
            flex: 1;
            overflow: auto;
        }
        .search-container {
            position: sticky;
            top: 0;
            background-color: white;
            padding: 0.5rem;
            border-bottom: 1px solid #e2e8f0;
            z-index: 10;
        }
        .table-container {
            overflow: auto;
            max-height: calc(100vh - 250px);
        }
        /* Style pour les éléments de répertoire */
        .directory-item {
            padding: 0.4rem 0.75rem;
            border-bottom: 1px solid #f0f4f8;
            font-size: 0.875rem;
            cursor: pointer;
            transition: background-color 0.2s;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: flex;
            align-items: center;
        }
        .directory-item:hover {
            background-color: #f1f5f9;
        }
        .directory-item.selected {
            background-color: #3b82f6;
            color: white;
        }
        /* Styles pour l'arborescence */
        .directory-toggle {
            display: inline-block;
            width: 16px;
            height: 16px;
            margin-right: 5px;
            text-align: center;
            line-height: 16px;
            cursor: pointer;
            font-weight: bold;
        }
        .directory-name {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .directory-children {
            display: none;
        }
        .directory-children.expanded {
            display: block;
        }
        /* Niveau d'indentation selon la profondeur */
        .level-0 { padding-left: 0.5rem; }
        .level-1 { padding-left: 1.5rem; }
        .level-2 { padding-left: 2.5rem; }
        .level-3 { padding-left: 3.5rem; }
        .level-4 { padding-left: 4.5rem; }
        .level-5 { padding-left: 5.5rem; }
        @media (max-width: 768px) {
            .content-container {
                grid-template-columns: 1fr;
            }
        }
        /* Style pour la barre de recherche */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        /* Style pour le modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 50;
            justify-content: center;
            align-items: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background-color: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            width: 100%;
            max-width: 24rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="main-container">
        <header class="mb-2">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-800">Gestionnaire de Configuration Nginx</h1>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded text-sm">Déconnexion</a>
            </div>
        </header>
        
        <?php if (!empty($message)): ?>
            <div class="mb-2 p-2 rounded text-sm <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="content-container">
            <!-- Section: Configurations existantes -->
            <div class="panel">
                <div class="panel-header">
                    <h2 class="text-lg font-semibold">Configurations existantes</h2>
                </div>
                <div class="panel-body">
                    <?php if (empty($configs)): ?>
                        <p class="text-gray-500">Aucune configuration trouvée.</p>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nom</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chemin</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Port</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($configs as $config): ?>
                                    <tr>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm"><?php echo htmlspecialchars($config['name']); ?></td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm"><?php echo htmlspecialchars($config['root']); ?></td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm"><?php echo htmlspecialchars($config['port']); ?></td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm flex space-x-2">
                                            <?php if ($config['name'] !== 'default'): ?>
                                                <button type="button" 
                                                    class="text-blue-600 hover:text-blue-900" 
                                                    onclick="openEditPortModal('<?php echo htmlspecialchars($config['name']); ?>', <?php echo (int)$config['port']; ?>)">
                                                    Modifier
                                                </button>
                                                <form method="post" class="inline">
                                                    <input type="hidden" name="action" value="delete_config">
                                                    <input type="hidden" name="config_name" value="<?php echo htmlspecialchars($config['name']); ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-900" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette configuration ?');">
                                                        Supprimer
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-gray-400">(Configuration par défaut)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <form method="post" class="mt-3">
                            <input type="hidden" name="action" value="reload_nginx">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white py-1 px-3 rounded text-sm">Recharger Nginx</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Section: Créer une nouvelle configuration -->
            <div class="panel">
                <div class="panel-header">
                    <h2 class="text-lg font-semibold">Créer une nouvelle configuration</h2>
                </div>
                <div class="panel-body">
                    <form method="post" id="configForm" class="space-y-3">
                        <input type="hidden" name="action" value="save_config">
                        <input type="hidden" name="path" id="selectedPathInput" value="">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sélectionner un répertoire</label>
                            <div class="directory-selector">
                                <div class="search-container">
                                    <input type="text" id="directorySearch" class="w-full px-2 py-1 text-sm border border-gray-300 rounded-md" placeholder="Rechercher un répertoire...">
                                </div>
                                
                                <!-- Arborescence des répertoires -->
                                <div class="directory-list-container">
                                    <div id="directoryTree">
                                        <!-- L'arborescence sera générée par JavaScript -->
                                    </div>
                                </div>
                                
                                <!-- Message de chargement -->
                                <div id="loadingIndicator" class="hidden p-2 text-center text-gray-500 text-sm">
                                    <div class="inline-block mr-2 pulse">⏳</div> Chargement...
                                </div>
                            </div>
                            
                            <div class="mt-1 text-sm text-gray-700" id="selectedPathDisplay">
                                Aucun répertoire sélectionné
                            </div>
                            <div class="text-xs text-gray-500" id="fullPathDisplay"></div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="port" class="block text-sm font-medium text-gray-700">Port</label>
                                <input type="number" name="port" id="port" min="1" max="65535" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-1 px-2 text-sm" required>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Autoindex</label>
                                <div class="flex items-center space-x-3 mt-1">
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="autoindex" value="1" class="h-3 w-3">
                                        <span class="ml-1 text-sm">Activé</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="autoindex" value="0" class="h-3 w-3" checked>
                                        <span class="ml-1 text-sm">Désactivé</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label for="custom_404" class="block text-sm font-medium text-gray-700">Page 404 personnalisée (optionnel)</label>
                            <input type="text" name="custom_404" id="custom_404" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-1 px-2 text-sm" placeholder="/var/www/html/404.html">
                        </div>
                        
                        <div class="pt-1">
                            <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 text-sm font-medium">
                                Enregistrer la configuration
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de modification de port -->
    <div id="editPortModal" class="modal">
        <div class="modal-content">
            <h3 class="text-lg font-semibold mb-4">Modifier le port</h3>
            <form method="post" id="editPortForm">
                <input type="hidden" name="action" value="update_port">
                <input type="hidden" name="config_name" id="editConfigName" value="">
                
                <div class="mb-4">
                    <label for="new_port" class="block text-sm font-medium text-gray-700 mb-1">Nouveau port</label>
                    <input type="number" name="new_port" id="newPort" min="1" max="65535" class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm" required>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-md hover:bg-gray-300 text-sm" onclick="closeEditPortModal()">
                        Annuler
                    </button>
                    <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 text-sm">
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Données des répertoires
        const directories = <?php echo json_encode($directoryTree); ?>;
        
        // Initialisation au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            // Construire l'arborescence des répertoires
            buildDirectoryTree();
            
            // Initialiser la recherche
            initializeSearch();
        });
        
        // Fonction pour construire l'arborescence des répertoires
        function buildDirectoryTree() {
            const treeContainer = document.getElementById('directoryTree');
            treeContainer.innerHTML = '';
            
            // Créer l'élément racine
            const rootDir = directories.find(dir => dir.path === '/var/www/html');
            if (rootDir) {
                const rootElement = createDirectoryElement(rootDir);
                treeContainer.appendChild(rootElement);
                
                // Construire récursivement l'arborescence
                buildSubtree(rootDir.path, treeContainer);
                
                // Développer automatiquement le premier niveau
                const rootToggle = rootElement.querySelector('.directory-toggle');
                if (rootToggle) {
                    toggleDirectory(rootToggle);
                }
            }
        }
        
        // Fonction pour créer un élément de répertoire
        function createDirectoryElement(dirInfo) {
            const hasChildren = directories.some(dir => 
                dir.parent === dirInfo.path && dir.path !== dirInfo.path
            );
            
            const container = document.createElement('div');
            container.className = `directory-container` + (dirInfo.path === '/var/www/html' ? ' root-directory' : '');
            container.dataset.path = dirInfo.path;
            
            const item = document.createElement('div');
            item.className = `directory-item level-${Math.min(dirInfo.level, 5)}`;
            item.dataset.path = dirInfo.path;
            item.dataset.display = dirInfo.display;
            
            // Créer le bouton de toggle pour les répertoires qui ont des enfants
            if (hasChildren) {
                const toggle = document.createElement('span');
                toggle.className = 'directory-toggle';
                toggle.textContent = '+';
                toggle.onclick = function(e) {
                    e.stopPropagation();
                    toggleDirectory(this);
                };
                item.appendChild(toggle);
            } else {
                // Ajouter un espace pour l'alignement
                const spacer = document.createElement('span');
                spacer.className = 'directory-toggle';
                spacer.style.visibility = 'hidden';
                spacer.textContent = ' ';
                item.appendChild(spacer);
            }
            
            // Créer le libellé du répertoire
            const nameSpan = document.createElement('span');
            nameSpan.className = 'directory-name';
            nameSpan.textContent = dirInfo.display;
            item.appendChild(nameSpan);
            
            // Ajouter l'événement click pour sélectionner le répertoire
            item.onclick = function() {
                selectDirectory(this, dirInfo.path);
            };
            
            container.appendChild(item);
            
            // Ajouter un conteneur pour les enfants si nécessaire
            if (hasChildren) {
                const childrenContainer = document.createElement('div');
                childrenContainer.className = 'directory-children';
                childrenContainer.dataset.parent = dirInfo.path;
                container.appendChild(childrenContainer);
            }
            
            return container;
        }
        
        // Fonction pour construire une sous-arborescence
        function buildSubtree(parentPath, parentContainer) {
            // Trouver tous les enfants directs du parent
            const children = directories.filter(dir => 
                dir.parent === parentPath && dir.path !== parentPath
            );
            
            if (children.length === 0) {
                return;
            }
            
            // Trouver le conteneur des enfants
            let childrenContainer;
            if (parentContainer.classList.contains('directory-children')) {
                childrenContainer = parentContainer;
            } else {
                childrenContainer = parentContainer.querySelector(`.directory-children[data-parent="${parentPath}"]`);
                if (!childrenContainer) {
                    return;
                }
            }
            
            // Ajouter chaque enfant au conteneur
            children.forEach(child => {
                const childElement = createDirectoryElement(child);
                childrenContainer.appendChild(childElement);
                
                // Construire récursivement les sous-arborescences
                buildSubtree(child.path, childElement);
            });
        }
        
        // Fonction pour basculer l'expansion d'un répertoire
        function toggleDirectory(toggleElement) {
            const item = toggleElement.closest('.directory-item');
            const container = item.closest('.directory-container');
            const childrenContainer = container.querySelector('.directory-children');
            
            if (childrenContainer) {
                childrenContainer.classList.toggle('expanded');
                toggleElement.textContent = childrenContainer.classList.contains('expanded') ? '-' : '+';
            }
        }
        
        // Fonction pour sélectionner un répertoire
        function selectDirectory(element, path) {
            // Enlever la classe selected de tous les éléments
            document.querySelectorAll('.directory-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            // Ajouter la classe selected à l'élément cliqué
            element.classList.add('selected');
            
            // Mettre à jour l'input caché
            document.getElementById('selectedPathInput').value = path;
            
            // Mettre à jour l'affichage du chemin sélectionné
            const displayName = element.getAttribute('data-display');
            document.getElementById('selectedPathDisplay').textContent = displayName;
            document.getElementById('fullPathDisplay').textContent = path;
        }
        
        // Fonction pour initialiser la recherche
        function initializeSearch() {
            document.getElementById('directorySearch').addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const items = document.querySelectorAll('.directory-item');
                
                // Afficher l'indicateur de chargement si la recherche est assez longue
                const showLoading = items.length > 100;
                if (showLoading) {
                    document.getElementById('loadingIndicator').classList.remove('hidden');
                }
                
                // Utiliser setTimeout pour ne pas bloquer l'interface utilisateur
                setTimeout(() => {
                    let visibleCount = 0;
                    
                    if (searchTerm === '') {
                        // Si la recherche est vide, réinitialiser l'arborescence
                        buildDirectoryTree();
                        visibleCount = items.length;
                    } else {
                        // Filtrer les éléments et montrer tous les parents des éléments correspondants
                        const matchingPaths = [];
                        
                        // Trouver tous les chemins correspondants
                        items.forEach(item => {
                            const path = item.getAttribute('data-path').toLowerCase();
                            const display = item.getAttribute('data-display').toLowerCase();
                            
                            if (path.includes(searchTerm) || display.includes(searchTerm)) {
                                matchingPaths.push(path);
                                visibleCount++;
                            }
                        });
                        
                        // Développer tous les parents des chemins correspondants
                        if (matchingPaths.length > 0) {
                            document.querySelectorAll('.directory-children').forEach(child => {
                                child.classList.remove('expanded');
                            });
                            
                            matchingPaths.forEach(path => {
                                // Trouver tous les parents de ce chemin
                                let currentPath = path;
                                while (currentPath && currentPath !== '/var/www/html') {
                                    const parentPath = directories.find(dir => dir.path === currentPath)?.parent;
                                    if (parentPath) {
                                        const parentContainer = document.querySelector(`.directory-container[data-path="${parentPath}"]`);
                                        const childrenContainer = parentContainer?.querySelector(`.directory-children[data-parent="${parentPath}"]`);
                                        const toggle = parentContainer?.querySelector('.directory-toggle');
                                        
                                        if (childrenContainer && toggle) {
                                            childrenContainer.classList.add('expanded');
                                            toggle.textContent = '-';
                                        }
                                    }
                                    currentPath = parentPath;
                                }
                                
                                // S'assurer que la racine est également développée
                                const rootToggle = document.querySelector('.root-directory .directory-toggle');
                                const rootChildren = document.querySelector('.root-directory .directory-children');
                                if (rootToggle && rootChildren) {
                                    rootChildren.classList.add('expanded');
                                    rootToggle.textContent = '-';
                                }
                            });
                        }
                    }
                    
                    // Cacher l'indicateur de chargement
                    if (showLoading) {
                        document.getElementById('loadingIndicator').classList.add('hidden');
                    }
                    
                    // Afficher un message si aucun résultat
                    const treeContainer = document.getElementById('directoryTree');
                    let noResultsMessage = treeContainer.querySelector('.no-results-message');
                    
                    if (visibleCount === 0) {
                        if (!noResultsMessage) {
                            noResultsMessage = document.createElement('div');
                            noResultsMessage.className = 'no-results-message p-2 text-center text-gray-500 text-sm';
                            noResultsMessage.textContent = 'Aucun répertoire trouvé';
                            treeContainer.appendChild(noResultsMessage);
                        }
                    } else if (noResultsMessage) {
                        noResultsMessage.remove();
                    }
                }, 100);
            });
        }

        // Fonction pour ajuster la taille du conteneur en fonction de la hauteur de la fenêtre
        function adjustHeight() {
            const vh = window.innerHeight;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        }

        // Ajuster la hauteur au chargement et au redimensionnement
        window.addEventListener('resize', adjustHeight);
        adjustHeight();

        // Validation du formulaire
        document.getElementById('configForm').addEventListener('submit', function(event) {
            const selectedPath = document.getElementById('selectedPathInput').value;
            const port = document.getElementById('port').value;
            
            if (!selectedPath) {
                event.preventDefault();
                alert('Veuillez sélectionner un répertoire.');
                return false;
            }
            
            if (!port || isNaN(parseInt(port)) || parseInt(port) < 1 || parseInt(port) > 65535) {
                event.preventDefault();
                alert('Veuillez entrer un numéro de port valide (1-65535).');
                return false;
            }
            
            return true;
        });
        
        // Fonctions pour le modal de modification de port
        function openEditPortModal(configName, currentPort) {
            document.getElementById('editConfigName').value = configName;
            document.getElementById('newPort').value = currentPort;
            document.getElementById('editPortModal').classList.add('active');
        }
        
        function closeEditPortModal() {
            document.getElementById('editPortModal').classList.remove('active');
        }
        
        // Fermer le modal si on clique en dehors
        document.getElementById('editPortModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeEditPortModal();
            }
        });
    </script>
</body>
</html> 