<?php
require_once __DIR__ . '/config.php';
require_login();

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function clean_document_content(string $content): string
{
  $allowedTags = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'ul', 'ol', 'li', 'blockquote', 'h1', 'h2', 'h3', 'div'];
  $content = strip_tags($content, '<p><br><strong><b><em><i><u><s><strike><ul><ol><li><blockquote><h1><h2><h3><div>');

  return (string)preg_replace_callback('/<(\/?)([a-z][a-z0-9]*)\b[^>]*>/i', static function (array $match) use ($allowedTags): string {
    $tag = strtolower($match[2]);
    if (!in_array($tag, $allowedTags, true)) {
      return '';
    }

    return $tag === 'br' ? '<br>' : '<' . $match[1] . $tag . '>';
  }, $content);
}

$view = (string)($_GET['view'] ?? 'for_action');
$allowedViews = ['for_action', 'draft', 'within_office', 'outside_office', 'archived'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'for_action';
}

$subject = trim((string)($_GET['subject'] ?? ''));
$fileName = trim((string)($_GET['file_name'] ?? ''));
$createdDate = trim((string)($_GET['created_date'] ?? ''));
$receivedDate = trim((string)($_GET['received_date'] ?? ''));
$type = trim((string)($_GET['type'] ?? ''));
$entriesLimit = (int)($_GET['limit'] ?? 10);
if (!in_array($entriesLimit, [10, 25, 50, 100], true)) {
  $entriesLimit = 10;
}
$draftType = trim((string)($_GET['draft_type'] ?? ''));
$withinType = trim((string)($_GET['within_type'] ?? ''));
$draftTypes = ['Correspondence', 'PNP Radio Message', 'Invistigation Report'];
$activeDraftType = in_array($draftType, $draftTypes, true) ? $draftType : '';
$activeWithinType = $withinType === 'Received Documents' ? $withinType : '';
if (!in_array($draftType, $draftTypes, true)) {
  $draftType = $draftTypes[0];
}
$showDraftForm = $view === 'draft';
$draftSaved = $view === 'draft' && (($_GET['saved'] ?? '') === '1');
$draftError = '';
$draftValues = [
  'subject' => '',
  'file_name' => '',
  'from_unit' => '',
  'to_unit' => '',
  'document_type' => $draftType,
  'stl_type' => '',
  'priority' => 'No',
];
$draftContent = '';
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$routeOptions = [
  'within_office' => [
    'Chief of Police',
    'Deputy Chief of Police',
    'C, Admininistrative Section',
    'C, Intelligence Section',
    'C, Operation Section',
    'C, Supply Section',
    'C, Municipal Community Affairs Section',
    'C, Finance Section',
    'C, Investigation Section',
  ],
  'outside_office' => [
    'Provincial Director',
    'Deputy Provincial Director',
    'C, Personnel & Records Mngt Unit',
    'C, Intelligence Unit',
    'C, Operations Unit',
    "C, Logistics & Research Dev't Unit",
    "C, Community Affairs & Dev't Unit",
    'C, Comptroller ship Unit',
    'C, Investigation & Detective Mngt Unit',
    'C, Learning & Doctrine Dev\'t Unit',
    'C, Plans & Strategy Mngt Unit',
    'C, ICT Mngt Unit',
  ],
];
$actionRequiredOptions = [
  'for Approval',
  'for Comment',
  'for Recommendation',
  'for Information',
  'for Notation',
  'for Signature',
  'for Study/Investigation',
  'for File/Reference',
  'See Me/Call Me',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'bulk_documents') {
  $bulkAction = (string)($_POST['bulk_action'] ?? '');
  $transitions = [
    'archive' => ['view' => 'for_action', 'from' => 'for_action', 'to' => 'archived', 'label' => 'archived'],
    'restore' => ['view' => 'archived', 'from' => 'archived', 'to' => 'for_action', 'label' => 'restored to For Action'],
    'receive' => ['view' => 'for_action', 'from' => 'for_action', 'to' => 'for_action', 'label' => 'received'],
  ];
  $transition = $transitions[$bulkAction] ?? null;

  if (!hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
    $_SESSION['flash_message'] = 'Your session expired. Please try again.';
  } elseif ($transition === null || $view !== $transition['view']) {
    $_SESSION['flash_message'] = 'That document action is not available here.';
  } else {
    $documentIds = array_values(array_unique(array_filter(array_map(
      static fn($id): int => filter_var($id, FILTER_VALIDATE_INT) !== false ? (int)$id : 0,
      (array)($_POST['document_ids'] ?? [])
    ), static fn(int $id): bool => $id > 0)));

    if ($documentIds === []) {
      $_SESSION['flash_message'] = 'Select at least one document first.';
    } else {
      $placeholders = [];
      $parameters = [':from_status' => $transition['from']];
      if ($bulkAction === 'receive') {
        $parameters[':date_in'] = date('Y-m-d');
      } else {
        $parameters[':to_status'] = $transition['to'];
      }
      foreach ($documentIds as $index => $documentId) {
        $placeholder = ':document_id_' . $index;
        $placeholders[] = $placeholder;
        $parameters[$placeholder] = $documentId;
      }

      $updateSql = $bulkAction === 'receive'
        ? 'UPDATE documents SET date_in = COALESCE(date_in, :date_in) WHERE status = :from_status AND id IN (' . implode(', ', $placeholders) . ')'
        : 'UPDATE documents SET status = :to_status WHERE status = :from_status AND id IN (' . implode(', ', $placeholders) . ')';
      $update = db()->prepare($updateSql);
      $update->execute($parameters);
      $changedCount = $update->rowCount();
      $_SESSION['flash_message'] = $changedCount > 0
        ? $changedCount . ' document(s) ' . $transition['label'] . '.'
        : ($bulkAction === 'receive' ? 'Selected documents already have a Date In date.' : 'No matching documents were updated.');
    }
  }

  header('Location: dashboard.php?view=' . rawurlencode($transition['view'] ?? ($view === 'archived' ? 'archived' : 'for_action')));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'route_document') {
  $routeTargets = ['within_office' => 'Within Office', 'outside_office' => 'Outside Office'];
  $routeTarget = (string)($_POST['route_target'] ?? '');
  $routeDestination = trim((string)($_POST['route_destination'] ?? ''));
  $remarks = trim((string)($_POST['remarks'] ?? ''));
  $actionRequired = trim((string)($_POST['action_required'] ?? ''));
  $routeRequiresDetails = $view === 'for_action' && isset($routeTargets[$routeTarget]);
  $documentId = filter_var($_POST['document_id'] ?? null, FILTER_VALIDATE_INT);

  if (!hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
    $_SESSION['flash_message'] = 'Your session expired. Please try again.';
  } elseif (!isset($routeTargets[$routeTarget]) || !in_array($routeDestination, $routeOptions[$routeTarget], true) || !in_array($view, ['for_action', 'within_office', 'outside_office'], true) || $documentId === false || $documentId === null || $documentId < 1 || ($routeRequiresDetails && (!in_array($actionRequired, $actionRequiredOptions, true) || strlen($remarks) > 20000))) {
    $_SESSION['flash_message'] = 'That document route is not available here.';
  } else {
    if ($routeRequiresDetails) {
      $route = db()->prepare('UPDATE documents SET status = :to_status, to_unit = :to_unit, remarks = :remarks, action_requested = :action_requested WHERE id = :id AND status = :from_status');
      $route->execute([':to_status' => $routeTarget, ':to_unit' => $routeDestination, ':remarks' => $remarks, ':action_requested' => $actionRequired, ':id' => $documentId, ':from_status' => $view]);
    } else {
      $route = db()->prepare('UPDATE documents SET status = :to_status, to_unit = :to_unit WHERE id = :id AND status = :from_status');
      $route->execute([':to_status' => $routeTarget, ':to_unit' => $routeDestination, ':id' => $documentId, ':from_status' => $view]);
    }
    $_SESSION['flash_message'] = $route->rowCount() > 0
      ? 'Document routed to ' . $routeDestination . '.'
      : 'No matching document was routed.';
  }

  header('Location: dashboard.php?view=' . rawurlencode(isset($routeTargets[$routeTarget]) ? $routeTarget : $view));
  exit;
}

