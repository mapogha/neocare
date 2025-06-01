<?php
$page_title = 'Nurse Dashboard';
require_once '../includes/header.php';

$session->requireRole('nurse');

$database = new Database();
$db = $database->getConnection();
$current_user = $session->getCurrentUser();
$hospital_id = $current_user['hospital_id'];

// Nurse statistics
$nurse_stats_query = "SELECT 
    (SELECT COUNT(*) FROM children WHERE hospital_id = :hospital_id) as total_children,
    (SELECT COUNT(*) FROM children WHERE registered_by = :user_id) as my_registrations,
    (SELECT COUNT(*) FROM vaccination_schedule vs JOIN children c ON vs.child_id = c.child_id WHERE vs.administered_by = :user_id) as my_vaccinations,
    (SELECT COUNT(*) FROM children WHERE hospital_id = :hospital_id AND DATE(created_at) = CURDATE()) as registered_today";

$nurse_stmt = $db->prepare($nurse_stats_query);
$nurse_stmt->bindParam(':hospital_id', $hospital_id);
$nurse_stmt->bindParam(':user_id', $current_user['user_id']);
$nurse_stmt->execute();
$nurse_stats = $nurse_stmt->fetch(PDO::FETCH_ASSOC);

// Vaccination workload today
$today_vaccines_query = "SELECT 
    COUNT(CASE WHEN vs.status = 'pending' AND vs.scheduled_date = CURDATE() THEN 1 END) as due_today,
    COUNT(CASE WHEN vs.status = 'pending' AND vs.scheduled_date < CURDATE() THEN 1 END) as overdue,
    COUNT(CASE WHEN vs.status = 'completed' AND vs.administered_date = CURDATE() THEN 1 END) as completed_today
    FROM vaccination_schedule vs
    JOIN children c ON vs.child_id = c.child_id
    WHERE c.hospital_id = :hospital_id";

$today_stmt = $db->prepare($today_vaccines_query);
$today_stmt->bindParam(':hospital_id', $hospital_id);
$today_stmt->execute();
$today_vaccines = $today_stmt->fetch(PDO::FETCH_ASSOC);

// Children due for vaccination today
$due_today_query = "SELECT 
    c.child_id,
    c.child_name,
    c.registration_number,
    c.parent_name,
    c.parent_phone,
    v.vaccine_name,
    vs.schedule_id,
    TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) as age_months
    FROM vaccination_schedule vs
    JOIN children c ON vs.child_id = c.child_id
    JOIN vaccines v ON vs.vaccine_id = v.vaccine_id
    WHERE c.hospital_id = :hospital_id 
    AND vs.status = 'pending' 
    AND vs.scheduled_date = CURDATE()
    ORDER BY c.child_name
    LIMIT 10";

$due_today_stmt = $db->prepare($due_today_query);
$due_today_stmt->bindParam(':hospital_id', $hospital_id);
$due_today_stmt->execute();
$children_due_today = $due_today_stmt->fetchAll(PDO::FETCH_ASSOC);

// Overdue vaccinations
$overdue_query = "SELECT 
    c.child_id,
    c.child_name,
    c.registration_number,
    c.parent_name,
    c.parent_phone,
    v.vaccine_name,
    vs.scheduled_date,
    vs.schedule_id,
    DATEDIFF(CURDATE(), vs.scheduled_date) as days_overdue
    FROM vaccination_schedule vs
    JOIN children c ON vs.child_id = c.child_id
    JOIN vaccines v ON vs.vaccine_id = v.vaccine_id
    WHERE c.hospital_id = :hospital_id 
    AND vs.status = 'pending' 
    AND vs.scheduled_date < CURDATE()
    ORDER BY vs.scheduled_date ASC
    LIMIT 10";

$overdue_stmt = $db->prepare($overdue_query);
$overdue_stmt->bindParam(':hospital_id', $hospital_id);
$overdue_stmt->execute();
$overdue_children = $overdue_stmt->fetchAll(PDO::FETCH_ASSOC);

// My recent activities
$my_activities_query = "SELECT 
    'registration' as activity_type,
    c.child_name as item_name,
    c.created_at as activity_date,
    c.registration_number as details
    FROM children c
    WHERE c.registered_by = :user_id AND c.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    
    UNION ALL
    
    SELECT 
    'vaccination' as activity_type,
    CONCAT(c.child_name, ' - ', v.vaccine_name) as item_name,
    vs.administered_date as activity_date,
    c.registration_number as details
    FROM vaccination_schedule vs
    JOIN children c ON vs.child_id = c.child_id
    JOIN vaccines v ON vs.vaccine_id = v.vaccine_id
    WHERE vs.administered_by = :user_id AND vs.administered_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    
    ORDER BY activity_date DESC
    LIMIT 10";

$activities_stmt = $db->prepare($my_activities_query);
$activities_stmt->bindParam(':user_id', $current_user['user_id']);
$activities_stmt->execute();
$my_activities = $activities_stmt->fetchAll(PDO::FETCH_ASSOC);

