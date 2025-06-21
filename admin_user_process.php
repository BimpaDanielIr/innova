<?php
session_start();

// Inclure le fichier de configuration de la base de données
// Assurez-vous que le chemin est correct par rapport à l'emplacement de admin_user_process.php
require_once 'config.php';

// Protection CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = "Erreur de sécurité : Jeton CSRF invalide. Veuillez réessayer.";
    header("Location: admin_manage_users.php");
    exit();
}

// Protection de la page : l'utilisateur doit être connecté ET avoir le rôle 'admin'
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_message'] = "Accès non autorisé. Vous n'avez pas les permissions d'administrateur.";
    header("Location: authentification.php");
    exit();
}

$redirect_url = "admin_manage_users.php"; // URL de redirection par défaut

try {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            handleAddUser($pdo);
            break;
        case 'update':
            handleUpdateUser($pdo);
            break;
        case 'delete':
            handleDeleteUser($pdo);
            break;
        default:
            $_SESSION['error_message'] = "Action non reconnue.";
            break;
    }

} catch (PDOException $e) {
    $_SESSION['error_message'] = "Erreur de base de données : " . $e->getMessage();
} catch (Exception $e) {
    $_SESSION['error_message'] = "Erreur : " . $e->getMessage();
} finally {
    // Redirection vers la page de gestion des utilisateurs
    header("Location: " . $redirect_url);
    exit();
}

/**
 * Gère l'ajout d'un nouvel utilisateur.
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @throws Exception En cas d'erreur de validation ou d'insertion.
 */
function handleAddUser(PDO $pdo) {
    $username = trim($_POST['username'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user'; // Rôle par défaut
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($username) || empty($email) || empty($password)) {
        throw new Exception("Tous les champs obligatoires (Nom d'utilisateur, Email, Mot de passe) doivent être remplis.");
    }
    if ($email === false) {
        throw new Exception("Adresse email invalide.");
    }
    if (!in_array($role, ['user', 'admin'])) { // Assurez-vous que le rôle est valide
        throw new Exception("Rôle utilisateur invalide.");
    }
    if (strlen($password) < 6) {
        throw new Exception("Le mot de passe doit contenir au moins 6 caractères.");
    }

    // Vérifier si l'email ou le nom d'utilisateur existe déjà
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email OR username = :username");
    $stmt_check->execute([':email' => $email, ':username' => $username]);
    if ($stmt_check->fetchColumn() > 0) {
        throw new Exception("L'email ou le nom d'utilisateur existe déjà.");
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, is_active) VALUES (:username, :email, :password_hash, :role, :is_active)");
    if (!$stmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => $hashed_password,
        ':role' => $role,
        ':is_active' => $is_active
    ])) {
        throw new Exception("Erreur lors de l'ajout de l'utilisateur : " . implode(" ", $stmt->errorInfo()));
    }

    $_SESSION['success_message'] = "Utilisateur ajouté avec succès !";
}

/**
 * Gère la mise à jour d'un utilisateur existant.
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @throws Exception En cas d'erreur de validation ou de mise à jour.
 */
function handleUpdateUser(PDO $pdo) {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $username = trim($_POST['username'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? ''; // Optionnel, seulement si modifié
    $role = $_POST['role'] ?? 'user';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (!$user_id) {
        throw new Exception("ID utilisateur invalide pour la modification.");
    }
    if (empty($username) || empty($email)) {
        throw new Exception("Le nom d'utilisateur et l'email sont obligatoires.");
    }
    if ($email === false) {
        throw new Exception("Adresse email invalide.");
    }
    if (!in_array($role, ['user', 'admin'])) {
        throw new Exception("Rôle utilisateur invalide.");
    }

    // Vérifier si l'email ou le nom d'utilisateur existe déjà pour un AUTRE utilisateur
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (email = :email OR username = :username) AND id != :user_id");
    $stmt_check->execute([':email' => $email, ':username' => $username, ':user_id' => $user_id]);
    if ($stmt_check->fetchColumn() > 0) {
        throw new Exception("L'email ou le nom d'utilisateur est déjà utilisé par un autre compte.");
    }

    $sql = "UPDATE users SET username = :username, email = :email, role = :role, is_active = :is_active";
    $params = [
        ':username' => $username,
        ':email' => $email,
        ':role' => $role,
        ':is_active' => $is_active,
        ':user_id' => $user_id
    ];

    if (!empty($password)) {
        if (strlen($password) < 6) {
            throw new Exception("Le nouveau mot de passe doit contenir au moins 6 caractères.");
        }
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql .= ", password_hash = :password_hash";
        $params[':password_hash'] = $hashed_password;
    }

    $sql .= " WHERE id = :user_id";

    $stmt = $pdo->prepare($sql);
    if (!$stmt->execute($params)) {
        throw new Exception("Erreur lors de la mise à jour de l'utilisateur : " . implode(" ", $stmt->errorInfo()));
    }

    $_SESSION['success_message'] = "Utilisateur mis à jour avec succès !";
}

/**
 * Gère la suppression d'un utilisateur.
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @throws Exception En cas d'erreur de validation ou de suppression.
 */
function handleDeleteUser(PDO $pdo) {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

    if (!$user_id) {
        throw new Exception("ID utilisateur invalide pour la suppression.");
    }

    // Protection : Ne pas permettre à un admin de se supprimer lui-même s'il est le seul admin
    // Vérifier le rôle de l'utilisateur à supprimer
    $stmt_role = $pdo->prepare("SELECT role FROM users WHERE id = :user_id");
    $stmt_role->execute([':user_id' => $user_id]);
    $user_to_delete_role = $stmt_role->fetchColumn();

    if ($user_to_delete_role === 'admin') {
        $stmt_admin_count = $pdo->prepare("SELECT COUNT(id) AS admin_count FROM users WHERE role = 'admin'");
        $stmt_admin_count->execute();
        $admin_data = $stmt_admin_count->fetch(PDO::FETCH_ASSOC);

        if ($admin_data['admin_count'] == 1 && $user_id == $_SESSION['user_id']) {
            throw new Exception("Vous êtes le seul administrateur. Vous ne pouvez pas supprimer votre propre compte.");
        }
    }

    // Début de la transaction
    $pdo->beginTransaction();
    try {
        // Optionnel: Supprimer les inscriptions de cet utilisateur
        // $stmt_enrollments = $pdo->prepare("DELETE FROM enrollments WHERE user_id = :user_id");
        // $stmt_enrollments->execute([':user_id' => $user_id]);

        // Optionnel: Supprimer les posts créés par cet utilisateur
        // $stmt_posts = $pdo->prepare("DELETE FROM posts WHERE author_id = :user_id");
        // $stmt_posts->execute([':user_id' => $user_id]);

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :user_id");
        if (!$stmt->execute([':user_id' => $user_id])) {
            throw new Exception("Erreur lors de la suppression de l'utilisateur : " . implode(" ", $stmt->errorInfo()));
        }

        $pdo->commit(); // Valider la transaction

        $_SESSION['success_message'] = "Utilisateur supprimé avec succès.";

        // Si l'utilisateur supprimé est l'utilisateur actuellement connecté, le déconnecter
        if ($user_id == $_SESSION['user_id']) {
            session_destroy();
            global $redirect_url; // Utiliser la variable globale
            $redirect_url = "authentification.php"; // Rediriger vers la page de connexion
        }
    } catch (Exception $e) {
        $pdo->rollBack(); // Annuler la transaction en cas d'erreur
        throw $e; // Rendre l'exception au bloc catch principal
    }
}