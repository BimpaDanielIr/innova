<?php
session_start();

// Activez l'affichage des erreurs pour le développement (à désactiver en production)
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// Inclure le fichier de récupération des paramètres du site
require_once 'includes/get_settings.php'; 

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

// Paramètres de connexion à la base de données pour récupérer les posts et événements
$servername = "localhost";
$username = "root";
$password = "";
$database = "innova_tech_db";

$conn = null;
$latest_posts = [];
$events_for_display = [];

try {
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        throw new Exception("Erreur de connexion à la base de données : " . $conn->connect_error);
    }

    // --- Récupérer les 3 dernières publications actives ---
    $sql_posts = "SELECT p.title, p.content, p.image_path, p.post_type, p.posted_at, u.username as author
                  FROM posts p
                  LEFT JOIN users u ON p.posted_by = u.id
                  WHERE p.is_active = TRUE
                  ORDER BY p.posted_at DESC
                  LIMIT 3";
    
    $result_posts = $conn->query($sql_posts);

    if ($result_posts) {
        while ($row = $result_posts->fetch_assoc()) {
            $latest_posts[] = $row;
        }
        $result_posts->free();
    } else {
        error_log("Erreur lors de la récupération des posts : " . $conn->error);
    }

    // --- Récupérer les 3 prochains événements actifs ---
    $sql_events = "SELECT id, title, description, event_date, event_time, location, image_path 
                   FROM events 
                   WHERE is_active = 1 AND event_date >= CURDATE()
                   ORDER BY event_date ASC, event_time ASC LIMIT 3"; 
    $result_events = $conn->query($sql_events);

    if ($result_events) {
        while ($row = $result_events->fetch_assoc()) {
            $events_for_display[] = $row;
        }
        $result_events->free();
    } else {
        error_log("Erreur lors de la récupération des événements pour la page d'accueil : " . $conn->error);
    }

} catch (Exception $e) {
    error_log("Erreur dans index.php lors de la récupération des données : " . $e->getMessage());
} finally {
    if ($conn instanceof mysqli && $conn->ping()) {
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site_settings['site_name']); ?> - Accueil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        /* Styles pour la section héroïque avec animation de fond */
        .hero-section {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            position: relative;
            overflow: hidden;
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
            animation: background-slideshow 40s infinite;
        }

        .hero-section::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1;
        }

        .hero-section .container {
            position: relative;
            z-index: 2;
        }

        @keyframes background-slideshow {
            0% { background-image: url('images/img3.jpg'); }
            25% { background-image: url('images/img4.jpg'); }
            50% { background-image: url('images/img5.jpg'); }
            75% { background-image: url('images/img6.jpg'); }
            100% { background-image: url('images/img7.jpg'); }
        }

        /* Styles pour les icônes de service (images) */
        .service-icon-img {
            width: 200px;
            height: 200px;
            margin-bottom: 1rem;
            display: block;
            object-fit: contain;
        }

        /* Styles pour les cartes de service */
        .card {
            border: none;
            transition: transform 0.2s ease-in-out;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        /* Styles existants pour les posts */
        .post-card {
            border: none;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s ease-in-out;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .post-card:hover {
            transform: translateY(-5px);
        }
        .post-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .post-card .card-body {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .post-card .card-text {
            flex-grow: 1;
        }
        .post-card .card-footer {
            background-color: transparent;
            border-top: none;
            padding-top: 0;
        }
        .post-badge {
            font-size: 0.8em;
            padding: 0.4em 0.7em;
            border-radius: 5px;
        }
        .badge-news { background-color: #6c757d; color: white; }
        .badge-formation { background-color: #0d6efd; color: white; }
        .badge-event { background-color: #fd7e14; color: white; }
        .badge-service_promo { background-color: #28a745; color: white; }

        /* Styles pour les cartes d'événements (similaire aux posts) */
        .event-card {
            border: none;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s ease-in-out;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .event-card:hover {
            transform: translateY(-5px);
        }
        .event-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .event-card .card-body {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .event-card .card-text {
            flex-grow: 1;
        }
        .event-card .list-group-item {
            border: none;
            padding: 0.5rem 0;
        }
        .event-card .list-group-item:last-child {
            padding-bottom: 0;
        }

        /* Nouveaux styles pour le pied de page */
        footer {
            background-color: #212529; /* Couleur sombre de Bootstrap */
            color: #f8f9fa; /* Texte clair */
            padding: 40px 0;
        }

        footer h5 {
            color: #007bff; /* Couleur primaire pour les titres de section */
            margin-bottom: 20px;
        }

        footer ul {
            list-style: none;
            padding: 0;
        }

        footer ul li {
            margin-bottom: 10px;
        }

        footer ul li a {
            color: #dee2e6; /* Gris clair pour les liens */
            text-decoration: none;
            transition: color 0.3s ease;
        }

        footer ul li a:hover {
            color: #ffffff; /* Blanc au survol */
        }

        footer .social-icons a {
            color: #f8f9fa;
            font-size: 1.5rem;
            margin-right: 15px;
            transition: color 0.3s ease;
        }

        footer .social-icons a:hover {
            color: #007bff; /* Couleur primaire au survol */
        }

        footer .footer-bottom {
            border-top: 1px solid #343a40; /* Ligne de séparation subtile */
            padding-top: 20px;
            margin-top: 30px;
            color: #adb5bd; /* Gris plus foncé pour le texte du copyright */
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <header class="bg-primary text-white text-center py-3">
        <div class="container">
            <h1><?php echo htmlspecialchars($site_settings['site_name']); ?></h1>
            <p class="lead"><?php echo htmlspecialchars($site_settings['site_slogan']); ?></p>
        </div>
    </header>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid container">
            <a class="navbar-brand d-flex align-items-center" href="<?php echo htmlspecialchars($site_settings['nav_link_home_url']); ?>">
                <img src="assets/images/logo.png" alt="<?php echo htmlspecialchars($site_settings['site_name']); ?> Logo" width="30" height="30" class="d-inline-block align-text-top me-2">
                <?php echo htmlspecialchars($site_settings['site_name']); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == $site_settings['nav_link_home_url']) ? 'active' : ''; ?>" aria-current="page" href="<?php echo htmlspecialchars($site_settings['nav_link_home_url']); ?>">
                            <?php echo htmlspecialchars($site_settings['nav_link_home_text']); ?>
                        </a>
                    </li>
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item"><a class="nav-link" href="dashboard.php">Profil</a></li>
                        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                            <li class="nav-item"><a class="nav-link" href="admin_dashboard.php">Tableau de Bord Admin</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link" href="auth_process.php?action=logout">Déconnexion</a></li>
                    <?php else: ?>
                       
                       
                    
                        <li class="nav-item"><a class="nav-link" href="authentification.php">Connexion</a></li>
                        
                    <?php endif; ?>
                    
                    

                    <?php 
                    // Afficher le bouton CTA uniquement si l'URL est définie et différente de '#'
                    if ($site_settings['nav_cta_button_url'] !== '#' && !empty($site_settings['nav_cta_button_text'])): 
                    ?>
                        <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                            <a class="btn btn-warning" href="<?php echo htmlspecialchars($site_settings['nav_cta_button_url']); ?>">
                                <?php echo htmlspecialchars($site_settings['nav_cta_button_text']); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <main class="flex-grow-1">
        <?php if ($error_message): ?>
            <div class="alert alert-danger mx-4 mt-4" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success mx-4 mt-4" role="alert">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <section class="hero-section text-center">
            <div class="container">
                <h1 class="display-3 fw-bold">Transformez votre avenir avec <?php echo htmlspecialchars($site_settings['site_name']); ?></h1>
                <p class="lead mt-4"><?php echo htmlspecialchars($site_settings['about_us_short']); ?></p>
                <a href="inscription_formation.php" class="btn btn-primary btn-lg mt-4">Découvrez nos Formations</a>
                <a href="contact.php" class="btn btn-outline-light btn-lg mt-4 ms-3">Contactez-nous</a>
            </div>
        </section>

        <section class="container my-5">
            <h2 class="text-center mb-5 text-primary">Nos Services Complémentaires</h2>
            <div class="row text-center">
                <div class="col-md-4 mb-4">
                    <div class="card h-100 shadow-sm p-4">
                        <img src="images/img5.jpg" alt="Création de Sites Web & Applications" class="service-icon-img mx-auto mb-3"> 
                        <h4 class="card-title">Création de Sites Web & Applications</h4>
                        <p class="card-text">Nous concevons et développons des sites web professionnels et des applications sur mesure, optimisés pour la performance et l'expérience utilisateur.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 shadow-sm p-4">
                        <img src="images/img7.jpg" alt="Installation & Maintenance Réseaux" class="service-icon-img mx-auto mb-3">
                        <h4 class="card-title">Installation & Maintenance Réseaux</h4>
                        <p class="card-text">Experts en infrastructure réseau, nous assurons l'installation, la configuration et la maintenance de vos systèmes pour une connectivité optimale.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 shadow-sm p-4">
                        <img src="images/img6.jpg" alt="Design Graphique & UI/UX" class="service-icon-img mx-auto mb-3">
                        <h4 class="card-title">Design Graphique & UI/UX</h4>
                        <p class="card-text">Donnez vie à votre marque avec un design créatif et innovant. Nous réalisons des identités visuelles percutantes et des interfaces utilisateur intuitives.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="container my-5">
            <h2 class="text-center mb-4 text-primary">Nos Prochains Événements</h2>
            <?php if (!empty($events_for_display)): ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($events_for_display as $event): ?>
                        <div class="col">
                            <div class="card event-card">
                                <?php if ($event['image_path'] && file_exists($event['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($event['image_path']); ?>" class="card-img-top" alt="Image de l'événement">
                                <?php else: ?>
                                    <img src="assets/images/default_event.jpg" class="card-img-top" alt="Image par défaut de l'événement">
                                <?php endif; ?>
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title text-info"><?php echo htmlspecialchars($event['title']); ?></h5>
                                    <p class="card-text flex-grow-1"><?php echo htmlspecialchars(substr($event['description'], 0, 100)); ?>...</p>
                                    <ul class="list-group list-group-flush mt-auto">
                                        <li class="list-group-item"><i class="far fa-calendar-alt me-2 text-muted"></i> Date: <?php echo date('d/m/Y', strtotime($event['event_date'])); ?></li>
                                        <?php if (!empty($event['event_time'])): ?>
                                            <li class="list-group-item"><i class="far fa-clock me-2 text-muted"></i> Heure: <?php echo date('H:i', strtotime($event['event_time'])); ?></li>
                                        <?php endif; ?>
                                        <?php if (!empty($event['location'])): ?>
                                            <li class="list-group-item"><i class="fas fa-map-marker-alt me-2 text-muted"></i> Lieu: <?php echo htmlspecialchars($event['location']); ?></li>
                                        <?php endif; ?>
                                    </ul>
                                    <a href="#" class="btn btn-primary mt-3">En savoir plus</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-5">
                    <a href="all_events.php" class="btn btn-outline-primary">Voir tous les événements</a>
                </div>
            <?php else: ?>
                <p class="text-center text-muted">Aucun événement à venir n'est actuellement disponible.</p>
            <?php endif; ?>
        </section>

        <section class="container my-5">
            <h2 class="text-center mb-5 text-success">Dernières Actualités & Annonces</h2>
            <?php if (!empty($latest_posts)): ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($latest_posts as $post): ?>
                        <div class="col">
                            <div class="card post-card">
                                <?php if ($post['image_path'] && file_exists($post['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($post['image_path']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($post['title']); ?>">
                                <?php else: ?>
                                    <img src="assets/images/default_post_image.jpg" class="card-img-top" alt="Image par défaut">
                                <?php endif; ?>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($post['title']); ?></h5>
                                    <p class="card-text text-muted small">
                                        Publié le <?php echo date('d/m/Y', strtotime($post['posted_at'])); ?>
                                        <?php if ($post['author']): ?>
                                            par <?php echo htmlspecialchars($post['author']); ?>
                                        <?php endif; ?>
                                        <span class="badge <?php echo 'badge-' . htmlspecialchars($post['post_type']); ?> post-badge ms-2">
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $post['post_type']))); ?>
                                        </span>
                                    </p>
                                    <p class="card-text"><?php echo nl2br(htmlspecialchars(substr($post['content'], 0, 150))) . (strlen($post['content']) > 150 ? '...' : ''); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-5">
                    <a href="#" class="btn btn-outline-secondary">Voir toutes les actualités</a>
                </div>
            <?php else: ?>
                <p class="text-center alert alert-info">Aucune annonce ou actualité disponible pour le moment.</p>
            <?php endif; ?>
        </section>

    </main>

    <footer class="mt-auto">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5>À Propos de <?php echo htmlspecialchars($site_settings['site_name']); ?></h5>
                    <p class="text-muted">
                        <?php echo nl2br(htmlspecialchars($site_settings['footer_about_us_text'])); ?>
                    </p>
                </div>

                <div class="col-md-4 mb-4">
                    <h5>Liens Rapides</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-decoration-none">Accueil</a></li>
                        <li><a href="inscription_formation.php" class="text-decoration-none">Formations</a></li>
                        <li><a href="about.php" class="text-decoration-none">À Propos de Nous</a></li>
                        <li><a href="contact.php" class="text-decoration-none">Contact</a></li>
                        <li><a href="assistance.php" class="text-decoration-none">Assistance</a></li>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li><a href="dashboard.php" class="text-decoration-none">Mon Profil</a></li>
                        <?php else: ?>
                            <li><a href="authentification.php" class="text-decoration-none">Connexion</a></li>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                            <li><a href="admin_dashboard.php" class="text-decoration-none">Panneau Admin</a></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="col-md-4 mb-4">
                    <h5>Nous Contacter</h5>
                    <p>
                        <i class="fas fa-map-marker-alt me-2"></i> <?php echo htmlspecialchars($site_settings['footer_address']); ?><br>
                        <i class="fas fa-envelope me-2"></i> <a href="mailto:<?php echo htmlspecialchars($site_settings['footer_contact_email']); ?>" class="text-decoration-none"><?php echo htmlspecialchars($site_settings['footer_contact_email']); ?></a><br>
                        <i class="fas fa-phone me-2"></i> <a href="tel:<?php echo htmlspecialchars($site_settings['footer_phone_number']); ?>" class="text-decoration-none"><?php echo htmlspecialchars($site_settings['footer_phone_number']); ?></a>
                    </p>
                    <div class="social-icons mt-3">
                        <?php if ($site_settings['social_facebook_url'] !== '#'): ?>
                            <a href="<?php echo htmlspecialchars($site_settings['social_facebook_url']); ?>" target="_blank" class="me-2"><i class="fab fa-facebook-f"></i></a>
                        <?php endif; ?>
                        <?php if ($site_settings['social_twitter_url'] !== '#'): ?>
                            <a href="<?php echo htmlspecialchars($site_settings['social_twitter_url']); ?>" target="_blank" class="me-2"><i class="fab fa-twitter"></i></a>
                        <?php endif; ?>
                        <?php if ($site_settings['social_linkedin_url'] !== '#'): ?>
                            <a href="<?php echo htmlspecialchars($site_settings['social_linkedin_url']); ?>" target="_blank" class="me-2"><i class="fab fa-linkedin-in"></i></a>
                        <?php endif; ?>
                        <?php if ($site_settings['social_instagram_url'] !== '#'): ?>
                            <a href="<?php echo htmlspecialchars($site_settings['social_instagram_url']); ?>" target="_blank" class="me-2"><i class="fab fa-instagram"></i></a>
                        <?php endif; ?>
                        <?php if ($site_settings['social_youtube_url'] !== '#'): ?>
                            <a href="<?php echo htmlspecialchars($site_settings['social_youtube_url']); ?>" target="_blank" class="me-2"><i class="fab fa-youtube"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="text-center footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_settings['site_name']); ?>. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>