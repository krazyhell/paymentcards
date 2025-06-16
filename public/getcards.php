<?php

/**
 * Script principal pour récupérer les cartes de test
 * Utilise l'autoloader centralisé pour charger les classes nécessaires
 */

// Inclusion de l'autoloader centralisé
require_once __DIR__ . '/../autoload.php';

// Définition de la source par défaut si non spécifiée
if (empty($_GET['source'])) {
    $_GET['source'] = 'adyen';
}

// Gestion des différentes sources de cartes
switch ($_GET['source']) {
    case 'adyen':
        // Vérification que la classe peut être chargée
        if (!can_load_class('getCardsAdyen')) {
            echo "Erreur : Impossible de charger la classe getCardsAdyen.";
            exit;
        }
        
        try {
            // Instanciation de l'extracteur Adyen
            $cardExtractor = new getCardsAdyen();

            print_r($cardExtractor->exportToJson());

        } catch (Exception $e) {
            echo "Erreur lors de l'extraction : " . $e->getMessage();
        }
        break;

    default:
        echo "Source inconnue : " . htmlspecialchars($_GET['source']);
        exit;
}
