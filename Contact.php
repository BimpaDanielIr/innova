<?php
session_start();
require_once 'config.php';

// Activez l'affichage des erreurs pour le développement (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Générer un token CSRF s'il n'existe pas déjà dans la session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialisation des variables de messages
$error_message = '';
$success_message = '';

// Récupérer et afficher les messages de session
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Récupérer les données du formulaire précédemment soumises en cas d'erreur
$old_contact_data = $_SESSION['old_contact_data'] ?? [
    'name' => '',
    'email' => '',
    'subject' => '',
    'message' => ''
];
unset($_SESSION['old_contact_data']); // Nettoyer après récupération

// Récupérer les paramètres du site (comme l'email, téléphone, adresse, réseaux sociaux) depuis la base de données
// Supposons que vous avez une table 'site_settings'
$site_settings = [
    'contact_email' => 'contact@innovatech.com',
    'contact_phone' => '+257 68 79 27 20',
    'address' => 'bujumbura mairie, Bujumbura, Burundi',
    'facebook_url' => 'https://facebook.com/InnovaTech',
    'twitter_url' => 'https://twitter.com/InnovaTech',
    'linkedin_url' => 'https://linkedin.com/company/InnovaTech'
];

try {
   

    // Traitement du formulaire de contact
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Vérification du token CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Erreur CSRF : Requête invalide.");
        }

        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $subject = trim($_POST['subject']);
        $message = trim($_POST['message']);

        // Sauvegarder les données pour les réafficher en cas d'erreur
        $_SESSION['old_contact_data'] = $_POST;

        // Validation simple
        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            throw new Exception("Tous les champs sont obligatoires.");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("L'adresse email n'est pas valide.");
        }

        // Ici, vous enverriez l'email ou enregistreriez le message dans une base de données
        // Pour cet exemple, nous allons juste simuler un envoi réussi.
        // N'oubliez pas d'implémenter l'envoi réel d'email (ex: via PHPMailer ou mail()).

        // Exemple d'envoi d'email (requires un serveur mail configuré ou PHPMailer)
        $to = $site_settings['contact_email']; // L'email de destination d'InnovaTech
        $email_subject = "Message de contact InnovaTech - " . htmlspecialchars($subject);
        $email_body = "Nom: " . htmlspecialchars($name) . "\n"
                      . "Email: " . htmlspecialchars($email) . "\n"
                      . "Sujet: " . htmlspecialchars($subject) . "\n\n"
                      . "Message:\n" . htmlspecialchars($message);
        $headers = "From: " . htmlspecialchars($email) . "\r\n" .
                   "Reply-To: " . htmlspecialchars($email) . "\r\n" .
                   "X-Mailer: PHP/" . phpversion();

        // Si l'envoi d'email est réussi (ou simulation)
        // mail($to, $email_subject, $email_body, $headers); // Décommenter pour un vrai envoi

        $_SESSION['success_message'] = "Votre message a été envoyé avec succès. Nous vous répondrons dans les plus brefs délais.";
        unset($_SESSION['old_contact_data']); // Nettoyer les données si succès
        header("Location: contact.php");
        exit();

    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
}

$page_title = "Contactez InnovaTech";
include 'header.php';
?>

<div class="container py-5">
    <h1 class="mb-4 text-center text-primary"><i class="fas fa-envelope me-2"></i> Contactez InnovaTech</h1>
    <p class="lead text-center text-muted">Nous sommes à votre écoute pour toute question, suggestion ou demande d'information.</p>

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
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white text-center">
                    <h4 class="mb-0">Envoyez-nous un message</h4>
                </div>
                <div class="card-body">
                    <form action="contact.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="mb-3">
                            <label for="name" class="form-label">Votre Nom</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($old_contact_data['name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Votre Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($old_contact_data['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="subject" class="form-label">Sujet</label>
                            <input type="text" class="form-control" id="subject" name="subject" value="<?php echo htmlspecialchars($old_contact_data['subject']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="message" class="form-label">Votre Message</label>
                            <textarea class="form-control" id="message" name="message" rows="5" required><?php echo htmlspecialchars($old_contact_data['message']); ?></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-paper-plane me-2"></i> Envoyer le Message</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-success text-white text-center">
                    <h4 class="mb-0">Nos Coordonnées</h4>
                </div>
                <div class="card-body text-center">
                    <h5 class="mb-3"><i class="fas fa-map-marker-alt me-2"></i> Notre Emplacement</h5>
                    <p class="mb-4"><?php echo htmlspecialchars($site_settings['address']); ?></p>

                    <h5 class="mb-3"><i class="fas fa-phone-alt me-2"></i> Appelez-nous</h5>
                    <p class="mb-4"><a href="tel:<?php echo htmlspecialchars($site_settings['contact_phone']); ?>" class="text-decoration-none text-success"><?php echo htmlspecialchars($site_settings['contact_phone']); ?></a></p>

                    <h5 class="mb-3"><i class="fas fa-envelope me-2"></i> Écrivez-nous</h5>
                    <p class="mb-4"><a href="mailto:<?php echo htmlspecialchars($site_settings['contact_email']); ?>" class="text-decoration-none text-success"><?php echo htmlspecialchars($site_settings['contact_email']); ?></a></p>

                    <h5 class="mt-5 mb-3"><i class="fas fa-share-alt me-2"></i> Suivez-nous</h5>
                    <div class="social-icons">
                        <?php if (!empty($site_settings['facebook_url'])): ?>
                            <a href="<?php echo htmlspecialchars($site_settings['facebook_url']); ?>" target="_blank" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($site_settings['twitter_url'])): ?>
                            <a href="<?php echo htmlspecialchars($site_settings['twitter_url']); ?>" target="_blank" title="Twitter"><i class="fab fa-twitter"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($site_settings['linkedin_url'])): ?>
                            <a href="<?php echo htmlspecialchars($site_settings['linkedin_url']); ?>" target="_blank" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                        <?php endif; ?>
                        </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include 'footer.php';
?>