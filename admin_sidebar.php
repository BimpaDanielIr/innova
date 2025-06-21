<?php
/*
// admin_sidebar.php
// Ce fichier est inclus dans les pages d'administration.
// Il ne doit pas démarrer de session ni inclure config.php,
// car ces actions sont gérées par les fichiers parents.

// Détermine le nom du fichier PHP en cours pour marquer l'élément de menu actif.
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav id="sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-cogs"></i> Admin InnovaTech</h3>
        <strong><i class="fas fa-wrench"></i></strong>
    </div>

    <ul class="list-unstyled components">
        <li class="<?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>">
            <a href="admin_dashboard.php">
                <i class="fas fa-tachometer-alt"></i>
                Tableau de Bord
            </a>
        </li>
        <li class="<?php echo (in_array($current_page, ['admin_manage_users.php', 'admin_user_process.php'])) ? 'active' : ''; ?>">
            <a href="#usersSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-users"></i>
                Utilisateurs
            </a>
            <ul class="collapse list-unstyled <?php echo (in_array($current_page, ['admin_manage_users.php', 'admin_user_process.php'])) ? 'show' : ''; ?>" id="usersSubmenu">
                <li>
                    <a href="admin_manage_users.php">Gérer les Utilisateurs</a>
                </li>
                </ul>
        </li>
        <li class="<?php echo (in_array($current_page, ['admin_manage_formations.php', 'admin_formation_process.php', 'admin_edit_formation.php', 'admin_add_formation.php'])) ? 'active' : ''; ?>">
            <a href="#formationsSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-graduation-cap"></i>
                Formations
            </a>
            <ul class="collapse list-unstyled <?php echo (in_array($current_page, ['admin_manage_formations.php', 'admin_formation_process.php', 'admin_edit_formation.php', 'admin_add_formation.php'])) ? 'show' : ''; ?>" id="formationsSubmenu">
                <li>
                    <a href="admin_manage_formations.php">Gérer les Formations</a>
                </li>
                <li>
                    <a href="admin_add_formation.php">Ajouter une Formation</a>
                </li>
            </ul>
        </li>
        <li class="<?php echo (in_array($current_page, ['admin_manage_events.php', 'admin_event_process.php', 'admin_edit_event.php', 'admin_add_event.php'])) ? 'active' : ''; ?>">
            <a href="#eventsSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-calendar-alt"></i>
                Événements
            </a>
            <ul class="collapse list-unstyled <?php echo (in_array($current_page, ['admin_manage_events.php', 'admin_event_process.php', 'admin_edit_event.php', 'admin_add_event.php'])) ? 'show' : ''; ?>" id="eventsSubmenu">
                <li>
                    <a href="admin_manage_events.php">Gérer les Événements</a>
                </li>
                <li>
                    <a href="admin_add_event.php">Ajouter un Événement</a>
                </li>
            </ul>
        </li>
        <li class="<?php echo (in_array($current_page, ['admin_manage_posts.php', 'add_post.php', 'admin_edit_post.php', 'admin_post_process.php'])) ? 'active' : ''; ?>">
            <a href="#postsSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-newspaper"></i>
                Publications
            </a>
            <ul class="collapse list-unstyled <?php echo (in_array($current_page, ['admin_manage_posts.php', 'add_post.php', 'admin_edit_post.php', 'admin_post_process.php'])) ? 'show' : ''; ?>" id="postsSubmenu">
                <li>
                    <a href="admin_manage_posts.php">Gérer les Publications</a>
                </li>
                <li>
                    <a href="add_post.php">Ajouter une Publication</a>
                </li>
            </ul>
        </li>
        <li class="<?php echo ($current_page == 'admin_manage_enrollments.php') ? 'active' : ''; ?>">
            <a href="admin_manage_enrollments.php">
                <i class="fas fa-user-check"></i>
                Inscriptions
            </a>
        </li>
        <li class="<?php echo ($current_page == 'admin_finances.php') ? 'active' : ''; ?>">
            <a href="admin_finances.php">
                <i class="fas fa-dollar-sign"></i>
                Finances
            </a>
        </li>
        <li class="<?php echo ($current_page == 'admin_contacts.php') ? 'active' : ''; ?>">
            <a href="admin_contacts.php">
                <i class="fas fa-envelope"></i>
                Messages Contact
            </a>
        </li>
        <li class="<?php echo ($current_page == 'admin_assistance.php') ? 'active' : ''; ?>">
            <a href="admin_assistance.php">
                <i class="fas fa-life-ring"></i>
                Demandes Assistance
            </a>
        </li>
        <li class="<?php echo ($current_page == 'admin_reports.php') ? 'active' : ''; ?>">
            <a href="admin_reports.php">
                <i class="fas fa-chart-line"></i>
                Rapports
            </a>
        </li>
        <li class="<?php echo ($current_page == 'admin_site_settings.php') ? 'active' : ''; ?>">
            <a href="admin_site_settings.php">
                <i class="fas fa-globe"></i>
                Paramètres du Site
            </a>
        </li>
        <li>
            <a href="auth_process.php?action=logout">
                <i class="fas fa-sign-out-alt"></i>
                Déconnexion
            </a>
        </li>
    </ul>
</nav> */