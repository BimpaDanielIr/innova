<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Génère un token de 64 caractères hexadécimaux
}

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

// Prépare les valeurs pour les champs si l'utilisateur est connecté
$name_prefill = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : '';
$email_prefill = isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InnovaTech - Formulaire de Contact</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</head>
<body class="d-flex flex-column min-vh-100">
    <header class="bg-primary text-white text-center py-3">
        <div class="container">
            <h1>InnovaTech</h1>
            <p class="lead">Envoyez-nous un message</p>
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
                    <div class="card-header bg-primary text-white text-center">
                        <h2 class="h5 mb-0">Formulaire de Contact</h2>
                    </div>
                    <div class="card-body">
                        <p class="text-muted text-center mb-4">Utilisez ce formulaire pour nous envoyer un message rapide. Nous vous recontacterons dans les plus brefs délais.</p>
                        <form action="process_contact.php" method="POST">
    <div class="form-group">
        <label for="name">Nom :</label>
        <input type="text" class="form-control" id="name" name="name" required>
    </div>

    <div class="form-group">
        <label for="email">Email :</label>
        <input type="email" class="form-control" id="email" name="email" required>
    </div>

    <div class="form-group">
        <label for="subject">Sujet :</label>
        <input type="text" class="form-control" id="subject" name="subject" required>
    </div>

    <div class="form-group">
        <label for="message">Message :</label>
        <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
    </div>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <button type="submit" name="contact_submit" class="btn btn-primary">Envoyer le message</button>
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