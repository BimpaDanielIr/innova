<?php
session_start();

// Protection de la page : l'utilisateur doit être connecté ET avoir le rôle 'admin'
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_message'] = "Accès non autorisé. Vous n'avez pas les permissions d'administrateur.";
    header("Location: authentification.php");
    exit();
}

// Inclure le fichier de configuration de la base de données (qui inclut PDO)
require_once 'config.php'; // Assurez-vous que ce chemin est correct

$redirect_url = "admin_manage_posts.php"; // URL de redirection par défaut

try {
    // Vérifier la méthode de requête POST et le token CSRF
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Erreur CSRF : Jeton invalide. Veuillez réessayer.");
        }

        $action = $_POST['action'] ?? ''; // 'add', 'update', 'delete'
        $post_id = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT); // Pour update et delete

        switch ($action) {
            case 'add':
                // Récupération et validation des données pour l'ajout
                $title = trim($_POST['title'] ?? '');
                $content = trim($_POST['content'] ?? '');
                $post_type = $_POST['post_type'] ?? '';
                $author_id = $_SESSION['user_id']; // L'auteur est l'admin connecté
                $is_active = isset($_POST['is_active']) ? 1 : 0; // Checkbox
                $image_path = null;

                // Validation simple (peut être étendue)
                if (empty($title) || empty($content) || empty($post_type)) {
                    $_SESSION['error_message'] = "Tous les champs obligatoires (Titre, Contenu, Type) doivent être remplis.";
                    $_SESSION['old_post_data'] = $_POST; // Pour pré-remplir le formulaire
                    $redirect_url = "add_post.php"; // Rediriger vers le formulaire d'ajout
                    break; // Sortir du switch
                }

                // Gestion de l'upload d'image
                if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
                    $target_dir = "uploads/posts/";
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }
                    $image_name = uniqid() . '_' . basename($_FILES['image']['name']);
                    $target_file = $target_dir . $image_name;
                    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

                    // Vérifier si le fichier est une vraie image
                    $check = getimagesize($_FILES['image']['tmp_name']);
                    if ($check === false) {
                        throw new Exception("Le fichier n'est pas une image valide.");
                    }

                    // Vérifier la taille du fichier (ex: 5MB maximum)
                    if ($_FILES['image']['size'] > 5000000) {
                        throw new Exception("Désolé, votre fichier est trop volumineux (max 5MB).");
                    }

                    // Autoriser certains formats de fichier
                    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                    if (!in_array($imageFileType, $allowed_types)) {
                        throw new Exception("Désolé, seuls les fichiers JPG, JPEG, PNG et GIF sont autorisés.");
                    }

                    // Tenter de télécharger le fichier
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                        $image_path = $target_file;
                    } else {
                        throw new Exception("Une erreur est survenue lors du téléchargement de votre image.");
                    }
                }

                // Insertion dans la base de données
                $stmt = $pdo->prepare("INSERT INTO posts (title, content, post_type, author_id, image_path, is_active) VALUES (:title, :content, :post_type, :author_id, :image_path, :is_active)");
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':content', $content);
                $stmt->bindParam(':post_type', $post_type);
                $stmt->bindParam(':author_id', $author_id, PDO::PARAM_INT);
                $stmt->bindParam(':image_path', $image_path);
                $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Publication ajoutée avec succès !";
                } else {
                    throw new Exception("Erreur lors de l'ajout de la publication.");
                }
                break;

            case 'update':
                // Vérification de l'ID pour la mise à jour
                if (!$post_id) {
                    $_SESSION['error_message'] = "ID de publication invalide pour la mise à jour.";
                    $redirect_url = "admin_manage_posts.php"; // Rediriger vers la liste
                    break;
                }

                $title = trim($_POST['title'] ?? '');
                $content = trim($_POST['content'] ?? '');
                $post_type = $_POST['post_type'] ?? '';
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                $current_image_path = $_POST['current_image_path'] ?? null; // Récupère le chemin de l'image actuelle

                if (empty($title) || empty($content) || empty($post_type)) {
                    $_SESSION['error_message'] = "Tous les champs obligatoires (Titre, Contenu, Type) doivent être remplis.";
                    $_SESSION['old_post_data'] = $_POST;
                    $redirect_url = "admin_edit_post.php?id=" . $post_id;
                    break;
                }

                $new_image_path = $current_image_path; // Par défaut, conserve l'ancienne image

                // Gestion de l'upload de nouvelle image
                if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
                    $target_dir = "uploads/posts/";
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }
                    $image_name = uniqid() . '_' . basename($_FILES['image']['name']);
                    $target_file = $target_dir . $image_name;
                    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

                    $check = getimagesize($_FILES['image']['tmp_name']);
                    if ($check === false) {
                        throw new Exception("Le fichier n'est pas une image valide.");
                    }
                    if ($_FILES['image']['size'] > 5000000) {
                        throw new Exception("Désolé, votre fichier est trop volumineux (max 5MB).");
                    }
                    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                    if (!in_array($imageFileType, $allowed_types)) {
                        throw new Exception("Désolé, seuls les fichiers JPG, JPEG, PNG et GIF sont autorisés.");
                    }

                    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                        $new_image_path = $target_file;
                        // Supprimer l'ancienne image si elle existe et est différente de la nouvelle
                        if ($current_image_path && file_exists($current_image_path) && $current_image_path != $new_image_path) {
                            unlink($current_image_path);
                        }
                    } else {
                        throw new Exception("Une erreur est survenue lors du téléchargement de votre nouvelle image.");
                    }
                } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
                    // Si l'utilisateur a coché la case "supprimer l'image"
                    if ($current_image_path && file_exists($current_image_path)) {
                        unlink($current_image_path);
                    }
                    $new_image_path = null; // Supprimer le chemin de l'image de la DB
                }


                // Mise à jour dans la base de données
                $stmt = $pdo->prepare("UPDATE posts SET title = :title, content = :content, post_type = :post_type, image_path = :image_path, is_active = :is_active WHERE id = :id");
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':content', $content);
                $stmt->bindParam(':post_type', $post_type);
                $stmt->bindParam(':image_path', $new_image_path);
                $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
                $stmt->bindParam(':id', $post_id, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Publication mise à jour avec succès !";
                } else {
                    throw new Exception("Erreur lors de la mise à jour de la publication.");
                }
                break;

            case 'delete':
                if (!$post_id) {
                    throw new Exception("ID de publication invalide pour la suppression.");
                }

                // Récupérer le chemin de l'image avant de supprimer l'entrée de la DB
                $stmt_img = $pdo->prepare("SELECT image_path FROM posts WHERE id = :id");
                $stmt_img->bindParam(':id', $post_id, PDO::PARAM_INT);
                $stmt_img->execute();
                $image_to_delete = $stmt_img->fetchColumn(); // Récupère directement le chemin de l'image

                // Supprimer l'entrée de la base de données
                $stmt = $pdo->prepare("DELETE FROM posts WHERE id = :id");
                $stmt->bindParam(':id', $post_id, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    // Supprimer le fichier image du serveur si existant
                    if ($image_to_delete && file_exists($image_to_delete)) {
                        unlink($image_to_delete);
                    }
                    $_SESSION['success_message'] = "Publication supprimée avec succès !";
                } else {
                    throw new Exception("Erreur lors de la suppression de la publication.");
                }
                break;

            default:
                throw new Exception("Action non reconnue.");
        }

    } else {
        throw new Exception("Requête non valide (doit être POST).");
    }

} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    // Garder les anciennes données pour les erreurs d'ajout/modification
    if ($action === 'add' || $action === 'update') {
        $_SESSION['old_post_data'] = $_POST;
    }
} finally {
    // Redirection vers la page de gestion des publications, en maintenant les messages de session
    header("Location: " . $redirect_url);
    exit();
}