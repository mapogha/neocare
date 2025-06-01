<?php
$page_title = 'Parent Dashboard';
require_once '../includes/header.php';

$session->requireParentLogin();

$database = new Database();
$db = $database->getConnection();

// Get child information
$child_id = $_SESSION['child_id'];
$child_query = "SELECT c.*, h.name as hospital_name 
                FROM children c 
                JOIN hospitals h ON c.hospital_id = h.hospital_id 
                WHERE c.child_id = :child_id";
$child_stmt = $db->prepare($child_query);
$child_stmt->bindParam(':child_id', $child_id);
$child_stmt->execute();
$child = $child_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate child's current age
$birth_date = new DateTime($child['date_of_birth']);
$current_date = new DateTime();
$age_diff = $current_date->diff($birth_date);
$age_months = ($age_diff->y * 12) + $age_diff->m;
$age_days = $age_diff->days;

// Get vaccination status
$vaccination_query = "SELECT 
    COUNT(*) as total_vaccines,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_vaccines,
    COUNT(CASE WHEN status = 'pending' AND scheduled_date <= CURDATE() THEN 1 END) as due_vaccines,
    COUNT(CASE WHEN status = 'pending' AND scheduled_date < CURDATE() THEN 1 END) as overdue_vaccines
    FROM vaccination_schedule 
    WHERE child_id = :child_id";
$vaccination_stmt = $db->prepare($vaccination_query);
$vaccination_stmt->bindParam(':child_id', $child_id);
$vaccination_stmt->execute();
$vaccination_status = $vaccination_stmt->fetch(PDO::FETCH_ASSOC);

// Get upcoming vaccinations
$upcoming_query = "SELECT vs.*, v.vaccine_name, v.description
                   FROM vaccination_schedule vs
                   JOIN vaccines v ON vs.vaccine_id = v.vaccine_id
                   WHERE vs.child_id = :child_id 
                   AND vs.status = 'pending'
                   ORDER BY vs.scheduled_date ASC
                   LIMIT 5";
$upcoming_stmt = $db->prepare($upcoming_query);
$upcoming_stmt->bindParam(':child_id', $child_id);
$upcoming_stmt->execute();
$upcoming_vaccines = $upcoming_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get completed vaccinations
$completed_query = "SELECT vs.*, v.vaccine_name, v.description, u.full_name as administered_by_name
                    FROM vaccination_schedule vs
                    JOIN vaccines v ON vs.vaccine_id = v.vaccine_id
                    LEFT JOIN users u ON vs.administered_by = u.user_id
                    WHERE vs.child_id = :child_id 
                    AND vs.status = 'completed'
                    ORDER BY vs.administered_date DESC
                    LIMIT 10";
$completed_stmt = $db->prepare($completed_query);
$completed_stmt->bindParam(':child_id', $child_id);
$completed_stmt->execute();
$completed_vaccines = $completed_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get latest medical records
$medical_records_query = "SELECT cmr.*, u.full_name as doctor_name
                          FROM child_medical_records cmr
                          LEFT JOIN users u ON cmr.doctor_id = u.user_id
                          WHERE cmr.child_id = :child_id
                          ORDER BY cmr.visit_date DESC
                          LIMIT 5";
$medical_stmt = $db->prepare($medical_records_query);
$medical_stmt->bindParam(':child_id', $child_id);
$medical_stmt->execute();
$medical_records = $medical_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get growth data for chart
$growth_data_query = "SELECT visit_date, weight_kg, height_cm, age_months
                      FROM child_medical_records
                      WHERE child_id = :child_id AND (weight_kg IS NOT NULL OR height_cm IS NOT NULL)
                      ORDER BY visit_date ASC";
$growth_stmt = $db->prepare($growth_data_query);
$growth_stmt->bindParam(':child_id', $child_id);
$growth_stmt->execute();
$growth_data = $growth_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate vaccination coverage percentage
$coverage_percentage = $vaccination_status['total_vaccines'] > 0 ? 
    round(($vaccination_status['completed_vaccines'] / $vaccination_status['total_vaccines']) * 100, 1) : 0;

