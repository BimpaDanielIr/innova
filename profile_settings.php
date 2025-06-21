<?php
session_start();
require_once 'config.php';

// Activez l'affichage des erreurs pour le développement (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Veuillez vous connecter pour accéder à cette page.";
    header("Location: authentification.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_data = null;
$error_message = '';
$success_message = '';

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

try {
    // Récupérer les informations actuelles de l'utilisateur
    $stmt = $pdo->prepare("SELECT id, username, email, profile_picture FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        $_SESSION['error_message'] = "Impossible de charger vos informations de profil. Veuillez vous reconnecter.";
        header("Location: authentification.php");
        exit();
    }

    // Gestion de la soumission du formulaire de mise à jour
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $new_username = trim($_POST['username']);
        $new_email = trim($_POST['email']);
        $new_password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Validation de base
        if (empty($new_username) || empty($new_email)) {
            $error_message = "Le nom d'utilisateur et l'email ne peuvent pas être vides.";
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Format d'email invalide.";
        } elseif (!empty($new_password) && $new_password !== $confirm_password) {
            $error_message = "Les mots de passe ne correspondent pas.";
        } else {
            // Mettre à jour l'email et le nom d'utilisateur
            $update_query = "UPDATE users SET username = :username, email = :email";
            $params = [
                ':username' => $new_username,
                ':email' => $new_email,
                ':user_id' => $user_id
            ];

            // Mettre à jour le mot de passe si fourni
            if (!empty($new_password)) {
                // Hacher le nouveau mot de passe
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query .= ", password_hash = :password_hash";
                $params[':password_hash'] = $hashed_password;
            }

            // Gestion de l'upload de photo de profil
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
                $target_dir = "uploads/profile_pictures/"; // Assurez-vous que ce dossier existe et est inscriptible
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }

                $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $new_file_name = uniqid('profile_') . '.' . $file_extension;
                $target_file = $target_dir . $new_file_name;
                $uploadOk = 1;
                $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

                // Vérifier si le fichier est une image réelle
                $check = getimagesize($_FILES['profile_picture']['tmp_name']);
                if ($check === false) {
                    $error_message = "Le fichier n'est pas une image.";
                    $uploadOk = 0;
                }

                // Vérifier la taille du fichier (ex: max 5MB)
                if ($_FILES['profile_picture']['size'] > 5000000) {
                    $error_message = "Désolé, votre fichier est trop grand.";
                    $uploadOk = 0;
                }

                // Autoriser certains formats de fichiers
                if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
                    $error_message = "Désolé, seuls les fichiers JPG, JPEG, PNG & GIF sont autorisés.";
                    $uploadOk = 0;
                }

                if ($uploadOk == 0) {
                    // Erreur déjà définie par les vérifications ci-dessus
                } else {
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                        // Supprimer l'ancienne photo si elle n'est pas la photo par défaut
                        if (!empty($user_data['profile_picture']) && $user_data['profile_picture'] !== 'assets/images/default_profile.png' && file_exists($user_data['profile_picture'])) {
                            unlink($user_data['profile_picture']);
                        }
                        $update_query .= ", profile_picture = :profile_picture";
                        $params[':profile_picture'] = $target_file;
                        $_SESSION['user_profile_picture'] = $target_file; // Mettre à jour la session si vous la gardez
                    } else {
                        $error_message = "Désolé, une erreur s'est produite lors de l'envoi de votre fichier.";
                    }
                }
            }

            if (empty($error_message)) { // Si aucune erreur d'upload ou de validation simple
                $stmt_update = $pdo->prepare($update_query . " WHERE id = :user_id");
                if ($stmt_update->execute($params)) {
                    $_SESSION['success_message'] = "Votre profil a été mis à jour avec succès.";
                    // Mettre à jour les données de session si elles ont changé
                    $_SESSION['username'] = $new_username; // Assurez-vous que votre système de session gère 'username'
                    // Recharger les données pour afficher les dernières informations
                    header("Location: profile_settings.php");
                    exit();
                } else {
                    $error_message = "Erreur lors de la mise à jour de la base de données. Veuillez réessayer.";
                }
            }
        }
    }

    // Récupérer les données de l'utilisateur après une éventuelle mise à jour
    $stmt = $pdo->prepare("SELECT id, username, email, profile_picture FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    $profile_picture_path = $user_data['profile_picture'] ?? 'assets/images/default_profile.png';

} catch (PDOException $e) {
    error_log("Database error on profile_settings.php: " . $e->getMessage());
    $error_message = "Une erreur de base de données est survenue. Veuillez réessayer plus tard.";
} catch (Exception $e) {
    $error_message = $e->getMessage();
}

$page_title = "Paramètres du Profil - InnovaTech";
include 'header.php';
?>

<div class="container py-5">
    <h1 class="mb-4 text-center text-primary"><i class="fas fa-user-cog me-2"></i> Paramètres de votre profil InnovaTech</h1>
    <p class="lead text-center text-muted">Mettez à jour vos informations personnelles et gérez votre compte.</p>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mt-4">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Modifier mes informations</h4>
        </div>
        <div class="card-body">
            <form action="profile_settings.php" method="POST" enctype="multipart/form-data">
                <div class="text-center mb-4">
                    <img src="<?php echo htmlspecialchars($profile_picture_path); ?>" alt="Photo de profil" class="img-fluid rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                    <h5 class="mt-3"><?php echo htmlspecialchars(ucfirst($user_data['username'])); ?></h5>
                </div>

                <div class="mb-3">
                    <label for="username" class="form-label">Nom d'utilisateur</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Adresse Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="profile_picture" class="form-label">Changer la photo de profil</label>
                    <input class="form-control" type="file" id="profile_picture" name="profile_picture" accept="image/*">
                    <div class="form-text">Formats acceptés : JPG, JPEG, PNG, GIF. Max 5MB.</div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Nouveau Mot de passe (laisser vide si inchangé)</label>
                    <input type="password" class="form-control" id="password" name="password">
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i> Enregistrer les modifications</button>
                    <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> Retour au Tableau de Bord</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
include 'footer.php';
?>