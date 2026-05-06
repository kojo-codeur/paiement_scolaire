<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if (!estAdmin()) {
    header('Location: index.php');
    exit;
}

$filter_classe = isset($_GET['classe']) ? $_GET['classe'] : '';
$filter_statut = isset($_GET['statut']) ? $_GET['statut'] : '';

$sql = "SELECT DISTINCT classe FROM eleves ORDER BY classe";
$stmt = $pdo->query($sql);
$classes = $stmt->fetchAll();

$sql = "SELECT SUM(montant) as total FROM tranches_paiement WHERE statut = 'actif'";
$stmt = $pdo->query($sql);
$total_annee = $stmt->fetch()['total'] ?? 0;

$sql = "SELECT e.*, u.nom, u.prenom, u.email, u.telephone, u.date_inscription,
        COALESCE((SELECT SUM(montant) FROM paiements WHERE eleve_id = e.id AND statut = 'valide'), 0) as total_paye,
        COALESCE((SELECT COUNT(*) FROM paiements WHERE eleve_id = e.id AND statut = 'valide'), 0) as nb_paiements,
        COALESCE((SELECT COUNT(*) FROM paiements WHERE eleve_id = e.id AND statut = 'en_attente'), 0) as nb_attente,
        COALESCE((SELECT COUNT(*) FROM paiements WHERE eleve_id = e.id AND statut = 'rejete'), 0) as nb_rejete,
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

$eleves_filtres = $all_eleves;
if ($filter_statut == 'a_jour') {
    $eleves_filtres = array_filter($all_eleves, function($e) use ($total_annee) {
        return $e['total_paye'] >= $total_annee;
    });
} elseif ($filter_statut == 'impaye') {
    $eleves_filtres = array_filter($all_eleves, function($e) use ($total_annee) {
        return $e['total_paye'] < $total_annee;
    });
} elseif ($filter_statut == 'partiel') {
    $eleves_filtres = array_filter($all_eleves, function($e) use ($total_annee) {
        return $e['total_paye'] > 0 && $e['total_paye'] < $total_annee;
    });
} elseif ($filter_statut == 'jamais_paye') {
    $eleves_filtres = array_filter($all_eleves, function($e) {
        return $e['total_paye'] == 0;
    });
}

$total_eleves = count($all_eleves);
$total_paye_global = array_sum(array_column($all_eleves, 'total_paye'));
$total_attente_global = array_sum(array_column($all_eleves, 'nb_attente'));
$total_rejete_global = array_sum(array_column($all_eleves, 'nb_rejete'));

$eleves_a_jour = 0;
$eleves_impayes = 0;
$eleves_partiel = 0;
$eleves_jamais_paye = 0;

foreach($all_eleves as $eleve) {
    $paye = $eleve['total_paye'];
    if ($paye >= $total_annee) {
        $eleves_a_jour++;
    } elseif ($paye > 0 && $paye < $total_annee) {
        $eleves_partiel++;
    } else {
        $eleves_jamais_paye++;
    }
}
$eleves_impayes = $eleves_partiel + $eleves_jamais_paye;

$montant_total_attendu = $total_annee * $total_eleves;
$taux_recouvrement = $montant_total_attendu > 0 ? round(($total_paye_global / $montant_total_attendu) * 100, 2) : 0;
?>


<div class="rapport-header">
    <div class="row-premium align-items-center">
        <div class="col-premium-8">
            <h2 style="color: white; margin-bottom: 0.5rem;">
                <i class="fas fa-chart-bar"></i> Rapports & Statistiques
            </h2>
            <p style="opacity: 0.9;">Bilan complet des paiements et situation des élèves</p>
        </div>
        <div class="col-premium-4 text-end">
            <div class="small" style="opacity: 0.8;">Date du rapport :</div>
            <div class="fw-bold"><?php echo date('d/m/Y'); ?></div>
        </div>
    </div>
    
    <div class="rapport-stats">
        <div class="rapport-stat-card" onclick="filterByStatus('all')">
            <div class="stat-value" style="font-size: 1.5rem; font-weight: 700;"><?php echo $total_eleves; ?></div>
            <div class="small">Total élèves</div>
        </div>
        <div class="rapport-stat-card" onclick="filterByStatus('a_jour')">
            <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; color: #10b981;"><?php echo $eleves_a_jour; ?></div>
            <div class="small">À jour</div>
        </div>
        <div class="rapport-stat-card" onclick="filterByStatus('partiel')">
            <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; color: #f59e0b;"><?php echo $eleves_partiel; ?></div>
            <div class="small">Paiement partiel</div>
        </div>
        <div class="rapport-stat-card" onclick="filterByStatus('jamais_paye')">
            <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; color: #ef4444;"><?php echo $eleves_jamais_paye; ?></div>
            <div class="small">Jamais payé</div>
        </div>
        <div class="rapport-stat-card">
            <div class="stat-value" style="font-size: 1.5rem; font-weight: 700;"><?php echo number_format($total_paye_global, 0, ',', ' '); ?> USD</div>
            <div class="small">Total encaissé</div>
        </div>
        <div class="rapport-stat-card">
            <div class="stat-value" style="font-size: 1.5rem; font-weight: 700;"><?php echo $taux_recouvrement; ?>%</div>
            <div class="small">Taux recouvrement</div>
        </div>
    </div>
