<?php
$user_id = $_SESSION['user_id'];
$eleve = getEleveByUserId($user_id, $pdo);

// Récupérer toutes les factures de l'élève
$sql = "SELECT f.*, p.reference_bordereau, p.date_paiement, p.statut as paiement_statut, t.libelle as tranche 
        FROM factures f 
        LEFT JOIN paiements p ON f.paiement_id = p.id 
        LEFT JOIN tranches_paiement t ON p.tranche_id = t.id 
        WHERE f.eleve_id = ? 
        ORDER BY f.date_emission DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$eleve['id']]);
$factures = $stmt->fetchAll();

// Calcul des totaux
$total_factures = count($factures);
$total_montant = array_sum(array_column($factures, 'montant_total'));
$total_paye = array_sum(array_column($factures, 'montant_paye'));
$total_restant = $total_montant - $total_paye;
$factures_payees = count(array_filter($factures, function($f) { 
    return ($f['montant_total'] - $f['montant_paye']) <= 0; 
}));

// Filtres
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$filter_date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';

// Appliquer les filtres
$factures_filtrees = $factures;
if ($filter_status) {
    if ($filter_status == 'payee') {
        $factures_filtrees = array_filter($factures_filtrees, function($f) {
            return ($f['montant_total'] - $f['montant_paye']) <= 0;
        });
    } elseif ($filter_status == 'impayee') {
        $factures_filtrees = array_filter($factures_filtrees, function($f) {
            return ($f['montant_total'] - $f['montant_paye']) > 0;
        });
    } elseif ($filter_status == 'envoye') {
        $factures_filtrees = array_filter($factures_filtrees, function($f) {
            return $f['envoye_email'] == 1;
        });
    }
}

if ($filter_date_debut) {
    $factures_filtrees = array_filter($factures_filtrees, function($f) use ($filter_date_debut) {
        return $f['date_emission'] >= $filter_date_debut;
    });
}

if ($filter_date_fin) {
    $factures_filtrees = array_filter($factures_filtrees, function($f) use ($filter_date_fin) {
        return $f['date_emission'] <= $filter_date_fin;
    });
}
?>

<div class="factures-header">
    <div class="row-premium align-items-center">
        <div class="col-premium-8">
            <h2 style="color: white; margin-bottom: 0.5rem;">
                <i class="fas fa-file-invoice"></i> Mes factures
            </h2>
            <p style="opacity: 0.9;">Retrouvez ici l'historique complet de toutes vos factures de scolarité</p>
        </div>
        <div class="col-premium-4 text-end">
            <div class="small" style="opacity: 0.8;">Élève :</div>
            <div class="fw-bold"><?php echo htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom']); ?></div>
            <div class="small">Matricule: <?php echo $eleve['matricule']; ?></div>
        </div>
    </div>
    
    <div class="factures-stats">
        <div class="facture-stat-card">
            <div class="facture-stat-value"><?php echo $total_factures; ?></div>
            <div class="facture-stat-label">Total factures</div>
        </div>
        <div class="facture-stat-card">
            <div class="facture-stat-value"><?php echo number_format($total_montant, 0, ',', ' '); ?> USD</div>
            <div class="facture-stat-label">Montant total</div>
        </div>
        <div class="facture-stat-card">
            <div class="facture-stat-value"><?php echo number_format($total_paye, 0, ',', ' '); ?> USD</div>
            <div class="facture-stat-label">Total payé</div>
        </div>
        <div class="facture-stat-card">
            <div class="facture-stat-value"><?php echo $factures_payees; ?>/<?php echo $total_factures; ?></div>
            <div class="facture-stat-label">Factures payées</div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="filter-section">
    <form method="GET" class="row-premium">
        <input type="hidden" name="page" value="factures">
        
        <div class="col-premium-3">
            <select name="status" class="form-control-premium">
                <option value="">Toutes les factures</option>
                <option value="payee" <?php echo $filter_status == 'payee' ? 'selected' : ''; ?>>Factures payées</option>
                <option value="impayee" <?php echo $filter_status == 'impayee' ? 'selected' : ''; ?>>Factures impayées</option>
                <option value="envoye" <?php echo $filter_status == 'envoye' ? 'selected' : ''; ?>>Email reçu</option>
            </select>
        </div>
        
        <div class="col-premium-3">
            <input type="date" name="date_debut" class="form-control-premium" value="<?php echo $filter_date_debut; ?>" placeholder="Date début">
        </div>
        
        <div class="col-premium-3">
            <input type="date" name="date_fin" class="form-control-premium" value="<?php echo $filter_date_fin; ?>" placeholder="Date fin">
        </div>
        
        <div class="col-premium-3">
            <button type="submit" class="btn-premium btn-premium-primary">
                <i class="fas fa-search"></i> Filtrer
            </button>
            <a href="?page=factures" class="btn-premium btn-premium-secondary">
                <i class="fas fa-undo"></i> Réinitialiser
            </a>
        </div>
    </form>
</div>

