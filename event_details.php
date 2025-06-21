<?php
// footer.php
// Inclure config.php si des paramètres du site sont nécessaires dans le pied de page
require_once 'config.php';

// Récupérer les paramètres du site pour le pied de page (ex: année de copyright, liens sociaux)
$site_settings = [];
try {
    $stmt = $pdo->query("SELECT setting_name, setting_value FROM site_settings WHERE setting_name IN ('site_name', 'facebook_url', 'twitter_url', 'linkedin_url', 'instagram_url')");
    $settings_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($settings_raw as $row) {
        $site_settings[$row['setting_name']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des paramètres du site pour le footer: " . $e->getMessage());
    // Définir des valeurs par défaut en cas d'erreur
    $site_settings['site_name'] = 'InnovaTech';
    $site_settings['facebook_url'] = '#';
    $site_settings['twitter_url'] = '#';
    $site_settings['linkedin_url'] = '#';
    $site_settings['instagram_url'] = '#'; // Ajoutez Instagram si vous le souhaitez
}

// Assurez-vous que toutes les clés nécessaires existent pour éviter les erreurs d'indice
$site_settings = array_merge([
    'site_name' => 'InnovaTech',
    'facebook_url' => '#',
    'twitter_url' => '#',
    'linkedin_url' => '#',
    'instagram_url' => '#',
], $site_settings);

?>

<footer class="site-footer py-5 mt-auto">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4 mb-md-0">
                <h5 class="footer-heading"><?php echo htmlspecialchars($site_settings['site_name']); ?></h5>
                <p class="footer-text">Votre partenaire pour l'innovation et l'excellence technologique. Nous offrons des formations et des événements pour stimuler la croissance et la connaissance.</p>
            </div>
            <div class="col-md-4 mb-4 mb-md-0">
                <h5 class="footer-heading">Liens Rapides</h5>
                <ul class="list-unstyled footer-links">
                    <li><a href="index.php"><i class="fas fa-angle-right me-2"></i> Accueil</a></li>
                    <li><a href="formations.php"><i class="fas fa-angle-right me-2"></i> Formations</a></li>
                    <li><a href="events.php"><i class="fas fa-angle-right me-2"></i> Événements</a></li>
                    <li><a href="about.php"><i class="fas fa-angle-right me-2"></i> À Propos</a></li>
                    <li><a href="contact.php"><i class="fas fa-angle-right me-2"></i> Contact</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="dashboard.php"><i class="fas fa-angle-right me-2"></i> Mon Tableau de Bord</a></li>
                    <?php else: ?>
                        <li><a href="authentification.php"><i class="fas fa-angle-right me-2"></i> Connexion / Inscription</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="col-md-4">
                <h5 class="footer-heading">Suivez-nous</h5>
                <div class="social-icons-footer mb-3">
                    <?php if (!empty($site_settings['facebook_url']) && $site_settings['facebook_url'] != '#'): ?>
                        <a href="<?php echo htmlspecialchars($site_settings['facebook_url']); ?>" target="_blank" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <?php endif; ?>
                    <?php if (!empty($site_settings['twitter_url']) && $site_settings['twitter_url'] != '#'): ?>
                        <a href="<?php echo htmlspecialchars($site_settings['twitter_url']); ?>" target="_blank" title="Twitter"><i class="fab fa-twitter"></i></a>
                    <?php endif; ?>
                    <?php if (!empty($site_settings['linkedin_url']) && $site_settings['linkedin_url'] != '#'): ?>
                        <a href="<?php echo htmlspecialchars($site_settings['linkedin_url']); ?>" target="_blank" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    <?php endif; ?>
                    <?php if (!empty($site_settings['instagram_url']) && $site_settings['instagram_url'] != '#'): ?>
                        <a href="<?php echo htmlspecialchars($site_settings['instagram_url']); ?>" target="_blank" title="Instagram"><i class="fab fa-instagram"></i></a>
                    <?php endif; ?>
                </div>
                <p class="footer-contact-info">
                    <i class="fas fa-map-marker-alt me-2"></i> 123 Rue de l'Innovation, Ville, Pays<br>
                    <i class="fas fa-envelope me-2"></i> <a href="mailto:info@innovatech.com" class="text-white">info@innovatech.com</a><br>
                    <i class="fas fa-phone-alt me-2"></i> <a href="tel:+1234567890" class="text-white">+123 456 7890</a>
                </p>
            </div>
        </div>
        <hr class="footer-divider">
        <div class="row">
            <div class="col-12 text-center">
                <p class="copyright-text">&copy; <?php echo date("Y"); ?> <?php echo htmlspecialchars($site_settings['site_name']); ?>. Tous droits réservés.</p>
            </div>
        </div>
    </div>
</footer>

<style>
    /* Styles pour le footer */
    .site-footer {
        background: linear-gradient(to right, #0056b3, #007bff); /* Dégradé InnovaTech mais plus foncé */
        color: rgba(255, 255, 255, 0.85);
        padding-top: 50px;
        font-size: 0.95rem;
    }
    .site-footer h5.footer-heading {
        color: #fff;
        font-weight: bold;
        margin-bottom: 25px;
        font-size: 1.3rem;
        position: relative;
    }
    .site-footer h5.footer-heading::after {
        content: '';
        position: absolute;
        left: 0;
        bottom: -10px;
        width: 50px;
        height: 3px;
        background-color: #fff;
        border-radius: 5px;
    }
    .site-footer p.footer-text {
        line-height: 1.8;
    }
    .site-footer .footer-links {
        padding-left: 0;
        list-style: none;
    }
    .site-footer .footer-links li {
        margin-bottom: 10px;
    }
    .site-footer .footer-links a {
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        transition: color 0.3s ease;
        display: flex;
        align-items: center;
    }
    .site-footer .footer-links a:hover {
        color: #fff;
    }
    .site-footer .footer-links a i {
        margin-right: 8px;
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.6);
    }
    .site-footer .social-icons-footer a {
        color: #fff;
        font-size: 1.6rem;
        margin-right: 15px;
        transition: color 0.3s ease, transform 0.3s ease;
    }
    .site-footer .social-icons-footer a:hover {
        color: #e9ecef;
        transform: translateY(-3px);
    }
    .site-footer .footer-contact-info i {
        color: #fff;
        margin-right: 10px;
        min-width: 20px;
    }
    .site-footer .footer-contact-info a {
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        transition: color 0.3s ease;
    }
    .site-footer .footer-contact-info a:hover {
        color: #fff;
    }
    .footer-divider {
        border-color: rgba(255, 255, 255, 0.2);
        margin-top: 40px;
        margin-bottom: 25px;
    }
    .copyright-text {
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.6);
    }
</style>