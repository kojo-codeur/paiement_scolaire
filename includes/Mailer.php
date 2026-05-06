<?php
// config/Mailer.php - Version corrigée avec design professionnel de facture

// Empêcher l'accès direct
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    die("Accès direct interdit.");
}

// Inclure les fichiers PHPMailer
require_once dirname(__DIR__) . '/phpmailer/PHPMailer.php';
require_once dirname(__DIR__) . '/phpmailer/SMTP.php';
require_once dirname(__DIR__) . '/phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mail;
    private $config;
    
    public function __construct() {
        $this->loadConfig();
        $this->initPHPMailer();
    }
    
    private function loadConfig() {
        $this->config = [
            'app_name' => 'Ecole Gestion Scolaire',
            'site_url' => 'http://localhost/paiement_scolaire',
            'email_from' => 'chalij68@gmail.com',
            'email_from_name' => 'Ecole Gestion Scolaire',
            'email_reply_to' => 'chalij68@gmail.com',
            'smtp_host' => 'smtp.gmail.com',
            'smtp_user' => 'chalij68@gmail.com',
            'smtp_pass' => 'gzvloyeflswyyodv',
            'smtp_port' => 587,
            'smtp_secure' => 'tls',
            'debug_mode' => false
        ];
    }
    
    private function initPHPMailer() {
        try {
            $this->mail = new PHPMailer(true);
            
            if ($this->config['debug_mode']) {
                $this->mail->SMTPDebug = SMTP::DEBUG_SERVER;
                $this->mail->Debugoutput = function($str, $level) {
                    error_log("PHPMailer DEBUG [$level]: $str");
                };
            }
            
            $this->mail->isSMTP();
            $this->mail->Host = $this->config['smtp_host'];
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $this->config['smtp_user'];
            $this->mail->Password = $this->config['smtp_pass'];
            $this->mail->SMTPSecure = $this->config['smtp_secure'];
            $this->mail->Port = $this->config['smtp_port'];
            
            $this->mail->CharSet = 'UTF-8';
            $this->mail->Encoding = 'base64';
            $this->mail->isHTML(true);
            $this->mail->setFrom($this->config['email_from'], $this->config['email_from_name']);
            $this->mail->addReplyTo($this->config['email_reply_to'], $this->config['email_from_name']);
            
            $this->mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            
            error_log("✅ PHPMailer initialisé avec succès");
            
        } catch (Exception $e) {
            error_log("❌ Erreur PHPMailer: " . $e->getMessage());
        }
    }
    
    /**
     * Envoyer un email avec pièce jointe
     */
    public function sendEmailWithAttachment($to, $toName, $subject, $body, $attachmentPath = null) {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            
            $this->mail->addAddress($to, $toName);
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;
            $this->mail->AltBody = strip_tags($body);

            
            
            if ($attachmentPath && file_exists($attachmentPath)) {
                $this->mail->addAttachment($attachmentPath);
                error_log("✅ Pièce jointe ajoutée: " . $attachmentPath);
            }
            
            $result = $this->mail->send();
            
            if ($result) {
                error_log("✅ Email envoyé avec succès à: $to - Sujet: $subject");
                return true;
            } else {
                error_log("❌ Échec d'envoi: " . $this->mail->ErrorInfo);
                return false;
            }
            
        } catch (Exception $e) {
            error_log("❌ Erreur d'envoi d'email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Envoyer un email simple (sans pièce jointe)
     */
    public function sendSimpleEmail($to, $toName, $subject, $body) {
        return $this->sendEmailWithAttachment($to, $toName, $subject, $body, null);
    }


    /**
     * Envoyer un email de facture avec design professionnel
     */
    public function sendFactureEmail($to, $toName, $factureData, $pdfPath = null) {
        $subject = "Votre facture de scolarité N° " . $factureData['numero_facture'];
        
        $montant_restant = ($factureData['montant_total'] ?? 0) - ($factureData['montant_paye'] ?? 0);
        $estPayee = $montant_restant <= 0;
        
        $body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Facture ' . $factureData['numero_facture'] . '</title>
            <style>
                body {
                    font-family: "Segoe UI", Arial, sans-serif;
                    margin: 0;
                    padding: 0;
                    background: #f0f2f5;
                    line-height: 1.6;
                }
                .email-container {
                    max-width: 700px;
                    margin: 20px auto;
                    background: white;
                    border-radius: 24px;
                    overflow: hidden;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                }
                .header {
                    background: linear-gradient(135deg, #1e385b, #0099fa);
                    padding: 35px;
                    text-align: center;
                    color: white;
                }
                .header h1 {
                    font-size: 28px;
                    margin: 0 0 10px 0;
                    font-weight: 700;
                }
                .header p {
                    margin: 0;
                    opacity: 0.9;
                }
                .facture-badge {
                    background: rgba(255,255,255,0.2);
                    border-radius: 30px;
                    padding: 8px 20px;
                    display: inline-block;
                    margin-top: 15px;
                    font-size: 14px;
                    font-weight: 600;
                }
                .content {
                    padding: 35px;
                }
                .section-title {
                    font-size: 16px;
                    font-weight: 700;
                    color: #1e385b;
                    border-left: 4px solid #0099fa;
                    padding-left: 12px;
                    margin: 25px 0 15px 0;
                }
                .section-title:first-of-type {
                    margin-top: 0;
                }
                .info-grid {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 16px;
                    margin-bottom: 20px;
                }
                .info-item {
                    display: flex;
                    justify-content: space-between;
                    padding: 10px 0;
                    border-bottom: 1px solid #e9ecef;
                }
                .info-item:last-child {
                    border-bottom: none;
                }
                .info-label {
                    font-weight: 600;
                    color: #6c757d;
                }
                .info-value {
                    color: #212529;
                    font-weight: 500;
                }
                .items-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                }
                .items-table th {
                    background: #1e385b;
                    color: white;
                    padding: 14px;
                    text-align: left;
                    font-weight: 600;
                }
                .items-table td {
                    padding: 12px;
                    border-bottom: 1px solid #e9ecef;
                }
                .total-section {
                    text-align: right;
                    margin-top: 25px;
                    padding-top: 20px;
                    border-top: 2px solid #e9ecef;
                }
                .total-line {
                    margin: 8px 0;
                    font-size: 14px;
                }
                .grand-total {
                    font-size: 20px;
                    font-weight: 800;
                    color: #1e385b;
                    margin-top: 12px;
                }
                .status-badge {
                    display: inline-block;
                    padding: 8px 24px;
                    border-radius: 40px;
                    font-size: 13px;
                    font-weight: 700;
                    text-align: center;
                    margin-top: 20px;
                }
                .status-paid {
                    background: linear-gradient(135deg, #10b981, #059669);
                    color: white;
                }
                .status-partial {
                    background: linear-gradient(135deg, #f59e0b, #d97706);
                    color: white;
                }
                .footer {
                    background: #f8f9fa;
                    padding: 25px;
                    text-align: center;
                    font-size: 12px;
                    color: #6c757d;
                    border-top: 1px solid #e9ecef;
                }
                .btn {
                    display: inline-block;
                    background: linear-gradient(135deg, #1e385b, #0099fa);
                    color: white;
                    padding: 12px 30px;
                    text-decoration: none;
                    border-radius: 40px;
                    margin-top: 20px;
                    font-weight: 600;
                }
                @media (max-width: 600px) {
                    .content { padding: 20px; }
                    .info-item { flex-direction: column; gap: 5px; }
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="header">
                    <h1>🎓 ÉCOLE DE GESTION SCOLAIRE</h1>
                    <p>Facture officielle de scolarité</p>
                    <div class="facture-badge">
                        N° FACTURE: ' . $factureData['numero_facture'] . '
                    </div>
                </div>
                
                <div class="content">
                    <div class="section-title">
                        📋 Informations de l\'élève
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Nom complet</span>
                            <span class="info-value"><strong>' . strtoupper(htmlspecialchars($toName)) . '</strong></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Matricule</span>
                            <span class="info-value">' . ($factureData['matricule'] ?? 'N/A') . '</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Classe</span>
                            <span class="info-value">' . ($factureData['classe'] ?? 'N/A') . '</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Année scolaire</span>
                            <span class="info-value">' . ($factureData['annee_scolaire'] ?? date('Y') . '-' . (date('Y')+1)) . '</span>
                        </div>
                    </div>
                    
                    <div class="section-title">
                        💰 Détails du paiement
                    </div>
                    <table class="items-table">
                        <thead>
                            <tr><th>DESCRIPTION</th><th>MONTANT</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>' . ($factureData['details'][0]['description'] ?? 'Frais de scolarité') . '</td>
                                <td><strong>' . number_format($factureData['montant_total'], 0, ',', ' ') . ' FCFA</strong></td>
                            </tr>
                            <tr>
                                <td>Date d\'émission</td>
                                <td>' . date('d/m/Y') . '</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div class="total-section">
                        <div class="total-line"><strong>Montant total :</strong> ' . number_format($factureData['montant_total'], 0, ',', ' ') . ' FCFA</div>
                        <div class="total-line"><strong>Montant payé :</strong> ' . number_format($factureData['montant_paye'], 0, ',', ' ') . ' FCFA</div>
                        <div class="total-line grand-total"><strong>Reste à payer :</strong> ' . number_format($montant_restant, 0, ',', ' ') . ' FCFA</div>
                    </div>
                    
                    <div style="text-align: center;">
                        ' . ($estPayee ? 
                            '<div class="status-badge status-paid">✓ FACTURE COMPLÈTEMENT PAYÉE</div>' : 
                            '<div class="status-badge status-partial">⚠ PAIEMENT PARTIEL</div>'
                        ) . '
                    </div>
                    
                    <div style="text-align: center;">
                        <a href="' . $this->config['site_url'] . '/dashboard.php?page=factures" class="btn">📄 Voir toutes mes factures</a>
                    </div>
                </div>
                
                <div class="footer">
                    <p>Merci de votre confiance ! Ce document fait office de facture officielle.</p>
                    <p>📞 +225 XX XX XX XX | ✉️ contact@ecole.com | 🌐 www.ecole.com</p>
                    <p>© ' . date('Y') . ' École de Gestion Scolaire. Tous droits réservés.</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $this->sendEmailWithAttachment($to, $toName, $subject, $body, $pdfPath);
    }
    
}

// ============================================
// FONCTIONS HELPER
// ============================================

function getMailer() {
    static $mailer = null;
    if ($mailer === null) {
        $mailer = new Mailer();
    }
    return $mailer;
}

function sendFactureEmail($to, $toName, $factureData, $pdfPath = null) {
    return getMailer()->sendFactureEmail($to, $toName, $factureData, $pdfPath);
}

function sendEmailWithAttachment($to, $toName, $subject, $body, $attachmentPath = null) {
    return getMailer()->sendEmailWithAttachment($to, $toName, $subject, $body, $attachmentPath);
}

function sendSimpleEmail($to, $toName, $subject, $body) {
    return getMailer()->sendSimpleEmail($to, $toName, $subject, $body);
}

function sendNotificationEmail($email, $name, $subject, $message) {
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
    </head>
    <body style="font-family: Arial, sans-serif;">
        <h2 style="color: #4361ee;">' . htmlspecialchars($subject) . '</h2>
        <p>Bonjour <strong>' . htmlspecialchars($name) . '</strong>,</p>
        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
            ' . nl2br(htmlspecialchars($message)) . '
        </div>
        <p style="margin-top: 20px;">Cordialement,<br>L\'administration</p>
    </body>
    </html>';
    
    return sendSimpleEmail($email, $name, $subject, $body);
}
?>