</div>

<div class="rapport-actions">
    <button onclick="generatePDFRapport('all')" class="btn-rapport btn-rapport-pdf">
        <i class="fas fa-file-pdf"></i> Tous les élèves
    </button>
    <button onclick="generatePDFRapport('a_jour')" class="btn-rapport btn-rapport-pdf" style="background: #10b981;">
        <i class="fas fa-file-pdf"></i> Élèves à jour
    </button>
    <button onclick="generatePDFRapport('impaye')" class="btn-rapport btn-rapport-pdf" style="background: #ef4444;">
        <i class="fas fa-file-pdf"></i> Élèves impayés
    </button>
    <button onclick="exportToExcel()" class="btn-rapport btn-rapport-excel">
        <i class="fas fa-file-excel"></i> Exporter Excel
    </button>
    <button onclick="window.print()" class="btn-rapport btn-rapport-print">
        <i class="fas fa-print"></i> Imprimer
    </button>
</div>

<div class="filter-bar">
    <form method="GET" class="row-premium" id="filterForm">
        <input type="hidden" name="page" value="rapports">
        
        <div class="col-premium-4">
            <select name="classe" class="form-control-premium" onchange="this.form.submit()">
                <option value="">Toutes les classes</option>
                <?php foreach($classes as $classe): ?>
                    <option value="<?php echo $classe['classe']; ?>" <?php echo $filter_classe == $classe['classe'] ? 'selected' : ''; ?>>
                        <?php echo $classe['classe']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-premium-4">
            <select name="statut" class="form-control-premium" onchange="this.form.submit()">
                <option value="">Tous les statuts</option>
                <option value="a_jour" <?php echo $filter_statut == 'a_jour' ? 'selected' : ''; ?>>À jour</option>
                <option value="impaye" <?php echo $filter_statut == 'impaye' ? 'selected' : ''; ?>>Impayés</option>
                <option value="partiel" <?php echo $filter_statut == 'partiel' ? 'selected' : ''; ?>>Paiement partiel</option>
                <option value="jamais_paye" <?php echo $filter_statut == 'jamais_paye' ? 'selected' : ''; ?>>Jamais payé</option>
            </select>
        </div>
        
        <div class="col-premium-4">
            <button type="submit" class="btn-premium btn-premium-primary w-100">
                <i class="fas fa-search"></i> Filtrer
            </button>
        </div>
    </form>
</div>

<div class="summary-card">
    <div class="summary-title">
        <i class="fas fa-chart-line"></i> Bilan financier global
    </div>
    <div class="row-premium">
        <div class="col-premium-3">
            <div class="small text-muted">Objectif annuel</div>
            <div class="h4 fw-bold"><?php echo number_format($montant_total_attendu, 0, ',', ' '); ?> USD</div>
        </div>
        <div class="col-premium-3">
            <div class="small text-muted">Déjà encaissé</div>
            <div class="h4 fw-bold text-success"><?php echo number_format($total_paye_global, 0, ',', ' '); ?> USD</div>
        </div>
        <div class="col-premium-3">
            <div class="small text-muted">Reste à recouvrer</div>
            <div class="h4 fw-bold text-danger"><?php echo number_format($montant_total_attendu - $total_paye_global, 0, ',', ' '); ?> USD</div>
        </div>
        <div class="col-premium-3">
            <div class="small text-muted">Taux de recouvrement</div>
            <div class="h4 fw-bold"><?php echo $taux_recouvrement; ?>%</div>
        </div>
    </div>
    <div class="progress-bar-global">
        <div class="progress-fill-global" style="width: <?php echo $taux_recouvrement; ?>%"></div>
    </div>
</div>

