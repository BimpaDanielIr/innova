<?php
session_start();
require_once 'config.php'; // Assurez-vous que ce fichier initialise l'objet $pdo

// Activez l'affichage des erreurs pour le développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Protection de la page : l'utilisateur doit être connecté ET avoir le rôle 'admin'
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_message'] = "Accès non autorisé. Vous n'avez pas les permissions d'administrateur.";
    header("Location: authentification.php");
    exit();
}

$redirect_url = "admin_manage_enrollments.php"; // URL de redirection par défaut

try {
    // Vérification du Token CSRF pour toutes les actions POST
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        throw new Exception("Erreur de sécurité : Jeton CSRF invalide. Veuillez réessayer.");
    }
    unset($_SESSION['csrf_token']); // Détruire le token après utilisation

    if (!isset($_POST['action'])) {
        throw new Exception("Action non spécifiée.");
    }

    $action = $_POST['action'];

    switch ($action) {
        case 'update_enrollment_payment':
            $enrollment_id = (int)($_POST['enrollment_id'] ?? 0);
            $user_id = (int)($_POST['user_id'] ?? 0); // User ID from hidden field in modal
            $enrollment_status = trim($_POST['enrollment_status'] ?? '');
            $payment_amount = floatval($_POST['payment_amount'] ?? 0);
            $payment_method = trim($_POST['payment_method'] ?? '');
            $payment_status_record = trim($_POST['payment_status_record'] ?? ''); // Status for the payment record itself

            if ($enrollment_id === 0 || empty($enrollment_status)) {
                throw new Exception("Données d'inscription ou de statut manquantes pour la mise à jour.");
            }

            $pdo->beginTransaction();

            // 1. Mettre à jour le statut de l'inscription et le statut de paiement global de l'inscription
            $stmt_update_enrollment = $pdo->prepare("UPDATE enrollments SET status = :enrollment_status, payment_status = :payment_status_record WHERE enrollment_id = :enrollment_id");
            $stmt_update_enrollment->bindParam(':enrollment_status', $enrollment_status);
            $stmt_update_enrollment->bindParam(':payment_status_record', $payment_status_record); // Link payment status to enrollment's payment status
            $stmt_update_enrollment->bindParam(':enrollment_id', $enrollment_id, PDO::PARAM_INT);
            if (!$stmt_update_enrollment->execute()) {
                throw new Exception("Erreur lors de la mise à jour du statut de l'inscription.");
            }

            // 2. Gérer le paiement associé
            // Vérifier si un enregistrement de paiement existe déjà pour cette inscription
            $stmt_check_payment = $pdo->prepare("SELECT payment_id FROM payments WHERE enrollment_id = :enrollment_id");
            $stmt_check_payment->bindParam(':enrollment_id', $enrollment_id, PDO::PARAM_INT);
            $stmt_check_payment->execute();
            $existing_payment = $stmt_check_payment->fetch(PDO::FETCH_ASSOC);

            if ($existing_payment) {
                // Mettre à jour le paiement existant
                $stmt_update_payment = $pdo->prepare("UPDATE payments SET amount = :amount, payment_method = :payment_method, status = :status, payment_date = NOW() WHERE payment_id = :payment_id");
                $stmt_update_payment->bindParam(':amount', $payment_amount);
                $stmt_update_payment->bindParam(':payment_method', $payment_method);
                $stmt_update_payment->bindParam(':status', $payment_status_record);
                $stmt_update_payment->bindParam(':payment_id', $existing_payment['payment_id'], PDO::PARAM_INT);
                if (!$stmt_update_payment->execute()) {
                    throw new Exception("Erreur lors de la mise à jour du paiement existant.");
                }
            } else {
                // Créer un nouvel enregistrement de paiement si aucun n'existe
                // S'assurer que le user_id est passé et valide
                if ($user_id === 0) {
                     throw new Exception("Impossible de créer un paiement : ID utilisateur manquant.");
                }

                $stmt_insert_payment = $pdo->prepare("INSERT INTO payments (user_id, enrollment_id, amount, payment_date, payment_method, status) VALUES (:user_id, :enrollment_id, :amount, NOW(), :payment_method, :status)");
                $stmt_insert_payment->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt_insert_payment->bindParam(':enrollment_id', $enrollment_id, PDO::PARAM_INT);
                $stmt_insert_payment->bindParam(':amount', $payment_amount);
                $stmt_insert_payment->bindParam(':payment_method', $payment_method);
                $stmt_insert_payment->bindParam(':status', $payment_status_record);
                if (!$stmt_insert_payment->execute()) {
                    throw new Exception("Erreur lors de la création du nouvel enregistrement de paiement.");
                }
            }

            $pdo->commit();
            $_SESSION['success_message'] = "Inscription et paiement mis à jour avec succès !";
            break;

        case 'delete_enrollment':
            $enrollment_id = (int)($_POST['enrollment_id'] ?? 0);

            if ($enrollment_id === 0) {
                throw new Exception("ID d'inscription manquant pour la suppression.");
            }

            $pdo->beginTransaction();

            // D'abord, supprimer les paiements liés à cette inscription
            $stmt_delete_payments = $pdo->prepare("DELETE FROM payments WHERE enrollment_id = :enrollment_id");
            $stmt_delete_payments->bindParam(':enrollment_id', $enrollment_id, PDO::PARAM_INT);
            if (!$stmt_delete_payments->execute()) {
                throw new Exception("Erreur lors de la suppression des paiements liés à l'inscription.");
            }

            // Ensuite, supprimer l'inscription elle-même
            $stmt_delete_enrollment = $pdo->prepare("DELETE FROM enrollments WHERE enrollment_id = :enrollment_id");
            $stmt_delete_enrollment->bindParam(':enrollment_id', $enrollment_id, PDO::PARAM_INT);
            if (!$stmt_delete_enrollment->execute()) {
                throw new Exception("Erreur lors de la suppression de l'inscription.");
            }

            $pdo->commit();
            $_SESSION['success_message'] = "Inscription et paiements associés supprimés avec succès.";
            break;

        default:
            throw new Exception("Action non reconnue.");
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = "Erreur de base de données : " . $e->getMessage();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = $e->getMessage();
} finally {
    header("Location: " . $redirect_url);
    exit();
}
?>