<?php

require_once 'includes/functions.php';
require_once 'includes/Mailer.php';

$message = '';
$error = '';

$filter_statut = isset($_GET['statut']) ? $_GET['statut'] : '';
$filter_date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$filter_date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';
$filter_eleve = isset($_GET['eleve']) ? $_GET['eleve'] : '';


if (isset($_GET['download'])) {
    $id = intval($_GET['download']);
    $sql = "SELECT pdf_path, numero_facture FROM factures WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $facture = $stmt->fetch();
    
    if ($facture && $facture['pdf_path'] && file_exists('../' . $facture['pdf_path'])) {
        $file = '../' . $facture['pdf_path'];
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="facture_' . $facture['numero_facture'] . '.pdf"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    } else {
        $error = "Fichier PDF non trouvé";
    }
}

if (isset($_GET['resend'])) {
    $id = intval($_GET['resend']);
    
    $sql = "SELECT f.*, e.matricule, e.classe, e.annee_scolaire,
            u.nom, u.prenom, u.email, u.telephone,
            t.libelle as tranche_libelle
            FROM factures f 
            JOIN eleves e ON f.eleve_id = e.id 
            JOIN utilisateurs u ON e.user_id = u.id 
            LEFT JOIN paiements p ON f.paiement_id = p.id 
            LEFT JOIN tranches_paiement t ON p.tranche_id = t.id 
            WHERE f.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $facture = $stmt->fetch();
    
    if ($facture) {
        $factureData = [
            'numero_facture' => $facture['numero_facture'],
            'montant_total' => $facture['montant_total'],
            'montant_paye' => $facture['montant_paye'],
            'montant_restant' => $facture['montant_restant'],
            'matricule' => $facture['matricule'],
            'classe' => $facture['classe'],
            'annee_scolaire' => $facture['annee_scolaire'],
            'details' => [
                [
                    'description' => $facture['tranche_libelle'] ?? 'Frais de scolarité',
                    'prix_unitaire' => $facture['montant_total'],
                    'quantite' => 1,
                    'total' => $facture['montant_total']
                ]
            ]
        ];
        
        $file_path = '../' . $facture['pdf_path'];
        if (!file_exists($file_path)) {
            $file_path = null;
        }
        
        $email_sent = sendFactureEmail(
            $facture['email'],
            $facture['prenom'] . ' ' . $facture['nom'],
            $factureData,
            $file_path
        );
        
        if ($email_sent) {
            $sql = "UPDATE factures SET envoye_email = 1 WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $message = "Facture renvoyée par email avec succès";
        } else {
            $error = "Erreur lors de l'envoi de l'email";
        }
    }
}

if (isset($_GET['generer']) && isset($_GET['paiement_id'])) {
    $paiement_id = intval($_GET['paiement_id']);
    
    $sql = "SELECT id FROM factures WHERE paiement_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$paiement_id]);
    if (!$stmt->fetch()) {
        $result = creerFactureApresValidation($paiement_id, $pdo);
        if ($result) {
            $message = "Facture générée avec succès";
        } else {
            $error = "Erreur lors de la génération de la facture";
        }
    } else {
        $error = "Une facture existe déjà pour ce paiement";
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    $sql = "SELECT pdf_path FROM factures WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $facture = $stmt->fetch();
    
    if ($facture && $facture['pdf_path'] && file_exists('../' . $facture['pdf_path'])) {
        unlink('../' . $facture['pdf_path']);
    }
    
    $sql = "DELETE FROM factures WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$id])) {
        $message = "Facture supprimée avec succès";
    } else {
        $error = "Erreur lors de la suppression";
    }
}