$flashMessage = (string)($_SESSION['flash_message'] ?? '');
unset($_SESSION['flash_message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'create_draft') {
  $showDraftForm = true;
  foreach ($draftValues as $field => $_value) {
    if ($field !== 'document_type') {
      $draftValues[$field] = trim((string)($_POST[$field] ?? ''));
    }
  }
  $draftValues['document_type'] = $draftType;
  $draftContent = clean_document_content((string)($_POST['document_content'] ?? ''));
  $destination = (string)($_POST['destination'] ?? 'draft');

  if (!hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
    $draftError = 'Your session expired. Please try again.';
  } elseif (strlen($draftContent) > 2000000) {
    $draftError = 'Document content is too large. Keep it under 2 MB.';
  } elseif (trim(strip_tags($draftContent)) === '') {
    $draftError = 'Add document content before saving.';
  } elseif ($draftValues['subject'] === '' || $draftValues['file_name'] === '' || $draftValues['from_unit'] === '' || $draftValues['to_unit'] === '' || $draftValues['stl_type'] === '') {
    $draftError = 'Complete all fields before saving the draft.';
  } elseif (!in_array($draftValues['document_type'], $draftTypes, true) || !in_array($draftValues['priority'], ['Yes', 'No'], true) || !in_array($destination, ['draft', 'for_action'], true)) {
    $draftError = 'Choose a valid document type, priority, and destination.';
  } else {
    $insert = db()->prepare('INSERT INTO documents (subject, file_name, from_unit, to_unit, document_type, stl_type, priority, document_content, status, created_at, created_by) VALUES (:subject, :file_name, :from_unit, :to_unit, :document_type, :stl_type, :priority, :document_content, :status, :created_at, :created_by)');
    $insert->execute([
      ':subject' => $draftValues['subject'],
      ':file_name' => $draftValues['file_name'],
      ':from_unit' => $draftValues['from_unit'],
      ':to_unit' => $draftValues['to_unit'],
      ':document_type' => $draftValues['document_type'],
      ':stl_type' => $draftValues['stl_type'],
      ':priority' => $draftValues['priority'],
      ':document_content' => $draftContent,
      ':status' => $destination,
      ':created_at' => date('Y-m-d'),
      ':created_by' => (int)$_SESSION['user_id'],
    ]);

    $redirect = $destination === 'for_action'
      ? ['view' => 'for_action']
      : ['view' => 'draft', 'new' => '1', 'saved' => '1', 'draft_type' => $draftValues['document_type']];
    header('Location: dashboard.php?' . http_build_query($redirect));
    exit;
  }
}

$statusLabels = [
    'for_action' => 'For Action',
    'draft' => 'Draft',
    'within_office' => 'Within Office',
    'outside_office' => 'Outside Office',
  'archived' => 'Archived',
];
$sectionTitle = $statusLabels[$view];
$sectionDescription = '';
if ($view === 'draft') {
  if ($draftType !== '') {
    $sectionTitle = $draftType;
  }
    $sectionDescription = $draftType !== '' ? $draftType : 'Documents saved for later completion';
} elseif ($view === 'within_office') {
  if ($withinType !== '') {
    $sectionTitle = $withinType;
  }
    $sectionDescription = $withinType !== '' ? $withinType : 'Documents moving within the office';
} elseif ($view === 'outside_office') {
    $sectionDescription = 'Documents sent outside the office';
} elseif ($view === 'archived') {
  $sectionDescription = 'Documents archived from For Action';
}

$query = 'SELECT id, subject, file_name, from_unit, to_unit, document_type, stl_type, priority, date_in, action_requested, created_at FROM documents WHERE status = :status';
$params = [':status' => $view];
if ($subject !== '') { $query .= ' AND subject LIKE :subject'; $params[':subject'] = '%' . $subject . '%'; }
if ($fileName !== '') { $query .= ' AND file_name LIKE :file_name'; $params[':file_name'] = '%' . $fileName . '%'; }
if ($createdDate !== '') { $query .= ' AND created_at = :created_at'; $params[':created_at'] = $createdDate; }
if ($receivedDate !== '' && $view === 'within_office' && $activeWithinType === 'Received Documents') { $query .= ' AND date_in = :date_in'; $params[':date_in'] = $receivedDate; }
if ($type !== '') { $query .= ' AND document_type = :document_type'; $params[':document_type'] = $type; }
if ($view === 'draft' && $draftType !== '') { $query .= ' AND document_type = :draft_type'; $params[':draft_type'] = $draftType; }
$query .= ' ORDER BY created_at DESC, subject ASC LIMIT ' . $entriesLimit;
$statement = db()->prepare($query);
$statement->execute($params);
$documents = $statement->fetchAll();
$selectedDocument = null;
$selectedDocumentId = filter_var($_GET['document_id'] ?? null, FILTER_VALIDATE_INT);
if ($selectedDocumentId !== false && $selectedDocumentId !== null && $selectedDocumentId > 0 && $view !== 'draft') {
  $detailQuery = db()->prepare('SELECT subject, file_name, from_unit, to_unit, document_type, stl_type, priority, date_in, action_requested, remarks, created_at, document_content FROM documents WHERE id = :id AND status = :status');
  $detailQuery->execute([':id' => $selectedDocumentId, ':status' => $view]);
  $selectedDocument = $detailQuery->fetch() ?: null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PNP Portal | Dashboard</title>
  <style>
    :root { --navy:#102a43; --blue:#1769aa; --gold:#f2b134; --ink:#19324a; --muted:#6b7d8f; --line:#d9e4ec; --canvas:#f4f8fb; }
    * { box-sizing:border-box; }
    body { margin:0; min-height:100vh; color:var(--ink); background:var(--canvas); font-family:Arial,sans-serif; }
    .topbar { min-height:88px; display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:center; gap:24px; padding:0 clamp(22px,5vw,70px); color:#fff; background:var(--navy); }
    .brand { display:flex; align-items:center; gap:12px; font: bold 1.2rem Georgia,'Times New Roman',serif; }
    .brand img { width:35px; height:48px; object-fit:contain; }
    .user-area { display:flex; align-items:center; gap:15px; color:#d7e4ee; font-size:.84rem; }
    .user-details { display:flex; flex-direction:column; align-items:flex-end; gap:4px; }
    .logout { padding:0; color:#f7c75a; background:transparent; border:0; cursor:pointer; font-size:12px; }
    .avatar { width:35px; height:35px; display:grid; place-items:center; color:var(--navy); background:var(--gold); border-radius:50%; font-weight:bold; }
    .layout { width:100%; max-width:none; margin:0; padding:32px clamp(22px,5vw,70px) 60px; }
    .heading-row { display:flex; align-items:end; justify-content:space-between; gap:20px; margin-bottom:28px; }
    .eyebrow { margin:0 0 8px; color:var(--blue); font-size:.73rem; font-weight:bold; letter-spacing:.12em; text-transform:uppercase; }
    h1,h2 { margin:0; color:var(--navy); font-family:Georgia,'Times New Roman',serif; }
    h1 { font-size:clamp(2rem,4vw,2.8rem); }
    h2 { font-size:1.45rem; }
    .date { color:var(--muted); font-size:.84rem; }
    .navigation { display:grid; grid-template-columns:repeat(5,minmax(105px,1fr)); align-items:center; gap:4px; width:100%; max-width:760px; margin:0 auto; padding:0; background:transparent; border:0; }
    .navigation form { position:relative; min-width:0; margin:0; }
    .nav-menu { position:relative; min-width:0; }
    .nav-menu summary { list-style:none; }
    .nav-menu summary::-webkit-details-marker { display:none; }
    .draft-options { position:absolute; z-index:5; top:calc(100% + 8px); left:50%; display:grid; min-width:190px; padding:6px; background:#fff; border:1px solid var(--line); border-radius:7px; box-shadow:0 10px 24px rgba(16,42,67,.18); transform:translateX(-50%); }
    .draft-option { padding:11px 12px; color:var(--ink); border-radius:5px; font-size:12px; text-align:left; text-decoration:none; white-space:nowrap; }
    .draft-option:hover,.draft-option.active { color:var(--navy); background:#eaf5ff; }
    .nav-button,.nav-select { width:100%; min-width:0; padding:11px 30px 11px 8px; color:#d7e4ee; background:transparent; border:0; border-radius:6px; cursor:pointer; font:bold 12px Arial,sans-serif; text-align:center; text-decoration:none; white-space:nowrap; }
    .nav-button { position:relative; display:flex; align-items:center; justify-content:center; gap:5px; }
    .nav-icon { color:#d7e4ee; font-size:14px; font-weight:bold; line-height:1; pointer-events:none; }
    .nav-button:hover,.nav-button.active,.nav-select:hover,.nav-select:focus { color:var(--navy); background:#eaf5ff; }
    .nav-select option { color:var(--ink); background:#fff; font-weight:normal; }
    .content-panel { width:100%; min-height:calc(100vh - 120px); padding:28px; background:#fff; border:1px solid var(--line); border-radius:8px; }
    .panel-heading { display:flex; align-items:center; justify-content:space-between; gap:16px; padding-bottom:18px; border-bottom:1px solid var(--line); }
    .panel-heading p { margin:0; color:var(--muted); font-size:.83rem; }
    .create-draft-link { padding:9px 12px; color:#fff; background:var(--blue); border-radius:5px; font-size:.8rem; font-weight:bold; text-decoration:none; white-space:nowrap; }
    .draft-form { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; padding:22px 0; border-bottom:1px solid var(--line); }
    .draft-form h3 { grid-column:1/-1; margin:0; color:var(--navy); font: bold 1rem Georgia,'Times New Roman',serif; }
    .draft-error { grid-column:1/-1; margin:0; color:#a83b3b; font-size:.85rem; }
    .draft-success { grid-column:1/-1; margin:0; color:#28734f; font-size:.85rem; font-weight:bold; }
    .draft-form-actions { grid-column:1/-1; display:flex; gap:8px; align-items:center; }
    .draft-submit,.draft-send { padding:10px 14px; color:#fff; background:var(--blue); border:0; border-radius:5px; cursor:pointer; font-weight:bold; }
    .draft-send { background:#28734f; }
    .draft-cancel { color:var(--muted); font-size:.85rem; text-decoration:none; }
    .editor-section { grid-column:1/-1; overflow:hidden; border:1px solid var(--line); border-radius:5px; }
    .editor-heading { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 14px; background:#fff; border-bottom:1px solid var(--line); }
    .editor-heading h3 { margin:0; color:var(--navy); font: bold 1rem Georgia,'Times New Roman',serif; }
    .editor-toolbar { display:flex; align-items:center; flex-wrap:wrap; gap:4px; padding:8px; background:#f1f6f9; border-bottom:1px solid var(--line); }
    .editor-tool { min-width:34px; height:32px; padding:0 8px; color:var(--ink); background:#fff; border:1px solid var(--line); border-radius:4px; cursor:pointer; font:13px Arial,sans-serif; }
    .editor-tool:hover,.editor-tool:focus { color:var(--navy); background:#eaf5ff; }
    .editor-divider { width:1px; height:24px; margin:0 4px; background:#cbd7df; }
    .editor-workspace { max-height:75vh; overflow:auto; padding:24px; background:#e7edf1; }
    .document-editor { width:min(100%,816px); min-height:1056px; margin:0 auto; padding:76px; color:#202a33; background:#fff; box-shadow:0 2px 10px rgba(16,42,67,.14); outline:none; font:16px/1.6 Georgia,'Times New Roman',serif; }
    .document-editor:empty::before { color:#91a0aa; content:attr(data-placeholder); pointer-events:none; }
    .docx-button { padding:8px 11px; color:#fff; background:#28734f; border:0; border-radius:4px; cursor:pointer; font-size:.8rem; font-weight:bold; white-space:nowrap; }
    .docx-button:hover { background:#205d40; }
    .filters { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); align-items:start; gap:12px; padding:22px 0; border-bottom:1px solid var(--line); }
    .field-stack { display:grid; align-content:start; gap:12px; }
    .field { display:grid; gap:7px; }
    .field label { min-height:14px; color:var(--muted); font-size:.75rem; font-weight:bold; line-height:14px; text-transform:uppercase; }
    .field input,.field select { width:100%; height:42px; padding:0 13px; color:var(--ink); background:#fbfdff; border:1px solid var(--line); border-radius:6px; font:14px Arial,sans-serif; }
    .field input[readonly] { background:#f1f6f9; cursor:default; }
    .filter-actions { grid-column:1/-1; display:flex; align-items:center; flex-wrap:wrap; gap:8px; padding-top:4px; }
    .entries-limit { display:flex; align-items:center; gap:8px; }
    .entries-actions { display:flex; align-items:center; flex-wrap:wrap; gap:8px; margin-left:auto; }
    .selected-label { margin-right:2px; color:var(--muted); font-size:.8rem; font-weight:bold; }
    .action-button, .filter-button { padding:8px 11px; background:#fff; border:1px solid; border-radius:5px; cursor:pointer; font:12px Arial,sans-serif; font-weight:bold; }
    .receive-button { color:#28734f; border-color:#28734f; }
    .return-button, .remove-button, .clear-button { color:#a83b3b; border-color:#a83b3b; }
    .bulk-action-form { display:inline-flex; margin:0; }
    .restore-button { color:#28734f; border-color:#28734f; }
    .route-button { color:var(--ink); border-color:#9aaab5; }
    .search-button { color:#fff; background:var(--blue); border-color:var(--blue); }
    .button-icon { margin-right:4px; font-weight:bold; }
    .search-icon { position:relative; display:inline-block; width:11px; height:11px; margin:0 6px 0 1px; border:1.5px solid currentColor; border-radius:50%; vertical-align:-1px; }
    .search-icon::after { content:''; position:absolute; right:-4px; bottom:-2px; width:5px; height:1.5px; background:currentColor; transform:rotate(45deg); }
    .action-divider { width:1px; height:22px; margin:0 5px; background:var(--line); }
    .entries { display:grid; gap:10px; padding-top:18px; }
    .entries-toolbar { display:flex; align-items:center; gap:8px; padding-top:18px; color:var(--muted); font-size:.83rem; }
    .entries-toolbar select { padding:7px 28px 7px 9px; color:var(--ink); background:#fff; border:1px solid var(--line); border-radius:5px; font:13px Arial,sans-serif; }
    .table-wrap { overflow-x:auto; padding-top:14px; }
    .document-preview { margin:20px 0 8px; padding:18px 0 22px; border-bottom:1px solid var(--line); }
    .document-preview-heading { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:14px; }
    .document-preview-heading h3 { margin:0; color:var(--navy); font: bold 1.15rem Georgia,'Times New Roman',serif; }
    .document-preview-heading a { color:var(--blue); font-size:.85rem; font-weight:bold; text-decoration:none; }
    .document-metadata { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin:0 0 18px; }
    .document-metadata div { min-width:0; }
    .document-metadata dt { margin-bottom:4px; color:var(--muted); font-size:.68rem; font-weight:bold; text-transform:uppercase; }
    .document-metadata dd { margin:0; overflow-wrap:anywhere; font-size:.88rem; }
    .document-body-preview { min-height:260px; padding:34px clamp(20px,6vw,72px); color:#202a33; background:#fff; border:1px solid var(--line); font:16px/1.6 Georgia,'Times New Roman',serif; }
    .documents-table { width:100%; min-width:1080px; border-collapse:collapse; font-size:.8rem; }
    .documents-table th { padding:11px 10px; color:var(--muted); background:#f1f6f9; border-bottom:1px solid var(--line); font-size:.7rem; letter-spacing:.04em; text-align:left; text-transform:uppercase; white-space:nowrap; }
    .documents-table td { padding:13px 10px; border-bottom:1px solid var(--line); vertical-align:top; }
    .documents-table tbody tr:hover { background:#f8fbfd; }
    .field-stack { display:grid; align-content:start; gap:12px; }
    .tool-actions { display:flex; flex-wrap:wrap; gap:5px; min-width:220px; }
    .tool-link,.route-tool-button { display:inline-block; padding:6px 8px; color:#fff; background:var(--blue); border:0; border-radius:4px; cursor:pointer; font: bold 11px Arial,sans-serif; text-decoration:none; white-space:nowrap; }
    .tool-link:hover,.route-tool-button:hover { background:var(--navy); }
    .route-tool-form { display:inline-flex; margin:0; }
    .route-dialog { width:min(560px,calc(100% - 32px)); max-height:min(760px,calc(100% - 32px)); padding:0; color:var(--ink); background:#fff; border:1px solid var(--line); border-radius:8px; box-shadow:0 18px 55px rgba(16,42,67,.28); }
    .route-dialog::backdrop { background:rgba(16,42,67,.56); }
    .route-dialog form { display:grid; gap:16px; padding:22px; }
    .route-dialog-heading { display:flex; align-items:start; justify-content:space-between; gap:16px; padding-bottom:14px; border-bottom:1px solid var(--line); }
    .route-dialog-heading h3 { margin:0; color:var(--navy); font: bold 1.25rem Georgia,'Times New Roman',serif; }
    .route-dialog-heading p { margin:6px 0 0; color:var(--muted); font-size:.85rem; }
    .route-dialog-close { width:32px; height:32px; color:var(--muted); background:#f1f6f9; border:0; border-radius:4px; cursor:pointer; font-size:20px; }
    .route-destinations { display:grid; gap:7px; max-height:52vh; overflow:auto; }
    .route-destination { display:flex; align-items:center; gap:10px; min-height:42px; padding:9px 11px; background:#f8fbfd; border:1px solid var(--line); border-radius:5px; cursor:pointer; font-size:.9rem; }
    .route-destination:has(input:checked) { background:#eaf5ff; border-color:var(--blue); }
    #routeDetails:not([hidden]) { display:grid; gap:14px; }
    .route-dialog textarea { width:100%; min-height:84px; padding:10px 13px; color:var(--ink); background:#fbfdff; border:1px solid var(--line); border-radius:6px; font:14px Arial,sans-serif; resize:vertical; }
    .route-dialog-actions { display:flex; justify-content:flex-end; gap:8px; padding-top:12px; border-top:1px solid var(--line); }
    .route-cancel,.route-confirm { padding:10px 14px; border:0; border-radius:4px; cursor:pointer; font-weight:bold; }
    .route-cancel { color:var(--ink); background:#edf2f5; }
    .route-confirm { color:#fff; background:var(--blue); }
    .entry { display:grid; grid-template-columns:1.25fr 1fr 1fr .8fr; gap:18px; align-items:center; padding:16px; background:#f8fbfd; border:1px solid var(--line); border-radius:6px; }
    .entry-subject { color:var(--navy); font-weight:bold; }
    .entry-file,.entry-type,.entry-date { color:var(--muted); font-size:.83rem; }
    .empty { padding:45px 10px 25px; color:var(--muted); text-align:center; }
    @media (max-width:1100px) { .topbar { grid-template-columns:auto auto; justify-content:space-between; padding-top:14px; padding-bottom:14px; } .navigation { grid-column:1/-1; grid-row:2; max-width:none; } }
    @media (max-width:720px) { .user-area span{display:none}.layout{padding-top:32px}.heading-row{display:block}.date{margin-top:10px}.filters{grid-template-columns:repeat(2,1fr)}.draft-form{grid-template-columns:1fr}.draft-form h3,.draft-error,.draft-success,.draft-form-actions,.editor-section{grid-column:1}.editor-heading{align-items:flex-start}.editor-workspace{padding:12px}.document-editor{min-height:760px;padding:28px 22px}.document-metadata{grid-template-columns:repeat(2,minmax(0,1fr))}.content-panel{padding:22px 18px}.entry{grid-template-columns:1fr;gap:7px}.entry-date{text-align:left} }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="brand"><img src="https://upload.wikimedia.org/wikipedia/commons/9/98/Philippine_National_Police_seal.svg" alt="PNP seal"><span>PNP EFDS</span></div>
    <nav class="navigation" aria-label="Document status navigation">
      <a class="nav-button <?= $view === 'for_action' ? 'active' : '' ?>" href="dashboard.php?view=for_action">For Action<span class="nav-icon" aria-hidden="true">&#9888;</span></a>
      <details class="nav-menu" <?= $view === 'draft' && $activeDraftType !== '' ? 'open' : '' ?>>
        <summary class="nav-button <?= $view === 'draft' && $activeDraftType === '' ? 'active' : '' ?>">Draft<span class="nav-icon" aria-hidden="true">&#9998;</span></summary>
        <div class="draft-options" aria-label="Draft document types">
          <a class="draft-option <?= $view === 'draft' && $activeDraftType === 'Correspondence' ? 'active' : '' ?>" href="dashboard.php?view=draft&new=1&draft_type=Correspondence">Correspondence</a>
          <a class="draft-option <?= $view === 'draft' && $activeDraftType === 'PNP Radio Message' ? 'active' : '' ?>" href="dashboard.php?view=draft&new=1&draft_type=PNP%20Radio%20Message">PNP Radio Message</a>
          <a class="draft-option <?= $view === 'draft' && $activeDraftType === 'Invistigation Report' ? 'active' : '' ?>" href="dashboard.php?view=draft&new=1&draft_type=Invistigation%20Report">Investigation Report</a>
        </div>
      </details>
      <details class="nav-menu" <?= $view === 'within_office' && $activeWithinType !== '' ? 'open' : '' ?>>
        <summary class="nav-button <?= $view === 'within_office' && $activeWithinType === '' ? 'active' : '' ?>">Within Office<span class="nav-icon" aria-hidden="true">&#8646;</span></summary>
        <div class="draft-options" aria-label="Within Office document types">
          <a class="draft-option <?= $view === 'within_office' && $activeWithinType === 'Received Documents' ? 'active' : '' ?>" href="dashboard.php?view=within_office&within_type=Received%20Documents">Received Documents</a>
        </div>
      </details>
      <a class="nav-button <?= $view === 'outside_office' ? 'active' : '' ?>" href="dashboard.php?view=outside_office">Outside Office<span class="nav-icon" aria-hidden="true">&#10132;</span></a>
      <a class="nav-button <?= $view === 'archived' ? 'active' : '' ?>" href="dashboard.php?view=archived">Archived<span class="nav-icon" aria-hidden="true">&#128451;</span></a>
    </nav>
    <div class="user-area"><div class="user-details"><span><?= e((string)$_SESSION['full_name']) ?></span><a class="logout" href="logout.php">Log out</a></div><div class="avatar">JD</div></div>
  </header>
  <main class="layout">
    <section class="content-panel">
      <div class="panel-heading"><h2><?= $view === 'draft' ? 'New Draft' : e($sectionTitle) ?></h2><?php if ($view !== 'draft' && $sectionDescription !== ''): ?><p><?= e($sectionDescription) ?></p><?php endif; ?></div>
      <?php if ($flashMessage !== ''): ?><p class="draft-success" role="status"><?= e($flashMessage) ?></p><?php endif; ?>
      <?php if ($showDraftForm): ?>
        <form class="draft-form" method="post" action="dashboard.php?view=draft&amp;new=1&amp;draft_type=<?= rawurlencode($draftType) ?>">
          <?php if ($draftSaved): ?><p class="draft-success" role="status">Draft saved.</p><?php endif; ?>
          <?php if ($draftError !== ''): ?><p class="draft-error" role="alert"><?= e($draftError) ?></p><?php endif; ?>
          <input type="hidden" name="form_action" value="create_draft">
          <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
          <div class="field"><label for="draft_subject">Subject</label><input id="draft_subject" name="subject" value="<?= e($draftValues['subject']) ?>" required></div>
          <div class="field"><label for="draft_file_name">File name</label><input id="draft_file_name" name="file_name" value="<?= e($draftValues['file_name']) ?>" required></div>
          <div class="field"><label for="draft_from">From</label><input id="draft_from" name="from_unit" value="<?= e($draftValues['from_unit']) ?>" required></div>
          <div class="field"><label for="draft_to">To</label><input id="draft_to" name="to_unit" value="<?= e($draftValues['to_unit']) ?>" required></div>
          <div class="field"><label for="draft_type">Type</label><input id="draft_type" value="<?= e($draftValues['document_type'] === 'Invistigation Report' ? 'Investigation Report' : $draftValues['document_type']) ?>" readonly></div>
          <div class="field"><label for="draft_stl_type">STL Type</label><input id="draft_stl_type" name="stl_type" value="<?= e($draftValues['stl_type']) ?>" required></div>
          <div class="field"><label for="draft_priority">Priority</label><select id="draft_priority" name="priority" required><option value="Yes" <?= $draftValues['priority'] === 'Yes' ? 'selected' : '' ?>>Yes</option><option value="No" <?= $draftValues['priority'] === 'No' ? 'selected' : '' ?>>No</option></select></div>
          <input id="documentContent" type="hidden" name="document_content" value="<?= e($draftContent) ?>">
          <section class="editor-section" aria-label="Document editor">
            <div class="editor-heading"><h3>Document editor</h3><button class="docx-button" id="downloadDocx" type="button">Download .docx</button></div>
            <div class="editor-toolbar" role="toolbar" aria-label="Document formatting">
              <button class="editor-tool" type="button" data-command="bold" aria-label="Bold" title="Bold"><strong>B</strong></button>
              <button class="editor-tool" type="button" data-command="italic" aria-label="Italic" title="Italic"><em>I</em></button>
              <button class="editor-tool" type="button" data-command="underline" aria-label="Underline" title="Underline"><u>U</u></button>
              <button class="editor-tool" type="button" data-command="strikeThrough" aria-label="Strikethrough" title="Strikethrough"><s>S</s></button>
              <span class="editor-divider" aria-hidden="true"></span>
              <button class="editor-tool" type="button" data-command="justifyLeft" aria-label="Align left" title="Align left">&#8676;</button>
              <button class="editor-tool" type="button" data-command="justifyCenter" aria-label="Align center" title="Align center">&#8596;</button>
              <button class="editor-tool" type="button" data-command="justifyRight" aria-label="Align right" title="Align right">&#8677;</button>
              <span class="editor-divider" aria-hidden="true"></span>
              <button class="editor-tool" type="button" data-command="insertUnorderedList" aria-label="Bulleted list" title="Bulleted list">&#8226; List</button>
              <button class="editor-tool" type="button" data-command="insertOrderedList" aria-label="Numbered list" title="Numbered list">1. List</button>
              <button class="editor-tool" type="button" data-command="undo" aria-label="Undo" title="Undo">&#8630;</button>
              <button class="editor-tool" type="button" data-command="redo" aria-label="Redo" title="Redo">&#8631;</button>
            </div>
            <div class="editor-workspace"><div id="documentEditor" class="document-editor" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Document content" data-placeholder="Start writing your document..."></div></div>
          </section>
          <div class="draft-form-actions"><button class="draft-submit" type="submit" name="destination" value="draft">Save Draft</button><button class="draft-send" type="submit" name="destination" value="for_action">Send to For Action</button><a class="draft-cancel" href="dashboard.php?view=for_action">Cancel</a></div>
        </form>
      <?php endif; ?>
      <?php if ($view !== 'draft'): ?>
      <form class="filters" id="filterForm" method="get">
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <input type="hidden" name="draft_type" value="<?= e($draftType) ?>">
        <input type="hidden" name="within_type" value="<?= e($withinType) ?>">
        <div class="field-stack"><div class="field"><label for="subject">Subject</label><input id="subject" name="subject" value="<?= e($subject) ?>" placeholder="Enter subject"></div><?php if ($view === 'within_office' && $activeWithinType === 'Received Documents'): ?><div class="field"><label for="date_received">Date Received</label><input id="date_received" name="received_date" type="date" value="<?= e($receivedDate) ?>"></div><?php endif; ?></div>
        <div class="field"><label for="file_name">File name</label><input id="file_name" name="file_name" value="<?= e($fileName) ?>" placeholder="Enter file name"></div>
        <div class="field"><label for="created_date">Created date</label><input id="created_date" name="created_date" type="date" value="<?= e($createdDate) ?>"></div>
        <div class="field"><label for="type">Type</label><select id="type" name="type"><option value="">All types</option><option <?= $type === 'Correspondence' ? 'selected' : '' ?>>Correspondence</option><option <?= $type === 'PNP Radio Message' ? 'selected' : '' ?>>PNP Radio Message</option><option value="Invistigation Report" <?= $type === 'Invistigation Report' ? 'selected' : '' ?>>Investigation Report</option></select></div>
      </form>
      <?php if ($selectedDocument !== null): ?>
        <article class="document-preview">
          <div class="document-preview-heading"><h3><?= e((string)$selectedDocument['subject']) ?></h3><div><button class="docx-button" id="downloadViewedDocx" type="button">Download .docx</button> <a href="dashboard.php?view=<?= rawurlencode($view) ?>">Close</a></div></div>
          <dl class="document-metadata">
            <div><dt>File name</dt><dd><?= e((string)$selectedDocument['file_name']) ?></dd></div>
            <div><dt>From</dt><dd><?= e((string)($selectedDocument['from_unit'] ?? '-')) ?></dd></div>
            <div><dt>To</dt><dd><?= e((string)($selectedDocument['to_unit'] ?? '-')) ?></dd></div>
            <div><dt>Type</dt><dd><?= e((string)$selectedDocument['document_type']) ?></dd></div>
            <div><dt>STL Type</dt><dd><?= e((string)($selectedDocument['stl_type'] ?? '-')) ?></dd></div>
            <div><dt>Priority</dt><dd><?= e((string)($selectedDocument['priority'] ?? '-')) ?></dd></div>
            <div><dt>Date In</dt><dd><?= e((string)($selectedDocument['date_in'] ?? '-')) ?></dd></div>
            <div><dt>Action Required</dt><dd><?= e((string)($selectedDocument['action_requested'] ?? '-')) ?></dd></div>
            <?php if (trim((string)($selectedDocument['remarks'] ?? '')) !== ''): ?><div><dt>Remarks</dt><dd><?= nl2br(e((string)$selectedDocument['remarks'])) ?></dd></div><?php endif; ?>
          </dl>
          <div class="document-body-preview"><?= clean_document_content((string)($selectedDocument['document_content'] ?? '')) ?: '<p>No document content.</p>' ?></div>
        </article>
      <?php endif; ?>
      <div class="entries-toolbar">
        <form class="entries-limit" method="get">
          <input type="hidden" name="view" value="<?= e($view) ?>">
          <input type="hidden" name="subject" value="<?= e($subject) ?>">
          <input type="hidden" name="file_name" value="<?= e($fileName) ?>">
          <input type="hidden" name="created_date" value="<?= e($createdDate) ?>">
          <input type="hidden" name="received_date" value="<?= e($receivedDate) ?>">
          <input type="hidden" name="type" value="<?= e($type) ?>">
          <input type="hidden" name="draft_type" value="<?= e($draftType) ?>">
          <input type="hidden" name="within_type" value="<?= e($withinType) ?>">
          <label for="entriesLimit">Show</label>
          <select id="entriesLimit" name="limit" onchange="this.form.submit()">
            <?php foreach ([10, 25, 50, 100] as $option): ?><option value="<?= $option ?>" <?= $entriesLimit === $option ? 'selected' : '' ?>><?= $option ?></option><?php endforeach; ?>
          </select>
          <span>entries</span>
        </form>
        <div class="entries-actions">
          <?php if (!($view === 'within_office' && $activeWithinType === 'Received Documents')): ?>
          <span class="selected-label">With selected:</span>
          <?php if ($view === 'for_action'): ?>
          <button class="action-button receive-button" type="submit" form="bulkActionForm" name="bulk_action" value="receive" data-action="Receive">Receive</button>
          <?php else: ?>
          <button class="action-button receive-button" type="button" data-action="Receive">Receive</button>
          <?php endif; ?>
          <button class="action-button return-button" type="button" data-action="Return">Return</button>
          <?php endif; ?>
          <?php if ($view === 'for_action'): ?>
            <form class="bulk-action-form" id="bulkActionForm" method="post">
              <input type="hidden" name="form_action" value="bulk_documents">
              <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
              <button class="action-button remove-button" type="submit" name="bulk_action" value="archive">Remove from For Action</button>
            </form>
          <?php elseif ($view === 'archived'): ?>
            <form class="bulk-action-form" id="bulkActionForm" method="post">
              <input type="hidden" name="form_action" value="bulk_documents">
              <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
              <button class="action-button restore-button" type="submit" name="bulk_action" value="restore">Restore to For Action</button>
            </form>
          <?php endif; ?>
          <?php if (!($view === 'within_office' && $activeWithinType === 'Received Documents')): ?>
          <button class="action-button route-button" type="button" data-action="Route">Route</button>
          <span class="action-divider" aria-hidden="true"></span>
          <?php endif; ?>
          <button class="filter-button clear-button" id="clearFilter" type="button"><span class="button-icon" aria-hidden="true">&#10005;</span>Clear Filter</button>
          <button class="filter-button search-button" id="searchButton" type="button"><span class="search-icon" aria-hidden="true"></span>Search</button>
        </div>
      </div>
      <div class="table-wrap">
        <table class="documents-table">
          <thead><tr><th><input id="selectAll" type="checkbox" aria-label="Select all documents"></th><th>Subject</th><th>Filename</th><th>From</th><th>To</th><th>Type</th><th>STL Type</th><th>Priority</th><th>Date In</th><th>Action Requested</th><th>Tools</th></tr></thead>
          <tbody>
          <?php if ($documents === []): ?><tr><td class="empty" colspan="11">No documents to display.</td></tr><?php endif; ?>
          <?php foreach ($documents as $document): ?>
            <tr>
              <td><input class="document-check" type="checkbox" aria-label="Select document" <?= in_array($view, ['for_action', 'archived'], true) ? 'form="bulkActionForm" name="document_ids[]" value="' . (int)$document['id'] . '"' : '' ?>></td>
              <td><?= e((string)$document['subject']) ?></td>
              <td><?= e((string)$document['file_name']) ?></td>
              <td><?= e((string)($document['from_unit'] ?? '-')) ?></td>
              <td><?= e((string)($document['to_unit'] ?? '-')) ?></td>
              <td><?= e((string)$document['document_type']) ?></td>
              <td><?= e((string)($document['stl_type'] ?? '-')) ?></td>
              <td><?= e((string)($document['priority'] ?? '-')) ?></td>
              <td><?= e((string)($document['date_in'] ?? '-')) ?></td>
              <td><?= e((string)($document['action_requested'] ?? '-')) ?></td>
              <td><div class="tool-actions"><a class="tool-link" href="dashboard.php?view=<?= rawurlencode($view) ?>&amp;document_id=<?= (int)$document['id'] ?>">View</a>
                <?php if (in_array($view, ['for_action', 'within_office', 'outside_office'], true)): ?>
                  <button class="route-tool-button" type="button" data-route-target="within_office" data-document-id="<?= (int)$document['id'] ?>">Route Within</button>
                  <button class="route-tool-button" type="button" data-route-target="outside_office" data-document-id="<?= (int)$document['id'] ?>">Route Outside</button>
                <?php endif; ?>
              </div></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>
  </main>
  <dialog class="route-dialog" id="routeDialog" aria-labelledby="routeDialogTitle">
    <form method="post" id="routeForm">
      <input type="hidden" name="form_action" value="route_document">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="document_id" id="routeDocumentId">
      <input type="hidden" name="route_target" id="routeTarget">
      <div class="route-dialog-heading">
        <div><h3 id="routeDialogTitle"></h3><p id="routeDialogDescription"></p></div>
        <button class="route-dialog-close" type="button" aria-label="Close route panel">&times;</button>
      </div>
      <div class="route-destinations" id="routeDestinations"></div>
      <div id="routeDetails" hidden>
        <div class="field"><label for="routeRemarks">Add Remarks</label><textarea id="routeRemarks" name="remarks" maxlength="5000" disabled></textarea></div>
        <div class="field"><label for="actionRequired">Action Required</label><select id="actionRequired" name="action_required" required disabled><option value="">Select action required</option><optgroup label="Appropriate action"><?php foreach ($actionRequiredOptions as $option): ?><option value="<?= e($option) ?>"><?= e($option) ?></option><?php endforeach; ?></optgroup></select></div>
      </div>
      <div class="route-dialog-actions"><button class="route-cancel" type="button">Cancel</button><button class="route-confirm" type="submit">Confirm Route</button></div>
    </form>
  </dialog>
  <?php if ($view === 'draft' || $selectedDocument !== null): ?><script src="https://cdn.jsdelivr.net/npm/html-docx-js@0.3.1/dist/html-docx.js"></script><?php endif; ?>
  <script>
    const documentEditor = document.getElementById('documentEditor');
    const documentContent = document.getElementById('documentContent');
    const draftForm = document.querySelector('.draft-form');

    if (documentEditor && documentContent?.value) {
      documentEditor.innerHTML = documentContent.value;
    }

    document.querySelectorAll('.editor-tool').forEach((button) => {
      button.addEventListener('mousedown', (event) => event.preventDefault());
      button.addEventListener('click', () => {
        documentEditor?.focus();
        document.execCommand(button.dataset.command, false);
      });
    });

    draftForm?.addEventListener('submit', () => {
      if (documentEditor && documentContent) {
        documentContent.value = documentEditor.innerHTML;
      }
    });

    document.getElementById('downloadDocx')?.addEventListener('click', () => {
      if (!documentEditor || !documentEditor.innerText.trim()) {
        window.alert('Add document content before downloading.');
        return;
      }
      if (!window.htmlDocx) {
        window.alert('Word export is unavailable. Check your internet connection and try again.');
        return;
      }
      const html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:Georgia,serif;font-size:12pt;line-height:1.6}</style></head><body>' + documentEditor.innerHTML + '</body></html>';
      const blob = window.htmlDocx.asBlob(html);
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = (document.getElementById('draft_file_name')?.value || 'document') + '.docx';
      link.click();
      URL.revokeObjectURL(url);
    });

    document.getElementById('downloadViewedDocx')?.addEventListener('click', () => {
      const preview = document.querySelector('.document-preview');
      if (!preview || !window.htmlDocx) {
        window.alert('Word export is unavailable. Check your internet connection and try again.');
        return;
      }

      const escapeHtml = (value) => value.replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
      })[character]);
      const title = preview.querySelector('.document-preview-heading h3')?.textContent.trim() || 'document';
      const metadata = [...preview.querySelectorAll('.document-metadata div')].map((item) => {
        const label = item.querySelector('dt')?.textContent.trim() || '';
        const value = item.querySelector('dd')?.textContent.trim() || '';
        return '<p><strong>' + escapeHtml(label) + ':</strong> ' + escapeHtml(value) + '</p>';
      }).join('');
      const content = preview.querySelector('.document-body-preview')?.innerHTML || '<p>No document content.</p>';
      const html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:Georgia,serif;font-size:12pt;line-height:1.6}h1{font-size:18pt}h2{font-size:16pt}</style></head><body><h1>' + escapeHtml(title) + '</h1>' + metadata + '<hr>' + content + '</body></html>';
      const blob = window.htmlDocx.asBlob(html);
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = (preview.querySelector('.document-metadata dd')?.textContent.trim() || title).replace(/[\\/:*?"<>|]/g, '_') + '.docx';
      link.click();
      URL.revokeObjectURL(url);
    });

    document.addEventListener('click', (event) => {
      document.querySelectorAll('.navigation .nav-menu[open]').forEach((menu) => {
        if (!menu.contains(event.target)) {
          menu.open = false;
        }
      });
    });

    document.getElementById('clearFilter')?.addEventListener('click', () => {
      window.location.href = 'dashboard.php?view=<?= e($view) ?>';
    });

    document.getElementById('searchButton')?.addEventListener('click', () => {
      document.getElementById('filterForm')?.submit();
    });

    document.getElementById('selectAll')?.addEventListener('change', (event) => {
      document.querySelectorAll('.document-check').forEach((checkbox) => {
        checkbox.checked = event.target.checked;
      });
    });

    const routeOptions = <?= json_encode($routeOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const routeDialog = document.getElementById('routeDialog');
    const routeDestinations = document.getElementById('routeDestinations');
    const routeForm = document.getElementById('routeForm');

    document.querySelectorAll('[data-route-target]').forEach((button) => {
      button.addEventListener('click', () => {
        const target = button.dataset.routeTarget;
        const isWithin = target === 'within_office';
        document.getElementById('routeDialogTitle').textContent = isWithin ? 'Route Within Station' : 'Route Outside Station';
        document.getElementById('routeDialogDescription').textContent = 'Choose the position to route this document to.';
        document.getElementById('routeTarget').value = target;
        document.getElementById('routeDocumentId').value = button.dataset.documentId;
        const showRouteDetails = <?= $view === 'for_action' ? 'true' : 'false' ?>;
        const routeDetails = document.getElementById('routeDetails');
        routeDetails.hidden = !showRouteDetails;
        routeDetails.querySelectorAll('textarea,select').forEach((field) => {
          field.disabled = !showRouteDetails;
        });
        routeDestinations.replaceChildren();

        routeOptions[target].forEach((destination, index) => {
          const label = document.createElement('label');
          const input = document.createElement('input');
          input.type = 'radio';
          input.name = 'route_destination';
          input.value = destination;
          input.required = index === 0;
          label.className = 'route-destination';
          label.append(input, document.createTextNode(destination));
          routeDestinations.append(label);
        });

        routeDialog.showModal();
      });
    });

    document.querySelectorAll('.route-dialog-close,.route-cancel').forEach((button) => {
      button.addEventListener('click', () => routeDialog.close());
    });

    routeDialog?.addEventListener('click', (event) => {
      if (event.target === routeDialog) routeDialog.close();
    });

    document.querySelectorAll('.selected-label ~ .action-button').forEach((button) => {
      button.addEventListener('click', (event) => {
        const selected = document.querySelectorAll('.document-check:checked').length;
        if (selected === 0) {
          if (button.type === 'submit') event.preventDefault();
          window.alert('Select at least one document first.');
          return;
        }
        if (button.type !== 'submit') {
          window.alert(button.dataset.action + ' action selected for ' + selected + ' document(s).');
        }
      });
    });
  </script>
</body>
</html>
