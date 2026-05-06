<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID facture manquant']);
    exit;
}

$facture_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Vérifier que l'utilisateur a accès à cette facture
if ($role == 'eleve') {
    $eleve = getEleveByUserId($user_id, $pdo);
    $sql = "SELECT f.*, e.matricule, e.classe, e.annee_scolaire,
            u.nom, u.prenom, u.email, u.telephone,
            t.libelle as tranche,
            p.reference_bordereau, p.date_paiement
            FROM factures f 
            JOIN eleves e ON f.eleve_id = e.id 
            JOIN utilisateurs u ON e.user_id = u.id 
            LEFT JOIN paiements p ON f.paiement_id = p.id 
            LEFT JOIN tranches_paiement t ON p.tranche_id = t.id 
            WHERE f.id = ? AND f.eleve_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$facture_id, $eleve['id']]);
} else {
    $sql = "SELECT f.*, e.matricule, e.classe, e.annee_scolaire,
            u.nom, u.prenom, u.email, u.telephone,
            t.libelle as tranche,
            p.reference_bordereau, p.date_paiement
            FROM factures f 
            JOIN eleves e ON f.eleve_id = e.id 
            JOIN utilisateurs u ON e.user_id = u.id 
            LEFT JOIN paiements p ON f.paiement_id = p.id 
            LEFT JOIN tranches_paiement t ON p.tranche_id = t.id 
            WHERE f.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$facture_id]);
}

$facture = $stmt->fetch();

if (!$facture) {
    echo json_encode(['success' => false, 'error' => 'Facture non trouvée']);
    exit;
}

echo json_encode([
    'success' => true,
    'facture' => $facture,
    'eleve' => [
        'nom' => $facture['nom'],
        'prenom' => $facture['prenom'],
        'matricule' => $facture['matricule'],
        'classe' => $facture['classe'],
        'email' => $facture['email']
    ]
]);
?>