<?php
$page_title = 'Hospital Reports';
require_once '../includes/header.php';

$session->requireRole('hospital_admin');

$database = new Database();
$db = $database->getConnection();
$current_user = $session->getCurrentUser();
$hospital_id = $current_user['hospital_id'];

// Date filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Hospital overview statistics
$overview_query = "SELECT 
    COUNT(DISTINCT c.child_id) as total_children,
    COUNT(DISTINCT CASE WHEN YEAR(c.created_at) = YEAR(CURDATE()) THEN c.child_id END) as new_children_this_year,
    COUNT(DISTINCT CASE WHEN c.gender = 'male' THEN c.child_id END) as male_children,
    COUNT(DISTINCT CASE WHEN c.gender = 'female' THEN c.child_id END) as female_children,
    COUNT(DISTINCT u.user_id) as total_staff,
    COUNT(DISTINCT CASE WHEN u.role = 'doctor' THEN u.user_id END) as total_doctors,
    COUNT(DISTINCT CASE WHEN u.role = 'nurse' THEN u.user_id END) as total_nurses
    FROM children c
    LEFT JOIN users u ON c.hospital_id = u.hospital_id AND u.role IN ('doctor', 'nurse')
    WHERE c.hospital_id = :hospital_id";

$overview_stmt = $db->prepare($overview_query);
$overview_stmt->bindParam(':hospital_id', $hospital_id);
$overview_stmt->execute();
$overview = $overview_stmt->fetch(PDO::FETCH_ASSOC);

// Vaccination coverage statistics
$vaccination_query = "SELECT 
    COUNT(vs.schedule_id) as total_scheduled,
    COUNT(CASE WHEN vs.status = 'completed' THEN vs.schedule_id END) as total_completed,
    COUNT(CASE WHEN vs.status = 'pending' AND vs.scheduled_date < CURDATE() THEN vs.schedule_id END) as total_overdue,
    COUNT(CASE WHEN vs.status = 'pending' AND vs.scheduled_date >= CURDATE() THEN vs.schedule_id END) as total_upcoming
    FROM vaccination_schedule vs
    JOIN children c ON vs.child_id = c.child_id
    WHERE c.hospital_id = :hospital_id
    AND vs.scheduled_date BETWEEN :start_date AND :end_date";

$vaccination_stmt = $db->prepare($vaccination_query);
$vaccination_stmt->bindParam(':hospital_id', $hospital_id);
$vaccination_stmt->bindParam(':start_date', $start_date);
$vaccination_stmt->bindParam(':end_date', $end_date);
$vaccination_stmt->execute();
$vaccination_stats = $vaccination_stmt->fetch(PDO::FETCH_ASSOC);

// Monthly registration trends
$monthly_query = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as registrations
    FROM children 
    WHERE hospital_id = :hospital_id 
    AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month";

$monthly_stmt = $db->prepare($monthly_query);
$monthly_stmt->bindParam(':hospital_id', $hospital_id);
$monthly_stmt->execute();
$monthly_trends = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);

// Vaccine-wise coverage
$vaccine_coverage_query = "SELECT 
    v.vaccine_name,
    v.child_age_weeks,
    COUNT(vs.schedule_id) as scheduled,
    COUNT(CASE WHEN vs.status = 'completed' THEN vs.schedule_id END) as completed,
    ROUND((COUNT(CASE WHEN vs.status = 'completed' THEN vs.schedule_id END) / COUNT(vs.schedule_id)) * 100, 1) as coverage_percentage
    FROM vaccines v
    LEFT JOIN vaccination_schedule vs ON v.vaccine_id = vs.vaccine_id
    LEFT JOIN children c ON vs.child_id = c.child_id
    WHERE c.hospital_id = :hospital_id OR c.hospital_id IS NULL
    GROUP BY v.vaccine_id
    ORDER BY v.child_age_weeks, v.dose_number";

$vaccine_coverage_stmt = $db->prepare($vaccine_coverage_query);
$vaccine_coverage_stmt->bindParam(':hospital_id', $hospital_id);
$vaccine_coverage_stmt->execute();
$vaccine_coverage = $vaccine_coverage_stmt->fetchAll(PDO::FETCH_ASSOC);

// Age group distribution
$age_groups_query = "SELECT 
    CASE 
        WHEN TIMESTAMPDIFF(MONTH, date_of_birth, CURDATE()) < 6 THEN '0-5 months'
        WHEN TIMESTAMPDIFF(MONTH, date_of_birth, CURDATE()) < 12 THEN '6-11 months'
        WHEN TIMESTAMPDIFF(MONTH, date_of_birth, CURDATE()) < 24 THEN '1-2 years'
        WHEN TIMESTAMPDIFF(MONTH, date_of_birth, CURDATE()) < 60 THEN '2-5 years'
        ELSE '5+ years'
    END as age_group,
    COUNT(*) as count
    FROM children 
    WHERE hospital_id = :hospital_id
    GROUP BY age_group
    ORDER BY MIN(TIMESTAMPDIFF(MONTH, date_of_birth, CURDATE()))";

