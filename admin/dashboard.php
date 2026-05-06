<?php
$stats = [];

$sql = "SELECT COUNT(*) as total FROM eleves";
$stmt = $pdo->query($sql);
$stats['eleves'] = $stmt->fetch()['total'];

$sql = "SELECT COUNT(*) as total FROM paiements WHERE statut = 'en_attente'";
$stmt = $pdo->query($sql);
$stats['en_attente'] = $stmt->fetch()['total'];

$sql = "SELECT SUM(montant) as total FROM paiements 
        WHERE statut = 'valide' AND MONTH(date_validation) = MONTH(CURDATE()) 
        AND YEAR(date_validation) = YEAR(CURDATE())";
$stmt = $pdo->query($sql);
$stats['encaissements_mois'] = $stmt->fetch()['total'] ?? 0;

$sql = "SELECT SUM(montant) as total FROM paiements 
        WHERE statut = 'valide' AND MONTH(date_validation) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
        AND YEAR(date_validation) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
$stmt = $pdo->query($sql);
$stats['encaissements_mois_precedent'] = $stmt->fetch()['total'] ?? 0;

if ($stats['encaissements_mois_precedent'] > 0) {
    $stats['evolution_encaissement'] = round((($stats['encaissements_mois'] - $stats['encaissements_mois_precedent']) / $stats['encaissements_mois_precedent']) * 100, 1);
} else {
    $stats['evolution_encaissement'] = $stats['encaissements_mois'] > 0 ? 100 : 0;
}

$sql = "SELECT 
            COUNT(CASE WHEN statut = 'valide' THEN 1 END) as valides,
            COUNT(*) as total
        FROM paiements";
$stmt = $pdo->query($sql);
$paiement_stats = $stmt->fetch();
$stats['taux_paiement'] = $paiement_stats['total'] > 0 ? round(($paiement_stats['valides'] / $paiement_stats['total']) * 100) : 0;

$sql = "SELECT 
            DATE_FORMAT(date_validation, '%Y-%m') as mois,
            SUM(montant) as total
        FROM paiements 
        WHERE statut = 'valide' 
        AND date_validation >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
        GROUP BY DATE_FORMAT(date_validation, '%Y-%m')
        ORDER BY mois ASC";
$stmt = $pdo->query($sql);
$paiements_mensuels = $stmt->fetchAll();

$mois_labels = [];
$montants_data = [];
$mois_noms = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juillet', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];

for ($i = 11; $i >= 0; $i--) {
    $date = new DateTime("-$i months");
    $mois_key = $date->format('Y-m');
    $mois_label = $mois_noms[$date->format('n') - 1];
    $mois_labels[] = $mois_label;
    
    $found = false;
    foreach ($paiements_mensuels as $pm) {
        if ($pm['mois'] == $mois_key) {
            $montants_data[] = floatval($pm['total']);
            $found = true;
            break;
        }
    }
    if (!$found) {
        $montants_data[] = 0;
    }
}

$sql = "SELECT 
            statut,
            COUNT(*) as nombre,
            SUM(montant) as total
        FROM paiements
        GROUP BY statut";
$stmt = $pdo->query($sql);
$stats_par_statut = [];
while ($row = $stmt->fetch()) {
    $stats_par_statut[$row['statut']] = [
        'nombre' => $row['nombre'],
        'total' => $row['total']
    ];
}

$sql = "SELECT 
            COUNT(DISTINCT eleve_id) as total_eleves_payeurs,
            COUNT(CASE WHEN MONTH(date_validation) = MONTH(CURDATE()) THEN 1 END) as nouveaux_payeurs_mois
        FROM paiements 
        WHERE statut = 'valide'";
$stmt = $pdo->query($sql);
$audience_stats = $stmt->fetch();

$stats['ctr'] = $stats['taux_paiement'];

$sql = "SELECT 
            COUNT(*) as total_mois_precedent
        FROM paiements 
        WHERE statut = 'valide' 
        AND MONTH(date_validation) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
