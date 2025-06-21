<?php
session_start();
require_once 'config.php'; // Inclut l'objet PDO $pdo

// Activez l'affichage des erreurs pour le développement (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: authentification.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_data = null;
$total_paid_amount = 0;
$user_training_name = 'Non assignée';
$profile_picture_path = 'assets/images/default_profile.png'; // Chemin par défaut pour la photo de profil

$error_message = '';
$success_message = '';

// Récupère les messages d'erreur/succès de la session
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

try {
    // Récupérer les informations de l'utilisateur, sa photo de profil et sa formation assignée
    // Utilisation de LEFT JOIN pour inclure la formation même si training_id est NULL
    $stmt = $pdo->prepare("SELECT u.id, u.username, u.email, u.role, u.profile_picture, f.title AS training_name
                            FROM users u
                            LEFT JOIN formations f ON u.training_id = f.id
                            WHERE u.id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_data) {
        if (!empty($user_data['training_name'])) {
            $user_training_name = $user_data['training_name'];
        }
        if (!empty($user_data['profile_picture']) && file_exists($user_data['profile_picture'])) {
            $profile_picture_path = $user_data['profile_picture'];
        }
    } else {
        // Si l'utilisateur n'est pas trouvé (cas rare, mais pour robustesse)
        $_SESSION['error_message'] = "Vos informations utilisateur n'ont pas pu être chargées. Veuillez vous reconnecter.";
        header("Location: authentification.php");
        exit();
    }

    // Récupérer le montant total payé par l'utilisateur
    // On somme seulement les paiements qui sont 'Completed' (réussis) et liés à une inscription
    $stmt_payments = $pdo->prepare("SELECT SUM(p.amount) AS total_paid
                                    FROM payments p
                                    JOIN enrollments e ON p.enrollment_id = e.formation_id
                                    WHERE p.user_id = :user_id AND p.status = 'Completed'");
    $stmt_payments->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_payments->execute();
    $result_payments = $stmt_payments->fetch(PDO::FETCH_ASSOC);

    if ($result_payments && $result_payments['total_paid'] !== null) {
        $total_paid_amount = $result_payments['total_paid'];
    }

} catch (PDOException $e) {
    $error_message = "Erreur de base de données : " . $e->getMessage();
    // En production, vous voudriez logger cela et afficher un message générique
}

$page_title = "Tableau de Bord - InnovaTech";
include 'header.php'; // Inclusion de l'en-tête
?>

<div class="wrapper">
    <div id="content" class="main-content">
        <main class="container-fluid py-4">
            <h1 class="mb-4 text-center text-primary"><i class="fas fa-tachometer-alt me-2"></i> Tableau de Bord Utilisateur</h1>
            <p class="lead text-center text-muted">Bienvenue, <?php echo htmlspecialchars($user_data['username']); ?> ! Gérez vos informations et suivez vos progrès.</p>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0"><i class="fas fa-user-circle"></i> Mon Profil</h4>
                        </div>
                        <div class="card-body text-center">
                            <img src="<?php echo htmlspecialchars($profile_picture_path); ?>" class="img-fluid rounded-circle mb-3" alt="Photo de profil" style="width: 120px; height: 120px; object-fit: cover;">
                            <h3 class="card-title"><?php echo htmlspecialchars($user_data['username']); ?></h3>
                            <p class="card-text text-muted mb-1"><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($user_data['email']); ?></p>
                            <p class="card-text text-muted mb-1"><strong>Rôle :</strong> <?php echo htmlspecialchars(ucfirst($user_data['role'])); ?></p>
                            <p class="card-text text-muted"><strong>Formation assignée :</strong> <?php echo htmlspecialchars($user_training_name); ?></p>
                            <a href="profile_settings.php" class="btn btn-outline-primary mt-3"><i class="fas fa-edit"></i> Modifier le Profil</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-success text-white">
                            <h4 class="mb-0"><i class="fas fa-money-bill-wave"></i> Mon Compte Financier</h4>
                        </div>
                        <div class="card-body">
                            <p class="lead">Votre solde total payé :</p>
                            <h2 class="display-4 text-success"><?php echo number_format($total_paid_amount, 2, ',', ' '); ?> CFA</h2>
                            <p class="text-muted">Ce montant représente la somme de tous les paiements enregistrés sur votre compte.</p>
                            <a href="payments.php" class="btn btn-outline-success mt-3"><i class="fas fa-receipt"></i> Voir mes paiements</a>
                            <a href="contact.php" class="btn btn-outline-info mt-3 ms-2"><i class="fas fa-question-circle"></i> Question sur mes paiements</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-info text-white">
                            <h4 class="mb-0"><i class="fas fa-book"></i> Mes Formations</h4>
                        </div>
                        <div class="card-body">
                            <p class="lead">Gérez et suivez vos inscriptions aux formations.</p>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Voir toutes mes inscriptions
                                    <a href="my_enrollments.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-arrow-right"></i></a>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    M'inscrire à une nouvelle formation
                                    <a href="inscription_formation_form.php" class="btn btn-sm btn-outline-success"><i class="fas fa-plus"></i></a>
                                </li>
                            </ul>
                            <a href="inscription_formation.php" class="btn btn-outline-info mt-3"><i class="fas fa-search me-2"></i> Explorer les Formations</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-warning text-dark">
                            <h4 class="mb-0"><i class="fas fa-life-ring"></i> Aide et Support</h4>
                        </div>
                        <div class="card-body">
                            <p class="lead">Besoin d'aide ? Contactez notre support ou consultez la FAQ.</p>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Soumettre une demande d'assistance
                                    <a href="assistance.php" class="btn btn-sm btn-outline-warning text-dark"><i class="fas fa-headset"></i></a>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Contacter l'administration
                                    <a href="contact.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-envelope"></i></a>
                                </li>
                            </ul>
                  
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php
include 'footer.php'; 
?>

<script>
    // Script pour le basculement de la barre latérale sur petits écrans
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