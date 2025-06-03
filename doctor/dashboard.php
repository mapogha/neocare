<?php
$page_title = 'Doctor Dashboard';
require_once '../includes/header.php';

$session->requireRole('doctor');

$database = new Database();
$db = $database->getConnection();
$current_user = $session->getCurrentUser();
$hospital_id = $current_user['hospital_id'];

// Doctor Statistics - FIXED: Use recorded_by instead of doctor_id
$doctor_stats_query = "SELECT 
    (SELECT COUNT(*) FROM children WHERE hospital_id = :hospital_id) as total_children,
    (SELECT COUNT(*) FROM child_medical_records cmr JOIN children c ON cmr.child_id = c.child_id WHERE c.hospital_id = :hospital_id AND cmr.recorded_by = :user_id) as my_consultations,
    (SELECT COUNT(*) FROM child_medical_records cmr JOIN children c ON cmr.child_id = c.child_id WHERE c.hospital_id = :hospital_id AND cmr.recorded_by = :user_id AND DATE(cmr.visit_date) = CURDATE()) as consultations_today,
    (SELECT COUNT(*) FROM child_medical_records cmr JOIN children c ON cmr.child_id = c.child_id WHERE c.hospital_id = :hospital_id AND cmr.recorded_by = :user_id AND WEEK(cmr.visit_date) = WEEK(CURDATE())) as consultations_this_week";

$doctor_stmt = $db->prepare($doctor_stats_query);
$doctor_stmt->bindParam(':hospital_id', $hospital_id);
$doctor_stmt->bindParam(':user_id', $current_user['user_id']);
$doctor_stmt->execute();
$doctor_stats = $doctor_stmt->fetch(PDO::FETCH_ASSOC);

// Health Monitoring Alerts - SIMPLIFIED: Remove doctor_id references
$health_alerts_query = "SELECT 
    c.child_id,
    c.child_name,
    c.registration_number,
    c.date_of_birth,
    TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) as age_months,
    cmr.weight_kg,
    cmr.height_cm,
    cmr.visit_date,
    cmr.notes,
    'underweight' as alert_type
    FROM children c
    JOIN child_medical_records cmr ON c.child_id = cmr.child_id
    WHERE c.hospital_id = :hospital_id 
    AND cmr.visit_date = (
        SELECT MAX(visit_date) 
        FROM child_medical_records cmr2 
        WHERE cmr2.child_id = c.child_id
    )
    AND (
        (TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) <= 12 AND cmr.weight_kg < 3.0) OR
        (TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) > 12 AND cmr.weight_kg < 5.0)
    )
    
    UNION ALL
    
    SELECT 
    c.child_id,
    c.child_name,
    c.registration_number,
    c.date_of_birth,
    TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) as age_months,
    NULL as weight_kg,
    NULL as height_cm,
    NULL as visit_date,
    NULL as notes,
    'no_recent_checkup' as alert_type
    FROM children c
    LEFT JOIN child_medical_records cmr ON c.child_id = cmr.child_id AND cmr.visit_date = (
        SELECT MAX(visit_date) 
        FROM child_medical_records cmr2 
        WHERE cmr2.child_id = c.child_id
    )
    WHERE c.hospital_id = :hospital_id 
    AND (cmr.visit_date IS NULL OR cmr.visit_date < DATE_SUB(CURDATE(), INTERVAL 3 MONTH))
    AND TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) >= 6
    
    ORDER BY age_months DESC
    LIMIT 10";

$alerts_stmt = $db->prepare($health_alerts_query);
$alerts_stmt->bindParam(':hospital_id', $hospital_id);
$alerts_stmt->execute();
$health_alerts = $alerts_stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent Patient Consultations - FIXED: Use recorded_by
$recent_consultations_query = "SELECT 
    c.child_id,
    c.child_name,
    c.registration_number,
    c.parent_name,
    cmr.visit_date,
    cmr.weight_kg,
    cmr.height_cm,
    cmr.temperature,
    cmr.notes,
    TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) as age_months
    FROM child_medical_records cmr
    JOIN children c ON cmr.child_id = c.child_id
    WHERE c.hospital_id = :hospital_id AND cmr.recorded_by = :user_id
    ORDER BY cmr.visit_date DESC
    LIMIT 15";

