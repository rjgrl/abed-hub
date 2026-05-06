<?php
require_once 'config/database.php';
require_once 'functions/helpers.php';

requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'];
$approval_status = isSuperAdmin() ? 'Approved' : 'Pending';

$project_code = sanitize($_POST['project_code'] ?? '');
$project_title = sanitize($_POST['project_title'] ?? '');
$fund_source = sanitize($_POST['fund_source'] ?? '');
$funding_year = intval($_POST['funding_year'] ?? date('Y'));
$beneficiary = sanitize($_POST['beneficiary'] ?? '');
$description = sanitize($_POST['description'] ?? '');
$proposed_amount = floatval($_POST['proposed_amount'] ?? 0);
$allocated_amount = floatval($_POST['allocated_amount'] ?? 0);
$province = sanitize($_POST['province'] ?? '');
$municipality = sanitize($_POST['municipality'] ?? '');
$barangay = sanitize($_POST['barangay'] ?? '');
$district = sanitize($_POST['district'] ?? '');
$latitude = floatval($_POST['latitude'] ?? 0);
$longitude = floatval($_POST['longitude'] ?? 0);

// Compatible with both schemas: afme.specifications (current) or afme.description (legacy).
$afmeSpecColumn = 'specifications';
$colCheck = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'afme' AND COLUMN_NAME IN ('specifications','description')");
if ($colCheck) {
    $found = [];
    while ($r = $colCheck->fetch_assoc()) {
        $found[] = (string) ($r['COLUMN_NAME'] ?? '');
    }
    if (in_array('specifications', $found, true)) {
        $afmeSpecColumn = 'specifications';
    } elseif (in_array('description', $found, true)) {
        $afmeSpecColumn = 'description';
    }
}

if (empty($project_code) || empty($project_title) || empty($fund_source)) {
    echo json_encode(['status' => 'error', 'message' => 'Required fields missing']);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM projects WHERE project_code = ?");
$stmt->bind_param("s", $project_code);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Project code already exists']);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO projects (
        project_type, project_code, title, fund_source, funding_year,
        beneficiary, description, province, municipality, barangay, district,
        proposed_amount, allocated_amount, latitude, longitude, user_id, approval_status
    ) VALUES ('afme', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param(
    'sssissssssddddis',
    $project_code, $project_title, $fund_source, $funding_year,
    $beneficiary, $description, $province, $municipality, $barangay, $district,
    $proposed_amount, $allocated_amount, $latitude, $longitude, $user_id, $approval_status
);

try {
    $conn->begin_transaction();
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Could not start transaction. Please try again.']);
    exit;
}

if ($stmt->execute()) {
    $project_id = $stmt->insert_id;

    // Keep AFME list/pages in sync: create a base AFME row linked to this project.
    // This ensures newly approved AFME projects appear on homepage AFME widgets.
    try {
        $seedSql = "INSERT INTO afme (
            project_id, machine_name, {$afmeSpecColumn}, beneficiary_name,
            farm_location, amount_proposed, amount_allocated, funding_year,
            indicative_funding_year, fund_source, current_status, latitude, longitude
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'For Validation', ?, ?)";
        $seed = $conn->prepare($seedSql);
        if (!$seed) {
            throw new Exception('Failed to initialize AFME row: ' . $conn->error);
        }

        $specText = $description;
        $farmLocation = trim(implode(', ', array_filter([$district, $barangay, $municipality, $province], fn($v) => $v !== '')));
        $seed->bind_param(
            'issssddiissd',
            $project_id,
            $project_title,
            $specText,
            $beneficiary,
            $farmLocation,
            $proposed_amount,
            $allocated_amount,
            $funding_year,
            $funding_year,
            $fund_source,
            $latitude,
            $longitude
        );
        if (!$seed->execute()) {
            throw new Exception('AFME row insert failed: ' . $seed->error);
        }
        $seed->close();

        $conn->commit();
        logAudit('CREATE_AFME_PROJECT', 'AFME', $project_id, null, $_POST);

        $msg = $approval_status === 'Pending'
            ? 'AFME project submitted for Super Admin approval. It will appear in the catalog once approved.'
            : 'AFME Project registered successfully';
        echo json_encode([
            'status' => 'success',
            'message' => $msg,
            'project_id' => $project_id,
            'approval_status' => $approval_status,
        ]);
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('register-afme-project bootstrap afme failed: ' . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Could not save AFME project details. Please try again.',
        ]);
    }
} else {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Error registering project']);
}

$stmt->close();
$conn->close();
?>

