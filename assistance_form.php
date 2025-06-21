<?php
session_start(); // Toujours en première ligne

// Générer un token CSRF s'il n'existe pas déjà dans la session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Génère un token de 64 caractères hexadécimaux
}

// --- INITIALISATION DES VARIABLES DE MESSAGES ICI POUR ÉVITER LES WARNINGS ---
$error_message = '';
$success_message = '';
// -----------------------------------------------------------------------------

// Récupérer et afficher les messages de session
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Prépare les valeurs pour les champs si l'utilisateur est connecté
$name_prefill = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : '';
$email_prefill = isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : '';
$user_id_prefill = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InnovaTech - Formulaire d'Assistance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</head>
<body class="d-flex flex-column min-vh-100">
    <header class="bg-primary text-white text-center py-3">
        <div class="container">
            <h1>InnovaTech</h1>
            <p class="lead">Soumettez votre demande d'assistance</p>
        </div>
    </header>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <img src="assets/images/logo.png" alt="InnovaTech Logo" width="30" height="30" class="d-inline-block align-text-top me-2">
            InnovaTech
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="index.php">Accueil</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Profil</a></li>
                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="admin_dashboard.php">Tableau de Bord Admin</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link" href="auth_process.php?action=logout">Déconnexion</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="authentification.php">Connexion</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                <li class="nav-item"><a class="nav-link" href="inscription_formation.php">Formations</a></li>
                <li class="nav-item"><a class="nav-link" href="assistance.php">Assistance</a></li>
            </ul>
        </div>
    </div>
</nav>
    </nav>

    <main class="container my-4 flex-grow-1">
        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-danger text-white text-center">
                        <h2 class="h5 mb-0">Formulaire de Demande d'Assistance</h2>
                    </div>
                    <div class="card-body">
                        <p class="text-muted text-center mb-4">Veuillez remplir ce formulaire avec le plus de détails possible pour que notre équipe puisse vous aider efficacement.</p>
                        <form action="process_assistance_request.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                            <input type="hidden" name="user_id" value="<?php echo $user_id_prefill; ?>">
                            <div class="mb-3">
                                <label for="nom" class="form-label">Votre Nom :</label>
                                <input type="text" class="form-control" id="nom" name="nom" value="<?php echo $name_prefill; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Votre Email :</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo $email_prefill; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="sujet" class="form-label">Sujet de la demande :</label>
                                <input type="text" class="form-control" id="sujet" name="sujet" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description détaillée du problème :</label>
                                <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                            </div>
                            <button type="submit" name="assistance_submit" class="btn btn-danger w-100">Envoyer la Demande</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-dark text-white text-center py-3 mt-auto">
        <div class="container">
            <p>&copy; 2025 InnovaTech. Tous droits réservés.</p>
        </div>
    </footer>
</body>
</html>