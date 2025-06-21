<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php'; // Assurez-vous que ce fichier initialise l'objet $pdo

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_message'] = "Accès non autorisé. Vous n'avez pas les permissions d'administrateur.";
    header("Location: authentification.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$payments = [];
$formations = []; // Pour la liste déroulante des formations
$message = '';
$message_type = '';

if (isset($_SESSION['error_message'])) {
    $message = $_SESSION['error_message'];
    $message_type = 'danger';
    unset($_SESSION['error_message']);
} elseif (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']);
}

try {
    // Récupère les paiements avec les détails de l'utilisateur (si lié), de la formation et le nom du payeur
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

    // Récupère la liste des formations pour les options de sélection
    $stmt_formations = $pdo->query("SELECT id, name, price FROM formations ORDER BY name ASC");
    $formations = $stmt_formations->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = "Erreur de base de données: " . $e->getMessage();
    $message_type = 'danger';
    error_log("DB Error in admin_manage_payments.php: " . $e->getMessage());
}

include 'header.php';
?>

<div class="container mt-5">
    <h2 class="mb-4">Gestion des Paiements</h2>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4 bg-dark text-white">
        <div class="card-header">
            Ajouter un nouveau Paiement
        </div>
        <div class="card-body">
            <form action="admin_payment_process.php" method="POST">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="add_formation_id" class="form-label">Formation Concernée</label>
                        <select class="form-select" id="add_formation_id" name="formation_id" required>
                            <option value="">Sélectionner une formation</option>
                            <?php foreach ($formations as $formation): ?>
                                <option value="<?php echo htmlspecialchars($formation['id']); ?>"
                                        data-formation-price="<?php echo htmlspecialchars($formation['price']); ?>">
                                    <?php echo htmlspecialchars($formation['name']); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="other">Autres</option> </select>
                        <small id="add_formation_price_info" class="form-text text-muted"></small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="add_payer_name" class="form-label">Nom du Payeur</label>
                        <input type="text" class="form-control" id="add_payer_name" name="payer_name" placeholder="Nom complet du payeur" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="add_amount" class="form-label">Montant du Paiement</label>
                        <input type="number" step="0.01" class="form-control" id="add_amount" name="amount" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="add_payment_date" class="form-label">Date du Paiement</label>
                        <input type="date" class="form-control" id="add_payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="add_payment_method" class="form-label">Méthode de Paiement</label>
                        <select class="form-select" id="add_payment_method" name="payment_method" required>
                            <option value="">Sélectionner une méthode</option>
                            <option value="Liquide">Liquide</option>
                            <option value="Banque">Banque</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="add_status" class="form-label">Statut du Paiement</label>
                    <select class="form-select" id="add_status" name="status" required>
                        <option value="Completed">Complété</option>
                        <option value="Pending">En attente</option>
                        <option value="Refunded">Remboursé</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Ajouter le Paiement</button>
            </form>
        </div>
    </div>

    <div class="card bg-dark text-white">
        <div class="card-header">
            Liste des Paiements
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-dark table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID Paiement</th>
                            <th>Payeur (Nom)</th>
                            <th>Formation</th>
                            <th>Montant</th>
                            <th>Date Paiement</th>
                            <th>Méthode</th>
                            <th>Statut Paiement</th>
                            <th>Statut Inscription</th> <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr><td colspan="9" class="text-center">Aucun paiement trouvé.</td></tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['payment_id']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payer_name'] ?? $payment['username'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($payment['formation_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($payment['amount'], 2)); ?> CFA</td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($payment['payment_date']))); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php
                                            if ($payment['status'] == 'Completed') echo 'success';
                                            elseif ($payment['status'] == 'Pending') echo 'warning';
                                            else echo 'info';
                                        ?>">
                                            <?php echo htmlspecialchars($payment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php
                                            if (($payment['enrollment_status'] ?? '') == 'Paid') echo 'success';
                                            elseif (($payment['enrollment_status'] ?? '') == 'Pending') echo 'warning';
                                            elseif (($payment['enrollment_status'] ?? '') == 'Confirmed') echo 'info';
                                            else echo 'secondary';
                                        ?>">
                                            <?php echo htmlspecialchars($payment['enrollment_status'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info edit-payment-btn"
                                                data-bs-toggle="modal" data-bs-target="#editPaymentModal"
                                                data-payment-id="<?php echo htmlspecialchars($payment['payment_id']); ?>"
                                                data-formation-id="<?php echo htmlspecialchars($payment['enrollment_id'] ? $pdo->query("SELECT formation_id FROM enrollments WHERE id = " . $payment['enrollment_id'])->fetchColumn() : ''); ?>"
                                                data-payer-name="<?php echo htmlspecialchars($payment['payer_name'] ?? $payment['username'] ?? ''); ?>"
                                                data-amount="<?php echo htmlspecialchars($payment['amount']); ?>"
                                                data-payment-date="<?php echo htmlspecialchars($payment['payment_date']); ?>"
                                                data-payment-method="<?php echo htmlspecialchars($payment['payment_method']); ?>"
                                                data-status="<?php echo htmlspecialchars($payment['status']); ?>">
                                            Modifier
                                        </button>
                                        <form action="admin_payment_process.php" method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce paiement ?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="payment_id" value="<?php echo htmlspecialchars($payment['payment_id']); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editPaymentModal" tabindex="-1" aria-labelledby="editPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h5 class="modal-title" id="editPaymentModalLabel">Modifier le Paiement</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="admin_payment_process.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="payment_id" id="edit_payment_id">

                    <div class="mb-3">
                        <label for="edit_formation_id" class="form-label">Formation Concernée</label>
                        <select class="form-select" id="edit_formation_id" name="formation_id" required>
                            <option value="">Sélectionner une formation</option>
                            <?php foreach ($formations as $formation): ?>
                                <option value="<?php echo htmlspecialchars($formation['id']); ?>"
                                        data-formation-price="<?php echo htmlspecialchars($formation['price']); ?>">
                                    <?php echo htmlspecialchars($formation['name']); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="other">Autres</option> </select>
                        <small id="edit_formation_price_info" class="form-text text-muted"></small>
                    </div>

                    <div class="mb-3">
                        <label for="edit_payer_name" class="form-label">Nom du Payeur</label>
                        <input type="text" class="form-control" id="edit_payer_name" name="payer_name" placeholder="Nom complet du payeur" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_amount" class="form-label">Montant du Paiement</label>
                        <input type="number" step="0.01" class="form-control" id="edit_amount" name="amount" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_payment_date" class="form-label">Date du Paiement</label>
                        <input type="date" class="form-control" id="edit_payment_date" name="payment_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_payment_method" class="form-label">Méthode de Paiement</label>
                        <select class="form-select" id="edit_payment_method" name="payment_method" required>
                            <option value="">Sélectionner une méthode</option>
                            <option value="Liquide">Liquide</option>
                            <option value="Banque">Banque</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Statut du Paiement</label>
                        <select class="form-select" id="edit_status" name="status" required>
                            <option value="Completed">Complété</option>
                            <option value="Pending">En attente</option>
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

<?php include 'footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Logique pour pré-remplir le modal d'édition
        var editPaymentModal = document.getElementById('editPaymentModal');
        editPaymentModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var paymentId = button.getAttribute('data-payment-id');
            var formationId = button.getAttribute('data-formation-id'); // Nouvelle donnée
            var payerName = button.getAttribute('data-payer-name'); // Nouvelle donnée
            var amount = button.getAttribute('data-amount');
            var paymentDate = button.getAttribute('data-payment-date');
            var paymentMethod = button.getAttribute('data-payment-method');
            var status = button.getAttribute('data-status');

            var modal = this;
            modal.querySelector('#edit_payment_id').value = paymentId;
            modal.querySelector('#edit_payer_name').value = payerName;
            modal.querySelector('#edit_amount').value = amount;
            modal.querySelector('#edit_payment_date').value = paymentDate;
            modal.querySelector('#edit_payment_method').value = paymentMethod;
            modal.querySelector('#edit_status').value = status;

            // Pré-sélectionner la formation dans le modal
            var editFormationSelect = modal.querySelector('#edit_formation_id');
            if (editFormationSelect) {
                editFormationSelect.value = formationId || ''; // Sélectionne l'ID ou vide
                // Déclenche l'événement change pour mettre à jour les infos de prix si nécessaire
                var event = new Event('change');
                editFormationSelect.dispatchEvent(event);
            }
        });

        // Logique pour afficher le prix de la formation dans le modal d'édition
        var editFormationSelect = document.getElementById('edit_formation_id');
        var editFormationPriceInfo = document.getElementById('edit_formation_price_info');
        var editAmountInput = document.getElementById('edit_amount');

        if (editFormationSelect) {
            editFormationSelect.addEventListener('change', function() {
                var selectedOption = this.options[this.selectedIndex];
                var price = selectedOption.getAttribute('data-formation-price');
                if (price) {
                    editFormationPriceInfo.textContent = 'Prix de la formation : ' + parseFloat(price).toFixed(2).replace('.', ',') + ' CFA';
                    // editAmountInput.value = parseFloat(price).toFixed(2); // Optionnel: Pré-remplir le montant dans le modal d'édition
                } else {
                    editFormationPriceInfo.textContent = '';
                }
            });
        }

        // Logique pour afficher le prix de la formation dans le formulaire d'ajout
        var addFormationSelect = document.getElementById('add_formation_id');
        var addFormationPriceInfo = document.getElementById('add_formation_price_info');
        var addAmountInput = document.getElementById('add_amount');

        if (addFormationSelect) {
            addFormationSelect.addEventListener('change', function() {
                var selectedOption = this.options[this.selectedIndex];
                var price = selectedOption.getAttribute('data-formation-price');
                if (price) {
                    addFormationPriceInfo.textContent = 'Prix de la formation : ' + parseFloat(price).toFixed(2).replace('.', ',') + ' CFA';
                    addAmountInput.value = parseFloat(price).toFixed(2); // Pré-remplir le montant
                } else {
                    addFormationPriceInfo.textContent = '';
                    addAmountInput.value = '';
                }
            });
        }
    });
</script>