<?php
session_start();

// Protection de la page : l'utilisateur doit être connecté ET avoir le rôle 'admin'
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_message'] = "Accès non autorisé. Vous n'avez pas les permissions d'administrateur.";
    header("Location: authentification.php");
    exit();
}

// Vérification du jeton CSRF pour toutes les actions POST
// Si le jeton n'est pas présent ou ne correspond pas, rediriger avec une erreur.
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Erreur de sécurité : Jeton CSRF invalide. Veuillez réessayer.";
        // Rediriger vers la page d'où vient la requête ou une page par défaut
        $redirect_url = "admin_manage_events.php";
        if (isset($_SERVER['HTTP_REFERER'])) {
            $redirect_url = $_SERVER['HTTP_REFERER'];
        }
        header("Location: " . $redirect_url);
        exit();
    }
} else {
    // Si la requête n'est pas POST (par exemple, un accès direct sans formulaire), rediriger
    $_SESSION['error_message'] = "Accès direct non autorisé à ce script de traitement.";
    header("Location: admin_manage_events.php");
    exit();
}


// Inclure le fichier de configuration de la base de données
require_once 'config.php'; // Assurez-vous que ce fichier initialise l'objet $pdo

$redirect_url = "admin_manage_events.php"; // URL de redirection par défaut

try {
    // Vérifier si $pdo est bien un objet PDO valide
    if (!($pdo instanceof PDO)) {
        throw new Exception("Erreur de configuration de la base de données: l'objet PDO n'est pas disponible.");
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            handleAddEvent($pdo);
            break;
        case 'delete':
            handleDeleteEvent($pdo);
            break;
        default:
            $_SESSION['error_message'] = "Action non reconnue.";
            break;
    }

} catch (PDOException $e) {
    $_SESSION['error_message'] = "Erreur de base de données : " . $e->getMessage();
} catch (Exception $e) {
    $_SESSION['error_message'] = "Erreur : " . $e->getMessage();
}

// Redirection finale
header("Location: " . $redirect_url);
exit();


/**
 * Gère l'ajout d'un nouvel événement.
 *
 * @param PDO $pdo L'objet de connexion PDO à la base de données.
 */
function handleAddEvent(PDO $pdo) {
    global $redirect_url;

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $event_date = $_POST['event_date']; // Format 'YYYY-MM-DDTHH:MM' de datetime-local
    $location = trim($_POST['location']);
    $is_active = isset($_POST['is_active']) ? 1 : 0; // Checkbox

    // Validation simple des entrées
    if (empty($title) || empty($description) || empty($event_date) || empty($location)) {
        $_SESSION['old_event_data'] = $_POST; // Pour pré-remplir le formulaire
        throw new Exception("Tous les champs obligatoires doivent être remplis.");
    }

    $image_path = null;

    // Gestion de l'upload d'image
    if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "uploads/events/"; // Assurez-vous que ce dossier existe et est inscriptible
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true); // Crée le dossier si inexistant
        }

        $image_file_type = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($image_file_type, $allowed_types)) {
            $_SESSION['old_event_data'] = $_POST;
            throw new Exception("Seuls les fichiers JPG, JPEG, PNG, GIF sont autorisés pour l'image.");
        }

        $unique_name = uniqid('event_', true) . '.' . $image_file_type;
        $target_file = $target_dir . $unique_name;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image_path = $target_file;
        } else {
            $_SESSION['old_event_data'] = $_POST;
            throw new Exception("Erreur lors de l'upload de l'image.");
        }
    }

    // Préparer la requête d'insertion
    $stmt = $pdo->prepare("INSERT INTO events (title, description, event_date, location, image_path, is_active, created_at, updated_at) VALUES (:title, :description, :event_date, :location, :image_path, :is_active, NOW(), NOW())");

    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':event_date', $event_date);
    $stmt->bindParam(':location', $location);
    $stmt->bindParam(':image_path', $image_path);
    $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Événement ajouté avec succès.";
        unset($_SESSION['old_event_data']); // Nettoyer les anciennes données si succès
    } else {
        $_SESSION['old_event_data'] = $_POST;
        throw new Exception("Erreur lors de l'ajout de l'événement.");
    }
}

/**
 * Gère la suppression d'un événement.
 *
 * @param PDO $pdo L'objet de connexion PDO à la base de données.
 */
function handleDeleteEvent(PDO $pdo) {
    global $redirect_url;

    $event_id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

    if (!$event_id) {
        throw new Exception("ID d'événement invalide pour la suppression.");
    }

    // Récupérer le chemin de l'image associée avant de supprimer l'événement de la DB
    $image_to_delete = null;
    $stmt_get_image = $pdo->prepare("SELECT image_path FROM events WHERE id = :id");
    $stmt_get_image->bindParam(':id', $event_id, PDO::PARAM_INT);
    $stmt_get_image->execute();
    $result_image = $stmt_get_image->fetch(PDO::FETCH_ASSOC);

    if ($result_image) {
        $image_to_delete = $result_image['image_path'];
    }

    // Démarrer une transaction pour s'assurer que la suppression de l'événement et de ses associations est atomique
    $pdo->beginTransaction();

    try {
        // Supprimer d'abord toutes les références à cet événement dans d'autres tables si nécessaire
        // Exemple (décommenter si vous avez des tables liées comme 'enrollments' ou 'event_registrations'):
        // $stmt_delete_registrations = $pdo->prepare("DELETE FROM event_registrations WHERE event_id = :event_id");
        // $stmt_delete_registrations->bindParam(':event_id', $event_id, PDO::PARAM_INT);
        // $stmt_delete_registrations->execute();

        // Supprimer l'événement de la table 'events'
        $stmt = $pdo->prepare("DELETE FROM events WHERE id = :id");
        $stmt->bindParam(':id', $event_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            // Si l'événement a été supprimé avec succès de la DB, supprimer l'image associée du serveur
            if ($image_to_delete && file_exists($image_to_delete)) {
                unlink($image_to_delete);
            }
            $pdo->commit(); // Confirmer la transaction
            $_SESSION['success_message'] = "Événement et ses données associées supprimés avec succès.";
        } else {
            throw new Exception("Erreur lors de la suppression de l'événement.");
        }
    } catch (Exception $e) {
        $pdo->rollBack(); // Annuler la transaction en cas d'erreur
        throw $e; // Relancer l'exception pour être capturée par le bloc try-catch principal
    }
}
?>