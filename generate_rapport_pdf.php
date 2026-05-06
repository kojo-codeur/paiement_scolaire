<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
require_once 'includes/tcpdf/tcpdf.php';

if (!estAdmin()) {
    header('Location: index.php');
    exit;
}

// Récupérer les paramètres
$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_classe = isset($_GET['classe']) ? $_GET['classe'] : '';

// Calcul du total à payer
$sql_total = "SELECT SUM(montant) as total FROM tranches_paiement WHERE statut = 'actif'";
$stmt_total = $pdo->query($sql_total);
$total_annee = $stmt_total->fetch()['total'] ?? 0;

// Récupérer les données selon le type
$sql = "SELECT e.*, u.nom, u.prenom, u.email, u.telephone,
        COALESCE((SELECT SUM(montant) FROM paiements WHERE eleve_id = e.id AND statut = 'valide'), 0) as total_paye,
        COALESCE((SELECT COUNT(*) FROM paiements WHERE eleve_id = e.id AND statut = 'valide'), 0) as nb_paiements,
        (SELECT MAX(date_paiement) FROM paiements WHERE eleve_id = e.id AND statut = 'valide') as dernier_paiement
        FROM eleves e
        JOIN utilisateurs u ON e.user_id = u.id
        WHERE 1=1";

$params = [];

if ($filter_classe) {
    $sql .= " AND e.classe = ?";
    $params[] = $filter_classe;
}

$sql .= " ORDER BY e.classe, u.nom ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$all_eleves = $stmt->fetchAll();

// Filtrer selon le type
$eleves = [];
foreach($all_eleves as $eleve) {
    $paye = $eleve['total_paye'];
    if ($type == 'a_jour' && $paye >= $total_annee) {
        $eleves[] = $eleve;
    } elseif ($type == 'impaye' && $paye < $total_annee) {
        $eleves[] = $eleve;
    } elseif ($type == 'partiel' && $paye > 0 && $paye < $total_annee) {
        $eleves[] = $eleve;
    } elseif ($type == 'jamais_paye' && $paye == 0) {
        $eleves[] = $eleve;
    } elseif ($type == 'all') {
        $eleves[] = $eleve;
    }
}

// Statistiques
$total_eleves = count($eleves);
$total_paye_global = array_sum(array_column($eleves, 'total_paye'));
$total_attendu = $total_annee * $total_eleves;
$taux_recouvrement = $total_attendu > 0 ? round(($total_paye_global / $total_attendu) * 100, 2) : 0;

// Titre du rapport
$titre_rapport = "RAPPORT DES PAIEMENTS";
$sous_titre = "";
switch($type) {
    case 'a_jour':
        $sous_titre = "Élèves à jour de leurs paiements";
        break;
    case 'impaye':
        $sous_titre = "Élèves en situation d'impayé";
        break;
    case 'partiel':
        $sous_titre = "Élèves avec paiement partiel";
        break;
    case 'jamais_paye':
        $sous_titre = "Élèves n'ayant jamais payé";
        break;
    default:
        $sous_titre = "Tous les élèves";
}

