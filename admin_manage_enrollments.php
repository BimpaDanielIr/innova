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

// Protection CSRF : Génère un jeton si non existant
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$enrollments = []; // Pour stocker les données d'inscriptions
$message = '';
$message_type = '';

// Récupère les messages d'erreur/succès de la session
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

try {
    // Récupérer toutes les inscriptions avec les détails de l'utilisateur, la formation et les paiements
    $stmt = $pdo->prepare("
        SELECT
            e.enrollment_id,
            e.enrollment_datetime,
            e.status AS enrollment_status,
            e.payment_status,
            e.notes,
            u.id AS user_id,
            u.username,
            u.email,
            f.id AS formation_id,
            f.title AS formation_title,
            f.price AS formation_price,
            p.payment_id,
            p.amount AS payment_amount,
            p.payment_method,
            p.status AS payment_record_status,
            p.payment_date
        FROM enrollments e
        JOIN users u ON e.user_id = u.id
        JOIN formations f ON e.formation_id = f.id
        LEFT JOIN payments p ON e.enrollment_id = p.enrollment_id
        ORDER BY e.enrollment_datetime DESC
    ");
    $stmt->execute();
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = "Erreur de base de données : " . $e->getMessage();
    $message_type = 'danger';
    // En production, logguez l'erreur et affichez un message générique.
}

$page_title = "Gérer les Inscriptions - InnovaTech Admin";
include 'header.php'; // Inclusion de l'en-tête
?>

<div class="wrapper">
    <?php include 'admin_sidebar.php'; // Inclure la barre latérale de l'admin ?>

    <div id="content" class="main-content">
        <main class="container-fluid py-4">
            <h1 class="mb-4 text-center text-info"><i class="fas fa-list-alt me-2"></i> Gestion des Inscriptions</h1>
            <p class="lead text-center text-muted">Affichez, modifiez et gérez toutes les inscriptions aux formations et événements.</p>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo ($message_type === 'success' ? 'check-circle' : 'exclamation-triangle'); ?> me-2"></i> <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Liste des Inscriptions</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($enrollments)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">ID Inscription</th>
                                        <th scope="col">Utilisateur</th>
                                        <th scope="col">Formation</th>
                                        <th scope="col">Date Inscription</th>
                                        <th scope="col">Statut Inscription</th>
                                        <th scope="col">Statut Paiement</th>
                                        <th scope="col">Montant Dû</th>
                                        <th scope="col">Montant Payé</th>
                                        <th scope="col">Méthode Paiement</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($enrollments as $enrollment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($enrollment['enrollment_id']); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($enrollment['username']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($enrollment['email']); ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($enrollment['formation_title']); ?></strong><br>
                                                <small class="text-muted">Prix: <?php echo number_format($enrollment['formation_price'], 2, ',', ' '); ?> CFA</small>
                                            </td>
                                            <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($enrollment['enrollment_datetime']))); ?></td>
                                            <td>
                                                <?php
                                                $status_class = '';
                                                switch ($enrollment['enrollment_status']) {
                                                    case 'Confirmed': $status_class = 'badge bg-success'; break;
                                                    case 'Pending': $status_class = 'badge bg-warning text-dark'; break;
                                                    case 'Cancelled': $status_class = 'badge bg-danger'; break;
                                                    default: $status_class = 'badge bg-secondary';
                                                }
                                                echo '<span class="' . $status_class . '">' . htmlspecialchars(ucfirst($enrollment['enrollment_status'])) . '</span>';
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                $payment_status_class = '';
                                                switch ($enrollment['payment_status']) {
                                                    case 'Paid': $payment_status_class = 'badge bg-success'; break;
                                                    case 'Pending': $payment_status_class = 'badge bg-warning text-dark'; break;
                                                    case 'Partially Paid': $payment_status_class = 'badge bg-info'; break;
                                                    case 'Refunded': $payment_status_class = 'badge bg-secondary'; break;
                                                    default: $payment_status_class = 'badge bg-dark';
                                                }
                                                echo '<span class="' . $payment_status_class . '">' . htmlspecialchars(ucfirst($enrollment['payment_status'])) . '</span>';
                                                ?>
                                            </td>
                                            <td><?php echo number_format($enrollment['formation_price'], 2, ',', ' '); ?> CFA</td>
                                            <td><?php echo number_format($enrollment['payment_amount'] ?? 0, 2, ',', ' '); ?> CFA</td>
                                            <td><?php echo htmlspecialchars(ucfirst($enrollment['payment_method'] ?? 'N/A')); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary me-1"
                                                        data-bs-toggle="modal" data-bs-target="#editEnrollmentPaymentModal"
                                                        data-enrollment_id="<?php echo htmlspecialchars($enrollment['enrollment_id']); ?>"
                                                        data-user_id="<?php echo htmlspecialchars($enrollment['user_id']); ?>"
                                                        data-username="<?php echo htmlspecialchars($enrollment['username']); ?>"
                                                        data-formation_title="<?php echo htmlspecialchars($enrollment['formation_title']); ?>"
                                                        data-formation_price="<?php echo htmlspecialchars($enrollment['formation_price']); ?>"
                                                        data-enrollment_status="<?php echo htmlspecialchars($enrollment['enrollment_status']); ?>"
                                                        data-payment_status="<?php echo htmlspecialchars($enrollment['payment_status']); ?>"
                                                        data-payment_amount="<?php echo htmlspecialchars($enrollment['payment_amount'] ?? 0); ?>"
                                                        data-payment_method="<?php echo htmlspecialchars($enrollment['payment_method'] ?? ''); ?>"
                                                        title="Modifier l'inscription et le paiement">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form action="admin_process_enrollment.php" method="POST" class="d-inline-block" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette inscription et ses paiements associés ?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <input type="hidden" name="action" value="delete_enrollment">
                                                    <input type="hidden" name="enrollment_id" value="<?php echo htmlspecialchars($enrollment['enrollment_id']); ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer l'inscription">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center alert alert-info">Aucune inscription enregistrée pour le moment.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="modal fade" id="editEnrollmentPaymentModal" tabindex="-1" aria-labelledby="editEnrollmentPaymentModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="editEnrollmentPaymentModalLabel">Modifier Inscription & Paiement</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form action="admin_process_enrollment.php" method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="update_enrollment_payment">
                                <input type="hidden" name="enrollment_id" id="enrollment_id">
                                <input type="hidden" name="user_id" id="payment_modal_user_id"> <div class="mb-3">
                                    <label class="form-label">Utilisateur :</label>
                                    <p id="payment_modal_username" class="form-control-plaintext fw-bold"></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Formation :</label>
                                    <p id="payment_modal_formation_title" class="form-control-plaintext fw-bold"></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Prix de la Formation :</label>
                                    <p id="payment_modal_formation_price" class="form-control-plaintext fw-bold"></p>
                                </div>

                                <div class="mb-3">
                                    <label for="enrollment_modal_status" class="form-label">Statut de l'Inscription</label>
                                    <select class="form-select" id="enrollment_modal_status" name="enrollment_status" required>
                                        <option value="Pending">En Attente</option>
                                        <option value="Confirmed">Confirmée</option>
                                        <option value="Cancelled">Annulée</option>
                                    </select>
                                </div>

                                <hr>
                                <h5>Mettre à jour le Paiement Associé</h5>
                                <p class="text-muted small">Si aucun paiement n'existe, il sera créé. Si un paiement existe, il sera mis à jour.</p>

                                <div class="mb-3">
                                    <label for="payment_modal_amount" class="form-label">Montant Payé (CFA)</label>
                                    <input type="number" step="0.01" class="form-control" id="payment_modal_amount" name="payment_amount" value="0.00">
                                </div>
                                <div class="mb-3">
                                    <label for="payment_modal_method" class="form-label">Méthode de Paiement</label>
                                    <select class="form-select" id="payment_modal_method" name="payment_method">
                                        <option value="">-- Sélectionner --</option>
                                        <option value="Mobile Money">Mobile Money</option>
                                        <option value="Bank Transfer">Virement Bancaire</option>
                                        <option value="Cash">Cash</option>
                                        <option value="Card">Carte Bancaire</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="payment_modal_status" class="form-label">Statut du Paiement</label>
                                    <select class="form-select" id="payment_modal_status" name="payment_status_record">
                                        <option value="Pending">En Attente</option>
                                        <option value="Completed">Terminé</option>
                                        <option value="Partially Paid">Partiellement Payé</option>
                                        <option value="Failed">Échoué</option>
                                        <option value="Refunded">Remboursé</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var editEnrollmentPaymentModal = document.getElementById('editEnrollmentPaymentModal');
        editEnrollmentPaymentModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;

            var enrollmentId = button.getAttribute('data-enrollment_id');
            var userId = button.getAttribute('data-user_id');
            var username = button.getAttribute('data-username');
            var formationTitle = button.getAttribute('data-formation_title');
            var formationPrice = button.getAttribute('data-formation_price');
            var enrollmentStatus = button.getAttribute('data-enrollment_status');
            var paymentStatus = button.getAttribute('data-payment_status'); // This is enrollment.payment_status
            var paymentAmount = button.getAttribute('data-payment_amount'); // This is payments.amount
            var paymentMethod = button.getAttribute('data-payment_method');

            // Set enrollment details
            editEnrollmentPaymentModal.querySelector('#enrollment_id').value = enrollmentId;
            editEnrollmentPaymentModal.querySelector('#payment_modal_user_id').value = userId;
            editEnrollmentPaymentModal.querySelector('#payment_modal_username').textContent = username;
            editEnrollmentPaymentModal.querySelector('#payment_modal_formation_title').textContent = formationTitle;
            editEnrollmentPaymentModal.querySelector('#payment_modal_formation_price').textContent = parseFloat(formationPrice).toFixed(2) + ' CFA';
            editEnrollmentPaymentModal.querySelector('#enrollment_modal_status').value = enrollmentStatus;

            // Set payment details
            editEnrollmentPaymentModal.querySelector('#payment_modal_amount').value = parseFloat(paymentAmount).toFixed(2);
            editEnrollmentPaymentModal.querySelector('#payment_modal_method').value = paymentMethod;
            editEnrollmentPaymentModal.querySelector('#payment_modal_status').value = paymentStatus; // Use the enrollment.payment_status here to match the form field 'payment_status_record'
        });
    });
</script>