<?php
$page_title = 'Medical Records';
require_once '../includes/header.php';

$session->requireRole('doctor');

$database = new Database();
$db = $database->getConnection();
$current_user = $session->getCurrentUser();
$hospital_id = $current_user['hospital_id'];

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $child_id = $_POST['child_id'] ?? '';
    $weight_kg = $_POST['weight_kg'] ?? '';
    $height_cm = $_POST['height_cm'] ?? '';
    $temperature = $_POST['temperature'] ?? '';
    $blood_pressure = $_POST['blood_pressure'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $visit_date = $_POST['visit_date'] ?? '';
    
    if (empty($child_id) || empty($visit_date)) {
        $error = 'Please select a child and visit date';
    } else {
        // Calculate age in months
        $child_query = "SELECT date_of_birth FROM children WHERE child_id = :child_id";
        $child_stmt = $db->prepare($child_query);
        $child_stmt->bindParam(':child_id', $child_id);
        $child_stmt->execute();
        $child_data = $child_stmt->fetch(PDO::FETCH_ASSOC);
        
        $age_months = $utils->getChildAgeInMonths($child_data['date_of_birth']);
        
        $query = "INSERT INTO child_medical_records (child_id, recorded_by, weight_kg, height_cm, 
                 age_months, temperature, blood_pressure, notes, visit_date) 
                 VALUES (:child_id, :recorded_by, :weight_kg, :height_cm, :age_months, 
                 :temperature, :blood_pressure, :notes, :visit_date)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':child_id', $child_id);
        $stmt->bindParam(':recorded_by', $current_user['user_id']);
        $stmt->bindParam(':weight_kg', $weight_kg);
        $stmt->bindParam(':height_cm', $height_cm);
        $stmt->bindParam(':age_months', $age_months);
        $stmt->bindParam(':temperature', $temperature);
        $stmt->bindParam(':blood_pressure', $blood_pressure);
        $stmt->bindParam(':notes', $notes);
        $stmt->bindParam(':visit_date', $visit_date);
        
        if ($stmt->execute()) {
            $success = 'Medical record added successfully';
        } else {
            $error = 'Failed to add medical record';
        }
    }
}

// Get children for this hospital
$children_query = "SELECT child_id, child_name, registration_number, date_of_birth, gender 
                   FROM children WHERE hospital_id = :hospital_id ORDER BY child_name";
$children_stmt = $db->prepare($children_query);
$children_stmt->bindParam(':hospital_id', $hospital_id);
$children_stmt->execute();
$children = $children_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent medical records
$records_query = "SELECT cmr.*, c.child_name, c.registration_number, u.full_name as doctor_name
                  FROM child_medical_records cmr
                  JOIN children c ON cmr.child_id = c.child_id
                  JOIN users u ON cmr.recorded_by = u.user_id
                  WHERE c.hospital_id = :hospital_id
                  ORDER BY cmr.visit_date DESC, cmr.created_at DESC
                  LIMIT 20";