$sql = "SELECT f.*, e.matricule, e.classe, u.nom, u.prenom, u.email, u.telephone,
        t.libelle as tranche, p.reference_bordereau, p.date_paiement
        FROM factures f 
        JOIN eleves e ON f.eleve_id = e.id 
        JOIN utilisateurs u ON e.user_id = u.id 
        LEFT JOIN paiements p ON f.paiement_id = p.id 
        LEFT JOIN tranches_paiement t ON p.tranche_id = t.id 
        WHERE 1=1";

$params = [];

if ($filter_statut == 'envoye') {
    $sql .= " AND f.envoye_email = 1";
} elseif ($filter_statut == 'non_envoye') {
    $sql .= " AND f.envoye_email = 0";
} elseif ($filter_statut == 'payee') {
    $sql .= " AND f.montant_restant = 0";
} elseif ($filter_statut == 'partielle') {
    $sql .= " AND f.montant_restant > 0";
}

if ($filter_date_debut) {
    $sql .= " AND DATE(f.date_emission) >= ?";
    $params[] = $filter_date_debut;
}

if ($filter_date_fin) {
    $sql .= " AND DATE(f.date_emission) <= ?";
    $params[] = $filter_date_fin;
}

if ($filter_eleve) {
    $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR e.matricule LIKE ?)";
    $search = "%$filter_eleve%";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

$sql .= " ORDER BY f.date_emission DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$factures = $stmt->fetchAll();

$sql = "SELECT 
            COUNT(*) as total,
            SUM(montant_total) as total_montant,
            SUM(montant_paye) as total_paye,
            SUM(montant_restant) as total_restant,
            COUNT(CASE WHEN envoye_email = 1 THEN 1 END) as emails_envoyes,
            COUNT(CASE WHEN montant_restant = 0 THEN 1 END) as factures_payees,
            COUNT(CASE WHEN pdf_path IS NOT NULL THEN 1 END) as pdf_generes
        FROM factures";
$stmt = $pdo->query($sql);
$stats = $stmt->fetch();
?>


<div class="stats-factures">
    <div class="stat-facture-card">
        <div class="stat-facture-icon"><i class="fas fa-file-invoice"></i></div>
        <div class="stat-facture-value"><?php echo $stats['total']; ?></div>
        <div class="small text-muted">Total factures</div>
    </div>
    <div class="stat-facture-card">
        <div class="stat-facture-icon"><i class="fas fa-money-bill-wave"></i></div>
        <div class="stat-facture-value"><?php echo number_format($stats['total_montant'] ?? 0, 0, ',', ' '); ?> USD</div>
        <div class="small text-muted">Montant total</div>
    </div>
    <div class="stat-facture-card">
        <div class="stat-facture-icon"><i class="fas fa-check-circle" style="color: var(--success);"></i></div>
        <div class="stat-facture-value"><?php echo $stats['factures_payees']; ?></div>
        <div class="small text-muted">Factures payées</div>
    </div>
    <div class="stat-facture-card">
        <div class="stat-facture-icon"><i class="fas fa-file-pdf" style="color: #dc2626;"></i></div>
        <div class="stat-facture-value"><?php echo $stats['pdf_generes']; ?></div>
        <div class="small text-muted">PDF générés</div>
    </div>
    <div class="stat-facture-card">
        <div class="stat-facture-icon"><i class="fas fa-envelope" style="color: #10b981;"></i></div>
        <div class="stat-facture-value"><?php echo $stats['emails_envoyes']; ?></div>
        <div class="small text-muted">Emails envoyés</div>
    </div>
</div>

