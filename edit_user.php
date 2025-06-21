<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: authentification.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$database = "innova_tech_db";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_data = null;
$error_message = '';
$success_message = '';
$trainings = [];

// Récupérer la liste des formations actives pour la liste déroulante
$sql_trainings = "SELECT training_id, training_name FROM trainings WHERE is_active = TRUE ORDER BY training_name";
$result_trainings = $conn->query($sql_trainings);
if ($result_trainings) {
    while ($row = $result_trainings->fetch_assoc()) {
        $trainings[] = $row;
    }
} else {
    $error_message .= "Erreur lors de la récupération des formations : " . $conn->error . "<br>";
}


// Récupérer les données de l'utilisateur à modifier
if ($user_id > 0) {
    $stmt = $conn->prepare("SELECT id, username, email, role, training_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
    } else {
        $error_message = "Utilisateur non trouvé.";
    }
    $stmt->close();
} else {
    $error_message = "ID utilisateur non spécifié.";
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && $user_data) {
    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);
    $new_role = $_POST['role'];
    $new_training_id = $_POST['training_id'] ?? null;

    if (empty($new_username) || empty($new_email)) {
        $error_message = "Le nom d'utilisateur et l'email ne peuvent pas être vides.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format d'email invalide.";
    } else {
        // Vérifier si le nouvel email ou nom d'utilisateur n'est pas déjà pris par un autre utilisateur
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt_check->bind_param("ssi", $new_username, $new_email, $user_id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $error_message = "Ce nom d'utilisateur ou cet email est déjà utilisé par un autre compte.";
        } else {
            $sql_update = "UPDATE users SET username = ?, email = ?, role = ?, training_id = ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            
            if ($new_training_id === '') { // Gérer le cas où "Aucune formation" est sélectionnée
                $new_training_id_bind = null;
                $stmt_update->bind_param("sssii", $new_username, $new_email, $new_role, $new_training_id_bind, $user_id);
            } else {
                $stmt_update->bind_param("sssi", $new_username, $new_email, $new_role, $new_training_id, $user_id);
            }

            if ($stmt_update->execute()) {
                $success_message = "Profil utilisateur mis à jour avec succès !";
                // Mettre à jour $user_data pour refléter les changements sans recharger
                $user_data['username'] = $new_username;
                $user_data['email'] = $new_email;
                $user_data['role'] = $new_role;
                $user_data['training_id'] = ($new_training_id === '') ? null : $new_training_id;

            } else {
                $error_message = "Erreur lors de la mise à jour : " . $stmt_update->error;
            }
            $stmt_update->close();
        }
        $stmt_check->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Utilisateur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid container">
            <a class="navbar-brand" href="admin_dashboard.php">Admin Dashboard</a>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="auth_process.php?action=logout">Déconnexion</a></li>
            </ul>
        </div>
    </nav>
    <div class="container mt-5">
        <h1 class="mb-4">Modifier le Profil de l'Utilisateur</h1>
        <a href="admin_dashboard.php#users" class="btn btn-secondary mb-4"><i class="fas fa-arrow-left"></i> Retour à la gestion des Utilisateurs</a>

        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($user_data): ?>
            <div class="card shadow-sm">
                <div class="card-body">
                    <form action="admin_users.php?id=<?php echo $user_id; ?>" method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Nom d'utilisateur</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Rôle</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="user" <?php echo ($user_data['role'] == 'user') ? 'selected' : ''; ?>>Utilisateur</option>
                                <option value="admin" <?php echo ($user_data['role'] == 'admin') ? 'selected' : ''; ?>>Administrateur</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="training_id" class="form-label">Formation assignée</label>
                            <select class="form-select" id="training_id" name="training_id">
                                <option value="">-- Aucune formation --</option>
                                <?php foreach ($trainings as $training): ?>
                                    <option value="<?php echo htmlspecialchars($training['training_id']); ?>"
                                        <?php echo ($user_data['training_id'] == $training['training_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($training['training_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer les modifications</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>