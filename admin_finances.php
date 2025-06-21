<?php
session_start();

// Activez l'affichage des erreurs pour le développement (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php'; // Assurez-vous que ce fichier initialise l'objet $pdo

// Protection de la page : l'utilisateur doit être connecté ET avoir le rôle 'admin'
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_message'] = "Accès non autorisé. Vous n'avez pas les permissions d'administrateur.";
    header("Location: authentification.php");
    exit();
}

// Initialisation des messages
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

// --- Statistiques Financières Globales ---
$total_revenue = 0;
$paid_enrollments_count = 0;
$pending_enrollments_count = 0;
$total_enrollments_count = 0;

try {
    // Calcul du revenu total
    $stmt_revenue = $pdo->query("SELECT SUM(amount) AS total_revenue FROM payments WHERE status = 'Completed'");
    $total_revenue = $stmt_revenue->fetchColumn() ?: 0;

    // Compteur d'inscriptions payées
    $stmt_paid_enrollments = $pdo->query("SELECT COUNT(*) AS paid_count FROM enrollments WHERE payment_status = 'paid'");
    $paid_enrollments_count = $stmt_paid_enrollments->fetchColumn() ?: 0;

    // Compteur d'inscriptions en attente de paiement
    $stmt_pending_enrollments = $pdo->query("SELECT COUNT(*) AS pending_count FROM enrollments WHERE payment_status = 'pending'");
    $pending_enrollments_count = $stmt_pending_enrollments->fetchColumn() ?: 0;

    // Compteur total d'inscriptions
    $stmt_total_enrollments = $pdo->query("SELECT COUNT(*) AS total_count FROM enrollments");
    $total_enrollments_count = $stmt_total_enrollments->fetchColumn() ?: 0;

    // Récupérer les paiements récents
    $sql = "SELECT p.payment_id, p.user_id, p.enrollment_id, p.amount, p.payment_date, p.payment_method, p.status, p.payer_name,
                   u.username,
                   f.name as formation_name, f.price as formation_price,
                   en.status as enrollment_status -- Statut de l'inscription si elle est liée
            FROM payments p
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN enrollments en ON p.enrollment_id = en.id
            LEFT JOIN formations f ON en.formation_id = f.id
            ORDER BY p.payment_date DESC";
    $stmt = $pdo->query($sql);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer toutes les inscriptions avec leur statut de paiement et les détails de l'utilisateur/formation
    $stmt_all_enrollments_payments = $pdo->query("
        SELECT e.enrollment_id, e.enrollment_datetime, e.status AS enrollment_status, e.payment_status,
               u.username, f.title AS formation_title, f.price AS formation_price
        FROM enrollments e
        JOIN users u ON e.user_id = u.id
        JOIN formations f ON e.formation_id = f.id
        ORDER BY e.enrollment_datetime DESC
    ");
    $enrollments_with_payments = $stmt_all_enrollments_payments->fetchAll();

} catch (PDOException $e) {
    $error_message = "Erreur de base de données : " . $e->getMessage();
}

$page_title = "Admin Finances - InnovaTech";
include 'header.php'; // Inclusion de l'en-tête, supposant qu'il gère le début du HTML et le nav
?>

<div class="wrapper">
    <?php include 'admin_sidebar.php'; // Sidebar spécifique à l'admin ?>
    <div id="content" class="container-fluid">
        <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
            <div class="container-fluid">
                <button type="button" id="sidebarCollapse" class="btn btn-info">
                    <i class="fas fa-align-left"></i>
                    <span>Toggle Sidebar</span>
                </button>
                <div class="ms-auto">
                    <a href="auth_process.php?action=logout" class="btn btn-warning"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                </div>
            </div>
        </nav>

        <main class="container-fluid">
            <h2 class="mb-4"><i class="fas fa-chart-line me-2"></i> Tableau de Bord Financier</h2>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card text-white bg-primary shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="card-title text-uppercase">Revenu Total</h5>
                                    <h1 class="display-4"><?php echo number_format($total_revenue, 2, ',', ' '); ?> CFA</h1>
                                </div>
                                <i class="fas fa-wallet fa-4x opacity-50"></i>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-top-0">
                            <small>Somme de tous les paiements complétés.</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card text-white bg-success shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="card-title text-uppercase">Inscriptions Payées</h5>
                                    <h1 class="display-4"><?php echo $paid_enrollments_count; ?></h1>
                                </div>
                                <i class="fas fa-check-circle fa-4x opacity-50"></i>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-top-0">
                            <small>Nombre d'inscriptions dont le paiement est complet.</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card text-white bg-warning shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="card-title text-uppercase">Inscriptions en Attente</h5>
                                    <h1 class="display-4"><?php echo $pending_enrollments_count; ?></h1>
                                </div>
                                <i class="fas fa-clock fa-4x opacity-50"></i>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-top-0">
                            <small>Nombre d'inscriptions en attente de paiement.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0"><i class="fas fa-money-check-alt me-2"></i> Paiements Récents (10 Derniers)</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_payments)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID Paiement</th>
                                        <th>Utilisateur</th>
                                        <th>Formation</th>
                                        <th>Montant</th>
                                        <th>Date</th>
                                        <th>Méthode</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_payments as $payment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($payment['payment_id']); ?></td>
                                            <td><?php echo htmlspecialchars($payment['username']); ?></td>
                                            <td><?php echo htmlspecialchars($payment['formation_title']); ?></td>
                                            <td><?php echo number_format($payment['amount'], 2, ',', ' '); ?> CFA</td>
                                            <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($payment['payment_date']))); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst($payment['payment_method'])); ?></td>
                                            <td>
                                                <?php
                                                $status_class = '';
                                                switch ($payment['payment_status']) {
                                                    case 'Completed':
                                                        $status_class = 'badge bg-success';
                                                        break;
                                                    case 'Pending':
                                                        $status_class = 'badge bg-warning text-dark';
                                                        break;
                                                    case 'Failed':
                                                        $status_class = 'badge bg-danger';
                                                        break;
                                                    default:
                                                        $status_class = 'badge bg-secondary';
                                                        break;
                                                }
                                                ?>
                                                <span class="<?php echo $status_class; ?>"><?php echo htmlspecialchars($payment['payment_status']); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted">Aucun paiement récent à afficher.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-list-alt me-2"></i> Toutes les Inscriptions et Statut de Paiement</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($enrollments_with_payments)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID Inscription</th>
                                        <th>Utilisateur</th>
                                        <th>Formation</th>
                                        <th>Prix Formation</th>
                                        <th>Date Inscription</th>
                                        <th>Statut Inscription</th>
                                        <th>Statut Paiement</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($enrollments_with_payments as $enrollment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($enrollment['enrollment_id']); ?></td>
                                            <td><?php echo htmlspecialchars($enrollment['username']); ?></td>
                                            <td><?php echo htmlspecialchars($enrollment['formation_title']); ?></td>
                                            <td><?php echo number_format($enrollment['formation_price'], 2, ',', ' '); ?> CFA</td>
                                            <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($enrollment['enrollment_datetime']))); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars(ucfirst($enrollment['enrollment_status'])); ?></span></td>
                                            <td>
                                                <?php
                                                $payment_status_class = '';
                                                switch ($enrollment['payment_status']) {
                                                    case 'paid':
                                                        $payment_status_class = 'badge bg-success';
                                                        break;
                                                    case 'pending':
                                                        $payment_status_class = 'badge bg-warning text-dark';
                                                        break;
                                                    case 'due':
                                                        $payment_status_class = 'badge bg-danger';
                                                        break;
                                                    default:
                                                        $payment_status_class = 'badge bg-secondary';
                                                        break;
                                                }
                                                ?>
                                                <span class="<?php echo $payment_status_class; ?>"><?php echo htmlspecialchars(ucfirst($enrollment['payment_status'])); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted">Aucune inscription à afficher.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="text-center mt-4">
                <a href="admin_dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> Retour au Tableau de Bord Admin</a>
            </div>

        </main>
    </div>
</div>

<?php include 'footer.php'; // Inclusion du pied de page commun ?>

<script>
    // Script pour le basculement de la barre latérale
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
</body>
</html>