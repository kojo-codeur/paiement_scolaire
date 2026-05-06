<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
require_once 'includes/tcpdf/tcpdf.php';

if (!estAdmin()) {
    header('Location: index.php');
    exit;
}

$eleve_id = isset($_GET['id']) ? $_GET['id'] : 0;

$sql = "SELECT e.*, u.nom, u.prenom, u.email, u.telephone, u.date_inscription
        FROM eleves e
        JOIN utilisateurs u ON e.user_id = u.id
        WHERE e.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$eleve_id]);
$eleve = $stmt->fetch();

if (!$eleve) {
    die('Élève non trouvé');
}

$sql = "SELECT p.*, t.libelle as tranche_libelle
        FROM paiements p
        JOIN tranches_paiement t ON p.tranche_id = t.id
        WHERE p.eleve_id = ? AND p.statut = 'valide'
        ORDER BY p.date_paiement DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$eleve_id]);
$paiements = $stmt->fetchAll();

$sql_total = "SELECT SUM(montant) as total FROM tranches_paiement WHERE statut = 'actif'";
$stmt_total = $pdo->query($sql_total);
$total_annee = $stmt_total->fetch()['total'] ?? 0;

$total_paye = array_sum(array_column($paiements, 'montant'));
$reste_a_payer = $total_annee - $total_paye;
$pourcentage = $total_annee > 0 ? round(($total_paye / $total_annee) * 100, 2) : 0;
$estAJour = $total_paye >= $total_annee;

class PDF_Eleve extends TCPDF {
    public function Header() {
        $this->SetY(10);
        $this->SetFont('helvetica', 'B', 16);
        $this->SetTextColor(67, 97, 238);
        $this->Cell(0, 10, 'ECOLE DE GESTION SCOLAIRE', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->SetY(20);
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 5, 'FICHE INDIVIDUELLE DE PAIEMENT', 0, false, 'C', 0, '', 0, false, 'M', 'M');
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

$pdf = new PDF_Eleve('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Ecole Gestion Scolaire');
$pdf->SetAuthor('Administration');
$pdf->SetTitle('Fiche élève - ' . $eleve['prenom'] . ' ' . $eleve['nom']);
$pdf->SetMargins(15, 40, 15);
$pdf->SetAutoPageBreak(TRUE, 20);
$pdf->AddPage();

$html = '
<style>
    .title { font-size: 14px; font-weight: bold; color: #4361ee; margin-bottom: 10px; }
    .info-grid { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
    .info-card { background: #f8f9fa; padding: 10px; border-radius: 8px; flex: 1; min-width: 200px; }
    .info-label { font-size: 9px; color: #666; }
    .info-value { font-size: 12px; font-weight: bold; }
    .statut-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 10px; font-weight: bold; }
    .badge-success { background: #e8f5e9; color: #2e7d32; }
    .badge-danger { background: #ffebee; color: #c62828; }
    .progress-bar { height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden; margin: 10px 0; }
    .progress-fill { height: 100%; background: linear-gradient(90deg, #4361ee, #10b981); border-radius: 4px; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th { background: #4361ee; color: white; padding: 8px; text-align: left; font-size: 10px; }
    td { padding: 6px; border-bottom: 1px solid #ddd; font-size: 9px; }
    .text-success { color: #10b981; }
    .text-danger { color: #ef4444; }
    .total-box { background: #f0f2f5; padding: 10px; border-radius: 8px; margin-top: 15px; text-align: right; }
</style>

<div class="title">INFORMATIONS DE L\'ÉLÈVE</div>
<div class="info-grid">
    <div class="info-card">
        <div class="info-label">Nom complet</div>
        <div class="info-value">' . strtoupper($eleve['prenom'] . ' ' . $eleve['nom']) . '</div>
    </div>
    <div class="info-card">
        <div class="info-label">Matricule</div>
        <div class="info-value">' . $eleve['matricule'] . '</div>
    </div>
    <div class="info-card">
        <div class="info-label">Classe</div>
        <div class="info-value">' . $eleve['classe'] . '</div>
    </div>
    <div class="info-card">
        <div class="info-label">Email</div>
        <div class="info-value">' . $eleve['email'] . '</div>
    </div>
    <div class="info-card">
        <div class="info-label">Téléphone</div>
        <div class="info-value">' . ($eleve['telephone'] ?? 'Non renseigné') . '</div>
    </div>
    <div class="info-card">
        <div class="info-label">Année scolaire</div>
        <div class="info-value">' . $eleve['annee_scolaire'] . '</div>
    </div>
</div>

<div class="title">SITUATION FINANCIÈRE</div>
<div class="info-grid">
    <div class="info-card">
        <div class="info-label">Total à payer</div>
        <div class="info-value">' . number_format($total_annee, 0, ',', ' ') . ' USD</div>
    </div>
    <div class="info-card">
        <div class="info-label">Total payé</div>
        <div class="info-value text-success">' . number_format($total_paye, 0, ',', ' ') . ' USD</div>
    </div>
    <div class="info-card">
        <div class="info-label">Reste à payer</div>
        <div class="info-value text-danger">' . number_format($reste_a_payer, 0, ',', ' ') . ' USD</div>
    </div>
    <div class="info-card">
        <div class="info-label">Taux de paiement</div>
        <div class="info-value">' . $pourcentage . '%</div>
    </div>
</div>

<div class="progress-bar">
    <div class="progress-fill" style="width: ' . $pourcentage . '%;"></div>
</div>

<div style="text-align: center; margin: 10px 0;">
    ' . ($estAJour ? '<span class="statut-badge badge-success">✓ ÉLÈVE À JOUR DE SES PAIEMENTS</span>' : '<span class="statut-badge badge-danger">⚠ ÉLÈVE EN SITUATION D\'IMPAYÉ</span>') . '
</div>';

if(count($paiements) > 0) {
    $html .= '
    <div class="title">HISTORIQUE DES PAIEMENTS</div>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Tranche</th>
                <th>Référence</th>
                <th>Montant</th>
                <th>Date validation</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach($paiements as $paiement) {
        $html .= '
            <tr>
                <td>' . date('d/m/Y', strtotime($paiement['date_paiement'])) . '</td>
                <td>' . $paiement['tranche_libelle'] . '</td>
                <td>' . $paiement['reference_bordereau'] . '</td>
                <td class="text-success">' . number_format($paiement['montant'], 0, ',', ' ') . ' USD</td>
                <td>' . date('d/m/Y', strtotime($paiement['date_validation'])) . '</td>
            </tr>';
    }
    
    $html .= '
        </tbody>
    </table>';
} else {
    $html .= '<div style="text-align: center; padding: 20px; color: #999;">Aucun paiement enregistré</div>';
}

$html .= '
<div class="total-box">
    <strong>Montant total payé :</strong> ' . number_format($total_paye, 0, ',', ' ') . ' USD<br>
    <strong>Reste à payer :</strong> ' . number_format($reste_a_payer, 0, ',', ' ') . ' USD
</div>

<div style="margin-top: 20px; text-align: center; font-size: 8px; color: #999;">
    <p>Document généré automatiquement par le système de gestion scolaire.</p>
    <p>École de Gestion Scolaire - Tel: +1 XX XX XX XX - Email: contact@ecole.com</p>
</div>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('fiche_eleve_' . $eleve['matricule'] . '_' . date('Y-m-d') . '.pdf', 'I');
?>