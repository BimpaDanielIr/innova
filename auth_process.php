<?php
session_start();
require_once 'config.php'; // Inclut l'objet PDO $pdo

// Régénérer l'ID de session à chaque connexion ou action sensible pour prévenir la fixation de session
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// Vérifier si le token CSRF est présent dans la session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$redirect_url = "authentification.php"; // URL de redirection par défaut

try {
    // Gérer l'action de déconnexion avant toute autre logique
    if (isset($_GET['action']) && $_GET['action'] === 'logout') {
        session_unset(); // Supprime toutes les variables de session
        session_destroy(); // Détruit la session
        $_SESSION['success_message'] = "Vous avez été déconnecté avec succès.";
        header("Location: authentification.php");
        exit();
    }

    // Le reste du script ne doit s'exécuter que pour les requêtes POST (login/register)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Vérification du token CSRF pour toutes les requêtes POST d'authentification
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Erreur de sécurité : Jeton CSRF invalide. Veuillez réessayer.");
        }

        // Action de connexion
        if (isset($_POST['login_submit'])) {
            $email = filter_var(trim($_POST['login_email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $password = $_POST['login_password'] ?? '';

            if (empty($email) || empty($password)) {
                throw new Exception("Veuillez saisir votre email et votre mot de passe.");
            }
            if ($email === false) {
                throw new Exception("Format d'email invalide.");
            }

            // Préparer et exécuter la requête pour récupérer l'utilisateur
            $stmt = $pdo->prepare("SELECT id, username, email, password_hash, role FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Vérifier si l'utilisateur existe et si le mot de passe est correct
            if ($user && password_verify($password, $user['password_hash'])) {
                // Mot de passe correct, initialiser la session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['success_message'] = "Connexion réussie ! Bienvenue, " . htmlspecialchars($user['username']) . ".";

                // Rediriger en fonction du rôle
                if ($user['role'] === 'admin') {
                    $redirect_url = "admin_dashboard.php";
                } else {
                    $redirect_url = "dashboard.php"; // Ou toute autre page pour les utilisateurs normaux
                }

            } else {
                // Identifiants incorrects
                $_SESSION['old_login_email'] = $email; // Pour pré-remplir le champ email
                throw new Exception("Email ou mot de passe incorrect.");
            }
        }
        // Action d'inscription
        elseif (isset($_POST['register_submit'])) {
            $username = trim($_POST['register_username'] ?? '');
            $email = filter_var(trim($_POST['register_email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $password = $_POST['register_password'] ?? '';
            $confirm_password = $_POST['register_confirm_password'] ?? '';

            // Stocker les données pour pré-remplir en cas d'erreur
            $_SESSION['old_register_data'] = [
                'register_username' => $username,
                'register_email' => $email
            ];

            if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
                throw new Exception("Tous les champs sont obligatoires pour l'inscription.");
            }
            if ($email === false) {
                throw new Exception("Format d'email invalide.");
            }
            if (strlen($username) < 3) {
                throw new Exception("Le nom d'utilisateur doit contenir au moins 3 caractères.");
            }
            if (strlen($password) < 6) {
                throw new Exception("Le mot de passe doit contenir au moins 6 caractères.");
            }
            if ($password !== $confirm_password) {
                throw new Exception("Les mots de passe ne correspondent pas.");
            }

            // Vérifier si l'email ou le nom d'utilisateur existe déjà
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email OR username = :username");
            $stmt_check->execute([':email' => $email, ':username' => $username]);
            if ($stmt_check->fetchColumn() > 0) {
                throw new Exception("L'email ou le nom d'utilisateur est déjà enregistré.");
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $default_role = 'user'; // Rôle par défaut pour les nouvelles inscriptions

            // Insérer le nouvel utilisateur
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (:username, :email, :password_hash, :role)");
            if (!$stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':password_hash' => $hashed_password,
                ':role' => $default_role
            ])) {
                throw new Exception("Erreur lors de l'inscription. Veuillez réessayer.");
            }

            $_SESSION['success_message'] = "Votre compte a été créé avec succès ! Vous pouvez maintenant vous connecter.";
            // Après l'inscription, rediriger vers la page de connexion pour que l'utilisateur se connecte
            $redirect_url = "authentification.php";

        } else {
            throw new Exception("Action d'authentification non spécifiée.");
        }
    } else {
        // Rediriger si le script est accédé directement sans POST (sauf pour logout)
        $_SESSION['error_message'] = "Accès direct non autorisé. Veuillez utiliser le formulaire.";
    }

} catch (PDOException $e) {
    // En cas d'erreur PDO, afficher un message générique pour l'utilisateur
    $_SESSION['error_message'] = "Une erreur est survenue lors de l'opération de base de données. Veuillez réessayer plus tard.";
    // Pour le débogage, vous pouvez logguer $e->getMessage();
    // error_log("PDO Error in auth_process.php: " . $e->getMessage());
} catch (Exception $e) {
    // Capturer les exceptions personnalisées (validation, CSRF, etc.)
    $_SESSION['error_message'] = $e->getMessage();
} finally {
    // Redirection finale
    header("Location: " . $redirect_url);
    exit();
}