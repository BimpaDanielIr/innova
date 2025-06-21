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

$redirect_url = "admin_manage_payments.php"; // URL de redirection par défaut

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
        case 'add_payment':
            $user_id = (int)($_POST['user_id'] ?? 0);
            $enrollment_id = !empty($_POST['enrollment_id']) ? (int)$_POST['enrollment_id'] : null;
            $amount = floatval($_POST['amount'] ?? 0);
            $payment_date = trim($_POST['payment_date'] ?? '');
            $payment_method = trim($_POST['payment_method'] ?? '');
            $status = trim($_POST['status'] ?? '');

            if ($user_id === 0 || empty($amount) || empty($payment_date) || empty($payment_method) || empty($status)) {
                throw new Exception("Tous les champs obligatoires pour l'ajout d'un paiement sont manquants.");
            }

            $pdo->beginTransaction();

            // Insérer le nouveau paiement
            $stmt_insert = $pdo->prepare("INSERT INTO payments (user_id, enrollment_id, amount, payment_date, payment_method, status) VALUES (:user_id, :enrollment_id, :amount, :payment_date, :payment_method, :status)");
            $stmt_insert->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_insert->bindParam(':enrollment_id', $enrollment_id, PDO::PARAM_INT);
            $stmt_insert->bindParam(':amount', $amount);
            $stmt_insert->bindParam(':payment_date', $payment_date);
            $stmt_insert->bindParam(':payment_method', $payment_method);
            $stmt_insert->bindParam(':status', $status);

            if (!$stmt_insert->execute()) {
                throw new Exception("Erreur lors de l'ajout du paiement.");
            }

            // Mettre à jour le statut de paiement de l'inscription si elle est liée
            if ($enrollment_id !== null) {
                // Ici, vous pouvez ajouter une logique plus complexe pour calculer le statut global de paiement de l'inscription
                // (par exemple, 'Partially Paid' si un montant partiel, 'Paid' si tout est couvert, etc.)
                // Pour l'instant, je vais simplement mettre à jour le statut basé sur le nouveau paiement, ou une logique simple.
                // Une approche plus robuste serait de recalculer le total payé pour cette inscription et le comparer au prix de la formation.
                $stmt_update_enrollment_status = $pdo->prepare("UPDATE enrollments SET payment_status = :payment_status WHERE enrollment_id = :enrollment_id");
                $stmt_update_enrollment_status->bindParam(':payment_status', $status);
                $stmt_update_enrollment_status->bindParam(':enrollment_id', $enrollment_id, PDO::PARAM_INT);
                $stmt_update_enrollment_status->execute();
            }

            $pdo->commit();
            $_SESSION['success_message'] = "Paiement ajouté avec succès !";
            break;

        case 'update_payment':
            $payment_id = (int)($_POST['payment_id'] ?? 0);
            $user_id = (int)($_POST['user_id'] ?? 0);
            $enrollment_id = !empty($_POST['enrollment_id']) ? (int)$_POST['enrollment_id'] : null;
            $amount = floatval($_POST['amount'] ?? 0);
            $payment_date = trim($_POST['payment_date'] ?? '');
            $payment_method = trim($_POST['payment_method'] ?? '');
            $status = trim($_POST['status'] ?? '');

            if ($payment_id === 0 || empty($amount) || empty($payment_date) || empty($payment_method) || empty($status)) {
                throw new Exception("Données de paiement manquantes ou invalides pour la mise à jour.");
            }

            $pdo->beginTransaction();

            $stmt_update = $pdo->prepare("UPDATE payments SET user_id = :user_id, enrollment_id = :enrollment_id, amount = :amount, payment_date = :payment_date, payment_method = :payment_method, status = :status WHERE payment_id = :payment_id");
            $stmt_update->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_update->bindParam(':enrollment_id', $enrollment_id, PDO::PARAM_INT);
            $stmt_update->bindParam(':amount', $amount);
            $stmt_update->bindParam(':payment_date', $payment_date);
            $stmt_update->bindParam(':payment_method', $payment_method);
            $stmt_update->bindParam(':status', $status);
            $stmt_update->bindParam(':payment_id', $payment_id, PDO::PARAM_INT);

            if (!$stmt_update->execute()) {
                throw new Exception("Erreur lors de la mise à jour du paiement.");
            }

            // Mettre à jour le statut de paiement de l'inscription si elle est liée et le paiement est 'Completed' ou 'Partially Paid'
            if ($enrollment_id !== null) {
                // Recalculer le statut de paiement de l'inscription
                $stmt_total_paid = $pdo->prepare("SELECT SUM(p.amount) FROM payments p WHERE p.enrollment_id = :enrollment_id AND p.status = 'Completed'");
                $stmt_total_paid->bindParam(':enrollment_id', $enrollment_id, PDO::PARAM_INT);
                $stmt_total_paid->execute();
                $total_paid_for_enrollment = $stmt_total_paid->fetchColumn();

                $stmt_formation_price = $pdo->prepare("SELECT f.price FROM formations f JOIN enrollments e ON f.id = e.formation_id WHERE e.enrollment_id = :enrollment_id");
                $stmt_formation_price->bindParam(':enrollment_id', $enrollment_id, PDO::PARAM_INT);
                $stmt_formation_price->execute();
                $formation_price = $stmt_formation_price->fetchColumn();

                $new_enrollment_payment_status = 'Pending';
                if ($total_paid_for_enrollment >= $formation_price && $formation_price > 0) {
                    $new_enrollment_payment_status = 'Paid';
                } elseif ($total_paid_for_enrollment > 0 && $total_paid_for_enrollment < $formation_price) {
                    $new_enrollment_payment_status = 'Partially Paid';
                } elseif ($total_paid_for_enrollment == 0 && ($status === 'Failed' || $status === 'Refunded')) {
                    $new_enrollment_payment_status = 'Failed'; // Ou un statut plus approprié
                }
                // Si l'inscription n'a pas de prix ou aucun paiement n'est complet, reste à Pending ou le statut du dernier paiement si échec/remboursé.

                $stmt_update_enrollment_status = $pdo->prepare("UPDATE enrollments SET payment_status = :payment_status WHERE enrollment_id = :enrollment_id");
                $stmt_update_enrollment_status->bindParam(':payment_status', $new_enrollment_payment_status);
                $stmt_update_enrollment_status->bindParam(':enrollment_id', $enrollment_id, PDO::PARAM_INT);
                $stmt_update_enrollment_status->execute();
            }

            $pdo->commit();
            $_SESSION['success_message'] = "Paiement mis à jour avec succès !";
            break;

        case 'delete_payment':
            $payment_id = (int)($_POST['payment_id'] ?? 0);

            if ($payment_id === 0) {
                throw new Exception("ID de paiement manquant pour la suppression.");
            }

            $pdo->beginTransaction();

            // Récupérer l'enrollment_id avant la suppression pour mettre à jour le statut de l'inscription
            $stmt_get_enrollment_id = $pdo->prepare("SELECT enrollment_id FROM payments WHERE payment_id = :payment_id");
            $stmt_get_enrollment_id->bindParam(':payment_id', $payment_id, PDO::PARAM_INT);
            $stmt_get_enrollment_id->execute();
            $enrollment_id_to_update = $stmt_get_enrollment_id->fetchColumn();


            $stmt_delete = $pdo->prepare("DELETE FROM payments WHERE payment_id = :payment_id");
            $stmt_delete->bindParam(':payment_id', $payment_id, PDO::PARAM_INT);

            if (!$stmt_delete->execute()) {
                throw new Exception("Erreur lors de la suppression du paiement.");
            }

            // Recalculer et mettre à jour le statut de paiement de l'inscription si un enrollment_id était lié
            if ($enrollment_id_to_update !== false) {
                $stmt_total_paid = $pdo->prepare("SELECT SUM(p.amount) FROM payments p WHERE p.enrollment_id = :enrollment_id AND p.status = 'Completed'");
                $stmt_total_paid->bindParam(':enrollment_id', $enrollment_id_to_update, PDO::PARAM_INT);
                $stmt_total_paid->execute();
                $total_paid_for_enrollment = $stmt_total_paid->fetchColumn();

                $stmt_formation_price = $pdo->prepare("SELECT f.price FROM formations f JOIN enrollments e ON f.id = e.formation_id WHERE e.enrollment_id = :enrollment_id");
                $stmt_formation_price->bindParam(':enrollment_id', $enrollment_id_to_update, PDO::PARAM_INT);
                $stmt_formation_price->execute();
                $formation_price = $stmt_formation_price->fetchColumn();

                $new_enrollment_payment_status = 'Pending';
                if ($total_paid_for_enrollment >= $formation_price && $formation_price > 0) {
                    $new_enrollment_payment_status = 'Paid';
                } elseif ($total_paid_for_enrollment > 0 && $total_paid_for_enrollment < $formation_price) {
                    $new_enrollment_payment_status = 'Partially Paid';
                }

                $stmt_update_enrollment_status = $pdo->prepare("UPDATE enrollments SET payment_status = :payment_status WHERE enrollment_id = :enrollment_id");
                $stmt_update_enrollment_status->bindParam(':payment_status', $new_enrollment_payment_status);
                $stmt_update_enrollment_status->bindParam(':enrollment_id', $enrollment_id_to_update, PDO::PARAM_INT);
                $stmt_update_enrollment_status->execute();
            }


            $pdo->commit();
            $_SESSION['success_message'] = "Paiement supprimé avec succès !";
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