<?php
session_start();

// Activez l'affichage des erreurs pour le développement (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php'; // Assurez-vous que ce fichier initialise l'objet $pdo

// Protection CSRF : Vérifie le jeton pour toutes les actions POST.
// Si le jeton n'est pas présent ou ne correspond pas, rediriger avec une erreur.
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = "Erreur de sécurité : Jeton CSRF invalide. Veuillez réessayer.";
    // Rediriger vers la page d'où vient la requête ou une page par défaut
    $redirect_url = "admin_manage_formations.php";
    if (isset($_SERVER['HTTP_REFERER'])) {
        $redirect_url = $_SERVER['HTTP_REFERER'];
    }
    header("Location: " . $redirect_url);
    exit();
}

// Protection de la page : l'utilisateur doit être connecté ET avoir le rôle 'admin'
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_message'] = "Accès non autorisé. Vous n'avez pas les permissions d'administrateur.";
    header("Location: authentification.php");
    exit();
}

$redirect_url = "admin_manage_formations.php"; // URL de redirection par défaut

try {
    if (!($pdo instanceof PDO)) {
        throw new Exception("Erreur de configuration de la base de données: l'objet PDO n'est pas disponible.");
    }

    $action = $_POST['action'] ?? '';
    $formation_id = filter_input(INPUT_POST, 'formation_id', FILTER_VALIDATE_INT);
    $current_image_path = null; // Pour stocker le chemin de l'image actuelle lors d'une modification

    // Récupérer le chemin de l'image actuelle si l'action est 'update' et que l'ID est valide
    if ($action === 'update' && $formation_id) {
        $stmt_get_image = $pdo->prepare("SELECT image_path FROM formations WHERE id = :formation_id");
        $stmt_get_image->bindParam(':formation_id', $formation_id, PDO::PARAM_INT);
        $stmt_get_image->execute();
        $result_img = $stmt_get_image->fetch(PDO::FETCH_ASSOC);
        if ($result_img) {
            $current_image_path = $result_img['image_path'];
        }
    }

    // Gérer l'upload d'image
    $image_path = $current_image_path; // Par défaut, conserve l'ancienne image
    if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "uploads/formations/";
        // Assurez-vous que le dossier d'upload existe
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $image_name = uniqid() . '_' . basename($_FILES['image']['name']);
        $target_file = $target_dir . $image_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Vérifier le type de fichier
        $check = getimagesize($_FILES["image"]["tmp_name"]);
        if ($check === false) {
            throw new Exception("Le fichier n'est pas une image.");
        }

        // Limiter la taille du fichier (ex: 5MB)
        if ($_FILES["image"]["size"] > 5000000) {
            throw new Exception("Désolé, votre image est trop grande. Max 5MB.");
        }

        // Autoriser certains formats de fichier
        if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
            throw new Exception("Désolé, seuls les fichiers JPG, JPEG, PNG et GIF sont autorisés.");
        }

        // Déplacer l'image uploadée
        if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            throw new Exception("Désolé, une erreur est survenue lors de l'upload de votre image.");
        }

        $image_path = $target_file;

        // Si c'est une mise à jour et qu'une nouvelle image a été uploadée, supprimer l'ancienne
        if ($action === 'update' && $current_image_path && file_exists($current_image_path)) {
            unlink($current_image_path);
        }
    } elseif ($action === 'update' && isset($_POST['delete_image']) && $_POST['delete_image'] === '1') {
        // Option pour supprimer l'image existante sans en uploader une nouvelle
        if ($current_image_path && file_exists($current_image_path)) {
            unlink($current_image_path);
        }
        $image_path = null; // Mettre le chemin à NULL dans la base de données
    }

    // Prépare les données communes aux actions 'add' et 'update'
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $instructor_id = filter_input(INPUT_POST, 'instructor_id', FILTER_VALIDATE_INT);
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $max_participants = filter_input(INPUT_POST, 'max_participants', FILTER_VALIDATE_INT);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation basique
    if (empty($title) || empty($description) || empty($category) || empty($duration) || $price === false || empty($start_date) || empty($end_date)) {
        throw new Exception("Tous les champs obligatoires (Titre, Description, Catégorie, Durée, Prix, Dates) doivent être remplis.");
    }
    if ($price < 0) {
        throw new Exception("Le prix ne peut pas être négatif.");
    }
    if ($max_participants !== false && $max_participants < 0) {
        throw new Exception("Le nombre maximum de participants ne peut pas être négatif.");
    }
    if (new DateTime($start_date) > new DateTime($end_date)) {
        throw new Exception("La date de début ne peut pas être postérieure à la date de fin.");
    }

    // S'assurer que l'instructeur existe si un ID est fourni
    if ($instructor_id) {
        $stmt_check_instructor = $pdo->prepare("SELECT id FROM users WHERE id = :instructor_id AND user_role = 'instructor'");
        $stmt_check_instructor->bindParam(':instructor_id', $instructor_id, PDO::PARAM_INT);
        $stmt_check_instructor->execute();
        if ($stmt_check_instructor->rowCount() === 0) {
            throw new Exception("L'ID de l'instructeur spécifié n'existe pas ou n'est pas un instructeur valide.");
        }
    } else {
        $instructor_id = null; // Si aucun instructeur n'est sélectionné, stocker NULL
    }

    switch ($action) {
        case 'add':
            $pdo->beginTransaction(); // Démarre une transaction
            $stmt = $pdo->prepare("
                INSERT INTO formations (title, description, category, duration, price, instructor_id, start_date, end_date, max_participants, image_path, is_active, created_at)
                VALUES (:title, :description, :category, :duration, :price, :instructor_id, :start_date, :end_date, :max_participants, :image_path, :is_active, NOW())
            ");
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':duration', $duration);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':instructor_id', $instructor_id, PDO::PARAM_INT);
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
            $stmt->bindParam(':max_participants', $max_participants, PDO::PARAM_INT);
            $stmt->bindParam(':image_path', $image_path);
            $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $pdo->commit(); // Confirme la transaction
                $_SESSION['success_message'] = "Formation ajoutée avec succès !";
            } else {
                $pdo->rollBack(); // Annule la transaction
                throw new Exception("Erreur lors de l'ajout de la formation.");
            }
            break;

        case 'update':
            if (!$formation_id) {
                throw new Exception("ID de formation manquant pour la modification.");
            }

            $pdo->beginTransaction(); // Démarre une transaction

            $stmt = $pdo->prepare("
                UPDATE formations
                SET title = :title,
                    description = :description,
                    category = :category,
                    duration = :duration,
                    price = :price,
                    instructor_id = :instructor_id,
                    start_date = :start_date,
                    end_date = :end_date,
                    max_participants = :max_participants,
                    image_path = :image_path,
                    is_active = :is_active,
                    created_at = NOW()
                WHERE id = :formation_id
            ");
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':duration', $duration);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':instructor_id', $instructor_id, PDO::PARAM_INT);
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
            $stmt->bindParam(':max_participants', $max_participants, PDO::PARAM_INT);
            $stmt->bindParam(':image_path', $image_path);
            $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
            $stmt->bindParam(':formation_id', $formation_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $pdo->commit(); // Confirme la transaction
                $_SESSION['success_message'] = "Formation mise à jour avec succès !";
            } else {
                $pdo->rollBack(); // Annule la transaction
                throw new Exception("Erreur lors de la mise à jour de la formation.");
            }
            break;

        case 'delete':
            if (!$formation_id) {
                throw new Exception("ID de formation manquant pour la suppression.");
            }

            $pdo->beginTransaction(); // Démarre une transaction

            // Récupérer le chemin de l'image associée avant de supprimer l'entrée de la DB
            $stmt_img = $pdo->prepare("SELECT image_path FROM formations WHERE id = :formation_id");
            $stmt_img->bindParam(':formation_id', $formation_id, PDO::PARAM_INT);
            $stmt_img->execute();
            $image_to_delete = $stmt_img->fetchColumn(); // Récupère directement la valeur de la colonne

            // Supprimer la formation de la base de données
            $stmt = $pdo->prepare("DELETE FROM formations WHERE id = :formation_id");
            $stmt->bindParam(':formation_id', $formation_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                // Supprimer le fichier image du serveur si existant
                if ($image_to_delete && file_exists($image_to_delete)) {
                    unlink($image_to_delete);
                }
                $pdo->commit(); // Confirme la transaction
                $_SESSION['success_message'] = "Formation supprimée avec succès.";
            } else {
                $pdo->rollBack(); // Annule la transaction
                throw new Exception("Erreur lors de la suppression de la formation.");
            }
            break;

        default:
            $_SESSION['error_message'] = "Action non reconnue.";
            break;
    }

} catch (PDOException $e) {
    // Si une transaction est active et qu'une erreur PDO se produit, la rollback
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = "Erreur de base de données : " . $e->getMessage();
} catch (Exception $e) {
    // Gérer les autres exceptions (validation, upload de fichier, etc.)
    if ($pdo->inTransaction()) { // Si une transaction a été commencée, l'annuler
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = $e->getMessage();
}

// Redirection vers la page de gestion des formations, en maintenant les messages de session
header("Location: " . $redirect_url);
exit();