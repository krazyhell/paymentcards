<?php

/**
 * Système d'autoload centralisé pour le projet PaymentCards
 * Ce fichier configure l'autoloader pour charger automatiquement les classes
 * depuis différents répertoires du projet
 */

// Vérification qu'on n'enregistre l'autoloader qu'une seule fois
if (!function_exists('paymentcards_autoload')) {
    
    /**
     * Fonction d'autoload personnalisée pour le projet
     * Recherche les classes dans plusieurs répertoires prédéfinis
     * 
     * @param string $class Le nom de la classe à charger
     */
    function paymentcards_autoload($class) {
        // Définition des chemins de recherche pour les classes
        $paths = [
            // Répertoire des classes principales
            __DIR__ . '/classes/' . $class . '.php',
            
            // Répertoire public (pour compatibilité)
            __DIR__ . '/public/' . $class . '.php',
            
            // Répertoire racine (pour les anciennes classes)
            __DIR__ . '/' . $class . '.php',
            
            // Répertoire des utilitaires
            __DIR__ . '/utils/' . $class . '.php',
            
            // Répertoire des interfaces
            __DIR__ . '/interfaces/' . $class . '.php'
        ];
        
        // Parcours de chaque chemin pour trouver la classe
        foreach ($paths as $file) {
            if (file_exists($file)) {
                // Inclusion du fichier trouvé
                require_once $file;
                
                // Vérification que la classe a bien été définie
                if (class_exists($class) || interface_exists($class) || trait_exists($class)) {
                    return true;
                }
            }
        }
        
        // Classe non trouvée
        return false;
    }
    
    // Enregistrement de l'autoloader dans la pile SPL
    spl_autoload_register('paymentcards_autoload');
    
    /**
     * Fonction utilitaire pour vérifier si une classe peut être chargée
     * 
     * @param string $class Le nom de la classe à vérifier
     * @return bool True si la classe peut être chargée
     */
    function can_load_class($class) {
        return class_exists($class) || interface_exists($class) || trait_exists($class);
    }
    
    /**
     * Fonction pour lister tous les fichiers de classes disponibles
     * Utile pour le debug et l'inventaire des classes
     * 
     * @return array Liste des fichiers de classes trouvés
     */
    function list_available_classes() {
        $directories = [
            __DIR__ . '/classes/',
            __DIR__ . '/public/',
            __DIR__ . '/utils/',
            __DIR__ . '/interfaces/'
        ];
        
        $classes = [];
        
        foreach ($directories as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '*.php');
                foreach ($files as $file) {
                    $className = basename($file, '.php');
                    $classes[] = [
                        'class' => $className,
                        'file' => $file,
                        'directory' => basename($dir)
                    ];
                }
            }
        }
        
        return $classes;
    }
}