$consultations_stmt = $db->prepare($recent_consultations_query);
$consultations_stmt->bindParam(':hospital_id', $hospital_id);
$consultations_stmt->bindParam(':user_id', $current_user['user_id']);
$consultations_stmt->execute();
$recent_consultations = $consultations_stmt->fetchAll(PDO::FETCH_ASSOC);

// Children requiring follow-up - SIMPLIFIED: Remove follow_up_date references (not in schema)
$followup_query = "SELECT 
    c.child_id,
    c.child_name,
    c.registration_number,
    c.parent_name,
    c.parent_phone,
    cmr.visit_date,
    cmr.notes,
    TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) as age_months
    FROM child_medical_records cmr
    JOIN children c ON cmr.child_id = c.child_id
    WHERE c.hospital_id = :hospital_id 
    AND cmr.recorded_by = :user_id
    AND cmr.visit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    AND cmr.notes LIKE '%follow%'
    ORDER BY cmr.visit_date DESC
    LIMIT 10";

$followup_stmt = $db->prepare($followup_query);
$followup_stmt->bindParam(':hospital_id', $hospital_id);
$followup_stmt->bindParam(':user_id', $current_user['user_id']);
$followup_stmt->execute();
$followup_patients = $followup_stmt->fetchAll(PDO::FETCH_ASSOC);

// Growth monitoring statistics
$growth_stats_query = "SELECT 
    COUNT(CASE WHEN cmr.weight_kg IS NOT NULL THEN 1 END) as children_with_weight,
    COUNT(CASE WHEN cmr.height_cm IS NOT NULL THEN 1 END) as children_with_height,
    AVG(cmr.weight_kg) as avg_weight,
    AVG(cmr.height_cm) as avg_height,
    COUNT(DISTINCT cmr.child_id) as unique_children_monitored
    FROM child_medical_records cmr
    JOIN children c ON cmr.child_id = c.child_id
    WHERE c.hospital_id = :hospital_id 
    AND cmr.visit_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";

$growth_stmt = $db->prepare($growth_stats_query);
$growth_stmt->bindParam(':hospital_id', $hospital_id);
$growth_stmt->execute();
$growth_stats = $growth_stmt->fetch(PDO::FETCH_ASSOC);

// Vaccination status overview for medical review
$vaccination_overview_query = "SELECT 
    COUNT(CASE WHEN vs.status = 'completed' THEN 1 END) as completed_vaccines,
    COUNT(CASE WHEN vs.status = 'pending' AND vs.scheduled_date <= CURDATE() THEN 1 END) as due_vaccines,
    COUNT(CASE WHEN vs.status = 'pending' AND vs.scheduled_date < CURDATE() THEN 1 END) as overdue_vaccines
    FROM vaccination_schedule vs
    JOIN children c ON vs.child_id = c.child_id
    WHERE c.hospital_id = :hospital_id";

$vaccination_stmt = $db->prepare($vaccination_overview_query);
$vaccination_stmt->bindParam(':hospital_id', $hospital_id);
$vaccination_stmt->execute();
$vaccination_overview = $vaccination_stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-user-md me-2 text-primary"></i>Dr. <?php echo htmlspecialchars($current_user['full_name']); ?> - Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="children.php" class="btn btn-primary">
                <i class="fas fa-baby me-1"></i>View Children
            </a>
            <a href="medical_records.php" class="btn btn-info">
                <i class="fas fa-notes-medical me-1"></i>Medical Records
            </a>
            <a href="growth_charts.php" class="btn btn-success">
                <i class="fas fa-chart-line me-1"></i>Growth Charts
            </a>
        </div>
    </div>
</div>

