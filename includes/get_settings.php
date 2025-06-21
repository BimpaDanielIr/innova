<?php
// includes/get_settings.php

// Paramètres de connexion à la base de données (assurez-vous qu'ils sont corrects)
$servername = "localhost";
$username = "root";
$password = "";
$database = "innova_tech_db";

$site_settings = []; // Tableau pour stocker tous les paramètres

try {
    $conn_settings = new mysqli($servername, $username, $password, $database);

    if ($conn_settings->connect_error) {
        // En cas d'erreur de connexion, enregistrez l'erreur mais ne bloquez pas le site
        error_log("Erreur de connexion à la base de données pour les paramètres: " . $conn_settings->connect_error);
        
        // Valeurs par défaut si la connexion échoue
        $site_settings['site_name'] = "InnovaTech";
        $site_settings['site_slogan'] = "Votre solution technologique innovante";
        $site_settings['admin_email'] = "contact@innovatech.com";
        $site_settings['contact_phone'] = "+257 68 123 456";
        $site_settings['address'] = "123 Rue de l'Innovation, Bujumbura";
        $site_settings['facebook_url'] = "#";
        $site_settings['twitter_url'] = "#";
        $site_settings['linkedin_url'] = "#";
        $site_settings['about_us_short'] = "InnovaTech est une entreprise leader dans la formation technologique et les services numériques, dédiée à l'innovation et à l'excellence.";

        // Valeurs par défaut pour le pied de page
        $site_settings['footer_contact_email'] = "info@innovatech.com";
        $site_settings['footer_phone_number'] = "+257 68 123 456";
        $site_settings['footer_address'] = "123 Avenue de la Technologie, Bujumbura";
        $site_settings['footer_about_us_text'] = "InnovaTech est votre partenaire dédié à l'innovation technologique au Burundi, offrant des formations de pointe et des solutions numériques sur mesure pour propulser votre succès.";
        $site_settings['social_facebook_url'] = "#";
        $site_settings['social_twitter_url'] = "#";
        $site_settings['social_linkedin_url'] = "#";
        $site_settings['social_instagram_url'] = "#";
        $site_settings['social_youtube_url'] = "#";

        // NOUVEAU : Valeurs par défaut pour la barre de navigation
        $site_settings['nav_link_home_text'] = "Accueil";
        $site_settings['nav_link_home_url'] = "index.php";
        $site_settings['nav_link_formations_text'] = "Formations";
        $site_settings['nav_link_formations_url'] = "inscription_formation.php";
        $site_settings['nav_link_contact_text'] = "Contact";
        $site_settings['nav_link_contact_url'] = "contact.php";
        $site_settings['nav_link_assistance_text'] = "Assistance";
        $site_settings['nav_link_assistance_url'] = "assistance.php";
        $site_settings['nav_cta_button_text'] = "Inscrivez-vous !";
        $site_settings['nav_cta_button_url'] = "inscription_formation.php";

    } else {
        $sql = "SELECT setting_key, setting_value FROM settings";
        $result = $conn_settings->query($sql);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $site_settings[$row['setting_key']] = $row['setting_value'];
            }
            $result->free();
        } else {
            error_log("Erreur lors de la récupération des paramètres depuis la base de données: " . $conn_settings->error);
            
            // Définir des valeurs par défaut en cas d'erreur de requête
            $site_settings['site_name'] = "InnovaTech";
            $site_settings['site_slogan'] = "Votre solution technologique innovante";
            $site_settings['admin_email'] = "contact@innovatech.com";
            $site_settings['contact_phone'] = "+257 68 123 456";
            $site_settings['address'] = "123 Rue de l'Innovation, Bujumbura";
            $site_settings['facebook_url'] = "#";
            $site_settings['twitter_url'] = "#";
            $site_settings['linkedin_url'] = "#";
            $site_settings['about_us_short'] = "InnovaTech est une entreprise leader dans la formation technologique et les services numériques, dédiée à l'innovation et à l'excellence.";

            // Valeurs par défaut pour le pied de page en cas d'erreur de requête
            $site_settings['footer_contact_email'] = "info@innovatech.com";
            $site_settings['footer_phone_number'] = "+257 68 123 456";
            $site_settings['footer_address'] = "123 Avenue de la Technologie, Bujumbura";
            $site_settings['footer_about_us_text'] = "InnovaTech est votre partenaire dédié à l'innovation technologique au Burundi, offrant des formations de pointe et des solutions numériques sur mesure pour propulser votre succès.";
            $site_settings['social_facebook_url'] = "#";
            $site_settings['social_twitter_url'] = "#";
            $site_settings['social_linkedin_url'] = "#";
            $site_settings['social_instagram_url'] = "#";
            $site_settings['social_youtube_url'] = "#";

            // NOUVEAU : Valeurs par défaut pour la barre de navigation en cas d'erreur de requête
            $site_settings['nav_link_home_text'] = "Accueil";
            $site_settings['nav_link_home_url'] = "index.php";
            $site_settings['nav_link_formations_text'] = "Formations";
            $site_settings['nav_link_formations_url'] = "inscription_formation.php";
            $site_settings['nav_link_contact_text'] = "Contact";
            $site_settings['nav_link_contact_url'] = "contact.php";
            $site_settings['nav_link_assistance_text'] = "Assistance";
            $site_settings['nav_link_assistance_url'] = "assistance.php";
            $site_settings['nav_cta_button_text'] = "Inscrivez-vous !";
            $site_settings['nav_cta_button_url'] = "inscription_formation.php";
        }
        $conn_settings->close();
    }
} catch (Exception $e) {
    error_log("Exception lors de la récupération des paramètres: " . $e->getMessage());
    
    // Définir des valeurs par défaut en cas d'exception
    $site_settings['site_name'] = "InnovaTech";
    $site_settings['site_slogan'] = "Votre solution technologique innovante";
    $site_settings['admin_email'] = "contact@innovatech.com";
    $site_settings['contact_phone'] = "+257 68 123 456";
    $site_settings['address'] = "123 Rue de l'Innovation, Bujumbura";
    $site_settings['facebook_url'] = "#";
    $site_settings['twitter_url'] = "#";
    $site_settings['linkedin_url'] = "#";
    $site_settings['about_us_short'] = "InnovaTech est une entreprise leader dans la formation technologique et les services numériques, dédiée à l'innovation et à l'excellence.";

    // Valeurs par défaut pour le pied de page en cas d'exception
    $site_settings['footer_contact_email'] = "info@innovatech.com";
    $site_settings['footer_phone_number'] = "+257 68 123 456";
    $site_settings['footer_address'] = "123 Avenue de la Technologie, Bujumbura";
    $site_settings['footer_about_us_text'] = "InnovaTech est votre partenaire dédié à l'innovation technologique au Burundi, offrant des formations de pointe et des solutions numériques sur mesure pour propulser votre succès.";
    $site_settings['social_facebook_url'] = "#";
    $site_settings['social_twitter_url'] = "#";
    $site_settings['social_linkedin_url'] = "#";
    $site_settings['social_instagram_url'] = "#";
    $site_settings['social_youtube_url'] = "#";

    // NOUVEAU : Valeurs par défaut pour la barre de navigation en cas d'exception
    $site_settings['nav_link_home_text'] = "Accueil";
    $site_settings['nav_link_home_url'] = "index.php";
    $site_settings['nav_link_formations_text'] = "Formations";
    $site_settings['nav_link_formations_url'] = "inscription_formation.php";
    $site_settings['nav_link_contact_text'] = "Contact";
    $site_settings['nav_link_contact_url'] = "contact.php";
    $site_settings['nav_link_assistance_text'] = "Assistance";
    $site_settings['nav_link_assistance_url'] = "assistance.php";
    $site_settings['nav_cta_button_text'] = "Inscrivez-vous !";
    $site_settings['nav_cta_button_url'] = "inscription_formation.php";
}

