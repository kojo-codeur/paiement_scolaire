<?php
session_start();
require_once 'includes/db_connect.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $email = $_POST['email'];
    $password = md5($_POST['password']);
    $confirm_password = md5($_POST['confirm_password']);
    $telephone = $_POST['telephone'];
    $role = 'eleve';
    
    // Vérification des mots de passe
    if ($_POST['password'] !== $_POST['confirm_password']) {
        $error = "Les mots de passe ne correspondent pas";
    } else {
        // Vérifier si l'email existe déjà
        $sql = "SELECT id FROM utilisateurs WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = "Cet email est déjà utilisé";
        } else {
            // Insérer l'utilisateur
            $sql = "INSERT INTO utilisateurs (email, password, nom, prenom, telephone, role) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$email, $password, $nom, $prenom, $telephone, $role])) {
                $user_id = $pdo->lastInsertId();
                
                // Générer un matricule unique
                $matricule = 'ELV' . date('Y') . str_pad($user_id, 4, '0', STR_PAD_LEFT);
                
                // Insérer les infos élève
                $sql = "INSERT INTO eleves (user_id, matricule, classe, niveau, annee_scolaire) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$user_id, $matricule, 'Non assignée', 'Non assigné', date('Y') . '-' . (date('Y')+1)]);
                
                $success = "Compte créé avec succès ! Vous pouvez maintenant vous connecter.";
            } else {
                $error = "Erreur lors de la création du compte";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Gestion Scolaire</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-graduation-cap"></i>
                    <h2>Créer un compte</h2>
                </div>
                <p class="login-subtitle">Inscrivez-vous pour accéder à votre espace</p>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <a href="index.php" style="margin-left: 10px;">Se connecter</a>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="login-form">
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="nom">
                                <i class="fas fa-user"></i> Nom
                            </label>
                            <input type="text" id="nom" name="nom" placeholder="Votre nom" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="prenom">
                                <i class="fas fa-user"></i> Prénom
                            </label>
                            <input type="text" id="prenom" name="prenom" placeholder="Votre prénom" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> Adresse email
                    </label>
                    <input type="email" id="email" name="email" placeholder="exemple@ecole.com" required>
                </div>
                
                <div class="form-group">
                    <label for="telephone">
                        <i class="fas fa-phone"></i> Téléphone
                    </label>
                    <input type="tel" id="telephone" name="telephone" placeholder="Votre numéro de téléphone">
                </div>
                
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="password">
                                <i class="fas fa-lock"></i> Mot de passe
                            </label>
                            <input type="password" id="password" name="password" placeholder="••••••••" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="confirm_password">
                                <i class="fas fa-lock"></i> Confirmer
                            </label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-user-plus"></i> S'inscrire
                </button>
            </form>
            
            <div class="login-footer">
                <p>Déjà un compte ? <a href="index.php">Se connecter</a></p>
            </div>
        </div>
    </div>
</body>
</html>