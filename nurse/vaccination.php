<?php
$page_title = 'Vaccination Management';
require_once '../includes/header.php';

$session->requireRole('nurse');

$database = new Database();
$db = $database->getConnection();
$current_user = $session->getCurrentUser();
$hospital_id = $current_user['hospital_id'];

$error = '';
$success = '';

// Handle vaccination recording
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'record_vaccination') {
        $schedule_id = $_POST['schedule_id'] ?? '';
        $administered_date = $_POST['administered_date'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        if (empty($schedule_id) || empty($administered_date)) {
            $error = 'Please fill in all required fields';
        } else {
            $query = "UPDATE vaccination_schedule 
                     SET status = 'completed', administered_date = :admin_date, 
                         administered_by = :admin_by, notes = :notes 
                     WHERE schedule_id = :schedule_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':admin_date', $administered_date);
            $stmt->bindParam(':admin_by', $current_user['user_id']);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindParam(':schedule_id', $schedule_id);
            
            if ($stmt->execute()) {
                $success = 'Vaccination recorded successfully';
                
                // Send SMS notification to parent
                $child_query = "SELECT c.child_name, c.parent_phone, v.vaccine_name 
                               FROM vaccination_schedule vs
                               JOIN children c ON vs.child_id = c.child_id
                               JOIN vaccines v ON vs.vaccine_id = v.vaccine_id
                               WHERE vs.schedule_id = :schedule_id";
                $child_stmt = $db->prepare($child_query);
                $child_stmt->bindParam(':schedule_id', $schedule_id);
                $child_stmt->execute();
                $child_data = $child_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($child_data) {
                    $message = "Good news! " . $child_data['child_name'] . " has received " . 
                              $child_data['vaccine_name'] . " vaccination today. Next appointment details will be sent soon.";
                    $utils->sendSMSReminder($child_data['parent_phone'], $message);
                }
            } else {
                $error = 'Failed to record vaccination';
            }
        }
    }
}

// Get pending vaccinations for this hospital
$pending_query = "SELECT vs.*, c.child_name, c.registration_number, c.parent_name, c.parent_phone,
                         v.vaccine_name, v.description
                  FROM vaccination_schedule vs
                  JOIN children c ON vs.child_id = c.child_id
                  JOIN vaccines v ON vs.vaccine_id = v.vaccine_id
                  WHERE c.hospital_id = :hospital_id 
                  AND vs.status = 'pending'
                  ORDER BY vs.scheduled_date ASC";
$pending_stmt = $db->prepare($pending_query);
$pending_stmt->bindParam(':hospital_id', $hospital_id);
$pending_stmt->execute();
$pending_vaccinations = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get today's vaccinations
$today_query = "SELECT vs.*, c.child_name, c.registration_number, v.vaccine_name
                FROM vaccination_schedule vs
                JOIN children c ON vs.child_id = c.child_id
                JOIN vaccines v ON vs.vaccine_id = v.vaccine_id
                WHERE c.hospital_id = :hospital_id 
                AND vs.scheduled_date = CURDATE()
                AND vs.status = 'pending'
                ORDER BY c.child_name";
$today_stmt = $db->prepare($today_query);
$today_stmt->bindParam(':hospital_id', $hospital_id);
$today_stmt->execute();
$today_vaccinations = $today_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get overdue vaccinations
$overdue_query = "SELECT vs.*, c.child_name, c.registration_number, v.vaccine_name
                  FROM vaccination_schedule vs
                  JOIN children c ON vs.child_id = c.child_id
                  JOIN vaccines v ON vs.vaccine_id = v.vaccine_id
                  WHERE c.hospital_id = :hospital_id 
                  AND vs.scheduled_date < CURDATE()
                  AND vs.status = 'pending'
                  ORDER BY vs.scheduled_date ASC";