$age_groups_stmt = $db->prepare($age_groups_query);
$age_groups_stmt->bindParam(':hospital_id', $hospital_id);
$age_groups_stmt->execute();
$age_groups = $age_groups_stmt->fetchAll(PDO::FETCH_ASSOC);

// Staff performance
$staff_performance_query = "SELECT 
    u.full_name,
    u.role,
    COUNT(CASE WHEN u.role = 'nurse' THEN c.child_id END) as children_registered,
    COUNT(CASE WHEN u.role = 'nurse' THEN vs.schedule_id END) as vaccinations_given,
    COUNT(CASE WHEN u.role = 'doctor' THEN cmr.record_id END) as medical_records_added
    FROM users u
    LEFT JOIN children c ON u.user_id = c.registered_by
    LEFT JOIN vaccination_schedule vs ON u.user_id = vs.administered_by
    LEFT JOIN child_medical_records cmr ON u.user_id = cmr.recorded_by
    WHERE u.hospital_id = :hospital_id AND u.role IN ('doctor', 'nurse')
    GROUP BY u.user_id
    ORDER BY u.role, u.full_name";

$staff_performance_stmt = $db->prepare($staff_performance_query);
$staff_performance_stmt->bindParam(':hospital_id', $hospital_id);
$staff_performance_stmt->execute();
$staff_performance = $staff_performance_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate coverage percentage
$coverage_percentage = $vaccination_stats['total_scheduled'] > 0 ? 
    round(($vaccination_stats['total_completed'] / $vaccination_stats['total_scheduled']) * 100, 1) : 0;
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Hospital Reports</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button onclick="window.print()" class="btn btn-outline-secondary me-2">
            <i class="fas fa-print me-1"></i>Print Report
        </button>
        <button onclick="exportData()" class="btn btn-primary">
            <i class="fas fa-download me-1"></i>Export Data
        </button>
    </div>
</div>

<!-- Date Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-4">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i>Apply Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Overview Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center border-primary">
            <div class="card-body">
                <i class="fas fa-baby fa-2x text-primary mb-2"></i>
                <h4 class="text-primary"><?php echo $overview['total_children']; ?></h4>
                <p class="card-text">Total Children</p>
                <small class="text-muted"><?php echo $overview['new_children_this_year']; ?> new this year</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-success">
            <div class="card-body">
                <i class="fas fa-syringe fa-2x text-success mb-2"></i>
                <h4 class="text-success"><?php echo $coverage_percentage; ?>%</h4>
                <p class="card-text">Vaccination Coverage</p>
                <small class="text-muted"><?php echo $vaccination_stats['total_completed']; ?>/<?php echo $vaccination_stats['total_scheduled']; ?> completed</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-warning">
            <div class="card-body">
                <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                <h4 class="text-warning"><?php echo $vaccination_stats['total_overdue']; ?></h4>
                <p class="card-text">Overdue Vaccinations</p>
                <small class="text-muted">Require immediate attention</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-info">
            <div class="card-body">
                <i class="fas fa-users fa-2x text-info mb-2"></i>
                <h4 class="text-info"><?php echo $overview['total_staff']; ?></h4>
                <p class="card-text">Total Staff</p>
                <small class="text-muted"><?php echo $overview['total_doctors']; ?> doctors, <?php echo $overview['total_nurses']; ?> nurses</small>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <!-- Gender Distribution -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-pie me-2"></i>Gender Distribution</h5>
            </div>
            <div class="card-body">
                <canvas id="genderChart" width="300" height="200"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Age Group Distribution -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-bar me-2"></i>Age Group Distribution</h5>
            </div>
            <div class="card-body">
                <canvas id="ageGroupChart" width="300" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Registration Trends -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-line me-2"></i>Monthly Registration Trends (Last 12 Months)</h5>
            </div>
            <div class="card-body">
                <canvas id="monthlyTrendsChart" width="800" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Vaccine Coverage Table -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-table me-2"></i>Vaccine-wise Coverage Report</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Vaccine</th>
                                <th>Age</th>
                                <th>Scheduled</th>
                                <th>Completed</th>
                                <th>Coverage %</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vaccine_coverage as $vaccine): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($vaccine['vaccine_name']); ?></td>
                                <td>
                                    <?php 
                                    if ($vaccine['child_age_weeks'] == 0) {
                                        echo 'At birth';
                                    } else {
                                        echo $vaccine['child_age_weeks'] . ' weeks';
                                    }
                                    ?>
                                </td>
                                <td><?php echo $vaccine['scheduled']; ?></td>
                                <td><?php echo $vaccine['completed']; ?></td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-<?php echo $vaccine['coverage_percentage'] >= 90 ? 'success' : ($vaccine['coverage_percentage'] >= 70 ? 'warning' : 'danger'); ?>" 
                                             style="width: <?php echo $vaccine['coverage_percentage']; ?>%">
                                            <?php echo $vaccine['coverage_percentage']; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($vaccine['coverage_percentage'] >= 90): ?>
                                        <span class="badge bg-success">Excellent</span>
                                    <?php elseif ($vaccine['coverage_percentage'] >= 70): ?>
                                        <span class="badge bg-warning">Good</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Needs Improvement</span>
                                    <?php endif; ?>
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

