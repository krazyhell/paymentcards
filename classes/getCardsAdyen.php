<?php

/**
 * Classe pour extraire les cartes de test depuis la documentation Adyen
 * Cette classe hérite de getCards et implémente le scraping de la page officielle Adyen
 */
class getCardsAdyen extends getCards {
    
    // URL de la documentation officielle Adyen contenant les cartes de test
    private $adyenUrl = 'https://docs.adyen.com/development-resources/testing/test-card-numbers/';
    
    // Tableau associatif stockant toutes les cartes extraites, organisées par catégorie
    private $cards = [];
    
    /**
     * Constructeur - Lance automatiquement l'extraction des cartes
     */
    public function __construct() {
        $this->scrapeAdyenCards();
    }
    
    /**
     * Méthode principale pour extraire toutes les cartes de test depuis la documentation Adyen
     * Récupère le HTML de la page et lance l'analyse
     */
    private function scrapeAdyenCards() {
        // Récupération du contenu HTML de la page Adyen
        $html = $this->fetchPageContent($this->adyenUrl);
        
        // Vérification que le contenu a bien été récupéré
        if (!$html) {
            throw new Exception("Impossible de récupérer le contenu de la page Adyen");
        }
        
        // Lancement de l'analyse du HTML pour extraire les cartes
        $this->parseCards($html);

        return $this->cards;
    }
    
