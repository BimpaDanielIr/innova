<?php
// config.php
// Configuration pour la connexion à la base de données avec PDO
$db_host = 'localhost';
$db_name = 'innova_tech_db'; // Mis à jour avec votre nom de base de données
$db_user = 'root';        // Remplacez par votre utilisateur de base de données
$db_pass = '';            // Remplacez par votre mot de passe de base de données

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    // Configure PDO pour lancer des exceptions en cas d'erreurs SQL
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Configure PDO pour retourner les résultats sous forme de tableaux associatifs par défaut
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // En production, ne JAMAIS afficher le message d'erreur détaillé à l'utilisateur final.
    // Loggez l'erreur et affichez un message générique.
    error_log("Database connection failed: " . $e->getMessage());
    die("Désolé, une erreur de connexion à la base de données est survenue. Veuillez réessayer plus tard.");
}
?>