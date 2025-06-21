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
$user_payments = [];
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

$page_title = "Mes Paiements - InnovaTech";
include 'header.php'; // Inclusion de l'en-tête
?>

<div class="container my-5">
    <h1 class="mb-4 text-center text-success"><i class="fas fa-money-bill-wave me-2"></i> Historique de Mes Paiements</h1>
    <p class="lead text-center text-muted">Consultez ici le détail de toutes vos transactions financières.</p>

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
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">Détails des transactions</h4>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        // Récupérer tous les paiements de l'utilisateur avec les détails de la formation/événement correspondante
                        $stmt = $pdo->prepare("
                            SELECT
                                p.payment_id,
                                p.amount,
                                p.payment_date,
                                p.payment_method,
                                p.status AS payment_status,
                                f.title AS item_title,
                                f.type AS item_type
                            FROM payments p
                            JOIN enrollments e ON p.enrollment_id = e.enrollment_id
                            JOIN formations f ON e.formation_id = f.id
                            WHERE p.user_id = :user_id
                            ORDER BY p.payment_date DESC
                        ");
                        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                        $stmt->execute();
                        $user_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    } catch (PDOException $e) {
                        $error_message = "Erreur lors du chargement de vos paiements : " . $e->getMessage();
                    }
                    ?>

                    <?php if (!empty($user_payments)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Montant</th>
                                        <th scope="col">Date du Paiement</th>
                                        <th scope="col">Méthode</th>
                                        <th scope="col">Statut</th>
                                        <th scope="col">Article Lié</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($user_payments as $payment): ?>
                                        <tr>
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
                                                    case 'Refunded':
                                                        $status_class = 'badge bg-secondary';
                                                        break;
                                                    default:
                                                        $status_class = 'badge bg-info'; // Pour 'Partially Paid' ou autre
                                                }
                                                echo '<span class="' . $status_class . '">' . htmlspecialchars(ucfirst($payment['payment_status'])) . '</span>';
                                                ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($payment['item_title']); ?></strong>
                                                <?php if (!empty($payment['item_type'])) {
                                                    echo '<br><small class="text-muted">(' . htmlspecialchars(ucfirst($payment['item_type'])) . ')</small>';
                                                }?>
                                            </td>
                                            <td>
                                                <a href="view_payment_details.php?id=<?php echo $payment['payment_id']; ?>" class="btn btn-sm btn-outline-primary" title="Voir les détails"><i class="fas fa-eye"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center" role="alert">
                            <i class="fas fa-info-circle me-2"></i> Aucun paiement n'a été enregistré pour votre compte.
                            <p class="mt-2">Vous n'avez pas encore effectué de transactions financières avec InnovaTech.</p>
                        </div>
                    <?php endif; ?>

                    <div class="text-center mt-4">
                        <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> Retour au Tableau de Bord</a>
                        <a href="contact.php" class="btn btn-outline-primary ms-3"><i class="fas fa-question-circle me-2"></i> Une question sur un paiement ?</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include 'footer.php'; // Inclusion du pied de page
?>