<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Génère un token de 64 caractères hexadécimaux
}

// Protection du script : l'utilisateur doit être connecté
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Accès non autorisé. Veuillez vous connecter.";
    header("Location: authentification.php");
    exit();
}

$user_id = $_SESSION['user_id']; // L'ID de l'utilisateur connecté

// Paramètres de connexion à la base de données
$servername = "localhost";
$username = "root";
$password = "";
$database = "innova_tech_db";

$conn = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["profile_picture"])) {
    $target_dir = "uploads/profile_pictures/"; // Dossier où les images seront stockées
    $original_file_name = basename($_FILES["profile_picture"]["name"]);
    $imageFileType = strtolower(pathinfo($original_file_name, PATHINFO_EXTENSION));

    // Générer un nom de fichier unique pour éviter les conflits et pour la sécurité
    $new_file_name = uniqid('profile_', true) . '.' . $imageFileType;
    $target_file = $target_dir . $new_file_name;

    $uploadOk = 1;

    // 1. Vérifier si le fichier est une image réelle
    $check = getimagesize($_FILES["profile_picture"]["tmp_name"]);
    if ($check !== false) {
        // Le fichier est une image
        $uploadOk = 1;
    } else {
        $_SESSION['error_message'] = "Le fichier n'est pas une image.";
        $uploadOk = 0;
    }

    // 2. Vérifier la taille du fichier (ex: max 5MB)
    if ($_FILES["profile_picture"]["size"] > 5000000) { // 5MB = 5000000 bytes
        $_SESSION['error_message'] = "Désolé, votre fichier est trop volumineux (max 5MB).";
        $uploadOk = 0;
    }

    // 3. Autoriser certains formats de fichiers
    $allowed_types = array("jpg", "png", "jpeg", "gif");
    if (!in_array($imageFileType, $allowed_types)) {
        $_SESSION['error_message'] = "Désolé, seuls les formats JPG, JPEG, PNG & GIF sont autorisés.";
        $uploadOk = 0;
    }

    // 4. Vérifier si $uploadOk est à 0 à cause d'une erreur
    if ($uploadOk == 0) {
        // Rediriger si une erreur est survenue pendant la validation
        header("Location: dashboard.php");
        exit();
    } else {
        // Si tout est ok, essayer de télécharger le fichier
        if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
            try {
                $conn = new mysqli($servername, $username, $password, $database);
                if ($conn->connect_error) {
                    throw new Exception("Erreur de connexion à la base de données : " . $conn->connect_error);
                }

                // Récupérer l'ancien nom de fichier pour le supprimer (si ce n'est pas l'image par défaut)
                $stmt_select = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
                $stmt_select->bind_param("i", $user_id);
                $stmt_select->execute();
                $result_select = $stmt_select->get_result();
                $old_picture_row = $result_select->fetch_assoc();
                $old_picture_name = $old_picture_row['profile_picture'];
                $stmt_select->close();

                // Supprimer l'ancienne photo si elle existe et n'est pas l'image par défaut
                if ($old_picture_name && $old_picture_name !== 'default_profile.png') {
                    $old_picture_path = $target_dir . $old_picture_name;
                    if (file_exists($old_picture_path)) {
                        unlink($old_picture_path); // Supprime le fichier physique
                    }
                }

                // Mettre à jour la base de données avec le nouveau nom de fichier
                $stmt_update = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                $stmt_update->bind_param("si", $new_file_name, $user_id);

                if ($stmt_update->execute()) {
                    $_SESSION['success_message'] = "Votre photo de profil a été mise à jour avec succès.";
                    $_SESSION['profile_picture'] = $new_file_name; // Met à jour la session
                } else {
                    $_SESSION['error_message'] = "Erreur lors de la mise à jour de la base de données : " . $stmt_update->error;
                    // Si la mise à jour échoue, supprime le fichier qui vient d'être téléchargé pour éviter les orphelins
                    if (file_exists($target_file)) {
                        unlink($target_file);
                    }
                }
                $stmt_update->close();

            } catch (Exception $e) {
                $_SESSION['error_message'] = $e->getMessage();
                // En cas d'erreur BD, supprime aussi le fichier
                if (file_exists($target_file)) {
                    unlink($target_file);
                }
            } finally {
                if ($conn instanceof mysqli && !$conn->connect_error) {
                    $conn->close();
                }
            }
        } else {
            $_SESSION['error_message'] = "Désolé, une erreur s'est produite lors du téléchargement de votre fichier.";
        }
    }
} else {
    $_SESSION['error_message'] = "Aucun fichier n'a été sélectionné ou la méthode de requête est incorrecte.";
}

header("Location: dashboard.php"); // Redirige toujours vers le tableau de bord
exit();
?>