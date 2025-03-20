# Gestionnaire de Configuration Nginx

Une application web en PHP permettant de gérer facilement les configurations Nginx pour vos sites web et applications.

## Fonctionnalités

- Interface responsive et adaptative qui s'ajuste à la taille de l'écran
- Explorateur de fichiers arborescent avec possibilité de déplier/replier les répertoires
- Recherche en temps réel des répertoires
- Affichage des répertoires présents dans `/var/www/html`
- Association de répertoires à des ports spécifiques
- Modification des ports des configurations existantes via une interface modale
- Protection de la configuration par défaut contre la modification et la suppression
- Activation/désactivation de l'autoindex pour chaque configuration
- Définition d'une page 404 personnalisée (optionnelle)
- Lecture des configurations Nginx existantes
- Suppression des configurations existantes (sauf "default")
- Génération dynamique des blocs de configuration Nginx
- Gestion des liens symboliques dans sites-enabled
- Rechargement de la configuration Nginx

## Installation

1. Clonez ce dépôt dans un répertoire accessible par votre serveur web (par exemple, `/var/www/html/nginx-manager`)
```bash
git clone https://github.com/votre-utilisateur/nginx-manager.git /var/www/html/nginx-manager
```
2. Assurez-vous que le serveur web a les permissions nécessaires sur ce répertoire
```bash
chown -R www-data:www-data /var/www/html/nginx-manager
```
3. Accédez à l'application via votre navigateur à l'adresse http://votre-serveur/nginx-manager/

### Note sur les permissions

L'application est conçue pour fonctionner avec l'authentification root. Une fois que l'utilisateur est authentifié en tant que root, l'application utilise ces identifiants pour exécuter les commandes nécessaires (création/suppression de fichiers, gestion des liens symboliques, rechargement Nginx).

**IMPORTANT** : Pour des raisons de sécurité, cette approche présente des risques significatifs et ne devrait être utilisée que dans un environnement contrôlé et sécurisé.

### Configuration du serveur web

Pour que l'application puisse utiliser la session root correctement, le script PHP doit avoir les permissions pour exécuter des commandes shell. L'utilisateur du serveur web doit donc avoir les permissions nécessaires.

## Sécurité

L'application utilise l'authentification avec le compte root du système pour toutes les opérations. Considérations importantes :

1. Cette approche présente des risques de sécurité significatifs car elle utilise directement le compte root
2. Utilisez HTTPS pour sécuriser les communications et empêcher l'interception du mot de passe root
3. Restreignez l'accès à l'application via des règles de pare-feu ou des configurations Nginx
4. Envisagez d'utiliser des méthodes d'authentification plus sécurisées et des permissions plus limitées dans un environnement de production
5. Si possible, utilisez un utilisateur avec des permissions limitées plutôt que root

## Personnalisation

Vous pouvez personnaliser l'application en modifiant les paramètres suivants dans `index.php` :

- `$baseDir` : Le répertoire racine à explorer (actuellement limité à `/var/www/html`)
- `$nginxSitesAvailable` : Le chemin vers le répertoire des sites disponibles de Nginx
- `$nginxSitesEnabled` : Le chemin vers le répertoire des sites activés de Nginx

## Utilisation

1. Accédez à l'application via votre navigateur
2. Connectez-vous avec le mot de passe root du système
3. Dans le panneau "Configurations existantes", vous pouvez :
   - Voir toutes les configurations existantes avec leur nom, chemin et port
   - Modifier le port d'une configuration existante (sauf "default")
   - Supprimer une configuration existante (sauf "default")
   - Recharger Nginx après avoir modifié les configurations
4. Dans le panneau "Créer une nouvelle configuration" :
   - Parcourez l'arborescence de répertoires en dépliant/repliant les dossiers
   - Utilisez la barre de recherche pour trouver rapidement un répertoire spécifique
   - Sélectionnez un répertoire en cliquant dessus
   - Spécifiez un port pour la nouvelle configuration
   - Activez ou désactivez l'autoindex
   - Spécifiez éventuellement une page 404 personnalisée
   - Enregistrez la configuration

## Dépannage

Si vous rencontrez des erreurs lors de l'enregistrement ou de la suppression des configurations, vérifiez que :

1. Vous êtes correctement authentifié en tant que root
2. Le serveur web a les permissions nécessaires pour exécuter des commandes shell
3. Les chemins vers les répertoires de configuration Nginx sont corrects
4. Les journaux d'erreurs se trouvent dans `/tmp/nginx-manager-error.log`

## Contribuer

Les contributions sont les bienvenues ! N'hésitez pas à soumettre des pull requests pour améliorer cette application.

1. Forkez le projet
2. Créez votre branche de fonctionnalité (`git checkout -b feature/AmazingFeature`)
3. Committez vos changements (`git commit -m 'Add some AmazingFeature'`)
4. Poussez vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrez une Pull Request

## Licence

Ce projet est distribué sous licence MIT. Voir le fichier LICENSE pour plus d'informations. 