// Assurez-vous que les clés essentielles existent même si elles ne sont pas en DB ou en cas d'erreur
// Ceci est une "filet de sécurité" final pour toutes les clés
if (!isset($site_settings['site_name'])) $site_settings['site_name'] = "InnovaTech";
if (!isset($site_settings['site_slogan'])) $site_settings['site_slogan'] = "Votre solution technologique innovante";
if (!isset($site_settings['admin_email'])) $site_settings['admin_email'] = "contact@innovatech.com";
if (!isset($site_settings['contact_phone'])) $site_settings['contact_phone'] = "+257 68 123 456";
if (!isset($site_settings['address'])) $site_settings['address'] = "123 Rue de l'Innovation, Bujumbura";
if (!isset($site_settings['facebook_url'])) $site_settings['facebook_url'] = "#";
if (!isset($site_settings['twitter_url'])) $site_settings['twitter_url'] = "#";
if (!isset($site_settings['linkedin_url'])) $site_settings['linkedin_url'] = "#";
if (!isset($site_settings['about_us_short'])) $site_settings['about_us_short'] = "InnovaTech est une entreprise leader dans la formation technologique et les services numériques, dédiée à l'innovation et à l'excellence.";

// Clés pour le pied de page
if (!isset($site_settings['footer_contact_email'])) $site_settings['footer_contact_email'] = "info@innovatech.com";
if (!isset($site_settings['footer_phone_number'])) $site_settings['footer_phone_number'] = "+257 68 123 456";
if (!isset($site_settings['footer_address'])) $site_settings['footer_address'] = "123 Avenue de la Technologie, Bujumbura";
if (!isset($site_settings['footer_about_us_text'])) $site_settings['footer_about_us_text'] = "InnovaTech est votre partenaire dédié à l'innovation technologique au Burundi, offrant des formations de pointe et des solutions numériques sur mesure pour propulser votre succès.";
if (!isset($site_settings['social_facebook_url'])) $site_settings['social_facebook_url'] = "#";
if (!isset($site_settings['social_twitter_url'])) $site_settings['social_twitter_url'] = "#";
if (!isset($site_settings['social_linkedin_url'])) $site_settings['social_linkedin_url'] = "#";
if (!isset($site_settings['social_instagram_url'])) $site_settings['social_instagram_url'] = "#";
if (!isset($site_settings['social_youtube_url'])) $site_settings['social_youtube_url'] = "#";

