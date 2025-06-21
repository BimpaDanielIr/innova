<?php
session_start();
require_once 'config.php'; // Assurez-vous que config.php est bien inclus

// Activez l'affichage des erreurs pour le développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Protection CSRF et autres validations
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = "Erreur de sécurité : Jeton CSRF invalide. Veuillez réessayer.";
    header("Location: inscription_formation_form.php");
    exit();
}
unset($_SESSION['csrf_token']); // Le token n'est utilisé qu'une seule fois

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Veuillez vous connecter pour vous inscrire à une formation.";
    header("Location: authentification.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_inscription'])) {
    $user_id = $_SESSION['user_id'];
    $formation_id = filter_input(INPUT_POST, 'formation_id', FILTER_VALIDATE_INT);
    $payment_method = trim($_POST['payment_method'] ?? '');
    $total_amount_due = filter_input(INPUT_POST, 'total_amount_due', FILTER_VALIDATE_FLOAT);

    if (!$formation_id || !$total_amount_due || empty($payment_method)) {
        $_SESSION['error_message'] = "Tous les champs obligatoires (formation, méthode de paiement, montant) doivent être remplis.";
        header("Location: inscription_formation_form.php");
        exit();
    }

    try {
        // Démarre une transaction pour s'assurer que les deux opérations (inscription et paiement initial) sont atomiques
        $pdo->beginTransaction();

        // 1. Insertion dans la table 'enrollments' (CORRECTION ICI !)
        $stmt_enrollment = $pdo->prepare(
            "INSERT INTO enrollments (user_id, formation_id, enrollment_datetime, status, payment_status)
             VALUES (:user_id, :formation_id, NOW(), :status, :payment_status)"
        );
        $stmt_enrollment->execute([
            ':user_id' => $user_id,
            ':formation_id' => $formation_id,
            ':status' => 'En attente', // Statut initial de l'inscription
            ':payment_status' => 'Non payé' // Statut initial du paiement
        ]);

        $enrollment_id = $pdo->lastInsertId(); // Récupère l'ID de l'inscription nouvellement créée

        // 2. Insertion dans la table 'payments' pour le paiement initial
        $stmt_payment = $pdo->prepare(
            "INSERT INTO payments (user_id, enrollment_id, amount, payment_date, payment_method, status)
             VALUES (:user_id, :enrollment_id, :amount, NOW(), :payment_method, :status)"
        );
        $stmt_payment->execute([
            ':user_id' => $user_id,
            ':enrollment_id' => $enrollment_id,
            ':amount' => $total_amount_due,
            ':payment_method' => $payment_method,
            ':status' => 'En attente' // Le paiement est en attente ou 'Traité' si c'est immédiat
        ]);

        $pdo->commit(); // Confirme la transaction si tout s'est bien passé

        $_SESSION['success_message'] = "Votre inscription a été enregistrée avec succès et le paiement initial est en attente de traitement.";
        header("Location: my_enrollments.php"); // Rediriger vers la page des inscriptions de l'utilisateur
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack(); // Annule la transaction en cas d'erreur
        $_SESSION['error_message'] = "Erreur de base de données lors de l'inscription : " . $e->getMessage();
        header("Location: inscription_formation_form.php"); // Rediriger vers le formulaire avec l'erreur
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: inscription_formation_form.php");
        exit();
    }
} else {
    $_SESSION['error_message'] = "Accès non autorisé au traitement du formulaire d'inscription.";
    header("Location: inscription_formation_form.php");
    exit();
}
?>