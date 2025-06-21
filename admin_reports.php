<?php
session_start();
require_once 'config.php'; // Assurez-vous que le chemin est correct

// Protection de la page : l'utilisateur doit être connecté ET avoir le rôle 'admin'
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_message'] = "Accès non autorisé. Vous n'avez pas les permissions d'administrateur.";
    header("Location: authentification.php");
    exit();
}

$message = '';
$message_type = '';

// Récupérer les messages de session
if (isset($_SESSION['error_message'])) {
    $message = $_SESSION['error_message'];
    $message_type = 'danger';
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']);
}

// Variables pour stocker les données statistiques
$totalUsers = 0;
$totalFormations = 0;
$totalEnrollments = 0;
$pendingEnrollments = 0;
$approvedEnrollments = 0;
$paidEnrollments = 0;
$usersByRole = [];
$enrollmentsByFormation = [];
$messagesBySubject = []; // Pour les messages de contact
$assistanceRequestsByStatus = []; // Pour les demandes d'assistance

try {
    // Vérifier si $pdo est une instance de PDO
    if (!($pdo instanceof PDO)) {
        throw new Exception("Erreur de configuration de la base de données: l'objet PDO n'est pas disponible.");
    }

    // --- Statistiques Générales ---

    // Total des utilisateurs
    $stmt = $pdo->prepare("SELECT COUNT(id) AS total_users FROM users");
    $stmt->execute();
    $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];

    // Total des formations
    $stmt = $pdo->prepare("SELECT COUNT(id) AS total_formations FROM formations");
    $stmt->execute();
    $totalFormations = $stmt->fetch(PDO::FETCH_ASSOC)['total_formations'];

    // Total des inscriptions
    $stmt = $pdo->prepare("SELECT COUNT(id) AS total_enrollments FROM enrollments");
    $stmt->execute();
    $totalEnrollments = $stmt->fetch(PDO::FETCH_ASSOC)['total_enrollments'];

    // Inscriptions en attente
    $stmt = $pdo->prepare("SELECT COUNT(id) AS pending_enrollments FROM enrollments WHERE status = 'pending'");
    $stmt->execute();
    $pendingEnrollments = $stmt->fetch(PDO::FETCH_ASSOC)['pending_enrollments'];

    // Inscriptions approuvées
    $stmt = $pdo->prepare("SELECT COUNT(id) AS approved_enrollments FROM enrollments WHERE status = 'approved'");
    $stmt->execute();
    $approvedEnrollments = $stmt->fetch(PDO::FETCH_ASSOC)['approved_enrollments'];

    // Inscriptions payées (statut de paiement)
    $stmt = $pdo->prepare("SELECT COUNT(id) AS paid_enrollments FROM enrollments WHERE payment_status = 'paid'");
    $stmt->execute();
    $paidEnrollments = $stmt->fetch(PDO::FETCH_ASSOC)['paid_enrollments'];

    // --- Statistiques détaillées ---

    // Utilisateurs par rôle
    $stmt = $pdo->prepare("SELECT role, COUNT(id) AS count FROM users GROUP BY role");
    $stmt->execute();
    $usersByRole = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Inscriptions par formation
    $stmt = $pdo->prepare("SELECT f.title AS formation_title, COUNT(e.id) AS count
                            FROM enrollments e
                            JOIN formations f ON e.formation_id = f.id
                            GROUP BY f.title
                            ORDER BY count DESC");
    $stmt->execute();
    $enrollmentsByFormation = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Messages de contact par sujet
    $stmt = $pdo->prepare("SELECT subject, COUNT(id) AS count FROM contacts GROUP BY subject ORDER BY count DESC");
    $stmt->execute();
    $messagesBySubject = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Demandes d'assistance par statut
    $stmt = $pdo->prepare("SELECT status, COUNT(id) AS count FROM assistance_requests GROUP BY status ORDER BY count DESC");
    $stmt->execute();
    $assistanceRequestsByStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = "Erreur de base de données : " . $e->getMessage();
    $message_type = 'danger';
} catch (Exception $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'danger';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InnovaTech - Admin : Rapports et Statistiques</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        .statistic-card {
            background-color: #f8f9fa;
            border-left: 5px solid #007bff;
            border-radius: .25rem;
        }
        .statistic-card.users { border-left-color: #6c757d; }
        .statistic-card.formations { border-left-color: #28a745; }
        .statistic-card.enrollments { border-left-color: #ffc107; }
        .statistic-card.pending { border-left-color: #dc3545; }
        .statistic-card.approved { border-left-color: #28a745; }
        .statistic-card.paid { border-left-color: #17a2b8; }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'admin_sidebar.php'; ?>

        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-info">
                        <i class="fas fa-align-left"></i>
                        <span>Toggle Sidebar</span>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarSupportedContent">
                        <ul class="nav navbar-nav ml-auto">
                            <li class="nav-item">
                                <a class="nav-link" href="admin_dashboard.php">Tableau de Bord</a>
                            </li>
                            <li class="nav-item active">
                                <a class="nav-link" href="admin_reports.php">Rapports</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="auth_process.php?action=logout">Déconnexion</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            <main class="container-fluid">
                <h1 class="mb-4">Rapports et Statistiques</h1>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card shadow-sm p-3 statistic-card users">
                            <h5><i class="fas fa-users"></i> Total Utilisateurs</h5>
                            <p class="h2"><?php echo $totalUsers; ?></p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card shadow-sm p-3 statistic-card formations">
                            <h5><i class="fas fa-graduation-cap"></i> Total Formations</h5>
                            <p class="h2"><?php echo $totalFormations; ?></p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card shadow-sm p-3 statistic-card enrollments">
                            <h5><i class="fas fa-user-check"></i> Total Inscriptions</h5>
                            <p class="h2"><?php echo $totalEnrollments; ?></p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card shadow-sm p-3 statistic-card pending">
                            <h5><i class="fas fa-hourglass-half"></i> Inscriptions en Attente</h5>
                            <p class="h2"><?php echo $pendingEnrollments; ?></p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card shadow-sm p-3 statistic-card approved">
                            <h5><i class="fas fa-check-circle"></i> Inscriptions Approuvées</h5>
                            <p class="h2"><?php echo $approvedEnrollments; ?></p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card shadow-sm p-3 statistic-card paid">
                            <h5><i class="fas fa-dollar-sign"></i> Inscriptions Payées</h5>
                            <p class="h2"><?php echo $paidEnrollments; ?></p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-secondary text-white">
                                <i class="fas fa-chart-pie"></i> Utilisateurs par Rôle
                            </div>
                            <div class="card-body">
                                <?php if (!empty($usersByRole)): ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($usersByRole as $role): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo htmlspecialchars(ucfirst($role['role'])); ?>
                                                <span class="badge bg-secondary rounded-pill"><?php echo $role['count']; ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-center text-muted">Aucune donnée d'utilisateur par rôle.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <i class="fas fa-book-open"></i> Inscriptions par Formation
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <?php if (!empty($enrollmentsByFormation)): ?>
                                        <?php foreach ($enrollmentsByFormation as $formation): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo htmlspecialchars($formation['formation_title']); ?>
                                                <span class="badge bg-primary rounded-pill"><?php echo $formation['count']; ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="list-group-item text-muted">Aucune donnée d'inscription par formation.</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-info text-white">
                                <i class="fas fa-envelope"></i> Messages de Contact par Sujet
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <?php if (!empty($messagesBySubject)): ?>
                                        <?php foreach ($messagesBySubject as $subject): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo htmlspecialchars($subject['subject']); ?>
                                                <span class="badge bg-info rounded-pill"><?php echo $subject['count']; ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="list-group-item text-muted">Aucun message de contact.</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-warning text-dark">
                                <i class="fas fa-question-circle"></i> Demandes d'Assistance par Statut
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <?php if (!empty($assistanceRequestsByStatus)): ?>
                                        <?php foreach ($assistanceRequestsByStatus as $status): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo htmlspecialchars(ucfirst($status['status'])); ?>
                                                <span class="badge bg-warning text-dark rounded-pill"><?php echo $status['count']; ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="list-group-item text-muted">Aucune demande d'assistance.</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // Script pour le basculement de la barre latérale
        document.getElementById('sidebarCollapse').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>