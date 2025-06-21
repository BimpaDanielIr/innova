<?php

// Paramètres de connexion à la base de données par défaut de XAMPP
$servername = "localhost"; // L'adresse du serveur de base de données (ton ordinateur local)
$username = "root";        // Le nom d'utilisateur par défaut de MySQL sur XAMPP
$password = "";            // Le mot de passe par défaut est vide pour root sur XAMPP
$database = "innova_tech_db"; // Nous allons créer cette base de données

// Tente de créer une connexion à la base de données
// mysqli est une extension PHP pour interagir avec MySQL
$conn = new mysqli($servername, $username, $password);

// Vérifie la connexion
if ($conn->connect_error) {
    die("Échec de la connexion à MySQL : " . $conn->connect_error);
}

echo "Connexion à MySQL réussie !<br>";

// Tente de créer la base de données si elle n'existe pas
$sql_create_db = "CREATE DATABASE IF NOT EXISTS $database";
if ($conn->query($sql_create_db) === TRUE) {
    echo "Base de données '$database' créée ou déjà existante.<br>";
} else {
    echo "Erreur lors de la création de la base de données : " . $conn->error . "<br>";
}

// Sélectionne la base de données nouvellement créée (ou existante)
$conn->select_db($database);

// Exemple simple : Créer une table `users` si elle n'existe pas
$sql_create_table = "
CREATE TABLE IF NOT EXISTS users (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- Pour stocker les mots de passe hachés
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)
";

if ($conn->query($sql_create_table) === TRUE) {
    echo "Table 'users' créée ou déjà existante.<br>";
} else {
    echo "Erreur lors de la création de la table 'users' : " . $conn->error . "<br>";
}


// Important : Ferme la connexion à la base de données une fois les opérations terminées
$conn->close();

echo "Script PHP exécuté avec succès. Vérifie phpMyAdmin pour la base de données et la table.";

?>