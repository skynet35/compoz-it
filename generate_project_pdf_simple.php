<?php
// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'auth.php';
require_once 'tcpdf/tcpdf.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Récupérer l'ID du projet
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($project_id <= 0) {
    die('ID de projet invalide');
}

// Récupérer les informations du projet
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
$stmt->execute([$project_id, $_SESSION['user_id']]);
$project = $stmt->fetch();

if (!$project) {
    die('Projet non trouvé');
}

// Récupérer les composants du projet avec prix
$stmt = $pdo->prepare("
    SELECT pc.quantity_needed, pc.quantity_used, c.name, c.manufacturer, c.package, 
           c.description, c.quantity as stock, c.price
    FROM project_components pc 
    JOIN components c ON pc.component_id = c.id 
    WHERE pc.project_id = ?
    ORDER BY c.name
");
$stmt->execute([$project_id]);
$components = $stmt->fetchAll();

// Récupérer les travaux et matériaux
$stmt = $pdo->prepare("
    SELECT item_type, item_name, description, quantity, unit, unit_price, total_price 
    FROM project_items 
    WHERE project_id = ?
    ORDER BY item_name
");
$stmt->execute([$project_id]);
$items = $stmt->fetchAll();

// Calculer les totaux
$components_total = 0;
foreach ($components as $component) {
    $components_total += ($component['quantity_needed'] * ($component['price'] ?? 0));
}

$items_total = 0;
foreach ($items as $item) {
    $items_total += $item['total_price'] ?? 0;
}

$total_cost = $components_total + $items_total;

// Créer le PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Informations du document
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('ElecStock');
$pdf->SetTitle('Nomenclature - ' . $project['name']);
$pdf->SetSubject('Nomenclature du projet');

// Supprimer l'en-tête et le pied de page par défaut
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Ajouter une page
$pdf->AddPage();

// Définir la police
$pdf->SetFont('helvetica', '', 12);

// Titre principal
$pdf->SetFont('helvetica', 'B', 18);
$pdf->Cell(0, 15, 'NOMENCLATURE DU PROJET', 0, 1, 'C');
$pdf->Ln(5);

// Informations du projet
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, htmlspecialchars($project['name']), 0, 1, 'C');
$pdf->Ln(3);

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 8, 'Créé le : ' . date('d/m/Y', strtotime($project['created_at'])), 0, 1, 'C');
if ($project['description']) {
    $pdf->Cell(0, 8, 'Description : ' . htmlspecialchars($project['description']), 0, 1, 'C');
}
$pdf->Ln(10);

// Section Composants
if (!empty($components)) {
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, '1. COMPOSANTS', 0, 1, 'L');
    $pdf->Ln(3);
    
    // En-têtes du tableau
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(50, 8, 'Nom', 1, 0, 'C');
    $pdf->Cell(30, 8, 'Fabricant', 1, 0, 'C');
    $pdf->Cell(20, 8, 'Boîtier', 1, 0, 'C');
    $pdf->Cell(15, 8, 'Qté', 1, 0, 'C');
    $pdf->Cell(20, 8, 'Prix unit.', 1, 0, 'C');
    $pdf->Cell(20, 8, 'Total', 1, 0, 'C');
    $pdf->Cell(35, 8, 'Description', 1, 1, 'C');
    
    // Données du tableau
    $pdf->SetFont('helvetica', '', 8);
    foreach ($components as $component) {
        $total_component = $component['quantity_needed'] * ($component['price'] ?? 0);
        
        $pdf->Cell(50, 6, htmlspecialchars(substr($component['name'], 0, 25)), 1, 0, 'L');
        $pdf->Cell(30, 6, htmlspecialchars(substr($component['manufacturer'] ?? '', 0, 15)), 1, 0, 'L');
        $pdf->Cell(20, 6, htmlspecialchars(substr($component['package'] ?? '', 0, 10)), 1, 0, 'C');
        $pdf->Cell(15, 6, $component['quantity_needed'], 1, 0, 'C');
        $pdf->Cell(20, 6, number_format($component['price'] ?? 0, 2) . '€', 1, 0, 'R');
        $pdf->Cell(20, 6, number_format($total_component, 2) . '€', 1, 0, 'R');
        $pdf->Cell(35, 6, htmlspecialchars(substr($component['description'] ?? '', 0, 20)), 1, 1, 'L');
    }
    
    // Total des composants
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(135, 8, 'TOTAL COMPOSANTS', 1, 0, 'R');
    $pdf->Cell(20, 8, number_format($components_total, 2) . '€', 1, 0, 'R');
    $pdf->Cell(35, 8, '', 1, 1, 'L');
    $pdf->Ln(10);
}

// Section Travaux et Matériaux
if (!empty($items)) {
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, '2. TRAVAUX ET MATÉRIAUX', 0, 1, 'L');
    $pdf->Ln(3);
    
    // En-têtes du tableau
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(25, 8, 'Type', 1, 0, 'C');
    $pdf->Cell(40, 8, 'Nom', 1, 0, 'C');
    $pdf->Cell(50, 8, 'Description', 1, 0, 'C');
    $pdf->Cell(15, 8, 'Qté', 1, 0, 'C');
    $pdf->Cell(15, 8, 'Unité', 1, 0, 'C');
    $pdf->Cell(20, 8, 'Prix unit.', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Total', 1, 1, 'C');
    
    // Données du tableau
    $pdf->SetFont('helvetica', '', 8);
    foreach ($items as $item) {
        $pdf->Cell(25, 6, htmlspecialchars(substr($item['item_type'] ?? '', 0, 12)), 1, 0, 'L');
        $pdf->Cell(40, 6, htmlspecialchars(substr($item['item_name'], 0, 20)), 1, 0, 'L');
        $pdf->Cell(50, 6, htmlspecialchars(substr($item['description'] ?? '', 0, 25)), 1, 0, 'L');
        $pdf->Cell(15, 6, $item['quantity'], 1, 0, 'C');
        $pdf->Cell(15, 6, htmlspecialchars(substr($item['unit'] ?? '', 0, 8)), 1, 0, 'C');
        $pdf->Cell(20, 6, number_format($item['unit_price'] ?? 0, 2) . '€', 1, 0, 'R');
        $pdf->Cell(25, 6, number_format($item['total_price'] ?? 0, 2) . '€', 1, 1, 'R');
    }
    
    // Total des travaux et matériaux
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(165, 8, 'TOTAL TRAVAUX ET MATÉRIAUX', 1, 0, 'R');
    $pdf->Cell(25, 8, number_format($items_total, 2) . '€', 1, 1, 'R');
    $pdf->Ln(10);
}

// Total général
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 12, 'COÛT TOTAL DU PROJET : ' . number_format($total_cost, 2) . '€', 0, 1, 'C');

// Pied de page
$pdf->Ln(20);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(0, 5, 'Document généré le ' . date('d/m/Y à H:i'), 0, 1, 'C');
$pdf->Cell(0, 5, 'ElecStock - Gestion de composants électroniques', 0, 1, 'C');

// Générer le nom du fichier
$filename = 'Nomenclature_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['name']) . '_' . date('Y-m-d') . '.pdf';

// Sortir le PDF
$pdf->Output($filename, 'D');
?>