# Gestionnaire de Configuration Nginx

Application web PHP pour gérer facilement les configurations Nginx pour vos sites web et applications.

## Fonctionnalités

- Interface responsive avec explorateur de fichiers arborescent
- Recherche en temps réel des répertoires
- Affichage des répertoires présents dans `/var/www/html`
- Association de répertoires à des ports spécifiques
- Modification des ports des configurations existantes
- Protection de la configuration par défaut contre la modification et la suppression
- Configuration toujours affichée en bas de la liste
- Activation/désactivation de l'autoindex pour chaque configuration
- Définition d'une page 404 personnalisée (optionnelle)
- Gestion des configurations Nginx et des liens symboliques
- Rechargement de la configuration Nginx

## Prérequis

- Serveur Linux avec Nginx installé
- PHP 7.4 ou supérieur
- Accès sudo pour certaines commandes
- Serveur web (Apache ou Nginx) configuré pour PHP

## Installation

1. Clonez le dépôt dans un répertoire accessible par votre serveur web
```bash
git clone https://github.com/votre-utilisateur/nginx-manager.git /var/www/html/nginx-manager
```

2. Donnez les permissions appropriées
```bash
sudo chown -R www-data:www-data /var/www/html/nginx-manager
sudo chmod -R 755 /var/www/html/nginx-manager
```

3. Configurez les permissions sudo pour l'utilisateur www-data
```bash
sudo echo 'www-data ALL=(ALL) NOPASSWD: /usr/sbin/nginx -t, /bin/systemctl reload nginx, /bin/rm /etc/nginx/sites-enabled/*, /bin/rm /etc/nginx/sites-available/*, /bin/mv /tmp/nginx-config-temp-* /etc/nginx/sites-available/*, /bin/ln -s /etc/nginx/sites-available/* /etc/nginx/sites-enabled/*' | sudo tee /etc/sudoers.d/nginx-manager
sudo chmod 440 /etc/sudoers.d/nginx-manager
```

4. Vérifiez que les répertoires de configuration Nginx existent
```bash
sudo mkdir -p /etc/nginx/sites-available /etc/nginx/sites-enabled
```

5. Assurez-vous que votre configuration principale de Nginx inclut les sites-enabled
```bash
sudo grep -q "include /etc/nginx/sites-enabled/\*;" /etc/nginx/nginx.conf || echo "include /etc/nginx/sites-enabled/*;" | sudo tee -a /etc/nginx/nginx.conf
```

6. Créez un fichier de configuration pour protéger l'accès à nginx-manager (optionnel mais recommandé)
```bash
sudo tee /etc/nginx/sites-available/nginx-manager <<EOL
server {
    listen 80;
    server_name nginx-manager.votre-domaine.com;
    
    root /var/www/html/nginx-manager;
    index index.php;
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    }
    
    location / {
        try_files \$uri \$uri/ /index.php?\$args;
    }
    
    # Restreindre l'accès par IP (recommandé)
    # allow 192.168.1.0/24;  # Exemple : autoriser le réseau local
    # deny all;  # Refuser toutes les autres IP
}
EOL

sudo ln -s /etc/nginx/sites-available/nginx-manager /etc/nginx/sites-enabled/
```

7. Testez et rechargez la configuration Nginx
```bash
sudo nginx -t && sudo systemctl reload nginx
```

8. Accédez à l'application via votre navigateur à l'adresse http://votre-serveur/nginx-manager/

## Configuration détaillée des permissions

L'application nécessite des permissions élevées pour gérer les configurations Nginx. Voici la liste détaillée des commandes qui doivent être autorisées sans mot de passe pour l'utilisateur www-data:

1. `/usr/sbin/nginx -t` - Pour tester la syntaxe des configurations
2. `/bin/systemctl reload nginx` - Pour recharger Nginx après modifications
3. `/bin/rm /etc/nginx/sites-enabled/*` - Pour supprimer les liens symboliques
4. `/bin/rm /etc/nginx/sites-available/*` - Pour supprimer les fichiers de configuration
5. `/bin/mv /tmp/nginx-config-temp-* /etc/nginx/sites-available/*` - Pour déplacer les fichiers temporaires
6. `/bin/ln -s /etc/nginx/sites-available/* /etc/nginx/sites-enabled/*` - Pour créer des liens symboliques

## Utilisation

1. Accédez à l'application dans votre navigateur
2. Connectez-vous avec les identifiants configurés
3. Pour créer une nouvelle configuration:
   - Naviguez dans l'arborescence et sélectionnez un répertoire 
   - Spécifiez un port non utilisé
   - Activez ou désactivez l'autoindex selon vos besoins
   - Ajoutez éventuellement une page 404 personnalisée
   - Cliquez sur "Enregistrer la configuration"
4. Pour gérer les configurations existantes:
   - Vous pouvez modifier le port d'une configuration (sauf default)
   - Vous pouvez supprimer une configuration (sauf default)
   - La configuration default apparaît toujours en bas de la liste
5. N'oubliez pas de cliquer sur "Recharger Nginx" après les modifications

## Sécurité

Cette application dispose d'un accès élevé au système, prenez ces précautions:

1. Limitez strictement l'accès à nginx-manager (par IP, authentification, etc.)
2. Utilisez HTTPS pour toutes les communications avec l'application
3. Vérifiez régulièrement les journaux pour détecter des activités suspectes
4. Ne déployez cette application que sur des serveurs internes ou de développement
5. Envisagez d'utiliser des solutions plus sécurisées en production

## Dépannage

Si vous rencontrez des problèmes:

1. Vérifiez les journaux d'erreur: `/tmp/nginx-manager-error.log`
2. Vérifiez que les permissions sudoers sont correctement configurées
3. Assurez-vous que les chemins dans functions.php correspondent à votre système
4. Vérifiez les permissions des répertoires de Nginx
5. Message "Impossible d'écrire le fichier de configuration": vérifiez les permissions sudoers
6. Message "Impossible de désactiver la configuration": vérifiez les permissions pour supprimer les liens symboliques

## Licence

Ce projet est distribué sous licence Apache 2.0. Voir le fichier LICENSE pour plus d'informations. 