    /**
     * Récupérer le contenu HTML d'une page web via cURL
     * Configure les options nécessaires pour simuler un navigateur réel
     * 
     * @param string $url L'URL à récupérer
     * @return string|false Le contenu HTML ou false en cas d'erreur
     */
    private function fetchPageContent($url, $options = []) {
        // Initialisation de la session cURL
        $ch = curl_init();
        
        // Configuration des options cURL pour une requête HTTP réussie
        curl_setopt($ch, CURLOPT_URL, $url);                    // URL cible
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);         // Retourner le résultat au lieu de l'afficher
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);         // Suivre les redirections automatiquement
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);        // Désactiver la vérification SSL pour éviter les erreurs
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'); // User-Agent pour simuler un navigateur
        
        if(!empty($options['proxy'])) {
            // Application des options supplémentaires si fournies
            curl_setopt($ch, CURLOPT_PROXY, $options['proxy']); // Proxy si spécifié
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP); // Type de proxy (HTTP par défaut)
        }

        // Exécution de la requête HTTP
        $html = curl_exec($ch);
        
        // Récupération du code de statut HTTP
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Fermeture de la session cURL
        curl_close($ch);
        
        // Vérification du succès de la requête (code 200 = OK)
        if ($httpCode !== 200) {
            return false;
        }
        
        return $html;
    }
    
    /**
     * Analyser le contenu HTML pour extraire les informations des cartes
     * Utilise DOMDocument et XPath pour parser le HTML structuré
     * 
     * @param string $html Le contenu HTML à analyser
     */
    private function parseCards($html) {
        // Création d'un objet DOMDocument pour parser le HTML
        $dom = new DOMDocument();
        
        // Chargement du HTML en supprimant les avertissements (@)
        @$dom->loadHTML($html);
        
        // Création d'un objet XPath pour naviguer dans le DOM
        $xpath = new DOMXPath($dom);
        
        // Initialisation du tableau des cartes
        $this->cards = [];
        
        // Recherche de tous les tableaux dans la page (les cartes sont dans des tableaux)
        $tables = $xpath->query('//table');
        
        // Parcours de chaque tableau trouvé pour extraire les cartes
        foreach ($tables as $table) {
            $this->extractCardsFromTable($table, $xpath);
        }
        
        // Si aucune carte n'a été trouvée dans les tableaux, essayer d'autres méthodes d'extraction
        if (empty($this->cards)) {
            $this->extractCardsFromText($html);
        }
        
        // Solution de secours : charger des cartes prédéfinies si l'extraction a échoué
        if (empty($this->cards)) {
            $this->loadFallbackCards();
        }
    }
    
    /**
     * Extraire les cartes depuis un tableau HTML spécifique
     * Analyse chaque ligne du tableau pour identifier les données de carte
     * 
     * @param DOMElement $table L'élément tableau à analyser
     * @param DOMXPath $xpath L'objet XPath pour naviguer dans le DOM
     */
    private function extractCardsFromTable($table, $xpath) {
        // Récupération de toutes les lignes du tableau
        $rows = $xpath->query('.//tr', $table);
        
        // Détermination de la catégorie de carte depuis le titre précédent le tableau
        $category = $this->findCategoryForTable($table, $xpath);
        
        // Parcours de chaque ligne du tableau
        foreach ($rows as $row) {
            // Récupération de toutes les cellules de la ligne
            $cells = $xpath->query('.//td', $row);
            
            // Vérification qu'il y a au moins 3 cellules (numéro, marque, expiration minimum)
            if ($cells->length >= 3) {
                // Extraction des données de carte depuis cette ligne
                $cardData = $this->extractCardDataFromRow($cells);
                
                // Validation des données extraites
                if ($cardData && $this->isValidCardNumber($cardData['number'])) {
                    // Initialisation de la catégorie si elle n'existe pas encore
                    if (!isset($this->cards[$category])) {
                        $this->cards[$category] = [];
                    }
                    
                    // Ajout de la carte à la catégorie appropriée
                    $this->cards[$category][] = $cardData;
                }
            }
        }
    }
    
    /**
     * Extraire les données de carte depuis une ligne de tableau
     * Analyse chaque cellule pour identifier le type de donnée (numéro, marque, expiration, etc.)
     * 
     * @param DOMNodeList $cells Les cellules de la ligne à analyser
     * @return array|null Les données de carte extraites ou null si invalide
     */
    private function extractCardDataFromRow($cells) {
        // Structure de données par défaut pour une carte
        $cardData = [
            'number' => '',      // Numéro de carte
            'brand' => '',       // Marque (Visa, Mastercard, etc.)
            'expiry' => '',      // Date d'expiration
            'cvc' => '',         // Code de sécurité
            'country' => '',     // Code pays
            'note' => ''         // Notes supplémentaires
        ];
        
        $cellIndex = 0;
        // Parcours de chaque cellule de la ligne
        foreach ($cells as $cell) {

            // Récupération du texte de la cellule en supprimant les espaces
            $text = trim($cell->textContent);
            
            // Analyse du contenu selon la position de la cellule
            switch ($cellIndex) {
                case 0:
                    // Première cellule : généralement le numéro de carte
                    // Suppression de tous les espaces du numéro
                    $cardData['number'] = preg_replace('/\s+/', '', $text);
                    break;
                    
                case 1:
                    // Deuxième cellule : marque ou pays
                    if ($this->isCountryCode($text)) {
                        $cardData['country'] = $text;
                    } else {
                        $cardData['brand'] = $text;
                    }
                    break;
                    
                case 2:
                    // Troisième cellule : date d'expiration ou marque
                    if (preg_match('/\d{2}\/\d{4}/', $text)) {
                        // Format date détecté (MM/YYYY)
                        $cardData['expiry'] = $text;
                    } elseif (!$cardData['brand']) {
                        // Si pas encore de marque définie, utiliser cette cellule
                        $cardData['brand'] = $text;
                    }
                    break;
                    
                case 3:
                    // Quatrième cellule : CVC, pays ou date d'expiration
                    if ($this->isCountryCode($text)) {
                        $cardData['country'] = $text;
                    } elseif (preg_match('/\d{3,4}/', $text) || strtolower($text) === 'none' || strpos(strtolower($text), 'not applicable') !== false) {
                        // Code CVC détecté (3-4 chiffres) ou mention "none"/"not applicable"
                        $cardData['cvc'] = $text;
                    } elseif (!$cardData['expiry'] && preg_match('/\d{2}\/\d{4}/', $text)) {
                        // Date d'expiration si pas encore définie
                        $cardData['expiry'] = $text;
                    }
                    break;
                    
                case 4:
                    // Cinquième cellule : pays ou CVC de secours
                    if ($this->isCountryCode($text)) {
                        $cardData['country'] = $text;
                    } elseif (!$cardData['cvc'] && (preg_match('/\d{3,4}/', $text) || strtolower($text) === 'none')) {
                        $cardData['cvc'] = $text;
                    }
                    break;
                    
                default:
                    // Cellules supplémentaires : ajout aux notes
                    if ($text) {
                        $cardData['note'] .= ($cardData['note'] ? ' | ' : '') . $text;
                    }
                    break;
            }
            $cellIndex++;
        }
        
        // Validation des données minimales requises
        if (!$cardData['number'] || !$this->isValidCardNumber($cardData['number'])) {
            return null;
        }
        
        // Valeurs par défaut
        if (!$cardData['expiry']) {
            $cardData['expiry'] = '03/2030';
        }
        if (!$cardData['cvc']) {
            $cardData['cvc'] = '737';
        }
        
        return $cardData;
    }
    
    /**
     * Trouver la catégorie pour le tableau en fonction des titres précédents
     */
    private function findCategoryForTable($table, $xpath) {
        $category = 'Autres';
        
        // Chercher les titres précédents
        $headings = $xpath->query('preceding::h2 | preceding::h3', $table);
        
        if ($headings->length > 0) {
            $lastHeading = $headings->item($headings->length - 1);
            $headingText = trim($lastHeading->textContent);
            
            // Nettoyer le titre
            $headingText = preg_replace('/^Anchor/', '', $headingText);
            $headingText = preg_replace('/\s+/', ' ', $headingText);
            
            if ($headingText) {
                $category = $headingText;
            }
        }
        
        return $category;
    }
    
    /**
     * Extraire les cartes depuis le contenu texte (méthode de secours)
     */
    private function extractCardsFromText($html) {
        // Expressions régulières pour détecter les numéros de carte
        $patterns = [
            // Formats courants de numéros de carte
            '/(\d{4}\s*\d{4}\s*\d{4}\s*\d{4})/i',
            '/(\d{4}\s*\d{6}\s*\d{5})/i', // Amex
            '/(\d{4}\s*\d{6}\s*\d{4})/i', // Diners
        ];
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $html, $matches);
            
            foreach ($matches[1] as $cardNumber) {
                $cleanNumber = preg_replace('/\s+/', '', $cardNumber);
                
                if ($this->isValidCardNumber($cleanNumber)) {
                    $brand = $this->detectCardBrand($cleanNumber);
                    
                    if (!isset($this->cards['Scrapped from text'])) {
                        $this->cards['Scrapped from text'] = [];
                    }
                    
                    $this->cards['Scrapped from text'][] = [
                        'number' => $cleanNumber,
                        'brand' => $brand,
                        'expiry' => '03/2030',
                        'cvc' => '737',
                        'country' => '',
                        'note' => 'Extracted from text'
                    ];
                }
            }
        }
    }
    
    /**
     * Détecter la marque de la carte à partir du numéro
     */
    private function detectCardBrand($number) {
        $number = preg_replace('/\s+/', '', $number);
        
        if (preg_match('/^4/', $number)) {
            return 'Visa';
        } elseif (preg_match('/^5[1-5]/', $number) || preg_match('/^2[2-7]/', $number)) {
            return 'Mastercard';
        } elseif (preg_match('/^3[47]/', $number)) {
            return 'American Express';
        } elseif (preg_match('/^3[0689]/', $number)) {
            return 'Diners';
        } elseif (preg_match('/^6011|^65/', $number)) {
            return 'Discover';
        } elseif (preg_match('/^35/', $number)) {
            return 'JCB';
        } elseif (preg_match('/^62/', $number)) {
            return 'China UnionPay';
        } else {
            return 'Unknown';
        }
    }
    
    /**
     * Vérifier si le texte est un code pays
     */
    protected function isCountryCode($text) {
        $countryCodes = ['US', 'GB', 'NL', 'FR', 'DE', 'CA', 'AU', 'BR', 'CN', 'IN', 'MX', 'BE', 'DK', 'ES', 'PL', 'IL', 'AZ', 'TW', 'MU', 'RU', 'GT'];
        return in_array(strtoupper($text), $countryCodes);
    }
    
    /**
     * Valider le numéro de carte en utilisant l'algorithme de Luhn
     */
    protected function isValidCardNumber($number) {
        $number = preg_replace('/\s+/', '', $number);
        
        // Vérifier la longueur
        if (strlen($number) < 13 || strlen($number) > 19) {
            return false;
        }
        
        // Vérifier que ce sont tous des chiffres
        if (!preg_match('/^\d+$/', $number)) {
            return false;
        }
        
        // Algorithme de Luhn
        $sum = 0;
        $alternate = false;
        
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = intval($number[$i]);
            
            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit = ($digit % 10) + 1;
                }
            }
            
            $sum += $digit;
            $alternate = !$alternate;
        }
        
        return ($sum % 10) === 0;
    }
    
    /**
     * Charger les cartes de secours prédéfinies
     * Utilisé quand l'extraction automatique échoue
     * Contient une liste statique de cartes de test connues et fonctionnelles
     */
    private function loadFallbackCards() {
        $this->cards = [
            'US Debit' => [
                ['number' => '5413330033003303', 'brand' => 'Mastercard Debit / PULSE / NYCE', 'expiry' => '03/30', 'cvc' => '737', 'country' => 'US'],
                ['number' => '6011609900000003', 'brand' => 'Discover Debit / Accel / STAR / Maestro USA', 'expiry' => '03/30', 'cvc' => '737', 'country' => 'US'],
                ['number' => '6445645000000002', 'brand' => 'Discover Debit / PULSE / NYCE', 'expiry' => '03/30', 'cvc' => '737', 'country' => 'US'],
            ],
            
            // Visa
            'Visa' => [
                ['number' => '4111111145551142', 'brand' => 'Visa Classic', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'NL', 'note' => 'Code de sécurité optionnel'],
                ['number' => '4111112014267661', 'brand' => 'Visa Debit', 'expiry' => '12/2030', 'cvc' => '737', 'country' => 'FR', 'note' => 'BIN à huit chiffres'],
                ['number' => '4988438843884305', 'brand' => 'Visa Classic', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'ES'],
                ['number' => '4166676667666746', 'brand' => 'Visa Classic', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'NL'],
                ['number' => '4646464646464644', 'brand' => 'Visa Classic', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'PL'],
                ['number' => '4000620000000007', 'brand' => 'Visa Commercial Credit', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'US'],
                ['number' => '4000060000000006', 'brand' => 'Visa Commercial Debit', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'US'],
                ['number' => '4293189100000008', 'brand' => 'Visa Commercial Premium Credit', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'AU'],
                ['number' => '4988080000000000', 'brand' => 'Visa Commercial Premium Debit', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'IN'],
                ['number' => '4111111111111111', 'brand' => 'Visa Consumer', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'NL'],
                ['number' => '4444333322221111', 'brand' => 'Visa Corporate', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'GB'],
                ['number' => '4001590000000001', 'brand' => 'Visa Corporate Credit', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'IL'],
                ['number' => '4000180000000002', 'brand' => 'Visa Corporate Debit', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'IN'],
                ['number' => '4000020000000000', 'brand' => 'Visa Credit', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'US'],
                ['number' => '4000160000000004', 'brand' => 'Visa Debit', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'IN'],
                ['number' => '4002690000000008', 'brand' => 'Visa Debit', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'AU'],
                ['number' => '4400000000000008', 'brand' => 'Visa Debit', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'US'],
                ['number' => '4484600000000004', 'brand' => 'Visa Fleet Credit', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'US'],
                ['number' => '4607000000000009', 'brand' => 'Visa Fleet Debit', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'MX'],
                ['number' => '4977949494949497', 'brand' => 'Visa Gold', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'FR'],
                ['number' => '4000640000000005', 'brand' => 'Visa Premium Credit', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'AZ'],
                ['number' => '4003550000000003', 'brand' => 'Visa Premium Credit', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'TW'],
                ['number' => '4000760000000001', 'brand' => 'Visa Premium Debit', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'MU'],
                ['number' => '4017340000000003', 'brand' => 'Visa Premium Debit', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'RU'],
                ['number' => '4005519000000006', 'brand' => 'Visa Purchasing Credit', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'US'],
                ['number' => '4131840000000003', 'brand' => 'Visa Purchasing Debit', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'GT'],
                ['number' => '4035501000000008', 'brand' => 'Visa', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'FR'],
                ['number' => '4151500000000008', 'brand' => 'Visa Credit', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'US'],
                ['number' => '4199350000000002', 'brand' => 'Visa Proprietary', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'FR'],
            ],
            
            // Visa Electron
            'Visa Electron' => [
                ['number' => '4001020000000009', 'brand' => 'Visa Electron', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'BR'],
            ],
            
            // V Pay
            'V Pay' => [
                ['number' => '4013250000000006', 'brand' => 'V Pay', 'expiry' => '03/2030', 'cvc' => '737', 'country' => 'PL'],
            ],
        ];
    }
    
    /**
     * Obtenir les statistiques de l'extraction des cartes
     * Fournit un résumé du nombre de cartes par catégorie
     * 
     * @return array Statistiques détaillées
     */
    public function getScrapingStats() {
        // Construction du tableau de statistiques
        $stats = [
            'total_cards' => $this->getTotalCount(),           // Nombre total de cartes
            'categories' => count($this->cards),               // Nombre de catégories
            'categories_list' => array_keys($this->cards),     // Liste des noms de catégories
            'cards_by_category' => []                          // Détail par catégorie
        ];
        
        // Calcul du nombre de cartes par catégorie
        foreach ($this->cards as $category => $cardList) {
            $stats['cards_by_category'][$category] = count($cardList);
        }
        
        return $stats;
    }
    
    /**
     * Rechercher des cartes par terme de recherche
     * Effectue une recherche dans tous les champs des cartes
     * 
     * @param string $searchTerm Le terme à rechercher
     * @return array Les cartes correspondant à la recherche
     */
    public function searchCards($searchTerm) {
        $results = [];
        // Conversion en minuscules pour une recherche insensible à la casse
        $searchTerm = strtolower($searchTerm);
        
        // Parcours de toutes les catégories et cartes
        foreach ($this->cards as $category => $cardList) {
            foreach ($cardList as $card) {
                // Recherche dans plusieurs champs : numéro, marque, pays, catégorie
                if (stripos($card['number'], $searchTerm) !== false ||
                    stripos($card['brand'], $searchTerm) !== false ||
                    stripos($card['country'], $searchTerm) !== false ||
                    stripos($category, $searchTerm) !== false) {
                    
                    // Ajout de la catégorie à la carte pour le contexte
                    $card['category'] = $category;
                    $results[] = $card;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Récupérer toutes les cartes organisées par catégorie
     * 
     * @return array Toutes les cartes extraites
     */
    public function getAllCards() {
        return $this->cards;
    }
    
    /**
     * Récupérer les cartes d'une catégorie spécifique
     * 
     * @param string $category Le nom de la catégorie
     * @return array Les cartes de la catégorie ou tableau vide
     */
    public function getCardsByCategory($category) {
        return isset($this->cards[$category]) ? $this->cards[$category] : [];
    }
    
    /**
     * Récupérer les cartes d'une marque spécifique
     * Recherche insensible à la casse dans le champ marque
     * 
     * @param string $brand La marque recherchée
     * @return array Les cartes correspondant à la marque
     */
    public function getCardsByBrand($brand) {
        $result = [];
        // Parcours de toutes les catégories
        foreach ($this->cards as $category => $cardList) {
            foreach ($cardList as $card) {
                // Recherche insensible à la casse dans la marque
                if (stripos($card['brand'], $brand) !== false) {
                    $result[] = $card;
                }
            }
        }
        return $result;
    }
    
    /**
     * Récupérer les cartes d'un pays spécifique
     * 
     * @param string $country Le code pays (ex: US, FR, GB)
     * @return array Les cartes du pays spécifié
     */
    public function getCardsByCountry($country) {
        $result = [];
        // Parcours de toutes les catégories
        foreach ($this->cards as $category => $cardList) {
            foreach ($cardList as $card) {
                // Comparaison exacte du code pays
                if (isset($card['country']) && $card['country'] === $country) {
                    $result[] = $card;
                }
            }
        }
        return $result;
    }
    
    /**
     * Compter le nombre total de cartes extraites
     * 
     * @return int Le nombre total de cartes
     */
    public function getTotalCount() {
        $count = 0;
        // Sommation du nombre de cartes dans chaque catégorie
        foreach ($this->cards as $cardList) {
            $count += count($cardList);
        }
        return $count;
    }
    
    /**
     * Exporter toutes les cartes au format JSON
     * Formatage avec indentation et caractères Unicode préservés
     * 
     * @return string Les cartes au format JSON
     */
    public function exportToJson() {
        return json_encode($this->cards, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}