<?php if(count($factures_filtrees) > 0): ?>
    <?php foreach($factures_filtrees as $facture): 
        $reste = $facture['montant_total'] - $facture['montant_paye'];
        $estPayee = $reste <= 0;
    ?>
        <div class="facture-card">
            <div class="facture-card-header">
                <div>
                    <span class="facture-number">
                        <i class="fas fa-hashtag"></i> <?php echo $facture['numero_facture']; ?>
                    </span>
                    <span class="facture-date ms-3">
                        <i class="fas fa-calendar"></i> Émise le <?php echo date('d/m/Y', strtotime($facture['date_emission'])); ?>
                    </span>
                </div>
                <div>
                    <?php if($estPayee): ?>
                        <span class="status-badge-facture paid">
                            <i class="fas fa-check-circle"></i> Payée
                        </span>
                    <?php else: ?>
                        <span class="status-badge-facture partial">
                            <i class="fas fa-clock"></i> Paiement partiel
                        </span>
                    <?php endif; ?>
                    
                    <?php if($facture['envoye_email']): ?>
                        <span class="status-badge-facture email-sent ms-2">
                            <i class="fas fa-envelope"></i> Email envoyé
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="facture-card-body">
                <div class="facture-details">
                    <div class="detail-item">
                        <span class="detail-label">Tranche</span>
                        <span class="detail-value"><?php echo $facture['tranche'] ?? 'Frais de scolarité'; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Référence bordereau</span>
                        <span class="detail-value"><?php echo $facture['reference_bordereau'] ?? '-'; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Date de paiement</span>
                        <span class="detail-value"><?php echo $facture['date_paiement'] ? date('d/m/Y', strtotime($facture['date_paiement'])) : '-'; ?></span>
                    </div>
                </div>
                
                <div class="amount-breakdown">
                    <div class="amount-item">
                        <div class="label">Montant total</div>
                        <div class="value total"><?php echo number_format($facture['montant_total'], 0, ',', ' '); ?> USD</div>
                    </div>
                    <div class="amount-item">
                        <div class="label">Montant payé</div>
                        <div class="value paid"><?php echo number_format($facture['montant_paye'], 0, ',', ' '); ?> USD</div>
                    </div>
                    <div class="amount-item">
                        <div class="label">Reste à payer</div>
                        <div class="value rest"><?php echo number_format($reste, 0, ',', ' '); ?> USD</div>
                    </div>
                </div>
                
                <div class="facture-actions">
                    <?php if($facture['pdf_path'] && file_exists($facture['pdf_path'])): ?>
                        <a href="<?php echo $facture['pdf_path']; ?>" target="_blank" class="btn-facture btn-facture-pdf">
                            <i class="fas fa-file-pdf"></i> Voir le PDF
                        </a>
                        <a href="<?php echo $facture['pdf_path']; ?>" download class="btn-facture btn-facture-download">
                            <i class="fas fa-download"></i> Télécharger
                        </a>
                    <?php else: ?>
                        <button onclick="generatePDF(<?php echo $facture['id']; ?>)" class="btn-facture btn-facture-pdf">
                            <i class="fas fa-file-pdf"></i> Générer le PDF
                        </button>
                    <?php endif; ?>
                    
                    <button onclick="printFacture(<?php echo $facture['id']; ?>)" class="btn-facture btn-facture-print">
                        <i class="fas fa-print"></i> Imprimer
                    </button>
                    
                    <button onclick="viewFactureDetail(<?php echo $facture['id']; ?>)" class="btn-facture btn-facture-download" style="background: var(--info);">
                        <i class="fas fa-eye"></i> Détails
                    </button>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    
    <!-- Pagination -->
    <?php if(count($factures_filtrees) > 10): ?>
    <div class="text-center mt-4">
        <nav class="pagination-wrapper">
            <ul class="pagination">
                <li class="page-item disabled"><a class="page-link" href="#">Précédent</a></li>
                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                <li class="page-item"><a class="page-link" href="#">2</a></li>
                <li class="page-item"><a class="page-link" href="#">3</a></li>
                <li class="page-item"><a class="page-link" href="#">Suivant</a></li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
    
<?php else: ?>
    <div class="empty-factures">
        <i class="fas fa-file-invoice"></i>
        <h4>Aucune facture trouvée</h4>
        <p class="text-muted">Vous n'avez pas encore de factures disponibles.</p>
        <?php if($filter_status || $filter_date_debut || $filter_date_fin): ?>
            <p class="text-muted">Essayez de modifier vos filtres de recherche.</p>
            <a href="?page=factures" class="btn-premium btn-premium-primary mt-2">
                <i class="fas fa-undo"></i> Réinitialiser les filtres
            </a>
        <?php else: ?>
            <p class="text-muted">Les factures apparaîtront après validation de vos paiements.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Modal Détails Facture -->
<div id="detailModal" class="modal-premium">
    <div class="modal-content-premium modal-large">
        <div class="modal-header-premium">
            <h3><i class="fas fa-file-invoice"></i> Détails de la facture</h3>
            <span class="close" onclick="closeDetailModal()">&times;</span>
        </div>
        <div class="modal-body-premium" id="detailContent" style="max-height: 70vh; overflow-y: auto;">
            <!-- Contenu chargé dynamiquement -->
        </div>
        <div class="modal-footer-premium">
            <button class="btn-premium btn-premium-primary" onclick="printDetailFacture()">
                <i class="fas fa-print"></i> Imprimer
            </button>
            <button class="btn-premium btn-premium-secondary" onclick="closeDetailModal()">Fermer</button>
        </div>
    </div>