// NOUVEAU : Clés pour la barre de navigation
if (!isset($site_settings['nav_link_home_text'])) $site_settings['nav_link_home_text'] = "Accueil";
if (!isset($site_settings['nav_link_home_url'])) $site_settings['nav_link_home_url'] = "index.php";
if (!isset($site_settings['nav_link_formations_text'])) $site_settings['nav_link_formations_text'] = "Formations";
if (!isset($site_settings['nav_link_formations_url'])) $site_settings['nav_link_formations_url'] = "inscription_formation.php";
if (!isset($site_settings['nav_link_contact_text'])) $site_settings['nav_link_contact_text'] = "Contact";
if (!isset($site_settings['nav_link_contact_url'])) $site_settings['nav_link_contact_url'] = "contact.php";
if (!isset($site_settings['nav_link_assistance_text'])) $site_settings['nav_link_assistance_text'] = "Assistance";
if (!isset($site_settings['nav_link_assistance_url'])) $site_settings['nav_link_assistance_url'] = "assistance.php";
if (!isset($site_settings['nav_cta_button_text'])) $site_settings['nav_cta_button_text'] = "Inscrivez-vous !";
if (!isset($site_settings['nav_cta_button_url'])) $site_settings['nav_cta_button_url'] = "inscription_formation.php";

// $site_settings contient maintenant tous les paramètres
?>