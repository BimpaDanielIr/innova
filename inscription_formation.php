<?php
session_start();

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

$servername = "localhost";
$username = "root";
$password = "";
$database = "innova_tech_db";

$formations = []; 
$conn = null;

try {
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        throw new Exception("Erreur de connexion à la base de données : " . $conn->connect_error);
    }

    $sql = "SELECT id, title, description, image_path, duration, price, start_date FROM formations WHERE is_active = 1 ORDER BY start_date ASC";
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $formations[] = $row;
        }
        $result->free();
    } else {
        $error_message = "Erreur lors de la récupération des formations : " . $conn->error;
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
} finally {
    if ($conn instanceof mysqli && !$conn->connect_error) {
        $conn->close();
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InnovaTech - Nos Formations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .card-img-top {
            width: 100%;
            height: 200px; 
            object-fit: cover; 
            border-bottom: 1px solid #eee;
        }
        .card-formation {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card-formation:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <header class="bg-primary text-white text-center py-3">
        <div class="container">
            <h1>InnovaTech</h1>
            <p class="lead">Votre solution technologique innovante</p>
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
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="inscription_formation.php">Formations</a></li>
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

        <div class="row justify-content-center mb-5">
            <div class="col-md-10 col-lg-9">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white text-center">
                        <h2 class="h5 mb-0">Découvrez nos Formations Innovantes</h2>
                    </div>
                    <div class="card-body">
                        <p class="card-text">Chez InnovaTech, nous nous engageons à vous offrir des formations de pointe pour maîtriser les technologies de demain. Que vous soyez débutant ou expert, nos programmes sont conçus pour vous propulser vers de nouveaux horizons.</p>
                        
                        <h3 class="mt-4 mb-3 text-primary">Nos Domaines d'Expertise :</h3>
                        <ul>
                            <li><strong>Développement Web Avancé :</strong> Apprenez les dernières frameworks et techniques pour créer des applications web robustes et dynamiques.</li>
                            <li><strong>Cybersécurité Fondamentale :</strong> Comprenez les menaces, les vulnérabilités et les meilleures pratiques pour protéger les systèmes et les données.</li>
                            <li><strong>Intelligence Artificielle pour Tous :</strong> Initiez-vous aux concepts de l'IA, de l'apprentissage machine et du deep learning avec des applications concrètes.</li>
                            <li><strong>Initiation à l'informatique :</strong> Maîtrisez les bases fondamentales de l'informatique avec les outils bureautiques <strong>(Word, PowerPoint, Excel, Outlook etc.) </strong>.</li>
                            <li><strong>Réseaux informatique :</strong> Explorez les fondamentaux des Réseaux informatiques, apprenez à administrer un réseau et à le sécuriser.</li>
                        </ul>

                        <strong><p class="card-text text-center">La seule limite c'est ton imagination</p></strong>

                        <div class="text-center mt-4">
                            <p class="lead">Prêt à développer vos compétences ?</p>
                            <a href="#formations-disponibles" class="btn btn-info btn-lg text-white">Je découvre les Formations Disponibles !</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <h2 class="text-center mb-4 text-primary" id="formations-disponibles">Formations Actuellement Disponibles</h2>
        <p class="text-center lead mb-5">Inscrivez-vous dès maintenant pour lancer votre carrière technologique.</p>

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php if (!empty($formations)): ?>
                <?php foreach ($formations as $formation): ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm card-formation">
                            <?php if ($formation['image_path'] && file_exists($formation['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($formation['image_path']); ?>" class="card-img-top" alt="Image de la formation <?php echo htmlspecialchars($formation['title']); ?>">
                            <?php else: ?>
                                <img src="assets/images/default_formation.jpg" class="card-img-top" alt="Image par défaut de la formation">
                            <?php endif; ?>
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title text-success"><?php echo htmlspecialchars($formation['title']); ?></h5>
                                <p class="card-text text-muted mb-2">
                                    <i class="fas fa-calendar-alt me-1"></i> Début : <?php echo date('d/m/Y', strtotime($formation['start_date'])); ?>
                                </p>
                                <p class="card-text text-muted mb-2">
                                    <i class="fas fa-clock me-1"></i> Durée : <?php echo htmlspecialchars($formation['duration']); ?>
                                </p>
                                <p class="card-text text-muted mb-3">
                                    <i class="fas fa-dollar-sign me-1"></i> Prix : <?php echo htmlspecialchars(number_format($formation['price'], 2, ',', ' ')); ?> FBU
                                </p>
                                <p class="card-text flex-grow-1 text-secondary"><?php echo nl2br(htmlspecialchars($formation['description'])); ?></p>
                                <div class="mt-auto">
                                    <a href="inscription_formation_form.php?formation_id=<?php echo $formation['id']; ?>" class="btn btn-primary btn-sm w-100">S'inscrire à cette Formation</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info text-center" role="alert">
                        Aucune formation n'est actuellement disponible. Revenez bientôt pour découvrir de nouvelles offres !
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <footer class="bg-dark text-white text-center py-3 mt-auto">
        <div class="container">
            <p>&copy; 2025 InnovaTech. Tous droits réservés.</p>
        </div>
    </footer>
</body>
</html>