$stmt = $pdo->query($sql);
$mois_precedent = $stmt->fetch()['total_mois_precedent'] ?? 0;
$mois_actuel = $paiement_stats['valides'];
$stats['croissance_audience'] = $mois_precedent > 0 ? round((($mois_actuel - $mois_precedent) / $mois_precedent) * 100) : 0;

$sql = "SELECT 
            classe,
            COUNT(*) as nombre
        FROM eleves
        GROUP BY classe
        ORDER BY nombre DESC
        LIMIT 5";
$stmt = $pdo->query($sql);
$top_classes = $stmt->fetchAll();

$total_eleves = $stats['eleves'];
foreach ($top_classes as &$classe) {
    $classe['pourcentage'] = $total_eleves > 0 ? round(($classe['nombre'] / $total_eleves) * 100) : 0;
}

$sql = "SELECT p.*, e.matricule, e.classe, u.nom, u.prenom, t.libelle as tranche 
        FROM paiements p 
        JOIN eleves e ON p.eleve_id = e.id 
        JOIN utilisateurs u ON e.user_id = u.id 
        JOIN tranches_paiement t ON p.tranche_id = t.id 
        WHERE p.statut = 'valide'
        ORDER BY p.date_paiement DESC LIMIT 6";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$derniers_paiements = $stmt->fetchAll();

$stats['operations'] = $paiement_stats['valides'] + $stats['en_attente'];
$stats['data_transfer'] = round($stats['operations'] * 0.512, 1);
$stats['data_total'] = 512.0; 
$stats['operations_total'] = 1000;
?>

<div class="stats-trendtide">
    <div class="stat-trendtide-card">
        <div class="stat-trendtide-header">
            <span class="stat-trendtide-title">CLICK-THROUGH RATE (CTR)</span>
            <div class="stat-trendtide-icon">
                <i class="fas fa-mouse-pointer"></i>
            </div>
        </div>
        <div class="stat-trendtide-value"><?php echo $stats['ctr']; ?>%</div>
        <div class="stat-trendtide-change up">
            <i class="fas fa-arrow-up"></i> +5.2% vs mois dernier
        </div>
    </div>
    
    <div class="stat-trendtide-card">
        <div class="stat-trendtide-header">
            <span class="stat-trendtide-title">AUDIENCE GROWTH</span>
            <div class="stat-trendtide-icon">
                <i class="fas fa-chart-line"></i>
            </div>
        </div>
        <div class="stat-trendtide-value">
            <?php echo $stats['croissance_audience'] >= 0 ? '+' : ''; ?><?php echo $stats['croissance_audience']; ?>%
        </div>
        <div class="stat-trendtide-change <?php echo $stats['croissance_audience'] >= 0 ? 'up' : 'down'; ?>">
            <i class="fas fa-arrow-<?php echo $stats['croissance_audience'] >= 0 ? 'up' : 'down'; ?>"></i>
            vs mois dernier
        </div>
    </div>
    
    <div class="stat-trendtide-card">
        <div class="stat-trendtide-header">
            <span class="stat-trendtide-title">FOLLOWERS</span>
            <div class="stat-trendtide-icon">
                <i class="fas fa-users"></i>
            </div>
        </div>
        <div class="stat-trendtide-value"><?php echo number_format($stats['eleves']); ?></div>
        <div class="stat-trendtide-change up">
            <i class="fas fa-arrow-up"></i> <?php echo $stats['croissance_audience']; ?>% cette semaine
        </div>
    </div>
    
    <div class="stat-trendtide-card">
        <div class="stat-trendtide-header">
            <span class="stat-trendtide-title">LIKES (PAIEMENTS)</span>
            <div class="stat-trendtide-icon">
                <i class="fas fa-heart"></i>
            </div>
        </div>
        <div class="stat-trendtide-value"><?php echo number_format($paiement_stats['valides']); ?></div>
        <div class="stat-trendtide-change up">
            <i class="fas fa-arrow-up"></i> +32% ce mois
        </div>
    </div>
</div>


