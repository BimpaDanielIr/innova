<?php
session_start();

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: authentification.php");
    exit();
}

// Paramètres de connexion à la base de données
$servername = "localhost";
$username = "root";
$password = "";
$database = "innova_tech_db";

$conn = null;
$settings = [];
$message = '';
$message_type = ''; // 'success' ou 'danger'

try {
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        throw new Exception("Erreur de connexion à la base de données : " . $conn->connect_error);
    }

    // Traitement de la soumission du formulaire
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_settings'])) {
        // Préparer la requête UPDATE
        $stmt_update = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        if ($stmt_update === false) {
            throw new Exception("Erreur de préparation de la requête de mise à jour : " . $conn->error);
        }

        foreach ($_POST as $key => $value) {
            // Ne traiter que les champs qui correspondent à des clés de paramètres
            if (strpos($key, 'setting_') === 0) {
                $setting_key = substr($key, 8); // Extrait la clé réelle ('site_name' de 'setting_site_name')
                $setting_value = trim($value); // Supprime les espaces blancs

                // Si c'est un champ de type URL de réseau social ou URL de navigation et qu'il est vide, le rendre '#' par défaut
                if ((strpos($setting_key, 'social_') === 0 || strpos($setting_key, '_url') !== false) && empty($setting_value)) {
                    $setting_value = '#';
                }
                
                $stmt_update->bind_param("ss", $setting_value, $setting_key);
                if (!$stmt_update->execute()) {
                    throw new Exception("Erreur lors de la mise à jour du paramètre '" . htmlspecialchars($setting_key) . "' : " . $stmt_update->error);
                }
            }
        }
        $stmt_update->close();
        $message = "Les paramètres ont été mis à jour avec succès !";
        $message_type = "success";
    }

    // Récupération de tous les paramètres existants pour l'affichage
    $sql_select_settings = "SELECT setting_key, setting_value, description FROM settings ORDER BY setting_key ASC";
    $result_settings = $conn->query($sql_select_settings);

    if ($result_settings) {
        while ($row = $result_settings->fetch_assoc()) {
            $settings[] = $row;
        }
        $result_settings->free();
    } else {
        throw new Exception("Erreur lors de la récupération des paramètres : " . $conn->error);
    }

} catch (Exception $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = "danger";
    error_log("Erreur dans settings_admin.php : " . $e->getMessage());
} finally {
    if ($conn instanceof mysqli && $conn->ping()) {
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Paramètres du Site</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            display: flex;
            min-height: 100vh;
            flex-direction: column;
        }
        .wrapper {
            display: flex;
            flex: 1;
        }
        #sidebar {
            min-width: 250px;
            max-width: 250px;
            background: #343a40;
            color: #fff;
            transition: all 0.3s;
        }
        #sidebar.active {
            margin-left: -250px;
        }
        #sidebar .sidebar-header {
            padding: 20px;
            background: #343a40;
        }
        #sidebar ul.components {
            padding: 20px 0;
            border-bottom: 1px solid #47748b;
        }
        #sidebar ul p {
            color: #fff;
            padding: 10px;
        }
        #sidebar ul li a {
            padding: 10px;
            font-size: 1.1em;
            display: block;
            color: #fff;
            text-decoration: none;
        }
        #sidebar ul li a:hover {
            color: #343a40;
            background: #fff;
        }
        #content {
            flex: 1;
            padding: 20px;
        }
        @media (max-width: 768px) {
            #sidebar {
                margin-left: -250px;
            }
            #sidebar.active {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <nav id="sidebar">
            <div class="sidebar-header">
                <h3>Tableau de Bord Admin</h3>
            </div>
            <ul class="list-unstyled components">
                <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="manage_users.php"><i class="fas fa-users"></i> Gérer les Utilisateurs</a></li>
                <li><a href="manage_posts.php"><i class="fas fa-newspaper"></i> Gérer les Actualités/Posts</a></li>
                <li class="active"><a href="settings_admin.php"><i class="fas fa-cogs"></i> Paramètres du Site</a></li>
                <li><a href="manage_events.php"><i class="fas fa-calendar-alt"></i> Gérer les Événements</a></li>
                <li><a href="auth_process.php?action=logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
            </ul>
        </nav>

        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-info">
                        <i class="fas fa-align-left"></i>
                        <span>Menu</span>
                    </button>
                    <h2 class="ms-3">Paramètres du Site</h2>
                </div>
            </nav>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> mt-3" role="alert">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="card mt-4">
                <div class="card-header bg-primary text-white">
                    Modifier les Paramètres du Site
                </div>
                <div class="card-body">
                    <form method="POST" action="settings_admin.php">
                        <input type="hidden" name="update_settings" value="1">
                        <?php if (!empty($settings)): ?>
                            <?php 
                            // Catégorisation pour un affichage plus clair
                            $general_settings = [];
                            $footer_contact_settings = [];
                            $social_media_settings = [];
                            $nav_settings = []; // NOUVEAU
                            $other_settings = [];

                            foreach ($settings as $setting) {
                                if (in_array($setting['setting_key'], ['site_name', 'site_slogan', 'about_us_short'])) {
                                    $general_settings[] = $setting;
                                } elseif (in_array($setting['setting_key'], ['footer_contact_email', 'footer_phone_number', 'footer_address', 'footer_about_us_text'])) {
                                    $footer_contact_settings[] = $setting;
                                } elseif (strpos($setting['setting_key'], 'social_') === 0) {
                                    $social_media_settings[] = $setting;
                                } elseif (strpos($setting['setting_key'], 'nav_') === 0) { // NOUVEAU
                                    $nav_settings[] = $setting;
                                } 
                                else {
                                    $other_settings[] = $setting;
                                }
                            }

                            // Fonction utilitaire pour générer le champ de formulaire
                            function generate_setting_field($setting) {
                                $input_type = 'text';
                                $label = htmlspecialchars(ucwords(str_replace(['_url', '_'], [' URL', ' '], $setting['setting_key'])));
                                
                                // Ajuster le type d'input pour les URLs
                                if (strpos($setting['setting_key'], '_url') !== false) {
                                    $input_type = 'url';
                                }
                                // Ajuster le type d'input pour les emails
                                if (strpos($setting['setting_key'], '_email') !== false) {
                                    $input_type = 'email';
                                }
                                // Utiliser textarea pour les descriptions plus longues
                                if (strpos($setting['setting_key'], '_text') !== false || strpos($setting['setting_key'], '_short') !== false) {
                                    echo '<div class="mb-3">';
                                    echo '<label for="setting_' . htmlspecialchars($setting['setting_key']) . '" class="form-label fw-bold">';
                                    echo $label . ' <small class="text-muted">(' . htmlspecialchars($setting['description']) . ')</small></label>';
                                    echo '<textarea class="form-control" id="setting_' . htmlspecialchars($setting['setting_key']) . '" name="setting_' . htmlspecialchars($setting['setting_key']) . '" rows="3">' . htmlspecialchars($setting['setting_value']) . '</textarea>';
                                    echo '</div>';
                                } else {
                                    echo '<div class="mb-3">';
                                    echo '<label for="setting_' . htmlspecialchars($setting['setting_key']) . '" class="form-label fw-bold">';
                                    echo $label . ' <small class="text-muted">(' . htmlspecialchars($setting['description']) . ')</small></label>';
                                    echo '<input type="' . $input_type . '" class="form-control" id="setting_' . htmlspecialchars($setting['setting_key']) . '" name="setting_' . htmlspecialchars($setting['setting_key']) . '" value="' . htmlspecialchars($setting['setting_value']) . '">';
                                    echo '</div>';
                                }
                            }
                            ?>

                            <h4 class="mt-4 mb-3">Informations Générales du Site</h4>
                            <?php foreach ($general_settings as $setting): ?>
                                <?php generate_setting_field($setting); ?>
                            <?php endforeach; ?>

                            <h4 class="mt-5 mb-3">Paramètres de la Barre de Navigation</h4>
                            <p class="text-muted small">Modifiez le texte et l'URL des liens de navigation, y compris le bouton d'appel à l'action.</p>
                            <?php foreach ($nav_settings as $setting): ?>
                                <?php generate_setting_field($setting); ?>
                            <?php endforeach; ?>

                            <h4 class="mt-5 mb-3">Informations de Contact (Pied de Page)</h4>
                            <?php foreach ($footer_contact_settings as $setting): ?>
                                <?php generate_setting_field($setting); ?>
                            <?php endforeach; ?>

                            <h4 class="mt-5 mb-3">Liens Réseaux Sociaux (Pied de Page)</h4>
                            <?php foreach ($social_media_settings as $setting): ?>
                                <?php generate_setting_field($setting); ?>
                            <?php endforeach; ?>

                            <?php if (!empty($other_settings)): ?>
                                <h4 class="mt-5 mb-3">Autres Paramètres</h4>
                                <?php foreach ($other_settings as $setting): ?>
                                    <?php generate_setting_field($setting); ?>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <button type="submit" class="btn btn-success mt-4">Enregistrer les Paramètres</button>
                        <?php else: ?>
                            <p class="text-muted">Aucun paramètre trouvé dans la base de données. Veuillez exécuter les requêtes SQL d'insertion.</p>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // Script pour le basculement de la barre latérale
        document.getElementById('sidebarCollapse').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>