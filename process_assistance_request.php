<?php
session_start();
require_once 'config.php'; // Ou db_connect.php, selon ton fichier de connexion PDO

// --- 1. Vérification du Token CSRF (DOIT ÊTRE LA PREMIÈRE VÉRIFICATION) ---
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = "Accès non autorisé au traitement du formulaire d'assistance (Erreur de sécurité CSRF).";
    error_log("CSRF Error: Invalid token for assistance form. IP: " . $_SERVER['REMOTE_ADDR']);
    header("Location: assistance.php"); // Redirige vers le formulaire d'assistance
    exit();
}
// Détruire le token après utilisation pour éviter la réutilisation
unset($_SESSION['csrf_token']);

// --- 2. Vérification du Bouton de Soumission (Utilise le 'name' que tu as ajouté) ---
if (isset($_POST['assistance_submit'])) { // <-- 'assistance_submit' doit correspondre au 'name' du bouton dans le HTML
    $user_id = trim($_POST['user_id']);
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $sujet = trim($_POST['sujet']);
    $description = trim($_POST['description']);

    // Validation des champs
    if (empty($nom) || empty($email) || empty($sujet) || empty($description)) {
        $_SESSION['error_message'] = "Tous les champs de la demande d'assistance sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "L'adresse email n'est pas valide.";
    } else {
        try {
            // Insertion dans la base de données
            $stmt = $pdo->prepare("INSERT INTO assistance_requests (user_id, name, email, subject, description, status, created_at) VALUES (:user_id, :name, :email, :subject, :description, 'Ouvert', NOW())");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':name', $nom);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':subject', $sujet);
            $stmt->bindParam(':description', $description);
            $stmt->execute();

            $_SESSION['success_message'] = "Votre demande d'assistance a été envoyée avec succès ! Notre équipe vous contactera bientôt.";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Erreur lors de l'envoi de la demande : " . $e->getMessage();
        }
    }
} else {
    // Si le script est accédé directement sans soumission de formulaire
    $_SESSION['error_message'] = "Accès non autorisé au traitement du formulaire d'assistance.";
}

header("Location: assistance.php");
exit();
?>