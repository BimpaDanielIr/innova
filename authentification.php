<?php
session_start(); // Démarre la session PHP pour pouvoir utiliser $_SESSION

// Inclure le fichier de configuration de la base de données
// Assurez-vous que le chemin est correct par rapport à l'emplacement de authentification.php
require_once 'config.php'; // Ce fichier doit initialiser $pdo

// Générer un token CSRF s'il n'existe pas déjà dans la session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Génère un token de 64 caractères hexadécimaux
}

// Initialisation des variables de messages
$error_message = '';
$success_message = '';

// Récupérer et afficher les messages de session
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Pré-remplir les champs du formulaire en cas d'erreur de soumission
$old_login_email = $_SESSION['old_login_email'] ?? '';
$old_register_username = $_SESSION['old_register_data']['register_username'] ?? '';
$old_register_email = $_SESSION['old_register_data']['register_email'] ?? '';

// Nettoyer les données de session après les avoir utilisées
unset($_SESSION['old_login_email']);
unset($_SESSION['old_register_data']);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InnovaTech - Connexion & Inscription</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        /* Styles personnalisés pour la page d'authentification */
        body {
            background-image: image-set("images/img5.jpg");
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .auth-container {
            background-color: rgba(248, 242, 242, 0.95); /* Fond blanc légèrement transparent */
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(23, 5, 5, 0.2);
            max-width: 800px;
            width: 200%;
        }
        .form-control-custom {
            border-radius: 20px;
            border: 1px solidrgb(1, 6, 10);
            padding: 10px 15px;
            transition: all 0.3s ease;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .form-control-custom:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
        }
        .form-label-custom {
            font-weight: bold;
            color: #343a40; /* Couleur de police plus foncée */
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        .btn-custom-primary {
            background-color: #007bff;
            border-color: #007bff;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            transition: background-color 0.3s ease, border-color 0.3s ease;
            width: 100%; /* Boutons pleine largeur */
        }
        .btn-custom-primary:hover {
            background-color: #0056b3;
            border-color: #004085;
        }
        .btn-custom-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            transition: background-color 0.3s ease, border-color 0.3s ease;
            width: 100%; /* Boutons pleine largeur */
        }
        .btn-custom-secondary:hover {
            background-color: #5a6268;
            border-color: #4e555b;
        }
        .nav-tabs .nav-link {
            color: #007bff; /* Couleur du texte des onglets */
            font-weight: bold;
            border: none;
            border-bottom: 3px solid transparent;
            padding-bottom: 10px;
        }
        .nav-tabs .nav-link.active {
            color: #0056b3; /* Couleur du texte de l'onglet actif */
            border-bottom-color: #007bff; /* Soulignement de l'onglet actif */
            background-color: transparent;
        }
        .nav-tabs .nav-item.show .nav-link {
            border-color: #007bff;
        }
        .text-center h2 {
            color: #007bff;
            font-weight: bold;
            margin-bottom: 30px;
        }
        .alert {
            margin-top: 20px;
            border-radius: 8px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="text-center mb-4">
            <h2 class="display-6"><i class="fas fa-lock"></i> InnovaTech Authentification</h2>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger text-center" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success text-center" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <ul class="nav nav-tabs justify-content-center mb-4" id="authTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button" role="tab" aria-controls="login" aria-selected="true">
                    Connexion
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab" aria-controls="register" aria-selected="false">
                    Inscription
                </button>
            </li>
        </ul>

        <div class="tab-content" id="authTabContent">
            <div class="tab-pane fade show active" id="login" role="tabpanel" aria-labelledby="login-tab">
                <form action="auth_process.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="mb-3">
                        <label for="login_email" class="form-label form-label-custom">Email :</label>
                        <input type="email" class="form-control form-control-custom" id="login_email" name="login_email" value="<?php echo htmlspecialchars($old_login_email); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="login_password" class="form-label form-label-custom">Mot de passe :</label>
                        <input type="password" class="form-control form-control-custom" id="login_password" name="login_password" required>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" name="login_submit" class="btn btn-custom-primary">Se connecter</button>
                        <a href="forgot_password.php" class="btn btn-link text-center text-decoration-none" style="color: #007bff;">Mot de passe oublié ?</a>
                    </div>
                </form>
            </div>

            <div class="tab-pane fade" id="register" role="tabpanel" aria-labelledby="register-tab">
                <form action="auth_process.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="mb-3">
                        <label for="register_username" class="form-label form-label-custom">Nom d'utilisateur :</label>
                        <input type="text" class="form-control form-control-custom" id="register_username" name="register_username" value="<?php echo htmlspecialchars($old_register_username); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="register_email" class="form-label form-label-custom">Email :</label>
                        <input type="email" class="form-control form-control-custom" id="register_email" name="register_email" value="<?php echo htmlspecialchars($old_register_email); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="register_password" class="form-label form-label-custom">Mot de passe :</label>
                        <input type="password" class="form-control form-control-custom" id="register_password" name="register_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="register_confirm_password" class="form-label form-label-custom">Confirmer le mot de passe :</label>
                        <input type="password" class="form-control form-control-custom" id="register_confirm_password" name="register_confirm_password" required>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" name="register_submit" class="btn btn-custom-secondary">S'inscrire</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // Si un message d'erreur ou de succès est présent, afficher l'onglet approprié
        document.addEventListener('DOMContentLoaded', function() {
            const authTab = new bootstrap.Tab(document.getElementById('login-tab'));
            <?php if ($error_message || $success_message): ?>
                <?php if (isset($_SESSION['old_login_email'])): // Si l'erreur vient du login, montrer l'onglet login ?>
                    authTab.show();
                <?php elseif (isset($_SESSION['old_register_data'])): // Si l'erreur vient de l'inscription, montrer l'onglet inscription ?>
                    const registerTab = new bootstrap.Tab(document.getElementById('register-tab'));
                    registerTab.show();
                <?php else: // Par défaut, rester sur la connexion ?>
                    authTab.show();
                <?php endif; ?>
            <?php else: // Si pas de message, montrer l'onglet de connexion par défaut ?>
                authTab.show();
            <?php endif; ?>
        });
    </script>
</body>
</html>