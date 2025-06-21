<?php
session_start();
require_once 'config.php'; // Inclut l'objet PDO $pdo

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Accès non autorisé. Veuillez vous connecter.";
    header("Location: authentification.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'update_profile':
            handleUpdateProfile($pdo, $user_id);
            break;
        case 'change_password':
            handleChangePassword($pdo, $user_id);
            break;
        default:
            $_SESSION['error_message'] = "Action non reconnue.";
            header("Location: profile_settings.php");
            exit();
    }
} else {
    $_SESSION['error_message'] = "Méthode de requête non autorisée.";
    header("Location: profile_settings.php");
    exit();
}

/**
 * Gère la mise à jour des informations de profil (email, photo).
 */
function handleUpdateProfile($pdo, $user_id) {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL); // Récupérer et filtrer l'email

    if (!$email) {
        $_SESSION['error_message'] = "Adresse e-mail invalide.";
        header("Location: profile_settings.php");
        exit();
    }

    $current_profile_picture = '';
    try {
        // Récupérer la photo de profil actuelle pour la supprimer si une nouvelle est uploadée
        $stmt_get_pic = $pdo->prepare("SELECT profile_picture FROM users WHERE id = :user_id");
        $stmt_get_pic->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt_get_pic->execute();
        $current_profile_picture = $stmt_get_pic->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération de l'ancienne photo de profil: " . $e->getMessage());
        $_SESSION['error_message'] = "Erreur interne lors de la mise à jour du profil.";
        header("Location: profile_settings.php");
        exit();
    }

    $profile_picture_name = $current_profile_picture; // Garder l'ancienne image par défaut

    // Gérer l'upload de la photo de profil
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/profile_pictures/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true); // Crée le dossier s'il n'existe pas
        }

        $file_tmp = $_FILES['profile_picture']['tmp_name'];
        $file_name = uniqid('profile_') . '_' . basename($_FILES['profile_picture']['name']); // Nom de fichier unique
        $file_path = $upload_dir . $file_name;
        $file_type = mime_content_type($file_tmp); // Utilise mime_content_type pour une meilleure sécurité

        // Vérifier le type de fichier
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error_message'] = "Seuls les fichiers JPG, PNG et GIF sont autorisés pour la photo de profil.";
            header("Location: profile_settings.php");
            exit();
        }

        // Vérifier la taille du fichier (ex: max 2MB)
        if ($_FILES['profile_picture']['size'] > 2 * 1024 * 1024) { // 2 MB
            $_SESSION['error_message'] = "La photo de profil est trop grande (max 2MB).";
            header("Location: profile_settings.php");
            exit();
        }

        if (move_uploaded_file($file_tmp, $file_path)) {
            // Suppression de l'ancienne photo si elle existe et n'est pas l'image par défaut
            if ($current_profile_picture && $current_profile_picture !== 'default_profile.png' && file_exists($upload_dir . $current_profile_picture)) {
                unlink($upload_dir . $current_profile_picture);
            }
            $profile_picture_name = $file_name;
        } else {
            $_SESSION['error_message'] = "Erreur lors du téléchargement de la photo de profil.";
            header("Location: profile_settings.php");
            exit();
        }
    }

    try {
        // Mettre à jour l'email et le nom de la photo de profil dans la base de données
        $stmt = $pdo->prepare("UPDATE users SET email = :email, profile_picture = :profile_picture WHERE id = :user_id");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':profile_picture', $profile_picture_name, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['success_message'] = "Profil mis à jour avec succès !";
        // Mettre à jour la variable de session si nécessaire (ex: username, pas nécessaire pour email)
        // $_SESSION['username'] = $username; // Si vous autorisez la modification de l'username
        header("Location: profile_settings.php");
        exit();

    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la mise à jour du profil: " . $e->getMessage());
        $_SESSION['error_message'] = "Une erreur est survenue lors de la mise à jour de votre profil. Veuillez réessayer.";
        header("Location: profile_settings.php");
        exit();
    }
}

/**
 * Gère le changement de mot de passe.
 */
function handleChangePassword($pdo, $user_id) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    // Vérification des champs vides
    if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
        $_SESSION['error_message'] = "Tous les champs du mot de passe sont requis.";
        header("Location: profile_settings.php");
        exit();
    }

    // Vérification de la correspondance des nouveaux mots de passe
    if ($new_password !== $confirm_new_password) {
        $_SESSION['error_message'] = "Le nouveau mot de passe et sa confirmation ne correspondent pas.";
        header("Location: profile_settings.php");
        exit();
    }

    // Règles de complexité du mot de passe (adaptez selon vos besoins)
    if (strlen($new_password) < 8 ||
        !preg_match("#[0-9]+#", $new_password) ||
        !preg_match("#[a-z]+#", $new_password) ||
        !preg_match("#[A-Z]+#", $new_password) ||
        !preg_match("#\W+#", $new_password)) { // Au moins un caractère spécial
        $_SESSION['error_message'] = "Le nouveau mot de passe doit contenir au moins 8 caractères, dont une majuscule, une minuscule, un chiffre et un caractère spécial.";
        header("Location: profile_settings.php");
        exit();
    }

    try {
        // Récupérer le mot de passe actuel de l'utilisateur depuis la base de données
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($current_password, $user['password'])) {
            $_SESSION['error_message'] = "L'ancien mot de passe est incorrect.";
            header("Location: profile_settings.php");
            exit();
        }

        // Hacher le nouveau mot de passe
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Mettre à jour le mot de passe dans la base de données
        $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :user_id");
        $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['success_message'] = "Mot de passe mis à jour avec succès !";
        header("Location: profile_settings.php");
        exit();

    } catch (PDOException $e) {
        error_log("Erreur PDO lors du changement de mot de passe: " . $e->getMessage());
        $_SESSION['error_message'] = "Une erreur est survenue lors du changement de mot de passe. Veuillez réessayer.";
        header("Location: profile_settings.php");
        exit();
    }
}
?>