<div class="card-premium">
    <div class="card-header-premium">
        <h3><i class="fas fa-users"></i> Situation des élèves</h3>
        <div>
            <span class="statut-badge a-jour">
                <i class="fas fa-check-circle"></i> À jour: <?php echo $eleves_a_jour; ?>
            </span>
            <span class="statut-badge partiel ms-2">
                <i class="fas fa-chart-line"></i> Partiel: <?php echo $eleves_partiel; ?>
            </span>
            <span class="statut-badge jamais ms-2">
                <i class="fas fa-times-circle"></i> Jamais: <?php echo $eleves_jamais_paye; ?>
            </span>
        </div>
    </div>
    <div class="card-body-premium">
        <?php if(count($eleves_filtres) > 0): ?>
            <div class="table-responsive-premium">
                <table class="data-table-premium" id="rapportTable">
                    <thead>
                        <tr>
                            <th>Matricule</th>
                            <th>Nom complet</th>
                            <th>Classe</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Total payé</th>
                            <th>Reste à payer</th>
                            <th>% payé</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($eleves_filtres as $eleve): 
                            $paye = $eleve['total_paye'];
                            $reste = $total_annee - $paye;
                            $pourcentage = $total_annee > 0 ? round(($paye / $total_annee) * 100, 2) : 0;
                            $estAJour = $paye >= $total_annee;
                            $estPartiel = $paye > 0 && $paye < $total_annee;
                            $estJamais = $paye == 0;
                        ?>
                            <tr class="eleve-row">
                                <td><?php echo $eleve['matricule']; ?></td>
                                <td><strong><?php echo htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom']); ?></strong></td>
                                <td><?php echo $eleve['classe']; ?></td>
                                <td><?php echo $eleve['email']; ?></td>
                                <td><?php echo $eleve['telephone'] ?? '-'; ?></td>
                                <td class="text-success fw-bold"><?php echo number_format($paye, 0, ',', ' '); ?> USD </td>
                                <td class="text-danger fw-bold"><?php echo number_format($reste, 0, ',', ' '); ?> USD </td>
                                <td>
                                    <div class="progress" style="height: 6px; width: 80px;">
                                        <div class="progress-bar" style="width: <?php echo $pourcentage; ?>%; background: <?php echo $estAJour ? '#10b981' : ($estPartiel ? '#f59e0b' : '#ef4444'); ?>"></div>
                                    </div>
                                    <span class="small"><?php echo $pourcentage; ?>%</span>
                                </td>
                                <td>
                                    <?php if($estAJour): ?>
                                        <span class="statut-badge a-jour">
                                            <i class="fas fa-check-circle"></i> À jour
                                        </span>
                                    <?php elseif($estPartiel): ?>
                                        <span class="statut-badge partiel">
                                            <i class="fas fa-chart-line"></i> Partiel
                                        </span>
                                    <?php else: ?>
                                        <span class="statut-badge jamais">
                                            <i class="fas fa-times-circle"></i> Jamais payé
                                        </span>
                                    <?php endif; ?>
                                 </td>
                                <td>
                                    <button onclick="viewEleveDetails(<?php echo $eleve['id']; ?>)" class="btn-action" title="Voir détails">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="generateElevePDF(<?php echo $eleve['id']; ?>)" class="btn-action pdf" title="PDF individuel">
                                        <i class="fas fa-file-pdf"></i>
                                    </button>
                                 </td>
                             </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: var(--gray-50); font-weight: bold;">
                            <td colspan="5" class="text-end">TOTAUX :</td>
                            <td class="text-success"><?php echo number_format(array_sum(array_column($eleves_filtres, 'total_paye')), 0, ',', ' '); ?> USD</td>
                            <td class="text-danger"><?php echo number_format(($total_annee * count($eleves_filtres)) - array_sum(array_column($eleves_filtres, 'total_paye')), 0, ',', ' '); ?> USD</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state-premium">
                <i class="fas fa-users"></i>
                <p>Aucun élève trouvé</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
<script>
function filterByStatus(status) {
    const form = document.getElementById('filterForm');
    const statutSelect = form.querySelector('select[name="statut"]');
    if (status === 'all') {
        statutSelect.value = '';
    } else {
        statutSelect.value = status;
    }
    form.submit();
}

function exportToExcel() {
    const table = document.getElementById('rapportTable');
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.table_to_sheet(table);
    XLSX.utils.book_append_sheet(wb, ws, 'Rapport_Paiements');
    XLSX.writeFile(wb, 'rapport_paiements_' + new Date().toISOString().slice(0,10) + '.xlsx');
    showToast('Export Excel effectué', 'success');
}

function generatePDFRapport(type) {
    let classe = '<?php echo $filter_classe; ?>';
    let url = 'generate_rapport_pdf.php?type=' + type + '&classe=' + encodeURIComponent(classe);
    window.open(url, '_blank');
}

function generateElevePDF(eleveId) {
    window.open('generate_eleve_pdf.php?id=' + eleveId, '_blank');
}

function viewEleveDetails(eleveId) {
    window.location.href = '?page=eleves&view=' + eleveId;
}
</script>