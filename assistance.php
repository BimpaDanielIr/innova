<?php
session_start();

// Activez l'affichage des erreurs pour le développement (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

$page_title = "Assistance Technique - InnovaTech";
include 'header.php'; // Inclusion du fichier d'en-tête
?>

<div class="container py-5">
    <h1 class="mb-4 text-center text-primary"><i class="fas fa-life-ring me-2"></i> Centre d'Assistance InnovaTech</h1>
    <p class="lead text-center text-muted">Nous sommes là pour vous aider à chaque étape de votre parcours avec InnovaTech.</p>

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

    <div class="row justify-content-center mt-4">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white text-center">
                    <h4 class="mb-0">Comment pouvons-nous vous aider ?</h4>
                </div>
                <div class="card-body">
                    <p class="card-text">Notre équipe d'experts est prête à répondre à vos interrogations concernant :</p>
                    <ul class="list-group list-group-flush mb-4">
                        <li class="list-group-item"><i class="fas fa-wrench me-2 text-info"></i> <strong>Problèmes techniques:</strong> avec nos services ou plateformes.</li>
                        <li class="list-group-item"><i class="fas fa-book me-2 text-success"></i> <strong>Questions sur les formations:</strong> et leur contenu.</li>
                        <li class="list-group-item"><i class="fas fa-lock me-2 text-danger"></i> <strong>Difficultés d'accès</strong> à votre compte.</li>
                        <li class="list-group-item"><i class="fas fa-info-circle me-2 text-secondary"></i> <strong>Toute autre demande</strong> nécessitant un support technique ou informatif.</li>
                    </ul>

                    <p class="card-text">Avant de soumettre une demande.</p>

                    <div class="text-center mt-4">
                        <p class="lead">Décrivez votre problème et nous vous répondrons dans les plus brefs délais.</p>
                        <a href="assistance_form.php" class="btn btn-primary btn-lg"><i class="fas fa-paper-plane me-2"></i> Soumettre une Demande d'Assistance</a>
                       
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include 'footer.php'; // Inclusion du fichier de pied de page
?>