// Check for urgent alerts
$urgent_alerts = [];
if ($vaccination_status['overdue_vaccines'] > 0) {
    $urgent_alerts[] = [
        'type' => 'overdue_vaccines',
        'message' => $vaccination_status['overdue_vaccines'] . ' vaccine(s) are overdue',
        'severity' => 'danger'
    ];
}

if ($vaccination_status['due_vaccines'] > 0) {
    $urgent_alerts[] = [
        'type' => 'due_vaccines',
        'message' => $vaccination_status['due_vaccines'] . ' vaccine(s) are due now',
        'severity' => 'warning'
    ];
}

// Check if child needs medical checkup
$last_checkup = !empty($medical_records) ? new DateTime($medical_records[0]['visit_date']) : null;
if (!$last_checkup || $last_checkup < (new DateTime())->sub(new DateInterval('P3M'))) {
    $urgent_alerts[] = [
        'type' => 'checkup_needed',
        'message' => 'Medical checkup recommended (last visit was over 3 months ago)',
        'severity' => 'info'
    ];
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-heart me-2 text-danger"></i>Welcome, <?php echo htmlspecialchars($child['parent_name']); ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="vaccination_schedule.php" class="btn btn-primary me-2">
            <i class="fas fa-calendar me-1"></i>Full Schedule
        </a>
        <a href="growth_records.php" class="btn btn-success">
            <i class="fas fa-chart-line me-1"></i>Growth Records
        </a>
    </div>
</div>

<!-- Child Information Card -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white">
                <h5><i class="fas fa-baby me-2"></i><?php echo htmlspecialchars($child['child_name']); ?>'s Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Full Name:</strong> <?php echo htmlspecialchars($child['child_name']); ?></p>
                        <p><strong>Registration Number:</strong> <?php echo htmlspecialchars($child['registration_number']); ?></p>
                        <p><strong>Date of Birth:</strong> <?php echo date('F j, Y', strtotime($child['date_of_birth'])); ?></p>
                        <p><strong>Gender:</strong> <?php echo ucfirst($child['gender']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Age:</strong> 
                            <?php if ($age_months < 12): ?>
                                <?php echo $age_months; ?> months
                            <?php else: ?>
                                <?php echo floor($age_months / 12); ?> years, <?php echo $age_months % 12; ?> months
                            <?php endif; ?>
                            (<?php echo $age_days; ?> days old)
                        </p>
                        <p><strong>Hospital:</strong> <?php echo htmlspecialchars($child['hospital_name']); ?></p>
                        <p><strong>Birth Weight:</strong> <?php echo $child['birth_weight'] ? $child['birth_weight'] . 'kg' : 'Not recorded'; ?></p>
                        <p><strong>Registered:</strong> <?php echo date('F j, Y', strtotime($child['created_at'])); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Vaccination Progress -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="card text-center border-success">
            <div class="card-body">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <h3 class="text-success"><?php echo $vaccination_status['completed_vaccines']; ?></h3>
                <p class="card-text">Completed Vaccines</p>
                <div class="progress">
                    <div class="progress-bar bg-success" style="width: <?php echo $coverage_percentage; ?>%">
                        <?php echo $coverage_percentage; ?>%
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card text-center border-warning">
            <div class="card-body">
                <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                <h3 class="text-warning"><?php echo $vaccination_status['due_vaccines']; ?></h3>
                <p class="card-text">Due Now</p>
                <small class="text-muted">Vaccines ready to be given</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card text-center border-danger">
            <div class="card-body">
                <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                <h3 class="text-danger"><?php echo $vaccination_status['overdue_vaccines']; ?></h3>
                <p class="card-text">Overdue</p>
                <small class="text-muted">Please schedule immediately</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card text-center border-info">
            <div class="card-body">
                <i class="fas fa-list fa-3x text-info mb-3"></i>
                <h3 class="text-info"><?php echo $vaccination_status['total_vaccines'] - $vaccination_status['completed_vaccines']; ?></h3>
                <p class="card-text">Remaining</p>
                <small class="text-muted">Future vaccinations</small>
            </div>
        </div>
    </div>
</div>

<!-- Urgent Alerts -->
<?php if (!empty($urgent_alerts)): ?>
<div class="row mb-4">
    <div class="col-12">
        <?php foreach ($urgent_alerts as $alert): ?>
        <div class="alert alert-<?php echo $alert['severity']; ?> alert-dismissible fade show">
            <i class="fas fa-<?php echo $alert['severity'] == 'danger' ? 'exclamation-triangle' : ($alert['severity'] == 'warning' ? 'clock' : 'info-circle'); ?> me-2"></i>
            <strong>Important:</strong> <?php echo $alert['message']; ?>
            <?php if ($alert['type'] == 'overdue_vaccines' || $alert['type'] == 'due_vaccines'): ?>
                - Please contact <?php echo htmlspecialchars($child['hospital_name']); ?> to schedule an appointment.
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Main Content -->
<div class="row">
    <!-- Upcoming Vaccinations -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5><i class="fas fa-calendar-alt me-2"></i>Upcoming Vaccinations</h5>
            </div>
            <div class="card-body">
                <?php if (empty($upcoming_vaccines)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5 class="text-success">All caught up!</h5>
                        <p class="text-muted">Your child is up to date with all scheduled vaccinations.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($upcoming_vaccines as $vaccine): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($vaccine['vaccine_name']); ?></h6>
                                    <p class="mb-1 small text-muted"><?php echo htmlspecialchars($vaccine['description']); ?></p>
                                    <small>
                                        <strong>Scheduled:</strong> <?php echo date('F j, Y', strtotime($vaccine['scheduled_date'])); ?>
                                        (<?php 
                                        $scheduled_date = new DateTime($vaccine['scheduled_date']);
                                        $diff = $current_date->diff($scheduled_date);
                                        if ($scheduled_date < $current_date) {
                                            echo $diff->days . ' days overdue';
                                        } elseif ($diff->days == 0) {
                                            echo 'Due today';
                                        } else {
                                            echo 'In ' . $diff->days . ' days';
                                        }
                                        ?>)
                                    </small>
                                </div>
                                <div>
                                    <?php
                                    if ($scheduled_date < $current_date) {
                                        echo '<span class="badge bg-danger">Overdue</span>';
                                    } elseif ($diff->days <= 7) {
                                        echo '<span class="badge bg-warning">Due Soon</span>';
                                    } else {
                                        echo '<span class="badge bg-success">Scheduled</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Vaccinations -->
        <div class="card mt-3">
            <div class="card-header bg-success text-white">
                <h5><i class="fas fa-syringe me-2"></i>Recent Vaccinations</h5>
            </div>
            <div class="card-body">
                <?php if (empty($completed_vaccines)): ?>
                    <p class="text-muted text-center">No vaccinations completed yet.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($completed_vaccines as $vaccine): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($vaccine['vaccine_name']); ?></h6>
                                    <small class="text-muted">
                                        Given on: <?php echo date('F j, Y', strtotime($vaccine['administered_date'])); ?>
                                        <?php if ($vaccine['administered_by_name']): ?>
                                            <br>By: <?php echo htmlspecialchars($vaccine['administered_by_name']); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <span class="badge bg-success">
                                    <i class="fas fa-check me-1"></i>Complete
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Growth & Health -->
    <div class="col-lg-6">
        <!-- Latest Medical Record -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5><i class="fas fa-stethoscope me-2"></i>Latest Health Record</h5>
            </div>
            <div class="card-body">
                <?php if (empty($medical_records)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-notes-medical fa-2x text-muted mb-2"></i>
                        <p class="text-muted">No medical records available yet.</p>
                        <small class="text-muted">Records will appear here after doctor visits.</small>
                    </div>
                <?php else: ?>
                    <?php $latest_record = $medical_records[0]; ?>
                    <div class="row text-center">
                        <div class="col-4">
                            <h5 class="text-primary"><?php echo $latest_record['weight_kg'] ? $latest_record['weight_kg'] . 'kg' : 'N/A'; ?></h5>
                            <small class="text-muted">Weight</small>
                        </div>
                        <div class="col-4">
                            <h5 class="text-success"><?php echo $latest_record['height_cm'] ? $latest_record['height_cm'] . 'cm' : 'N/A'; ?></h5>
                            <small class="text-muted">Height</small>
                        </div>
                        <div class="col-4">
                            <h5 class="text-warning"><?php echo $latest_record['temperature'] ? $latest_record['temperature'] . '°C' : 'N/A'; ?></h5>
                            <small class="text-muted">Temperature</small>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-12">
                            <p><strong>Last Visit:</strong> <?php echo date('F j, Y', strtotime($latest_record['visit_date'])); ?></p>
                            <?php if ($latest_record['doctor_name']): ?>
                                <p><strong>Seen by:</strong> Dr. <?php echo htmlspecialchars($latest_record['doctor_name']); ?></p>
                            <?php endif; ?>
                            <?php if ($latest_record['notes']): ?>
                                <p><strong>Notes:</strong> <?php echo htmlspecialchars($latest_record['notes']); ?></p>
                            <?php endif; ?>
                            <?php if ($latest_record['follow_up_date']): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-calendar me-2"></i>
                                    <strong>Follow-up scheduled:</strong> <?php echo date('F j, Y', strtotime($latest_record['follow_up_date'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Growth Chart -->
        <?php if (!empty($growth_data)): ?>
        <div class="card mt-3">
            <div class="card-header">
                <h5><i class="fas fa-chart-line me-2"></i>Growth Progress</h5>
            </div>
            <div class="card-body">
                <canvas id="growthChart" height="150"></canvas>
                <div class="text-center mt-2">
                    <small class="text-muted">Track your child's growth over time</small>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Health Summary -->
        <div class="card mt-3">
            <div class="card-header">
                <h5><i class="fas fa-heartbeat me-2"></i>Health Summary</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <div class="text-center border-end">
                            <h6 class="text-info"><?php echo count($medical_records); ?></h6>
                            <small class="text-muted">Doctor Visits</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center">
                            <?php
                            $months_since_last_visit = 0;
                            if (!empty($medical_records)) {
                                $last_visit = new DateTime($medical_records[0]['visit_date']);
                                $interval = $current_date->diff($last_visit);
                                $months_since_last_visit = ($interval->y * 12) + $interval->m;
                            }
                            ?>
                            <h6 class="<?php echo $months_since_last_visit > 3 ? 'text-warning' : 'text-success'; ?>">
                                <?php echo $months_since_last_visit; ?>
                            </h6>
                            <small class="text-muted">Months Since Last Visit</small>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-6">
                        <div class="text-center border-end">
                            <h6 class="<?php echo $coverage_percentage >= 80 ? 'text-success' : ($coverage_percentage >= 60 ? 'text-warning' : 'text-danger'); ?>">
                                <?php echo $coverage_percentage; ?>%
                            </h6>
                            <small class="text-muted">Vaccination Coverage</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center">
                            <?php
                            if ($coverage_percentage >= 90) {
                                echo '<h6 class="text-success">Excellent</h6>';
                            } elseif ($coverage_percentage >= 70) {
                                echo '<h6 class="text-warning">Good</h6>';
                            } elseif ($coverage_percentage >= 50) {
                                echo '<h6 class="text-warning">Fair</h6>';
                            } else {
                                echo '<h6 class="text-danger">Needs Attention</h6>';
                            }
                            ?>
                            <small class="text-muted">Health Status</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Important Contacts -->
        <div class="card mt-3">
            <div class="card-header bg-secondary text-white">
                <h5><i class="fas fa-phone me-2"></i>Important Contacts</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?php echo htmlspecialchars($child['hospital_name']); ?></strong>
                            <br><small class="text-muted">Your child's hospital</small>
                        </div>
                        <button class="btn btn-outline-primary btn-sm" onclick="contactHospital()">
                            <i class="fas fa-phone"></i>
                        </button>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Emergency Services</strong>
                            <br><small class="text-muted">24/7 emergency care</small>
                        </div>
                        <button class="btn btn-outline-danger btn-sm" onclick="callEmergency()">
                            <i class="fas fa-phone"></i>
                        </button>
                    </div>
                </div>
                
                <hr>
                
                <h6 class="text-muted">Quick Actions</h6>
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-info btn-sm" onclick="scheduleAppointment()">
                        <i class="fas fa-calendar-plus me-1"></i>Schedule Appointment
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="downloadRecords()">
                        <i class="fas fa-download me-1"></i>Download Records
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Educational Tips -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-light">
                <h5><i class="fas fa-lightbulb me-2"></i>Health Tips for <?php echo htmlspecialchars($child['child_name']); ?></h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="text-center">
                            <i class="fas fa-syringe fa-2x text-primary mb-2"></i>
                            <h6>Vaccination Importance</h6>
                            <p class="small text-muted">Keep up with vaccination schedules to protect your child from preventable diseases. Vaccines are most effective when given on time.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <i class="fas fa-apple-alt fa-2x text-success mb-2"></i>
                            <h6>Nutrition & Growth</h6>
                            <p class="small text-muted">
                                <?php if ($age_months < 6): ?>
                                    Exclusive breastfeeding is recommended for the first 6 months. Your baby is currently <?php echo $age_months; ?> months old.
                                <?php elseif ($age_months < 12): ?>
                                    Continue breastfeeding while introducing solid foods. Ensure a variety of nutritious foods.
                                <?php else: ?>
                                    Provide a balanced diet with fruits, vegetables, and adequate nutrition for healthy growth.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <i class="fas fa-calendar-check fa-2x text-warning mb-2"></i>
                            <h6>Regular Checkups</h6>
                            <p class="small text-muted">Regular medical checkups help monitor your child's growth and development. Schedule visits every 3-6 months or as recommended by your doctor.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($growth_data)): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
// Growth Chart
const growthCtx = document.getElementById('growthChart').getContext('2d');
const growthChart = new Chart(growthCtx, {
    type: 'line',
    data: {
        labels: [
            <?php foreach ($growth_data as $data): ?>
                '<?php echo date('M Y', strtotime($data['visit_date'])); ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            label: 'Weight (kg)',
            data: [
                <?php foreach ($growth_data as $data): ?>
                    <?php echo $data['weight_kg'] ?? 'null'; ?>,
                <?php endforeach; ?>
            ],
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            borderWidth: 2,
            fill: false,
            tension: 0.4,
            yAxisID: 'y'
        }, {
            label: 'Height (cm)',
            data: [
                <?php foreach ($growth_data as $data): ?>
                    <?php echo $data['height_cm'] ?? 'null'; ?>,
                <?php endforeach; ?>
            ],
            borderColor: '#28a745',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            borderWidth: 2,
            fill: false,
            tension: 0.4,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Weight (kg)'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Height (cm)'
                },
                grid: {
                    drawOnChartArea: false,
                },
            }
        },
        plugins: {
            legend: {
                display: true,
                position: 'top'
            }
        }
    }
});
</script>
<?php endif; ?>

<script>
function contactHospital() {
    alert('Please contact <?php echo htmlspecialchars($child['hospital_name']); ?> directly for appointments and inquiries.');
}

function callEmergency() {
    if (confirm('This will call emergency services. Continue?')) {
        window.open('tel:911', '_self');
    }
}

function scheduleAppointment() {
    alert('Please contact <?php echo htmlspecialchars($child['hospital_name']); ?> to schedule an appointment for <?php echo htmlspecialchars($child['child_name']); ?>.');
}

function downloadRecords() {
    window.location.href = 'download_records.php?child_id=<?php echo $child_id; ?>';
}

// Show helpful notifications
<?php if ($vaccination_status['due_vaccines'] > 0): ?>
setTimeout(function() {
    if (confirm('<?php echo htmlspecialchars($child['child_name']); ?> has vaccines due. Would you like to view the vaccination schedule?')) {
        window.location.href = 'vaccination_schedule.php';
    }
}, 3000);
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>