</div>

<script>
let currentFactureId = null;

function generatePDF(factureId) {
    window.location.href = 'generate_pdf.php?id=' + factureId;
}

function printFacture(factureId) {
    window.open('print_facture.php?id=' + factureId, '_blank', 'width=800,height=600');
}

function viewFactureDetail(factureId) {
    currentFactureId = factureId;
    fetch('get_facture_details.php?id=' + factureId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const reste = data.facture.montant_total - data.facture.montant_paye;
                const estPayee = reste <= 0;
                
                const html = `
                    <div style="padding: 20px;">
                        <div style="text-align: center; margin-bottom: 30px;">
                            <h2 style="color: var(--primary);">GESTION SCOLAIRE</h2>
                            <p>Facture de scolarité</p>
                            <div style="background: var(--primary-gradient); color: white; padding: 10px 20px; border-radius: 30px; display: inline-block;">
                                N° ${data.facture.numero_facture}
                            </div>
                        </div>
                        
                        <div style="background: var(--gray-50); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 20px;">
                            <h4 style="color: var(--primary); margin-bottom: 15px;">Informations de l'élève</h4>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                                <div><strong>Nom complet:</strong> ${data.eleve.nom} ${data.eleve.prenom}</div>
                                <div><strong>Matricule:</strong> ${data.eleve.matricule}</div>
                                <div><strong>Classe:</strong> ${data.eleve.classe}</div>
                                <div><strong>Email:</strong> ${data.eleve.email}</div>
                            </div>
                        </div>
                        
                        <div style="background: var(--gray-50); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 20px;">
                            <h4 style="color: var(--primary); margin-bottom: 15px;">Détails du paiement</h4>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                                <div><strong>Tranche:</strong> ${data.facture.tranche || 'Frais de scolarité'}</div>
                                <div><strong>Référence bordereau:</strong> ${data.facture.reference_bordereau || '-'}</div>
                                <div><strong>Date d'émission:</strong> ${new Date(data.facture.date_emission).toLocaleDateString('fr-FR')}</div>
                                <div><strong>Date de paiement:</strong> ${data.facture.date_paiement ? new Date(data.facture.date_paiement).toLocaleDateString('fr-FR') : '-'}</div>
                            </div>
                        </div>
                        
                        <div style="background: var(--gray-50); border-radius: var(--radius-lg); padding: 20px;">
                            <h4 style="color: var(--primary); margin-bottom: 15px;">Récapitulatif financier</h4>
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr style="border-bottom: 1px solid var(--gray-300);">
                                    <td style="padding: 10px;"><strong>Montant total</strong></td>
                                    <td style="padding: 10px; text-align: right;">${new Intl.NumberFormat('fr-FR').format(data.facture.montant_total)} FCFA</td>
                                </tr>
                                <tr style="border-bottom: 1px solid var(--gray-300);">
                                    <td style="padding: 10px;"><strong>Montant payé</strong></td>
                                    <td style="padding: 10px; text-align: right; color: var(--success);">${new Intl.NumberFormat('fr-FR').format(data.facture.montant_paye)} FCFA</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px;"><strong>Reste à payer</strong></td>
                                    <td style="padding: 10px; text-align: right; color: ${estPayee ? 'var(--success)' : 'var(--danger)'}; font-weight: bold;">
                                        ${new Intl.NumberFormat('fr-FR').format(reste)} FCFA
                                    </td>
                                </tr>
                            </table>
                            
                            <div style="text-align: center; margin-top: 20px;">
                                ${estPayee ? 
                                    '<span style="background: var(--success); color: white; padding: 8px 20px; border-radius: 30px;">✓ FACTURE COMPLÈTEMENT PAYÉE</span>' : 
                                    '<span style="background: var(--warning); color: white; padding: 8px 20px; border-radius: 30px;">⚠ PAIEMENT PARTIEL</span>'
                                }
                            </div>
                        </div>
                        
                        <div style="text-align: center; margin-top: 30px; font-size: 12px; color: var(--gray-500);">
                            <p>Merci de votre confiance ! Ce document fait office de facture officielle.</p>
                            <p>École de Gestion Scolaire - Tel: +1 XX XX XX XX - Email: contact@ecole.com</p>
                        </div>
                    </div>
                `;
                document.getElementById('detailContent').innerHTML = html;
                document.getElementById('detailModal').classList.add('show');
            } else {
                showToast('Erreur lors du chargement des détails', 'error');
            }
        })
        .catch(error => {
            showToast('Erreur réseau', 'error');
        });
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.remove('show');
}

function printDetailFacture() {
    const content = document.getElementById('detailContent').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Facture</title>
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body { font-family: 'Inter', sans-serif; padding: 20px; background: white; }
                    @media print {
                        body { margin: 0; padding: 0; }
                    }
                </style>
            </head>
            <body>${content}</body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
</script>