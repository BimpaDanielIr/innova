<?php
// header.php
// Ce fichier est inclus au début de chaque page.
// Il assure la cohérence du design et de la navigation.

// Assurez-vous que la session est déjà démarrée dans la page appelante
// (ex: dashboard.php, index.php, etc.)

// On peut ici définir le titre par défaut si non défini dans la page appelante
if (!isset($page_title)) {
    $page_title = "InnovaTech - Votre plateforme d'apprentissage";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVXwBv3QJz7q3v50w5f5Q5q5Q5q5Q5q5Q5q5Q5q5Q5q5Q5q5Q5q5Q==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        /* Styles généraux pour l'application InnovaTech */
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: #f8f9fa; /* Couleur de fond légère */
        }
        .main-content {
            flex: 1;
            padding-top: 20px; /* Pour éviter le chevauchement avec la navbar fixe */
        }
        .navbar-brand {
            font-weight: bold;
            color: #ffffff !important; /* Couleur de texte pour la marque */
        }
        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
        }
        .navbar-nav .nav-link:hover {
            color: #ffffff !important;
        }
        .btn-brand {
            background-color: #28a745; /* Couleur de bouton InnovaTech */
            border-color: #28a745;
            color: white;
        }
        .btn-brand:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        .card-header {
            background-color: #007bff; /* Couleur primaire de Bootstrap */
            color: white;
        }
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }
        .footer {
            background-color: #343a40; /* Couleur sombre pour le pied de page */
            color: white;
            padding: 2rem 0;
            margin-top: auto; /* Pousse le pied de page en bas */
        }
        .social-icons a {
            color: white;
            font-size: 1.5rem;
            margin: 0 10px;
            transition: color 0.3s ease;
        }
        .social-icons a:hover {
            color: #007bff; /* Couleur au survol */
        }
        .alert {
            margin-top: 15px;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-microchip me-2"></i> InnovaTech
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt me-1"></i>Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-1"></i> Tableau de Bord</a>
                    </li>
                   
                    
                    <li class="nav-item">
                        <a class="nav-link" href="assistance.php"><i class="fas fa-life-ring me-1"></i> Assistance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php"><i class="fas fa-envelope me-1"></i> Contact</a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link btn btn-outline-light ms-2 px-3 py-1" href="auth_process.php?action=logout"><i class="fas fa-sign-out-alt me-1"></i> Déconnexion</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link btn btn-outline-light ms-2 px-3 py-1" href="authentification.php"><i class="fas fa-sign-in-alt me-1"></i> Connexion</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="main-content">