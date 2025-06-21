<?php
session_start();
require_once 'config.php'; // Assurez-vous que ce fichier initialise l'objet $pdo

// Activez l'affichage des erreurs pour le développement (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Veuillez vous connecter pour accéder à cette page.";
    header("Location: authentification.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_enrollments = [];
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

$page_title = "Mes Inscriptions - InnovaTech";
include 'header.php'; // Inclusion de l'en-tête
?>

<div class="container my-5">
    <h1 class="mb-4 text-center text-primary"><i class="fas fa-list-alt me-2"></i> Mes Inscriptions aux Formations</h1>
    <p class="lead text-center text-muted">Retrouvez ici toutes les formations et événements auxquels vous êtes inscrit(e).</p>

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

    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Détails de mes inscriptions</h4>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        // Récupérer toutes les inscriptions de l'utilisateur avec les détails de la formation/événement
                        $stmt = $pdo->prepare("
                            SELECT
                                e.enrollment_id,
                                e.enrollment_datetime,
                                e.status AS enrollment_status,
                                e.payment_status,
                                f.title AS item_title,
                                f.description AS item_description,
                                f.price AS item_price,
                                f.type AS item_type
                            FROM enrollments e
                            JOIN formations f ON e.formation_id = f.id
                            WHERE e.user_id = :user_id
                            ORDER BY e.enrollment_datetime DESC
                        ");
                        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                        $stmt->execute();
                        $user_enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    } catch (PDOException $e) {
                        $error_message = "Erreur lors du chargement de vos inscriptions : " . $e->getMessage();
                    }
                    ?>

                    <?php if (!empty($user_enrollments)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Formation/Événement</th>
                                        <th scope="col">Date d'Inscription</th>
                                        <th scope="col">Statut Inscription</th>
                                        <th scope="col">Statut Paiement</th>
                                        <th scope="col">Prix</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($user_enrollments as $enrollment): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($enrollment['item_title']); ?></strong>
                                                <?php if (!empty($enrollment['item_type'])) {
                                                    echo '<br><small class="text-muted">(' . htmlspecialchars(ucfirst($enrollment['item_type'])) . ')</small>';
                                                }?>
                                            </td>
                                            <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($enrollment['enrollment_datetime']))); ?></td>
                                            <td>
                                                <?php
                                                $status_class = '';
                                                switch ($enrollment['enrollment_status']) {
                                                    case 'Confirmed':
                                                        $status_class = 'badge bg-success';
                                                        break;
                                                    case 'Pending':
                                                        $status_class = 'badge bg-warning text-dark';
                                                        break;
                                                    case 'Cancelled':
                                                        $status_class = 'badge bg-danger';
                                                        break;
                                                    default:
                                                        $status_class = 'badge bg-secondary';
                                                }
                                                echo '<span class="' . $status_class . '">' . htmlspecialchars(ucfirst($enrollment['enrollment_status'])) . '</span>';
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                $payment_status_class = '';
                                                switch ($enrollment['payment_status']) {
                                                    case 'Paid':
                                                        $payment_status_class = 'badge bg-success';
                                                        break;
                                                    case 'Pending':
                                                        $payment_status_class = 'badge bg-warning text-dark';
                                                        break;
                                                    case 'Partially Paid':
                                                        $payment_status_class = 'badge bg-info';
                                                        break;
                                                    case 'Refunded':
                                                        $payment_status_class = 'badge bg-secondary';
                                                        break;
                                                    default:
                                                        $payment_status_class = 'badge bg-dark';
                                                }
                                                echo '<span class="' . $payment_status_class . '">' . htmlspecialchars(ucfirst($enrollment['payment_status'])) . '</span>';
                                                ?>
                                            </td>
                                            <td><?php echo number_format($enrollment['item_price'], 2, ',', ' '); ?> CFA</td>
                                            <td>
                                                <a href="view_enrollment_details.php?id=<?php echo $enrollment['enrollment_id']; ?>" class="btn btn-sm btn-outline-primary" title="Voir les détails"><i class="fas fa-eye"></i></a>
                                                </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center" role="alert">
                            <i class="fas fa-info-circle me-2"></i> Vous n'avez pas encore d'inscriptions enregistrées.
                            <p class="mt-2">Découvrez nos formations et événements dès maintenant pour commencer votre parcours avec InnovaTech !</p>
                            <a href="formations.php" class="btn btn-primary mt-3"><i class="fas fa-plus-circle me-2"></i> Explorer les Formations</a>
                        </div>
                    <?php endif; ?>

                    <div class="text-center mt-4">
                        <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> Retour au Tableau de Bord</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include 'footer.php'; // Inclusion du pied de page
?>