$records_stmt = $db->prepare($records_query);
$records_stmt->bindParam(':hospital_id', $hospital_id);
$records_stmt->execute();
$recent_records = $records_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Medical Records</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="growth_charts.php" class="btn btn-outline-secondary me-2">
            <i class="fas fa-chart-area me-1"></i>Growth Charts
        </a>
        <a href="children.php" class="btn btn-outline-secondary">
            <i class="fas fa-baby me-1"></i>View Children
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-plus me-2"></i>Add Medical Record</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="child_id" class="form-label">Select Child *</label>
                        <select class="form-select" id="child_id" name="child_id" required onchange="loadChildInfo()">
                            <option value="">Choose a child...</option>
                            <?php foreach ($children as $child): ?>
                            <option value="<?php echo $child['child_id']; ?>" 
                                    data-birth="<?php echo $child['date_of_birth']; ?>"
                                    data-gender="<?php echo $child['gender']; ?>">
                                <?php echo htmlspecialchars($child['child_name']); ?> 
                                (<?php echo htmlspecialchars($child['registration_number']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="child-info" class="alert alert-info d-none">
                        <small id="child-details"></small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="visit_date" class="form-label">Visit Date *</label>
                        <input type="date" class="form-control" id="visit_date" name="visit_date" 
                               value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="weight_kg" class="form-label">Weight (kg)</label>
                                <input type="number" class="form-control" id="weight_kg" name="weight_kg" 
                                       step="0.1" min="0" max="200">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="height_cm" class="form-label">Height (cm)</label>
                                <input type="number" class="form-control" id="height_cm" name="height_cm" 
                                       step="0.1" min="0" max="250">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="temperature" class="form-label">Temperature (°C)</label>
                                <input type="number" class="form-control" id="temperature" name="temperature" 
                                       step="0.1" min="30" max="45">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="blood_pressure" class="form-label">Blood Pressure</label>
                                <input type="text" class="form-control" id="blood_pressure" name="blood_pressure" 
                                       placeholder="120/80">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Clinical Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="4" 
                                  placeholder="Enter examination findings, diagnoses, recommendations..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save me-2"></i>Save Medical Record
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-file-medical me-2"></i>Recent Medical Records</h5>
                <div class="btn-group btn-group-sm" role="group">
                    <input type="radio" class="btn-check" name="filter" id="all" autocomplete="off" checked>
                    <label class="btn btn-outline-primary" for="all">All</label>
                    
                    <input type="radio" class="btn-check" name="filter" id="today" autocomplete="off">
                    <label class="btn btn-outline-primary" for="today">Today</label>
                    
                    <input type="radio" class="btn-check" name="filter" id="week" autocomplete="off">
                    <label class="btn btn-outline-primary" for="week">This Week</label>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="recordsTable">
                        <thead>
                            <tr>
                                <th>Child</th>
                                <th>Visit Date</th>
                                <th>Measurements</th>
                                <th>Vitals</th>
                                <th>Doctor</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_records as $record): ?>
                            <tr data-date="<?php echo $record['visit_date']; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($record['child_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($record['registration_number']); ?></small>
                                </td>
                                <td><?php echo $utils->formatDate($record['visit_date']); ?></td>
                                <td>
                                    <?php if ($record['weight_kg']): ?>
                                        <small><strong>Weight:</strong> <?php echo $record['weight_kg']; ?> kg</small><br>
                                    <?php endif; ?>
                                    <?php if ($record['height_cm']): ?>
                                        <small><strong>Height:</strong> <?php echo $record['height_cm']; ?> cm</small>
                                    <?php endif; ?>
                                    <?php if ($record['weight_kg'] && $record['height_cm']): ?>
                                        <br><small><strong>BMI:</strong> <?php echo $utils->calculateBMI($record['weight_kg'], $record['height_cm']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($record['temperature']): ?>
                                        <small><strong>Temp:</strong> <?php echo $record['temperature']; ?>°C</small><br>
                                    <?php endif; ?>
                                    <?php if ($record['blood_pressure']): ?>
                                        <small><strong>BP:</strong> <?php echo htmlspecialchars($record['blood_pressure']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($record['doctor_name']); ?></small>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewRecord(<?php echo htmlspecialchars(json_encode($record)); ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Record Modal -->
<div class="modal fade" id="viewRecordModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Medical Record Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="recordDetails">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
            </div>
        </div>
    </div>
</div>

<?php
$extra_scripts = '
<script>
function loadChildInfo() {
    const select = document.getElementById("child_id");
    const option = select.options[select.selectedIndex];
    const infoDiv = document.getElementById("child-info");
    const detailsDiv = document.getElementById("child-details");
    
    if (option.value) {
        const birthDate = new Date(option.dataset.birth);
        const today = new Date();
        const ageMonths = Math.floor((today - birthDate) / (1000 * 60 * 60 * 24 * 30.44));
        const ageYears = Math.floor(ageMonths / 12);
        const remainingMonths = ageMonths % 12;
        
        let ageText = "";
        if (ageYears > 0) {
            ageText = `${ageYears} year${ageYears > 1 ? "s" : ""} ${remainingMonths} month${remainingMonths > 1 ? "s" : ""}`;
        } else {
            ageText = `${ageMonths} month${ageMonths > 1 ? "s" : ""}`;
        }
        
        detailsDiv.innerHTML = `
            <strong>Age:</strong> ${ageText} (${ageMonths} months)<br>
            <strong>Gender:</strong> ${option.dataset.gender}<br>
            <strong>Birth Date:</strong> ${new Date(option.dataset.birth).toLocaleDateString()}
        `;
        infoDiv.classList.remove("d-none");
    } else {
        infoDiv.classList.add("d-none");
    }
}

function viewRecord(record) {
    const details = `
        <div class="row">
            <div class="col-md-6">
                <h6><i class="fas fa-baby me-2"></i>Child Information</h6>
                <p><strong>Name:</strong> ${record.child_name}</p>
                <p><strong>Registration:</strong> ${record.registration_number}</p>
                <p><strong>Visit Date:</strong> ${new Date(record.visit_date).toLocaleDateString()}</p>
            </div>
            <div class="col-md-6">
                <h6><i class="fas fa-user-md me-2"></i>Recorded By</h6>
                <p><strong>Doctor:</strong> ${record.doctor_name}</p>
                <p><strong>Date Recorded:</strong> ${new Date(record.created_at).toLocaleDateString()}</p>
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-md-6">
                <h6><i class="fas fa-weight me-2"></i>Measurements</h6>
                ${record.weight_kg ? `<p><strong>Weight:</strong> ${record.weight_kg} kg</p>` : ""}
                ${record.height_cm ? `<p><strong>Height:</strong> ${record.height_cm} cm</p>` : ""}
                ${record.age_months ? `<p><strong>Age:</strong> ${record.age_months} months</p>` : ""}
            </div>
            <div class="col-md-6">
                <h6><i class="fas fa-heartbeat me-2"></i>Vital Signs</h6>
                ${record.temperature ? `<p><strong>Temperature:</strong> ${record.temperature}°C</p>` : ""}
                ${record.blood_pressure ? `<p><strong>Blood Pressure:</strong> ${record.blood_pressure}</p>` : ""}
            </div>
        </div>
        ${record.notes ? `
            <hr>
            <h6><i class="fas fa-notes-medical me-2"></i>Clinical Notes</h6>
            <p>${record.notes.replace(/\n/g, "<br>")}</p>
        ` : ""}
    `;
    
    document.getElementById("recordDetails").innerHTML = details;
    new bootstrap.Modal(document.getElementById("viewRecordModal")).show();
}

// Filter records
document.querySelectorAll("input[name=filter]").forEach(radio => {
    radio.addEventListener("change", function() {
        const filter = this.id;
        const rows = document.querySelectorAll("#recordsTable tbody tr");
        const today = new Date();
        const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
        
        rows.forEach(row => {
            const recordDate = new Date(row.dataset.date);
            let show = true;
            
            if (filter === "today") {
                show = recordDate.toDateString() === today.toDateString();
            } else if (filter === "week") {
                show = recordDate >= weekAgo;
            }
            
            row.style.display = show ? "" : "none";
        });
    });
});
</script>';

require_once '../includes/footer.php';
?>