<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

// CSRF Protection
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = "Erreur de sécurité: Jeton CSRF invalide. Veuillez réessayer.";
    header("Location: admin_manage_payments.php");
    exit();
}

// Ensure admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_message'] = "Accès non autorisé.";
    header("Location: authentification.php");
    exit();
}

$redirect_url = "admin_manage_payments.php";

try {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $formation_id = filter_var($_POST['formation_id'], FILTER_SANITIZE_NUMBER_INT);
            $payer_name = htmlspecialchars(trim($_POST['payer_name']));
            $amount = filter_var($_POST['amount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            $payment_date = $_POST['payment_date'];
            $payment_method = htmlspecialchars(trim($_POST['payment_method']));
            $status = htmlspecialchars(trim($_POST['status']));

            if (empty($formation_id) || empty($payer_name) || !is_numeric($amount) || empty($payment_date) || empty($payment_method) || empty($status)) {
                throw new Exception("Veuillez remplir tous les champs obligatoires.");
            }

            $user_id_for_payment = null;
            $enrollment_id_for_payment = null;

            // 1. Tenter de trouver ou créer un user_id basé sur le payer_name
            $stmt_find_user = $pdo->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
            $stmt_find_user->bindParam(':username', $payer_name);
            $stmt_find_user->execute();
            $existing_user = $stmt_find_user->fetch(PDO::FETCH_ASSOC);

            if ($existing_user) {
                $user_id_for_payment = $existing_user['id'];
            } else {
                // L'utilisateur n'existe pas, nous devons le créer
                // ATTENTION : Pour un vrai système, vous devriez gérer les mots de passe ici.
                // Pour l'instant, on va créer un utilisateur avec un mot de passe temporaire/vide
                // ou marquer comme "guest" si c'est pour des paiements non liés à des comptes classiques.
                // Ceci est une simplification pour résoudre l'erreur NOT NULL.
                // Idéalement, si vous créez un utilisateur, il doit avoir un mot de passe, un rôle par défaut, etc.
                // Pour l'exemple, nous allons utiliser un mot de passe haché simple.
                $temp_password_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT); // Mot de passe aléatoire
                $stmt_create_user = $pdo->prepare("INSERT INTO users (username, password, is_admin, created_at) VALUES (:username, :password, 0, NOW())");
                $stmt_create_user->bindParam(':username', $payer_name);
                $stmt_create_user->bindParam(':password', $temp_password_hash);
                if ($stmt_create_user->execute()) {
                    $user_id_for_payment = $pdo->lastInsertId();
                } else {
                    throw new Exception("Impossible de créer l'utilisateur pour le paiement.");
                }
            }

            // 2. Tenter de trouver ou créer une inscription (enrollment)
            // Puisque nous avons maintenant un user_id_for_payment valide (existant ou nouvellement créé),
            // nous pouvons toujours chercher/créer une inscription.
            $stmt_find_enrollment = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = :user_id AND formation_id = :formation_id LIMIT 1");
            $stmt_find_enrollment->bindParam(':user_id', $user_id_for_payment);
            $stmt_find_enrollment->bindParam(':formation_id', $formation_id);
            $stmt_find_enrollment->execute();
            $existing_enrollment = $stmt_find_enrollment->fetch(PDO::FETCH_ASSOC);

            if ($existing_enrollment) {
                $enrollment_id_for_payment = $existing_enrollment['id'];
            } else {
                // Si pas d'inscription existante pour cet utilisateur et cette formation, la créer
                $stmt_create_enrollment = $pdo->prepare("INSERT INTO enrollments (user_id, formation_id, enrollment_date, status) VALUES (:user_id, :formation_id, NOW(), 'Pending')");
                $stmt_create_enrollment->bindParam(':user_id', $user_id_for_payment);
                $stmt_create_enrollment->bindParam(':formation_id', $formation_id);
                $stmt_create_enrollment->execute();
                $enrollment_id_for_payment = $pdo->lastInsertId();
            }


            // 3. Insérer le paiement
            // user_id ici est l'utilisateur associé au paiement, pas l'admin qui l'ajoute.
            $stmt = $pdo->prepare("INSERT INTO payments (user_id, enrollment_id, amount, payment_date, payment_method, status, payer_name) VALUES (:user_id_payer, :enrollment_id, :amount, :payment_date, :payment_method, :status, :payer_name)");
            $stmt->bindParam(':user_id_payer', $user_id_for_payment, PDO::PARAM_INT);
            $stmt->bindParam(':enrollment_id', $enrollment_id_for_payment, PDO::PARAM_INT);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':payment_date', $payment_date);
            $stmt->bindParam(':payment_method', $payment_method);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':payer_name', $payer_name);

            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Paiement ajouté avec succès.";

                // Si une inscription a été créée ou trouvée et que le paiement est "Completed",
                // mettez à jour le statut de l'inscription.
                if ($enrollment_id_for_payment && $status === 'Completed') {
                    $update_enrollment_stmt = $pdo->prepare("UPDATE enrollments SET status = 'Paid' WHERE id = :enrollment_id");
                    $update_enrollment_stmt->bindParam(':enrollment_id', $enrollment_id_for_payment);
                    $update_enrollment_stmt->execute();
                }
            } else {
                throw new Exception("Erreur lors de l'ajout du paiement.");
            }
            break;

        case 'edit':
            $payment_id = filter_var($_POST['payment_id'], FILTER_SANITIZE_NUMBER_INT);
            $formation_id = filter_var($_POST['formation_id'], FILTER_SANITIZE_NUMBER_INT);
            $payer_name = htmlspecialchars(trim($_POST['payer_name']));
            $amount = filter_var($_POST['amount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            $payment_date = $_POST['payment_date'];
            $payment_method = htmlspecialchars(trim($_POST['payment_method']));
            $status = htmlspecialchars(trim($_POST['status']));

            if (empty($payment_id) || empty($formation_id) || empty($payer_name) || !is_numeric($amount) || empty($payment_date) || empty($payment_method) || empty($status)) {
                throw new Exception("Veuillez remplir tous les champs obligatoires pour la modification.");
            }

            $user_id_for_payment = null;
            $enrollment_id_for_payment = null;

            // 1. Tenter de trouver ou créer un user_id basé sur le payer_name (pour la mise à jour)
            $stmt_find_user = $pdo->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
            $stmt_find_user->bindParam(':username', $payer_name);
            $stmt_find_user->execute();
            $existing_user = $stmt_find_user->fetch(PDO::FETCH_ASSOC);

            if ($existing_user) {
                $user_id_for_payment = $existing_user['id'];
            } else {
                 // L'utilisateur n'existe pas, le créer
                $temp_password_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                $stmt_create_user = $pdo->prepare("INSERT INTO users (username, password, is_admin, created_at) VALUES (:username, :password, 0, NOW())");
                $stmt_create_user->bindParam(':username', $payer_name);
                $stmt_create_user->bindParam(':password', $temp_password_hash);
                if ($stmt_create_user->execute()) {
                    $user_id_for_payment = $pdo->lastInsertId();
                } else {
                    throw new Exception("Impossible de créer l'utilisateur pour la modification du paiement.");
                }
            }

            // 2. Tenter de trouver ou créer/mettre à jour l'inscription (enrollment)
            // Puisque nous avons un user_id_for_payment valide, nous pouvons toujours chercher/créer une inscription.
            $stmt_find_enrollment = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = :user_id AND formation_id = :formation_id LIMIT 1");
            $stmt_find_enrollment->bindParam(':user_id', $user_id_for_payment);
            $stmt_find_enrollment->bindParam(':formation_id', $formation_id);
            $stmt_find_enrollment->execute();
            $existing_enrollment = $stmt_find_enrollment->fetch(PDO::FETCH_ASSOC);

            if ($existing_enrollment) {
                $enrollment_id_for_payment = $existing_enrollment['id'];
            } else {
                // Si pas d'inscription existante, la créer
                $stmt_create_enrollment = $pdo->prepare("INSERT INTO enrollments (user_id, formation_id, enrollment_date, status) VALUES (:user_id, :formation_id, NOW(), 'Pending')");
                $stmt_create_enrollment->bindParam(':user_id', $user_id_for_payment);
                $stmt_create_enrollment->bindParam(':formation_id', $formation_id);
                $stmt_create_enrollment->execute();
                $enrollment_id_for_payment = $pdo->lastInsertId();
            }

            // 3. Mettre à jour le paiement
            $stmt = $pdo->prepare("UPDATE payments SET user_id = :user_id_payer, enrollment_id = :enrollment_id, amount = :amount, payment_date = :payment_date, payment_method = :payment_method, status = :status, payer_name = :payer_name WHERE payment_id = :payment_id");
            $stmt->bindParam(':user_id_payer', $user_id_for_payment, PDO::PARAM_INT);
            $stmt->bindParam(':enrollment_id', $enrollment_id_for_payment, PDO::PARAM_INT);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':payment_date', $payment_date);
            $stmt->bindParam(':payment_method', $payment_method);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':payer_name', $payer_name);
            $stmt->bindParam(':payment_id', $payment_id);

            if ($stmt->execute()) {
                // Mettre à jour le statut de l'inscription si elle est liée
                if ($enrollment_id_for_payment) {
                    $update_enrollment_stmt = $pdo->prepare("UPDATE enrollments SET status = :payment_status WHERE id = :enrollment_id");
                    $update_enrollment_stmt->bindParam(':payment_status', $status);
                    $update_enrollment_stmt->bindParam(':enrollment_id', $enrollment_id_for_payment);
                    $update_enrollment_stmt->execute();
                }

                $_SESSION['success_message'] = "Paiement modifié avec succès.";
            } else {
                throw new Exception("Erreur lors de la modification du paiement.");
            }
            break;

        case 'delete':
            // ... (pas de changement nécessaire pour la suppression) ...
            $payment_id = filter_var($_POST['payment_id'], FILTER_SANITIZE_NUMBER_INT);
            if (empty($payment_id)) {
                throw new Exception("ID de paiement manquant pour la suppression.");
            }
            $stmt = $pdo->prepare("DELETE FROM payments WHERE payment_id = :payment_id");
            $stmt->bindParam(':payment_id', $payment_id);

            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Paiement supprimé avec succès.";
            } else {
                throw new Exception("Erreur lors de la suppression du paiement.");
            }
            break;

        default:
            $_SESSION['error_message'] = "Action non reconnue.";
            break;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Erreur de base de données: " . $e->getMessage();
    error_log("Payment process DB error: " . $e->getMessage());
} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    error_log("Payment process general error: " . $e->getMessage());
}

header("Location: " . $redirect_url);
exit();
?>