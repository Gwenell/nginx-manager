<?php
/**
 * Script de déconnexion
 * 
 * Ce script gère la déconnexion de l'utilisateur en détruisant sa session
 * puis en le redirigeant vers la page de connexion.
 * 
 * @author Votre nom
 * @version 1.0
 */

// Inclure le fichier d'authentification
require_once 'auth.php';

// Déconnecter l'utilisateur
logoutUser();

// Rediriger vers la page de connexion
header('Location: login.php');
exit; 