// Weekly performance summary
$weekly_summary_query = "SELECT 
    (SELECT COUNT(*) FROM children WHERE registered_by = :user_id AND WEEK(created_at) = WEEK(CURDATE())) as registrations_this_week,
    (SELECT COUNT(*) FROM vaccination_schedule vs WHERE vs.administered_by = :user_id AND WEEK(vs.administered_date) = WEEK(CURDATE())) as vaccinations_this_week,
    (SELECT COUNT(*) FROM children WHERE registered_by = :user_id AND MONTH(created_at) = MONTH(CURDATE())) as registrations_this_month,
    (SELECT COUNT(*) FROM vaccination_schedule vs WHERE vs.administered_by = :user_id AND MONTH(vs.administered_date) = MONTH(CURDATE())) as vaccinations_this_month";

$weekly_stmt = $db->prepare($weekly_summary_query);
$weekly_stmt->bindParam(':user_id', $current_user['user_id']);
$weekly_stmt->execute();
$weekly_summary = $weekly_stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-user-nurse me-2 text-success"></i>Nurse <?php echo htmlspecialchars($current_user['full_name']); ?> - Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="register_child.php" class="btn btn-success me-2">
            <i class="fas fa-plus me-1"></i>Register Child
        </a>
        <a href="vaccination.php" class="btn btn-primary">
            <i class="fas fa-syringe me-1"></i>Manage Vaccinations
        </a>
    </div>
</div>

<!-- Nurse Overview Stats -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="card text-center border-primary">
            <div class="card-body">
                <i class="fas fa-baby fa-3x text-primary mb-3"></i>
                <h3 class="text-primary"><?php echo $nurse_stats['total_children']; ?></h3>
                <p class="card-text">Hospital Children</p>
                <small class="text-muted"><?php echo $nurse_stats['registered_today']; ?> registered today</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card text-center border-success">
            <div class="card-body">
                <i class="fas fa-user-plus fa-3x text-success mb-3"></i>
                <h3 class="text-success"><?php echo $nurse_stats['my_registrations']; ?></h3>
                <p class="card-text">My Registrations</p>
                <small class="text-muted">Children I registered</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card text-center border-info">
            <div class="card-body">
                <i class="fas fa-syringe fa-3x text-info mb-3"></i>
                <h3 class="text-info"><?php echo $nurse_stats['my_vaccinations']; ?></h3>
                <p class="card-text">Vaccines Given</p>
                <small class="text-muted">By me total</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card text-center border-warning">
            <div class="card-body">
                <i class="fas fa-calendar-day fa-3x text-warning mb-3"></i>
                <h3 class="text-warning"><?php echo $today_vaccines['due_today']; ?></h3>
                <p class="card-text">Due Today</p>
                <small class="text-muted"><?php echo $today_vaccines['completed_today']; ?> completed today</small>
            </div>
        </div>
    </div>
</div>

