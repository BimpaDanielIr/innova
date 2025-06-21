<?php
session_start();
require_once 'config.php'; // Inclut l'objet PDO $pdo

$redirect_url = "contact.php"; // URL de redirection par défaut

try {
    // Vérifier la méthode de requête POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Vérification du token CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Erreur de sécurité : Jeton CSRF invalide. Veuillez réessayer.");
        }

        // Récupérer et nettoyer les données du formulaire
        $name = trim($_POST['name'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        // Stocker les anciennes données pour pré-remplir le formulaire en cas d'erreur
        $_SESSION['old_contact_data'] = [
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'message' => $message
        ];

        // Validation des données
        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            throw new Exception("Tous les champs du formulaire de contact sont obligatoires.");
        }
        if ($email === false) {
            throw new Exception("L'adresse email fournie est invalide.");
        }
        if (strlen($message) < 10) {
            throw new Exception("Votre message est trop court. Veuillez fournir plus de détails (minimum 10 caractères).");
        }

        // Préparer la requête d'insertion dans la table 'contacts'
        $stmt = $pdo->prepare("INSERT INTO contacts (name, email, subject, message, created_at) VALUES (:name, :email, :subject, :message, NOW())");

        // Exécuter la requête
        if ($stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':subject' => $subject,
            ':message' => $message
        ])) {
            $_SESSION['success_message'] = "Votre message a été envoyé avec succès ! Nous vous répondrons bientôt.";
            // Nettoyer les anciennes données après un succès
            unset($_SESSION['old_contact_data']);
        } else {
            // Cette partie devrait être rarement atteinte si PDO::ERRMODE_EXCEPTION est activé
            throw new Exception("Une erreur inattendue est survenue lors de l'envoi de votre message.");
        }

    } else {
        // Si le script est accédé directement sans POST
        throw new Exception("Requête non valide. Le formulaire doit être soumis via POST.");
    }

} catch (PDOException $e) {
    // Erreur de base de données
    $_SESSION['error_message'] = "Une erreur de base de données est survenue. Veuillez réessayer plus tard.";
    // Optionnel: logguer l'erreur pour le débogage (e.g., error_log($e->getMessage());)
} catch (Exception $e) {
    // Capturer les exceptions personnalisées (validation, CSRF, etc.)
    $_SESSION['error_message'] = $e->getMessage();
} finally {
    // Redirection vers la page de contact, même en cas d'erreur
    header("Location: " . $redirect_url);
    exit();
}