$overdue_stmt = $db->prepare($overdue_query);
$overdue_stmt->bindParam(':hospital_id', $hospital_id);
$overdue_stmt->execute();
$overdue_vaccinations = $overdue_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent completed vaccinations
$completed_query = "SELECT vs.*, c.child_name, c.registration_number, v.vaccine_name, u.full_name as nurse_name
                    FROM vaccination_schedule vs
                    JOIN children c ON vs.child_id = c.child_id
                    JOIN vaccines v ON vs.vaccine_id = v.vaccine_id
                    LEFT JOIN users u ON vs.administered_by = u.user_id
                    WHERE c.hospital_id = :hospital_id 
                    AND vs.status = 'completed'
                    ORDER BY vs.administered_date DESC
                    LIMIT 10";
$completed_stmt = $db->prepare($completed_query);
$completed_stmt->bindParam(':hospital_id', $hospital_id);
$completed_stmt->execute();
$completed_vaccinations = $completed_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Vaccination Management</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="register_child.php" class="btn btn-outline-secondary me-2">
            <i class="fas fa-plus me-1"></i>Register Child
        </a>
        <a href="children_list.php" class="btn btn-outline-secondary">
            <i class="fas fa-list me-1"></i>Children List
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

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-calendar-day fa-2x text-primary mb-2"></i>
                <h4><?php echo count($today_vaccinations); ?></h4>
                <p class="card-text">Due Today</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                <h4><?php echo count($overdue_vaccinations); ?></h4>
                <p class="card-text">Overdue</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                <h4><?php echo count($pending_vaccinations); ?></h4>
                <p class="card-text">Total Pending</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <h4><?php echo count($completed_vaccinations); ?></h4>
                <p class="card-text">Recent Completed</p>
            </div>
        </div>
    </div>
</div>