<!-- Urgent Action Required -->
<?php if ($today_vaccines['due_today'] > 0 || $today_vaccines['overdue'] > 0): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-warning">
            <h5><i class="fas fa-bell me-2"></i>Action Required Today</h5>
            <div class="row">
                <div class="col-md-6">
                    <?php if ($today_vaccines['due_today'] > 0): ?>
                        <p class="mb-1"><strong><?php echo $today_vaccines['due_today']; ?></strong> children have vaccinations due today.</p>
                    <?php endif; ?>
                    <?php if ($today_vaccines['overdue'] > 0): ?>
                        <p class="mb-1"><strong><?php echo $today_vaccines['overdue']; ?></strong> children have overdue vaccinations.</p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 text-end">
                    <a href="vaccination.php" class="btn btn-warning">
                        <i class="fas fa-syringe me-1"></i>Manage Vaccinations
                    </a>
                    <button class="btn btn-outline-warning" onclick="sendTodaysReminders()">
                        <i class="fas fa-paper-plane me-1"></i>Send Reminders
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Main Dashboard Content -->
<div class="row">
    <!-- Daily Vaccination Schedule -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5><i class="fas fa-calendar-day me-2"></i>Today's Vaccination Schedule</h5>
            </div>
            <div class="card-body">
                <?php if (empty($children_due_today)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <h6 class="text-success">No vaccinations due today!</h6>
                        <p class="text-muted small">All children are up to date</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($children_due_today as $child): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($child['child_name']); ?></div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($child['registration_number']); ?> • 
                                        <?php echo $child['age_months']; ?> months old
                                    </small>
                                    <div class="mt-1">
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($child['vaccine_name']); ?></span>
                                    </div>
                                </div>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button class="btn btn-outline-success" onclick="recordVaccination(<?php echo $child['schedule_id']; ?>)">
                                        <i class="fas fa-syringe"></i>
                                    </button>
                                    <button class="btn btn-outline-info" onclick="callParent('<?php echo $child['parent_phone']; ?>')">
                                        <i class="fas fa-phone"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Overdue Vaccinations -->
        <?php if (!empty($overdue_children)): ?>
        <div class="card mt-3">
            <div class="card-header bg-danger text-white">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Overdue Vaccinations</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($overdue_children as $child): ?>
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($child['child_name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($child['registration_number']); ?></small>
                                <div class="mt-1">
                                    <span class="badge bg-danger"><?php echo htmlspecialchars($child['vaccine_name']); ?></span>
                                    <small class="text-danger ms-2"><?php echo $child['days_overdue']; ?> days overdue</small>
                                </div>
                            </div>
                            <div class="btn-group btn-group-sm" role="group">
                                <button class="btn btn-outline-danger" onclick="recordVaccination(<?php echo $child['schedule_id']; ?>)">
                                    <i class="fas fa-syringe"></i>
                                </button>
                                <button class="btn btn-outline-warning" onclick="sendUrgentReminder('<?php echo $child['parent_phone']; ?>')">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Nursing Tools & Performance -->
    <div class="col-lg-6">
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5><i class="fas fa-tools me-2"></i>Nursing Tools</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="register_child.php" class="btn btn-outline-success">
                        <i class="fas fa-plus me-2"></i>Register New Child
                    </a>
                    <a href="vaccination.php" class="btn btn-outline-primary">
                        <i class="fas fa-syringe me-2"></i>Vaccination Management
                    </a>
                    <a href="children_list.php" class="btn btn-outline-info">
                        <i class="fas fa-list me-2"></i>Children List & Search
                    </a>
                    <a href="reports.php" class="btn btn-outline-warning">
                        <i class="fas fa-chart-bar me-2"></i>My Performance Reports
                    </a>
                </div>
                
                <hr>
                
                <h6 class="text-muted">Quick Actions</h6>
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-secondary btn-sm" onclick="viewVaccineSchedule()">
                        <i class="fas fa-calendar me-1"></i>Vaccine Schedule Reference
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="contactParentsApp()">
                        <i class="fas fa-phone me-1"></i>Contact Parents
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Weekly Performance -->
        <div class="card mt-3">
            <div class="card-header">
                <h5><i class="fas fa-chart-line me-2"></i>My Performance</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <h4 class="text-primary"><?php echo $weekly_summary['registrations_this_week']; ?></h4>
                            <small class="text-muted">Registrations This Week</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h4 class="text-success"><?php echo $weekly_summary['vaccinations_this_week']; ?></h4>
                        <small class="text-muted">Vaccinations This Week</small>
                    </div>
                </div>
                <hr>
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <h5 class="text-info"><?php echo $weekly_summary['registrations_this_month']; ?></h5>
                            <small class="text-muted">Monthly Registrations</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h5 class="text-warning"><?php echo $weekly_summary['vaccinations_this_month']; ?></h5>
                        <small class="text-muted">Monthly Vaccinations</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activities -->
        <div class="card mt-3">
            <div class="card-header">
                <h5><i class="fas fa-clock me-2"></i>My Recent Activities</h5>
            </div>
            <div class="card-body">
                <?php if (empty($my_activities)): ?>
                    <p class="text-muted text-center">No recent activities</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($my_activities as $activity): ?>
                        <div class="list-group-item px-0 py-2">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <?php if ($activity['activity_type'] == 'registration'): ?>
                                        <i class="fas fa-plus text-success me-1"></i>
                                        <small>Registered:</small> <?php echo htmlspecialchars($activity['item_name']); ?>
                                    <?php else: ?>
                                        <i class="fas fa-syringe text-primary me-1"></i>
                                        <small>Vaccinated:</small> <?php echo htmlspecialchars($activity['item_name']); ?>
                                    <?php endif; ?>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($activity['details']); ?></small>
                                </div>
                                <small class="text-muted">
                                    <?php echo date('M j, g:i A', strtotime($activity['activity_date'])); ?>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function recordVaccination(scheduleId) {
    window.location.href = `vaccination.php?action=administer&schedule_id=${scheduleId}`;
}

function callParent(phone) {
    if (phone) {
        window.open(`tel:${phone}`, '_self');
    } else {
        alert('No phone number available for this parent.');
    }
}

function sendTodaysReminders() {
    if (confirm('Send SMS reminders to all parents with children due for vaccination today?')) {
        // AJAX call to send reminders
        fetch('ajax/send_reminders.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({type: 'today'})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Successfully sent ${data.count} reminders.`);
            } else {
                alert('Error sending reminders: ' + data.message);
            }
        });
    }
}

function sendUrgentReminder(phone) {
    if (confirm('Send urgent reminder to this parent?')) {
        fetch('ajax/send_reminders.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({type: 'urgent', phone: phone})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Urgent reminder sent successfully.');
            } else {
                alert('Error sending reminder: ' + data.message);
            }
        });
    }
}

function viewVaccineSchedule() {
    // Open vaccine schedule reference in modal or new window
    window.open('vaccine_schedule.php', '_blank', 'width=800,height=600');
}

function contactParentsApp() {
    window.location.href = 'contact_parents.php';
}
</script>

<?php require_once '../includes/footer.php'; ?>