// Classe PDF personnalisée
class PDF_Rapport extends TCPDF {
    public function Header() {
        $this->SetY(10);
        $this->SetFont('helvetica', 'B', 16);
        $this->SetTextColor(67, 97, 238);
        $this->Cell(0, 10, 'ECOLE DE GESTION SCOLAIRE', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->SetY(20);
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 5, 'RAPPORT DES PAIEMENTS', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(10);
        $this->SetDrawColor(67, 97, 238);
        $this->SetLineWidth(0.5);
        $this->Line(15, 30, 195, 30);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'Généré le ' . date('d/m/Y à H:i') . ' - Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

$pdf = new PDF_Rapport('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Ecole Gestion Scolaire');
$pdf->SetAuthor('Administration');
$pdf->SetTitle('Rapport des paiements - ' . $sous_titre);
$pdf->SetMargins(15, 40, 15);
$pdf->SetAutoPageBreak(TRUE, 20);
$pdf->AddPage();

$html = '
<style>
    .title-main { font-size: 16px; font-weight: bold; color: #1a1a2e; text-align: center; margin-bottom: 5px; }
    .title-sub { font-size: 12px; color: #666; text-align: center; margin-bottom: 15px; }
    .info-box { background: #f8f9fa; padding: 10px; border-radius: 8px; margin-bottom: 15px; }
    .stat-grid { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
    .stat-card { background: #f0f2f5; padding: 10px; border-radius: 8px; text-align: center; flex: 1; min-width: 100px; }
    .stat-value { font-size: 16px; font-weight: bold; }
    .stat-label { font-size: 9px; color: #666; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th { background: #4361ee; color: white; padding: 8px; text-align: left; font-size: 10px; }
    td { padding: 6px; border-bottom: 1px solid #ddd; font-size: 9px; }
    .text-success { color: #10b981; }
    .text-danger { color: #ef4444; }
    .text-warning { color: #f59e0b; }
    .badge-success { background: #e8f5e9; color: #2e7d32; padding: 2px 8px; border-radius: 12px; font-size: 8px; display: inline-block; }
    .badge-warning { background: #fff3e0; color: #ed6c02; padding: 2px 8px; border-radius: 12px; font-size: 8px; display: inline-block; }
    .badge-danger { background: #ffebee; color: #c62828; padding: 2px 8px; border-radius: 12px; font-size: 8px; display: inline-block; }
    .footer-note { margin-top: 20px; text-align: center; font-size: 8px; color: #999; }
</style>

<div class="title-main">RAPPORT DES PAIEMENTS</div>
<div class="title-sub">' . $sous_titre . '</div>

<div class="info-box">
    <table style="width: 100%;">
        <tr>
            <td><strong>Date du rapport:</strong> ' . date('d/m/Y à H:i') . '</td>
            <td><strong>Année scolaire:</strong> ' . date('Y') . '-' . (date('Y')+1) . '</td>
        </tr>
        <tr>
            <td><strong>Classe:</strong> ' . ($filter_classe ?: 'Toutes les classes') . '</td>
            <td><strong>Total élèves:</strong> ' . $total_eleves . ' élèves</td>
        </tr>
    </table>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-value">' . $total_eleves . '</div>
        <div class="stat-label">Total élèves</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">' . number_format($total_paye_global, 0, ',', ' ') . ' USD</div>
        <div class="stat-label">Total encaissé</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">' . number_format($total_attendu, 0, ',', ' ') . ' USD</div>
        <div class="stat-label">Montant attendu</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">' . number_format($total_attendu - $total_paye_global, 0, ',', ' ') . ' USD</div>
        <div class="stat-label">Reste à payer</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">' . $taux_recouvrement . '%</div>
        <div class="stat-label">Taux recouvrement</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>N°</th>
            <th>Matricule</th>
            <th>Nom complet</th>
            <th>Classe</th>
            <th>Email</th>
            <th>Téléphone</th>
            <th>Total payé</th>
            <th>Reste à payer</th>
            <th>% payé</th>
            <th>Statut</th>
        </tr>
    </thead>
    <tbody>';

$i = 1;
foreach($eleves as $eleve) {
    $paye = $eleve['total_paye'];
    $reste = $total_annee - $paye;
    $pourcentage = $total_annee > 0 ? round(($paye / $total_annee) * 100, 2) : 0;
    
    if ($paye >= $total_annee) {
        $badge = '<span class="badge-success">✓ À jour</span>';
    } elseif ($paye > 0) {
        $badge = '<span class="badge-warning">⚠ Partiel</span>';
    } else {
        $badge = '<span class="badge-danger">✗ Jamais payé</span>';
    }
    
    $html .= '
        <tr>
            <td>' . $i . '</td>
            <td>' . $eleve['matricule'] . '</td>
            <td>' . strtoupper($eleve['prenom'] . ' ' . $eleve['nom']) . '</td>
            <td>' . $eleve['classe'] . '</td>
            <td>' . $eleve['email'] . '</td>
            <td>' . ($eleve['telephone'] ?? '-') . '</td>
            <td class="text-success">' . number_format($paye, 0, ',', ' ') . ' USD</td>
            <td class="text-danger">' . number_format($reste, 0, ',', ' ') . ' USD</td>
            <td>' . $pourcentage . '%</td>
            <td>' . $badge . '</td>
        </tr>';
    $i++;
}

$html .= '
    </tbody>
</table>

<div class="footer-note">
    <p>Rapport généré automatiquement par le système de gestion scolaire.</p>
    <p>École de Gestion Scolaire - Tel: +1 XX XX XX XX - Email: contact@ecole.com</p>
</div>';

$pdf->writeHTML($html, true, false, true, false, '');

// Nom du fichier selon le type
$filename = 'rapport_';
switch($type) {
    case 'a_jour': $filename .= 'eleves_a_jour'; break;
    case 'impaye': $filename .= 'eleves_impayes'; break;
    case 'partiel': $filename .= 'eleves_paiement_partiel'; break;
    case 'jamais_paye': $filename .= 'eleves_jamais_paye'; break;
    default: $filename .= 'complet';
}
$filename .= '_' . date('Y-m-d') . '.pdf';

$pdf->Output($filename, 'I');
?>