<!-- Doctor Overview Stats -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="card text-center border-primary">
            <div class="card-body">
                <i class="fas fa-baby fa-3x text-primary mb-3"></i>
                <h3 class="text-primary"><?php echo $doctor_stats['total_children']; ?></h3>
                <p class="card-text">Hospital Children</p>
                <small class="text-muted">Available for consultation</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card text-center border-success">
            <div class="card-body">
                <i class="fas fa-stethoscope fa-3x text-success mb-3"></i>
                <h3 class="text-success"><?php echo $doctor_stats['my_consultations']; ?></h3>
                <p class="card-text">Total Consultations</p>
                <small class="text-muted"><?php echo $doctor_stats['consultations_this_week']; ?> this week</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card text-center border-warning">
            <div class="card-body">
                <i class="fas fa-weight fa-3x text-warning mb-3"></i>
                <h3 class="text-warning"><?php echo $growth_stats['unique_children_monitored']; ?></h3>
                <p class="card-text">Children Monitored</p>
                <small class="text-muted">Growth tracking (3 months)</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card text-center border-info">
            <div class="card-body">
                <i class="fas fa-calendar-check fa-3x text-info mb-3"></i>
                <h3 class="text-info"><?php echo count($followup_patients); ?></h3>
                <p class="card-text">Recent Records</p>
                <small class="text-muted">With follow-up notes</small>
            </div>
        </div>
    </div>
</div>