<div class="filter-bar">
    <form method="GET" class="row-premium">
        <input type="hidden" name="page" value="factures">
        <div class="col-premium-2">
            <select name="statut" class="form-control-premium">
                <option value="">Tous les statuts</option>
                <option value="envoye" <?php echo $filter_statut == 'envoye' ? 'selected' : ''; ?>>Email envoyé</option>
                <option value="non_envoye" <?php echo $filter_statut == 'non_envoye' ? 'selected' : ''; ?>>Email non envoyé</option>
                <option value="payee" <?php echo $filter_statut == 'payee' ? 'selected' : ''; ?>>Facture payée</option>
                <option value="partielle" <?php echo $filter_statut == 'partielle' ? 'selected' : ''; ?>>Paiement partiel</option>
            </select>
        </div>
        <div class="col-premium-2">
            <input type="date" name="date_debut" class="form-control-premium" value="<?php echo $filter_date_debut; ?>">
        </div>
        <div class="col-premium-2">
            <input type="date" name="date_fin" class="form-control-premium" value="<?php echo $filter_date_fin; ?>">
        </div>
        <div class="col-premium-3">
            <input type="text" name="eleve" class="form-control-premium" value="<?php echo htmlspecialchars($filter_eleve); ?>" placeholder="Rechercher un élève...">
        </div>
        <div class="col-premium-3">
            <button type="submit" class="btn-premium btn-premium-primary"><i class="fas fa-search"></i> Filtrer</button>
            <button type="button" class="btn-premium btn-premium-success" onclick="exportFacturesToExcel()"><i class="fas fa-file-excel"></i> Exporter</button>
        </div>
    </form>
</div>

