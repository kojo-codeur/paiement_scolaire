<?php
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID invalide']);
    exit();
}

try {
    // Récupérer les informations de l'élève
    $sql = "SELECT e.*, u.nom, u.prenom, u.email, u.telephone, u.created_at 
            FROM eleves e 
            JOIN utilisateurs u ON e.user_id = u.id 
            WHERE e.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $eleve = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$eleve) {
        echo json_encode(['success' => false, 'error' => 'Élève non trouvé']);
        exit();
    }
    
    // Statistiques des paiements
    $sql = "SELECT 
                SUM(CASE WHEN statut = 'valide' THEN montant ELSE 0 END) as total_paye,
                COUNT(*) as nb_paiements
            FROM paiements 
            WHERE eleve_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calcul du total à payer
    $sql = "SELECT SUM(montant) as total_a_payer FROM tranches_paiement WHERE annee_scolaire = ? AND statut = 'actif'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$eleve['annee_scolaire']]);
    $total_a_payer = $stmt->fetch(PDO::FETCH_ASSOC)['total_a_payer'] ?? 0;
    
    $total_paye = $stats['total_paye'] ?? 0;
    $reste_a_payer = $total_a_payer - $total_paye;
    
    echo json_encode([
        'success' => true,
        'eleve' => [
            'id' => $eleve['id'],
            'matricule' => $eleve['matricule'],
            'nom' => $eleve['nom'],
            'prenom' => $eleve['prenom'],
            'email' => $eleve['email'],
            'telephone' => $eleve['telephone'],
            'classe' => $eleve['classe'],
            'niveau' => $eleve['niveau'],
            'annee_scolaire' => $eleve['annee_scolaire'],
            'created_at' => $eleve['created_at']
        ],
        'stats' => [
            'total_paye' => floatval($total_paye),
            'reste_a_payer' => floatval($reste_a_payer),
            'nb_paiements' => intval($stats['nb_paiements'] ?? 0)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>