<div class="row-premium">
    <div class="col-premium-6">
        <div class="card-premium">
            <div class="card-header-premium">
                <h3><i class="fas fa-trophy"></i> Top classes</h3>
                <a href="?page=eleves" class="show-all-link">Voir tout →</a>
            </div>
            <div class="card-body-premium">
                <table class="top-classes-table">
                    <?php foreach($top_classes as $index => $classe): ?>
                    <tr>
                        <td width="40">
                            <span class="rank-badge"><?php echo $index + 1; ?></span>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($classe['classe']); ?></strong>
                            <div class="small text-muted"><?php echo $classe['nombre']; ?> élèves</div>
                        </td>
                        <td class="text-end">
                            <span class="fw-bold text-primary"><?php echo $classe['pourcentage']; ?>%</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-premium-6">
        <div class="card-premium">
            <div class="card-header-premium">
                <h3><i class="fas fa-clock"></i> Derniers paiements</h3>
                <a href="?page=validation" class="show-all-link">Voir tout →</a>
            </div>
            <div class="card-body-premium">
                <div class="table-responsive-premium">
                    <table class="data-table-premium">
                        <thead>
                            <tr>
                                <th>Élève</th>
                                <th>Classe</th>
                                <th>Montant</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($derniers_paiements as $p): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($p['prenom'] . ' ' . $p['nom']); ?></strong></td>
                                <td><?php echo htmlspecialchars($p['classe']); ?></td>
                                <td class="fw-bold text-primary"><?php echo number_format($p['montant'], 0, ',', ' '); ?> USD</td>
                                <td><?php echo date('d/m/Y', strtotime($p['date_paiement'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>

const paymentChartData = {
    labels: <?php echo json_encode($mois_labels); ?>,
    datasets: [{
        label: 'Montant (USD)',
        data: <?php echo json_encode($montants_data); ?>,
        borderColor: '#4361ee',
        backgroundColor: 'rgba(67, 97, 238, 0.05)',
        borderWidth: 2,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#4361ee',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 4,
        pointHoverRadius: 6
    }]
};

const statusChartData = {
    labels: ['Validés', 'En attente', 'Rejetés'],
    datasets: [{
        data: [
            <?php echo $stats_par_statut['valide']['nombre'] ?? 0; ?>,
            <?php echo $stats_par_statut['en_attente']['nombre'] ?? 0; ?>,
            <?php echo $stats_par_statut['rejete']['nombre'] ?? 0; ?>
        ],
        backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
        borderWidth: 0,
        hoverOffset: 10
    }]
};

document.addEventListener('DOMContentLoaded', function() {

    const paymentCtx = document.getElementById('paymentChart');
    
    if(paymentCtx) {
        new Chart(paymentCtx, {
            type: 'line',
            data: paymentChartData,
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 10 } },
                    tooltip: { 
                        callbacks: { 
                            label: function(context) { 
                                return context.dataset.label + ': ' + new Intl.NumberFormat('fr-FR').format(context.raw) + ' USD'; 
                            } 
                        } 
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        grid: { color: '#e2e8f0' }, 
                        ticks: { 
                            callback: function(value) { 
                                return new Intl.NumberFormat('fr-FR').format(value) + ' USD';
                            } 
                        } 
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }
    
    const statusCtx = document.getElementById('statusChart');
    if(statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: statusChartData,
            options: { 
                responsive: true, 
                maintainAspectRatio: true, 
                plugins: { legend: { display: false } }, 
                cutout: '65%' 
            }
        });
    }
});

function exportChartData() {
    const data = {
        labels: <?php echo json_encode($mois_labels); ?>,
        values: <?php echo json_encode($montants_data); ?>
    };
    const blob = new Blob([JSON.stringify(data, null, 2)], {type: 'application/json'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'paiements_data.json';
    a.click();
    URL.revokeObjectURL(url);
    showToast('Export JSON effectué', 'success');
}

function exportLocationData() {
    const data = <?php echo json_encode($top_classes); ?>;
    const blob = new Blob([JSON.stringify(data, null, 2)], {type: 'application/json'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'classes_data.json';
    a.click();
    URL.revokeObjectURL(url);
    showToast('Export JSON effectué', 'success');
}
</script>