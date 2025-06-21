<?php
session_start();

// Activez l'affichage des erreurs pour le développement (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclure le fichier de configuration de la base de données
require_once 'config.php'; // Ce fichier doit contenir la logique pour établir la connexion $pdo

// Protection de la page : l'utilisateur doit être connecté ET avoir le rôle 'admin'
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_message'] = "Accès non autorisé. Vous n'avez pas les permissions d'administrateur pour InnovaTech.";
    header("Location: authentification.php");
    exit();
}

$error_message = '';
$success_message = '';

// Récupérer les messages de succès/erreur de la session et les effacer
if (isset($_SESSION['success_message']) && $_SESSION['success_message'] !== '') {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
} elseif (isset($_SESSION['error_message']) && $_SESSION['error_message'] !== '') {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Initialisation des données pour les statistiques
$total_users = 0;
$total_enrollments = 0;
$total_formations = 0;
$total_payments_amount = 0;
$recent_activities = []; // Initialiser comme un tableau vide

try {
    // Récupérer le nombre total d'utilisateurs
    $stmt_users = $pdo->query("SELECT COUNT(*) FROM users");
    $total_users = $stmt_users->fetchColumn();
    $total_users = $total_users ?? 0; // S'assurer que c'est 0 si null

    // Récupérer le nombre total d'inscriptions
    $stmt_enrollments = $pdo->query("SELECT COUNT(*) FROM enrollments");
    $total_enrollments = $stmt_enrollments->fetchColumn();
    $total_enrollments = $total_enrollments ?? 0;

    // Récupérer le nombre total de formations
    $stmt_formations = $pdo->query("SELECT COUNT(*) FROM formations");
    $total_formations = $stmt_formations->fetchColumn();
    $total_formations = $total_formations ?? 0;

    // Récupérer le montant total des paiements "completed"
    $stmt_payments = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'completed'");
    $total_payments_amount = $stmt_payments->fetchColumn();
    $total_payments_amount = $total_payments_amount ?? 0; // S'assurer que c'est 0 si SUM est null

    // --- Gestion des activités récentes (COMMENTÉ PAR DÉFAUT) ---
    // Si vous avez une table pour les activités ou les journaux, vous pouvez décommenter et adapter cette partie.
    // Exemple : table 'activities' avec des colonnes 'text' et 'date'
    /*
    $stmt_activities = $pdo->query("SELECT text, date FROM activities ORDER BY date DESC LIMIT 5");
    $recent_activities = $stmt_activities->fetchAll(PDO::FETCH_ASSOC);
    */

} catch (PDOException $e) {
    // En cas d'erreur de base de données, affiche un message d'erreur pour l'utilisateur
    // et log l'erreur pour le débogage (ceci est très important)
    $error_message = "Une erreur est survenue lors du chargement des statistiques. Veuillez réessayer plus tard.";
    error_log("Admin Dashboard Stats Error: " . $e->getMessage()); // Ceci écrira l'erreur dans les logs du serveur PHP
}

$page_title = "Tableau de Bord Admin - InnovaTech";
include 'header.php'; // Inclusion de l'en-tête commun
?>

<div class="container-fluid py-4">
    <div class="row">
        <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
            <div class="position-sticky pt-3">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted text-uppercase">
                    <span>Gestion InnovaTech</span>
                </h6>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="admin_dashboard.php">
                            <i class="fas fa-home me-2"></i>
                            Tableau de bord
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_manage_users.php">
                            <i class="fas fa-users me-2"></i>
                            Gestion des Utilisateurs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_manage_formations.php">
                            <i class="fas fa-graduation-cap me-2"></i>
                            Gestion des Formations
                        </a>
                    </li>
                      
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_manage_enrollments.php">
                            <i class="fas fa-list-alt me-2"></i>
                            Gestion des Inscriptions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_manage_payments.php">
                            <i class="fas fa-money-bill-wave me-2"></i>
                            Gestion des Paiements
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_contacts.php">
                            <i class="fas fa-envelope me-2"></i>
                            Messages de Contact
                        </a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link" href="admin_assistance.php">
                            <i class="fas fa-life-ring me-2"></i>
                            Demandes d'Assistance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_site_settings.php">
                            <i class="fas fa-cogs me-2"></i>
                            Paramètres du site
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_reports.php">
                            <i class="fas fa-chart-line me-2"></i>
                            Rapports
                        </a>
                    </li>
                </ul>

                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted text-uppercase">
                    <span>Mon Compte</span>
                </h6>
                <ul class="nav flex-column mb-2">
                    <li class="nav-item">
                        <a class="nav-link" href="profile_settings.php">
                            <i class="fas fa-user-circle me-2"></i>
                            Mon Profil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="auth_process.php?action=logout">
                            <i class="fas fa-sign-out-alt me-2"></i>
                            Déconnexion
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2 text-primary"><i class="fas fa-chart-line me-2"></i> Tableau de Bord Administrateur</h1>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card shadow-sm border-0 text-white bg-primary">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">
                                        Total Utilisateurs
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo htmlspecialchars($total_users); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card shadow-sm border-0 text-white bg-success">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">
                                        Total Formations
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo htmlspecialchars($total_formations); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-graduation-cap fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card shadow-sm border-0 text-white bg-info">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">
                                        Total Inscriptions
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo htmlspecialchars($total_enrollments); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-list-alt fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card shadow-sm border-0 text-white bg-warning">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">
                                        Total Paiements (Complétés)
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($total_payments_amount, 2, ',', ' '); ?> CFA</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-dollar-sign fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-link me-2"></i> Accès Rapide aux Gestions</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="d-grid">
                                <a href="admin_manage_users.php" class="btn btn-outline-primary py-3">
                                    <i class="fas fa-users fa-2x d-block mb-1"></i> Gérer les Utilisateurs
                                </a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-grid">
                                <a href="admin_manage_formations.php" class="btn btn-outline-success py-3">
                                    <i class="fas fa-graduation-cap fa-2x d-block mb-1"></i> Gérer les Formations
                                </a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-grid">
                                <a href="admin_manage_enrollments.php" class="btn btn-outline-info py-3">
                                    <i class="fas fa-list-alt fa-2x d-block mb-1"></i> Gérer les Inscriptions
                                </a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-grid">
                                <a href="admin_manage_payments.php" class="btn btn-outline-warning text-dark py-3">
                                    <i class="fas fa-money-bill-wave fa-2x d-block mb-1"></i> Gérer les Paiements
                                </a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-grid">
                                <a href="admin_contacts.php" class="btn btn-outline-dark py-3">
                                    <i class="fas fa-envelope fa-2x d-block mb-1"></i> Messages de Contact
                                </a>
                            </div>
                        </div>
                         <div class="col-md-4">
                            <div class="d-grid">
                                <a href="admin_assistance.php" class="btn btn-outline-danger py-3">
                                    <i class="fas fa-life-ring fa-2x d-block mb-1"></i> Demandes d'Assistance
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light text-primary">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i> Activités Récentes (Logs)</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recent_activities)): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <li class="list-group-item">
                                            <i class="fas fa-info-circle text-info me-2"></i>
                                            <?php echo htmlspecialchars($activity['text']); ?> <span class="text-muted small float-end"><?php echo htmlspecialchars($activity['date']); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted">Aucune activité récente à afficher. <br> </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php
include 'footer.php'; // Inclusion du pied de page commun
?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var sidebarCollapse = document.getElementById('sidebarCollapse');
        if (sidebarCollapse) {
            sidebarCollapse.addEventListener('click', function() {
                var sidebar = document.getElementById('sidebar');
                if (sidebar) {
                    sidebar.classList.toggle('active');
                }
            });
        }
    });
</script>