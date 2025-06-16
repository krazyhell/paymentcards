<?php

/**
 * Classe de base pour l'extraction des cartes de paiement de test
 * Cette classe abstraite définit l'interface commune pour tous les extracteurs de cartes
 * Les classes filles doivent implémenter les méthodes spécifiques à chaque fournisseur
 */
class GetCards {
    
    /**
     * Méthodes communes et utilitaires partagés par tous les extracteurs
     * Cette classe peut contenir des méthodes de validation, formatage, etc.
     */
    
    /**
     * Valider un numéro de carte de crédit selon l'algorithme de Luhn
     * 
     * @param string $cardNumber Le numéro de carte à valider
     * @return bool True si le numéro est valide, false sinon
     */
    protected function isValidCardNumber($cardNumber) {
        // Suppression des espaces et caractères non numériques
        $cardNumber = preg_replace('/\D/', '', $cardNumber);
        
        // Vérification de la longueur (généralement entre 13 et 19 chiffres)
        if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
            return false;
        }
        
        // Application de l'algorithme de Luhn pour validation
        $sum = 0;
        $alternate = false;
        
        // Parcours du numéro de droite à gauche
        for ($i = strlen($cardNumber) - 1; $i >= 0; $i--) {
            $digit = intval($cardNumber[$i]);
            
            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            
            $sum += $digit;
            $alternate = !$alternate;
        }
        
        // Le numéro est valide si la somme est divisible par 10
        return ($sum % 10 === 0);
    }
    
    /**
     * Vérifier si un texte correspond à un code pays ISO (2 lettres)
     * 
     * @param string $text Le texte à vérifier
     * @return bool True si c'est un code pays valide
     */
    protected function isCountryCode($text) {
        // Vérification : exactement 2 lettres majuscules
        return preg_match('/^[A-Z]{2}$/', trim($text));
    }
    
    /**
     * Nettoyer et formater un numéro de carte
     * 
     * @param string $cardNumber Le numéro brut
     * @return string Le numéro nettoyé
     */
    protected function cleanCardNumber($cardNumber) {
        // Suppression de tous les caractères non numériques
        return preg_replace('/\D/', '', $cardNumber);
    }
    
    /**
     * Formater un numéro de carte pour l'affichage (avec espaces)
     * 
     * @param string $cardNumber Le numéro de carte
     * @return string Le numéro formaté avec espaces
     */
    protected function formatCardNumber($cardNumber) {
        // Nettoyage du numéro
        $clean = $this->cleanCardNumber($cardNumber);
        
        // Ajout d'espaces tous les 4 chiffres
        return chunk_split($clean, 4, ' ');
    }
}