<!-- Vaccination Tables -->
<div class="row">
    <div class="col-lg-6">
        <!-- Today's Vaccinations -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5><i class="fas fa-calendar-day me-2"></i>Due Today</h5>
            </div>
            <div class="card-body">
                <?php if (empty($today_vaccinations)): ?>
                    <p class="text-muted">No vaccinations due today.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Child</th>
                                    <th>Vaccine</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($today_vaccinations as $vaccination): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($vaccination['child_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($vaccination['registration_number']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($vaccination['vaccine_name']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-success" onclick="recordVaccination(<?php echo htmlspecialchars(json_encode($vaccination)); ?>)">
                                            <i class="fas fa-syringe"></i> Record
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Overdue Vaccinations -->
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Overdue Vaccinations</h5>
            </div>
            <div class="card-body">
                <?php if (empty($overdue_vaccinations)): ?>
                    <p class="text-muted">No overdue vaccinations.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Child</th>
                                    <th>Vaccine</th>
                                    <th>Due Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($overdue_vaccinations as $vaccination): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($vaccination['child_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($vaccination['registration_number']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($vaccination['vaccine_name']); ?></td>
                                    <td>
                                        <span class="text-danger"><?php echo $utils->formatDate($vaccination['scheduled_date']); ?></span><br>
                                        <small class="text-muted">
                                            <?php 
                                            $days_overdue = (time() - strtotime($vaccination['scheduled_date'])) / (24 * 60 * 60);
                                            echo ceil($days_overdue) . ' days overdue';
                                            ?>
                                        </small>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="recordVaccination(<?php echo htmlspecialchars(json_encode($vaccination)); ?>)">
                                            <i class="fas fa-syringe"></i> Record
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <!-- Recent Completed Vaccinations -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5><i class="fas fa-check-circle me-2"></i>Recently Completed</h5>
            </div>
            <div class="card-body">
                <?php if (empty($completed_vaccinations)): ?>
                    <p class="text-muted">No recent completed vaccinations.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Child</th>
                                    <th>Vaccine</th>
                                    <th>Date Given</th>
                                    <th>By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($completed_vaccinations as $vaccination): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($vaccination['child_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($vaccination['registration_number']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($vaccination['vaccine_name']); ?></td>
                                    <td><?php echo $utils->formatDate($vaccination['administered_date']); ?></td>
                                    <td>
                                        <small><?php echo htmlspecialchars($vaccination['nurse_name'] ?? 'Unknown'); ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Record Vaccination Modal -->
<div class="modal fade" id="recordVaccinationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="record_vaccination">
                <input type="hidden" name="schedule_id" id="modal_schedule_id">
                <div class="modal-header">
                    <h5 class="modal-title">Record Vaccination</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Child:</strong> <span id="modal_child_name"></span><br>
                        <strong>Registration:</strong> <span id="modal_registration"></span><br>
                        <strong>Vaccine:</strong> <span id="modal_vaccine_name"></span><br>
                        <strong>Scheduled Date:</strong> <span id="modal_scheduled_date"></span>
                    </div>
                    
                    <div class="mb-3">
                        <label for="administered_date" class="form-label">Date Administered *</label>
                        <input type="date" class="form-control" id="administered_date" name="administered_date" 
                               value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Any observations, reactions, or additional notes..."></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> Please ensure you have verified the child's identity and vaccine before administration.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-syringe me-2"></i>Record Vaccination
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Send Reminders Modal -->
<div class="modal fade" id="sendRemindersModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Vaccination Reminders</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Send SMS reminders to parents for upcoming vaccinations?</p>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remind_today" checked>
                    <label class="form-check-label" for="remind_today">
                        Due today (<?php echo count($today_vaccinations); ?> children)
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remind_overdue" checked>
                    <label class="form-check-label" for="remind_overdue">
                        Overdue (<?php echo count($overdue_vaccinations); ?> children)
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remind_upcoming">
                    <label class="form-check-label" for="remind_upcoming">
                        Due in next 7 days
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="sendReminders()">
                    <i class="fas fa-paper-plane me-2"></i>Send Reminders
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="fixed-bottom p-3" style="right: 20px; left: auto; width: auto;">
    <div class="btn-group-vertical" role="group">
        <button type="button" class="btn btn-primary mb-2" data-bs-toggle="modal" data-bs-target="#sendRemindersModal">
            <i class="fas fa-paper-plane"></i> Send Reminders
        </button>
        <a href="register_child.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Register Child
        </a>
    </div>
</div>

<?php
$extra_scripts = '
<script>
function recordVaccination(vaccination) {
    document.getElementById("modal_schedule_id").value = vaccination.schedule_id;
    document.getElementById("modal_child_name").textContent = vaccination.child_name;
    document.getElementById("modal_registration").textContent = vaccination.registration_number;
    document.getElementById("modal_vaccine_name").textContent = vaccination.vaccine_name;
    document.getElementById("modal_scheduled_date").textContent = new Date(vaccination.scheduled_date).toLocaleDateString();
    
    new bootstrap.Modal(document.getElementById("recordVaccinationModal")).show();
}

function sendReminders() {
    const today = document.getElementById("remind_today").checked;
    const overdue = document.getElementById("remind_overdue").checked;
    const upcoming = document.getElementById("remind_upcoming").checked;
    
    if (!today && !overdue && !upcoming) {
        alert("Please select at least one reminder type.");
        return;
    }
    
    // Create form and submit
    const form = document.createElement("form");
    form.method = "POST";
    form.innerHTML = `
        <input type="hidden" name="action" value="send_reminders">
        <input type="hidden" name="remind_today" value="${today}">
        <input type="hidden" name="remind_overdue" value="${overdue}">
        <input type="hidden" name="remind_upcoming" value="${upcoming}">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Auto-refresh every 5 minutes to show updated vaccination status
setInterval(function() {
    location.reload();
}, 300000);

// Search functionality
function searchVaccinations() {
    const input = document.getElementById("searchInput");
    const filter = input.value.toLowerCase();
    const tables = document.querySelectorAll(".table tbody tr");
    
    tables.forEach(row => {
        const childName = row.cells[0].textContent.toLowerCase();
        const vaccine = row.cells[1].textContent.toLowerCase();
        
        if (childName.includes(filter) || vaccine.includes(filter)) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
}
</script>';

require_once '../includes/footer.php';
?>