<!-- Staff Performance -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-users me-2"></i>Staff Performance Summary</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Staff Member</th>
                                <th>Role</th>
                                <th>Children Registered</th>
                                <th>Vaccinations Given</th>
                                <th>Medical Records</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staff_performance as $staff): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($staff['full_name']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $staff['role'] == 'doctor' ? 'primary' : 'success'; ?>">
                                        <?php echo ucfirst($staff['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($staff['role'] == 'nurse'): ?>
                                        <span class="badge bg-info"><?php echo $staff['children_registered']; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($staff['role'] == 'nurse'): ?>
                                        <span class="badge bg-success"><?php echo $staff['vaccinations_given']; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($staff['role'] == 'doctor'): ?>
                                        <span class="badge bg-primary"><?php echo $staff['medical_records_added']; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $total_activity = $staff['children_registered'] + $staff['vaccinations_given'] + $staff['medical_records_added'];
                                    if ($total_activity >= 50) {
                                        echo '<span class="badge bg-success">High</span>';
                                    } elseif ($total_activity >= 20) {
                                        echo '<span class="badge bg-warning">Moderate</span>';
                                    } else {
                                        echo '<span class="badge bg-secondary">Low</span>';
                                    }
                                    ?>
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

<?php
$extra_scripts = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
// Gender Distribution Chart
const genderCtx = document.getElementById("genderChart").getContext("2d");
new Chart(genderCtx, {
    type: "doughnut",
    data: {
        labels: ["Male", "Female"],
        datasets: [{
            data: [' . $overview['male_children'] . ', ' . $overview['female_children'] . '],
            backgroundColor: ["#17a2b8", "#ffc107"],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: "bottom"
            }
        }
    }
});

// Age Group Distribution Chart
const ageGroupCtx = document.getElementById("ageGroupChart").getContext("2d");
new Chart(ageGroupCtx, {
    type: "bar",
    data: {
        labels: [' . implode(',', array_map(function($ag) { return '"' . $ag['age_group'] . '"'; }, $age_groups)) . '],
        datasets: [{
            label: "Children",
            data: [' . implode(',', array_column($age_groups, 'count')) . '],
            backgroundColor: "rgba(53, 50, 84, 0.8)",
            borderColor: "rgb(53, 50, 84)",
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

// Monthly Trends Chart
const monthlyCtx = document.getElementById("monthlyTrendsChart").getContext("2d");
new Chart(monthlyCtx, {
    type: "line",
    data: {
        labels: [' . implode(',', array_map(function($mt) { return '"' . date('M Y', strtotime($mt['month'] . '-01')) . '"'; }, $monthly_trends)) . '],
        datasets: [{
            label: "New Registrations",
            data: [' . implode(',', array_column($monthly_trends, 'registrations')) . '],
            borderColor: "rgb(53, 50, 84)",
            backgroundColor: "rgba(53, 50, 84, 0.1)",
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// Export function
function exportData() {
    const reportData = {
        hospital: "' . htmlspecialchars($current_user['hospital_name']) . '",
        date_range: "' . $start_date . ' to ' . $end_date . '",
        overview: ' . json_encode($overview) . ',
        vaccination_stats: ' . json_encode($vaccination_stats) . ',
        vaccine_coverage: ' . json_encode($vaccine_coverage) . ',
        staff_performance: ' . json_encode($staff_performance) . '
    };
    
    // Create and download JSON file
    const dataStr = JSON.stringify(reportData, null, 2);
    const dataBlob = new Blob([dataStr], {type: "application/json"});
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "hospital_report_" + new Date().toISOString().split("T")[0] + ".json";
    link.click();
    URL.revokeObjectURL(url);
}

// Print styles
const printStyles = `
    @media print {
        .btn-toolbar, .navbar, .sidebar, .no-print {
            display: none !important;
        }
        .main-content {
            margin: 0 !important;
            padding: 0 !important;
        }
        .card {
            break-inside: avoid;
            margin-bottom: 20px;
        }
        .chart-container {
            height: 300px !important;
        }
    }
`;

// Add print styles
const styleSheet = document.createElement("style");
styleSheet.type = "text/css";
styleSheet.innerText = printStyles;
document.head.appendChild(styleSheet);
</script>';

require_once '../includes/footer.php';
?>