<!-- Health Alerts -->
<?php if (!empty($health_alerts)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-warning">
            <h5><i class="fas fa-exclamation-triangle me-2"></i>Health Monitoring Alerts</h5>
            <p class="mb-2">Children requiring immediate medical attention:</p>
            <div class="row">
                <?php foreach (array_slice($health_alerts, 0, 3) as $alert): ?>
                <div class="col-md-4">
                    <div class="card border-warning">
                        <div class="card-body">
                            <h6 class="card-title"><?php echo htmlspecialchars($alert['child_name']); ?></h6>
                            <p class="card-text small">
                                <strong>Age:</strong> <?php echo $alert['age_months']; ?> months<br>
                                <?php if ($alert['alert_type'] == 'underweight'): ?>
                                    <span class="text-danger">⚠️ Underweight: <?php echo $alert['weight_kg']; ?>kg</span>
                                <?php else: ?>
                                    <span class="text-warning">⚠️ No checkup in 3+ months</span>
                                <?php endif; ?>
                            </p>
                            <a href="children.php?child_id=<?php echo $alert['child_id']; ?>" class="btn btn-sm btn-warning">Review</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Main Dashboard Content -->
<div class="row">
    <!-- Medical Activities -->
    <div class="col-lg-8">
        <!-- Recent Medical Records with Follow-up Notes -->
        <?php if (!empty($followup_patients)): ?>
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <h5><i class="fas fa-calendar-alt me-2"></i>Recent Records with Follow-up Notes</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($followup_patients as $patient): ?>
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($patient['child_name']); ?></div>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($patient['registration_number']); ?> • 
                                    <?php echo $patient['age_months']; ?> months old
                                </small>
                                <div class="mt-1">
                                    <small class="text-primary">
                                        Last visit: <?php echo date('M j, Y', strtotime($patient['visit_date'])); ?>
                                    </small>
                                </div>
                                <?php if ($patient['notes']): ?>
                                    <div class="mt-1">
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($patient['notes'], 0, 100)); ?>...</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="medical_records.php?action=add&child_id=<?php echo $patient['child_id']; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-notes-medical"></i>
                                </a>
                                <?php if ($patient['parent_phone']): ?>
                                <button class="btn btn-outline-info" onclick="callParent('<?php echo $patient['parent_phone']; ?>')">
                                    <i class="fas fa-phone"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Consultations -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-notes-medical me-2"></i>My Recent Consultations</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_consultations)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-clipboard fa-2x text-muted mb-2"></i>
                        <h6 class="text-muted">No recent consultations</h6>
                        <p class="text-muted small">Start by viewing children and adding medical records</p>
                        <a href="children.php" class="btn btn-primary">View Children</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Child</th>
                                    <th>Age</th>
                                    <th>Visit Date</th>
                                    <th>Measurements</th>
                                    <th>Notes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($recent_consultations, 0, 10) as $consultation): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($consultation['child_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($consultation['registration_number']); ?></small>
                                    </td>
                                    <td><?php echo $consultation['age_months']; ?> months</td>
                                    <td><?php echo date('M j, Y', strtotime($consultation['visit_date'])); ?></td>
                                    <td>
                                        <?php if ($consultation['weight_kg']): ?>
                                            <small class="d-block">Weight: <?php echo $consultation['weight_kg']; ?>kg</small>
                                        <?php endif; ?>
                                        <?php if ($consultation['height_cm']): ?>
                                            <small class="d-block">Height: <?php echo $consultation['height_cm']; ?>cm</small>
                                        <?php endif; ?>
                                        <?php if ($consultation['temperature']): ?>
                                            <small class="d-block">Temp: <?php echo $consultation['temperature']; ?>°C</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($consultation['notes']): ?>
                                            <small><?php echo htmlspecialchars(substr($consultation['notes'], 0, 50)); ?><?php echo strlen($consultation['notes']) > 50 ? '...' : ''; ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">No notes</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="medical_records.php?child_id=<?php echo $consultation['child_id']; ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="growth_charts.php?child_id=<?php echo $consultation['child_id']; ?>" class="btn btn-outline-success">
                                                <i class="fas fa-chart-line"></i>
                                            </a>
                                        </div>
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
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Medical Tools -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5><i class="fas fa-medical-kit me-2"></i>Medical Tools</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="children.php" class="btn btn-outline-primary">
                        <i class="fas fa-search me-2"></i>Find Child Records
                    </a>
                    <a href="medical_records.php?action=add" class="btn btn-outline-success">
                        <i class="fas fa-plus me-2"></i>Add Medical Record
                    </a>
                    <a href="growth_charts.php" class="btn btn-outline-info">
                        <i class="fas fa-chart-line me-2"></i>Growth Monitoring
                    </a>
                    <a href="vaccination_review.php" class="btn btn-outline-warning">
                        <i class="fas fa-syringe me-2"></i>Vaccination Review
                    </a>
                </div>
                
                <hr>
                
                <h6 class="text-muted">Quick References</h6>
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-secondary btn-sm" onclick="openGrowthStandards()">
                        <i class="fas fa-ruler me-1"></i>Growth Standards
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="openVaccineSchedule()">
                        <i class="fas fa-calendar me-1"></i>Vaccine Schedule
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="openMedicalCalculator()">
                        <i class="fas fa-calculator me-1"></i>BMI Calculator
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Vaccination Overview -->
        <div class="card mt-3">
            <div class="card-header">
                <h5><i class="fas fa-syringe me-2"></i>Hospital Vaccination Status</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <h5 class="text-success"><?php echo $vaccination_overview['completed_vaccines']; ?></h5>
                        <small class="text-muted">Completed</small>
                    </div>
                    <div class="col-4">
                        <h5 class="text-warning"><?php echo $vaccination_overview['due_vaccines']; ?></h5>
                        <small class="text-muted">Due</small>
                    </div>
                    <div class="col-4">
                        <h5 class="text-danger"><?php echo $vaccination_overview['overdue_vaccines']; ?></h5>
                        <small class="text-muted">Overdue</small>
                    </div>
                </div>
                <hr>
                <div class="text-center">
                    <a href="vaccination_review.php" class="btn btn-sm btn-outline-primary">Review Details</a>
                </div>
            </div>
        </div>
        
        <!-- Growth Monitoring Summary -->
        <div class="card mt-3">
            <div class="card-header">
                <h5><i class="fas fa-chart-area me-2"></i>Growth Monitoring Summary</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <h5 class="text-info"><?php echo $growth_stats['children_with_weight']; ?></h5>
                            <small class="text-muted">Weight Recorded</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h5 class="text-success"><?php echo $growth_stats['children_with_height']; ?></h5>
                        <small class="text-muted">Height Recorded</small>
                    </div>
                </div>
                <hr>
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <h6 class="text-primary"><?php echo $growth_stats['avg_weight'] ? round($growth_stats['avg_weight'], 1) : '0'; ?>kg</h6>
                            <small class="text-muted">Avg Weight</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h6 class="text-warning"><?php echo $growth_stats['avg_height'] ? round($growth_stats['avg_height'], 1) : '0'; ?>cm</h6>
                        <small class="text-muted">Avg Height</small>
                    </div>
                </div>
                <div class="text-center mt-2">
                    <small class="text-muted">Last 3 months data</small>
                </div>
            </div>
        </div>
        
        <!-- Health Alerts Summary -->
        <?php if (!empty($health_alerts)): ?>
        <div class="card mt-3">
            <div class="card-header bg-warning text-dark">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Health Alerts</h5>
            </div>
            <div class="card-body">
                <p class="text-warning mb-2">
                    <strong><?php echo count($health_alerts); ?></strong> children need medical attention
                </p>
                <div class="list-group list-group-flush">
                    <?php foreach (array_slice($health_alerts, 0, 3) as $alert): ?>
                    <div class="list-group-item px-0 py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="fw-bold"><?php echo htmlspecialchars($alert['child_name']); ?></small>
                                <br>
                                <small class="text-muted">
                                    <?php if ($alert['alert_type'] == 'underweight'): ?>
                                        Underweight (<?php echo $alert['weight_kg']; ?>kg)
                                    <?php else: ?>
                                        No recent checkup
                                    <?php endif; ?>
                                </small>
                            </div>
                            <a href="children.php?child_id=<?php echo $alert['child_id']; ?>" class="btn btn-sm btn-outline-warning">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($health_alerts) > 3): ?>
                    <div class="text-center mt-2">
                        <small class="text-muted">+<?php echo count($health_alerts) - 3; ?> more alerts</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Performance Summary -->
        <div class="card mt-3">
            <div class="card-header">
                <h5><i class="fas fa-chart-pie me-2"></i>My Performance</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <h4 class="text-primary"><?php echo $doctor_stats['consultations_today']; ?></h4>
                            <small class="text-muted">Today's Consultations</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h4 class="text-success"><?php echo $doctor_stats['consultations_this_week']; ?></h4>
                        <small class="text-muted">This Week</small>
                    </div>
                </div>
                <hr>
                <div class="text-center">
                    <small class="text-muted">
                        <?php
                        $avg_daily = $doctor_stats['consultations_this_week'] / 7;
                        if ($avg_daily >= 3) {
                            echo '<span class="badge bg-success">High Activity</span>';
                        } elseif ($avg_daily >= 1) {
                            echo '<span class="badge bg-warning">Moderate Activity</span>';
                        } else {
                            echo '<span class="badge bg-info">Light Activity</span>';
                        }
                        ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function callParent(phone) {
    if (phone) {
        window.open(`tel:${phone}`, '_self');
    } else {
        alert('No phone number available for this parent.');
    }
}

function openGrowthStandards() {
    // You can create this page later or link to external standards
    alert('Growth standards reference - Feature coming soon!');
}

function openVaccineSchedule() {
    // You can create this page later or link to vaccination schedule
    alert('Vaccine schedule reference - Feature coming soon!');
}

function openMedicalCalculator() {
    // Simple BMI calculator popup
    const weight = prompt('Enter weight in kg:');
    const height = prompt('Enter height in cm:');
    
    if (weight && height) {
        const heightM = height / 100;
        const bmi = (weight / (heightM * heightM)).toFixed(1);
        alert(`BMI: ${bmi}\n\nChild BMI Categories:\n- Underweight: <5th percentile\n- Normal: 5th-85th percentile\n- Overweight: 85th-95th percentile\n- Obese: >95th percentile`);
    }
}

// Auto-refresh dashboard every 15 minutes
setTimeout(function() {
    location.reload();
}, 900000);
</script>

<?php require_once '../includes/footer.php'; ?>