<div class="card-premium">
    <div class="card-header-premium">
        <h3><i class="fas fa-list"></i> Liste des factures</h3>
        <div>
            <span class="status-badge-premium valide"><i class="fas fa-check-circle"></i> <?php echo $stats['total'] - $stats['factures_payees']; ?> payées</span>
        </div>
    </div>
    <div class="card-body-premium">
        <?php if($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if(count($factures) > 0): ?>
            <div class="table-responsive-premium">
                <table class="data-table-premium" id="facturesTable">
                    <thead>
                        <tr>
                            <th>N° Facture</th><th>Élève</th><th>Matricule</th><th>Classe</th><th>Tranche</th>
                            <th>Montant</th><th>Payé</th><th>Reste</th><th>Date</th><th>Statut</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($factures as $facture): 
                            $reste = $facture['montant_total'] - $facture['montant_paye'];
                            $estPayee = $reste <= 0;
                        ?>
                            <tr class="facture-row">
                                <td><span class="facture-number"><?php echo htmlspecialchars($facture['numero_facture']); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($facture['prenom'] . ' ' . $facture['nom']); ?></strong><div class="small text-muted"><?php echo htmlspecialchars($facture['email']); ?></div></td>
                                <td><?php echo htmlspecialchars($facture['matricule']); ?></td>
                                <td><?php echo htmlspecialchars($facture['classe']); ?></td>
                                <td><?php echo htmlspecialchars($facture['tranche'] ?? 'Frais divers'); ?></td>
                                <td class="amount-cell text-primary"><?php echo number_format($facture['montant_total'], 0, ',', ' '); ?> USD</td>
                                <td class="amount-cell text-success"><?php echo number_format($facture['montant_paye'], 0, ',', ' '); ?> USD</td>
                                <td class="amount-cell <?php echo $estPayee ? 'text-success' : 'text-danger'; ?>"><?php echo number_format($facture['montant_restant']); ?> USD</td>
                                <td><?php echo date('d/m/Y', strtotime($facture['date_emission'])); ?></td>
                                <td>
                                    <?php if($estPayee): ?>
                                        <span class="status-badge-premium valide"><i class="fas fa-check-circle"></i> Payée</span>
                                    <?php else: ?>
                                        <span class="status-badge-premium en_attente"><i class="fas fa-clock"></i> Partielle</span>
                                    <?php endif; ?>
                                    <?php if($facture['envoye_email']): ?>
                                        <div class="small text-success mt-1"><i class="fas fa-envelope"></i> Email envoyé</div>
                                    <?php else: ?>
                                        <div class="small text-warning mt-1"><i class="fas fa-clock"></i> Email non envoyé</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons-facture">
                                        <?php if($facture['pdf_path'] && file_exists('../' . $facture['pdf_path'])): ?>
                                            <a href="<?php echo $facture['pdf_path']; ?>" target="_blank" class="btn-action pdf" title="Voir PDF"><i class="fas fa-file-pdf"></i></a>
                                            <a href="?page=factures&download=<?php echo $facture['id']; ?>" class="btn-action" title="Télécharger"><i class="fas fa-download"></i></a>
                                        <?php else: ?>
                                            <a href="?page=factures&generer=1&paiement_id=<?php echo $facture['paiement_id']; ?>" class="btn-action pdf" title="Générer PDF"><i class="fas fa-file-pdf"></i></a>
                                        <?php endif; ?>
                                        <button onclick="viewFacture(<?php echo $facture['id']; ?>)" class="btn-action" title="Aperçu"><i class="fas fa-eye"></i></button>
                                        <?php if(!$facture['envoye_email'] && $facture['pdf_path']): ?>
                                            <a href="?page=factures&resend=<?php echo $facture['id']; ?>" class="btn-action email" title="Renvoyer par email" onclick="return confirm('Renvoyer cette facture par email ?')"><i class="fas fa-envelope"></i></a>
                                        <?php endif; ?>
                                        <button onclick="printFacture(<?php echo $facture['id']; ?>)" class="btn-action print" title="Imprimer"><i class="fas fa-print"></i></button>
                                        <a href="?page=factures&delete=<?php echo $facture['id']; ?>" class="btn-action delete" title="Supprimer" onclick="return confirm('Supprimer cette facture ?')"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state-premium">
                <i class="fas fa-file-invoice"></i>
                <p>Aucune facture trouvée</p>
                <p class="text-muted">Les factures apparaîtront après validation des paiements</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="factureModal" class="modal-premium">
    <div class="modal-content-premium modal-large">
        <div class="modal-header-premium">
            <h3><i class="fas fa-file-invoice"></i> Aperçu de la facture</h3>
            <span class="close" onclick="closeFactureModal()">&times;</span>
        </div>
        <div class="modal-body-premium" id="factureContent" style="max-height: 70vh; overflow-y: auto; text-align:center; padding:2rem;">
            <i class="fas fa-spinner fa-spin"></i> Chargement...
        </div>
        <div class="modal-footer-premium">
            <button class="btn-premium btn-premium-primary" onclick="printModalFacture()"><i class="fas fa-print"></i> Imprimer</button>
            <button class="btn-premium btn-premium-secondary" onclick="closeFactureModal()">Fermer</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
<script>
function exportFacturesToExcel() {
    const table = document.getElementById('facturesTable');
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.table_to_sheet(table);
    XLSX.utils.book_append_sheet(wb, ws, 'Factures');
    XLSX.writeFile(wb, 'factures_' + new Date().toISOString().slice(0,10) + '.xlsx');
    if(typeof showToast === 'function') showToast('Export Excel effectué', 'success');
    else alert('Export Excel effectué');
}

function printFacture(id) {
    window.open('print_facture.php?id=' + id, '_blank', 'width=800,height=600');
}

function viewFacture(id) {
    document.getElementById('factureContent').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Chargement...';
    document.getElementById('factureModal').classList.add('show');
    fetch('get_facture_content.php?id=' + id)
        .then(response => response.text())
        .then(html => { document.getElementById('factureContent').innerHTML = html; })
        .catch(error => { document.getElementById('factureContent').innerHTML = '<div class="text-danger">Erreur de chargement</div>'; });
}

function closeFactureModal() {
    document.getElementById('factureModal').classList.remove('show');
}

function printModalFacture() {
    const content = document.getElementById('factureContent').innerHTML;
    const win = window.open('', '_blank');
    win.document.write('<html><head><title>Facture</title><link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"><style>body{font-family:"Inter",sans-serif;padding:20px;}</style></head><body>' + content + '</body></html>');
    win.document.close();
    win.print();
}
</script>