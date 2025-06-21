<?php
session_start();
require_once 'config.php'; // Inclut l'objet PDO $pdo

// Activez l'affichage des erreurs pour le développement (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Génère un token de 64 caractères hexadécimaux
}

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

// Prépare les valeurs pour les champs si l'utilisateur est connecté
$name_prefill = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : '';
$email_prefill = isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : '';
$user_id_prefill = isset($_SESSION['user_id']) ? htmlspecialchars($_SESSION['user_id']) : '';

// Récupérer la liste des formations actives depuis la base de données
$formations = [];
try {
    $stmt = $pdo->query("SELECT id, title, price, description FROM formations WHERE status = '1' ORDER BY title ASC");
    $formations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erreur lors du chargement des formations : " . $e->getMessage();
    // En production, vous voudriez logger cela et afficher un message générique
}

$page_title = "InnovaTech - Formulaire d'Inscription Formation";
include 'header.php'; // Inclusion de l'en-tête
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-lg border-0 rounded-lg">
                <div class="card-header bg-primary text-white text-center">
                    <h2 class="fw-light my-3"><i class="fas fa-edit me-2"></i> Inscription à une Formation</h2>
                </div>
                <div class="card-body p-4">
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

                    <p class="text-muted text-center mb-4">Veuillez remplir le formulaire ci-dessous pour vous inscrire à la formation de votre choix.</p>

                    <form action="process_formation_inscription.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <input type="hidden" name="user_id" value="<?php echo $user_id_prefill; ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="name" class="form-label"><i class="fas fa-user me-2"></i> Votre Nom Complet</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo $name_prefill; ?>" required <?php echo isset($_SESSION['user_id']) ? 'readonly' : ''; ?>>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label"><i class="fas fa-envelope me-2"></i> Votre Adresse Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo $email_prefill; ?>" required <?php echo isset($_SESSION['user_id']) ? 'readonly' : ''; ?>>
                        </div>

                        <div class="mb-3">
                            <label for="formation_id" class="form-label"><i class="fas fa-book-open me-2"></i> Sélectionner la Formation/l'Événement</label>
                            <select class="form-select" id="formation_id" name="formation_id" required>
                                <option value="">-- Choisir une formation --</option>
                                <?php foreach ($formations as $formation): ?>
                                    <option value="<?php echo htmlspecialchars($formation['id']); ?>" data-price="<?php echo htmlspecialchars($formation['price']); ?>">
                                        <?php echo htmlspecialchars($formation['title']); ?> (<?php echo number_format($formation['price'], 2, ',', ' '); ?> CFA)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text" id="formationPriceDisplay"></div>
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label"><i class="fas fa-comment-alt me-2"></i> Message (Optionnel)</label>
                            <textarea class="form-control" id="message" name="message" rows="4" placeholder="Des questions ou commentaires supplémentaires ?"></textarea>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" name="submit_enrollment" class="btn btn-primary btn-lg"><i class="fas fa-paper-plane me-2"></i> S'inscrire</button>
                            <a href="formations.php" class="btn btn-outline-secondary btn-lg"><i class="fas fa-arrow-left me-2"></i> Retour aux Formations</a>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center py-3">
                    <div class="small"><a href="contact.php">Des questions ? Contactez-nous !</a></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const formationSelect = document.getElementById('formation_id');
        const formationPriceDisplay = document.getElementById('formationPriceDisplay');

        if (formationSelect && formationPriceDisplay) {
            formationSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const price = selectedOption.getAttribute('data-price');
                if (price) {
                    formationPriceDisplay.textContent = `Prix de la formation : ${parseFloat(price).toFixed(2).replace('.', ',')} CFA`;
                } else {
                    formationPriceDisplay.textContent = '';
                }
            });
            // Initialisation de l'affichage si une option est déjà sélectionnée au chargement
            if (formationSelect.value) {
                const selectedOption = formationSelect.options[formationSelect.selectedIndex];
                const price = selectedOption.getAttribute('data-price');
                if (price) {
                    formationPriceDisplay.textContent = `Prix de la formation : ${parseFloat(price).toFixed(2).replace('.', ',')} CFA`;
                }
            }
        }
    });
</script>

<?php
include 'footer.php'; // Inclusion du pied de page
?>