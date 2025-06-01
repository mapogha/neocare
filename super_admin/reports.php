<?php
$page_title = 'Global Reports';
require_once '../includes/header.php';

$session->requireRole('super_admin');

$database = new Database();
$db = $database->getConnection();

// Date filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Global system overview
$overview_query = "SELECT 
    COUNT(DISTINCT h.hospital_id) as total_hospitals,
    COUNT(DISTINCT c.child_id) as total_children,
    COUNT(DISTINCT u.user_id) as total_staff,
    COUNT(DISTINCT CASE WHEN u.role = 'hospital_admin' THEN u.user_id END) as total_admins,
    COUNT(DISTINCT CASE WHEN u.role = 'doctor' THEN u.user_id END) as total_doctors,
    COUNT(DISTINCT CASE WHEN u.role = 'nurse' THEN u.user_id END) as total_nurses,
    COUNT(DISTINCT CASE WHEN YEAR(c.created_at) = YEAR(CURDATE()) THEN c.child_id END) as children_this_year
    FROM hospitals h
    LEFT JOIN children c ON h.hospital_id = c.hospital_id
    LEFT JOIN users u ON h.hospital_id = u.hospital_id AND u.role != 'super_admin'";

$overview_stmt = $db->prepare($overview_query);
$overview_stmt->execute();
$overview = $overview_stmt->fetch(PDO::FETCH_ASSOC);

// Global vaccination statistics
$vaccination_query = "SELECT 
    COUNT(vs.schedule_id) as total_scheduled,
    COUNT(CASE WHEN vs.status = 'completed' THEN vs.schedule_id END) as total_completed,
    COUNT(CASE WHEN vs.status = 'pending' AND vs.scheduled_date < CURDATE() THEN vs.schedule_id END) as total_overdue,
    COUNT(CASE WHEN vs.status = 'pending' AND vs.scheduled_date >= CURDATE() THEN vs.schedule_id END) as total_upcoming,
    COUNT(CASE WHEN vs.administered_date BETWEEN :start_date AND :end_date THEN vs.schedule_id END) as completed_in_period
    FROM vaccination_schedule vs
    JOIN children c ON vs.child_id = c.child_id";

$vaccination_stmt = $db->prepare($vaccination_query);
$vaccination_stmt->bindParam(':start_date', $start_date);
$vaccination_stmt->bindParam(':end_date', $end_date);
$vaccination_stmt->execute();
$vaccination_stats = $vaccination_stmt->fetch(PDO::FETCH_ASSOC);

// Hospital performance comparison
$hospital_performance_query = "SELECT 
    h.hospital_name,
    h.hospital_id,
    COUNT(DISTINCT c.child_id) as total_children,
    COUNT(DISTINCT u.user_id) as total_staff,
    COUNT(vs.schedule_id) as total_vaccines_scheduled,
    COUNT(CASE WHEN vs.status = 'completed' THEN vs.schedule_id END) as vaccines_completed,
    ROUND((COUNT(CASE WHEN vs.status = 'completed' THEN vs.schedule_id END) / NULLIF(COUNT(vs.schedule_id), 0)) * 100, 1) as coverage_percentage,
    COUNT(CASE WHEN vs.status = 'pending' AND vs.scheduled_date < CURDATE() THEN vs.schedule_id END) as overdue_vaccines
    FROM hospitals h
    LEFT JOIN children c ON h.hospital_id = c.hospital_id
    LEFT JOIN users u ON h.hospital_id = u.hospital_id AND u.role IN ('doctor', 'nurse')
    LEFT JOIN vaccination_schedule vs ON c.child_id = vs.child_id
    GROUP BY h.hospital_id
    ORDER BY coverage_percentage DESC, total_children DESC";

$hospital_performance_stmt = $db->prepare($hospital_performance_query);
$hospital_performance_stmt->execute();
$hospital_performance = $hospital_performance_stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly registration trends across all hospitals
$monthly_trends_query = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as registrations,
    COUNT(DISTINCT hospital_id) as active_hospitals
    FROM children 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month";

$monthly_trends_stmt = $db->prepare($monthly_trends_query);
$monthly_trends_stmt->execute();
$monthly_trends = $monthly_trends_stmt->fetchAll(PDO::FETCH_ASSOC);

// Top performing staff across all hospitals
$top_staff_query = "SELECT 
    u.full_name,
    u.role,
    h.hospital_name,
    COUNT(CASE WHEN u.role = 'nurse' THEN c.child_id END) as children_registered,
    COUNT(CASE WHEN u.role = 'nurse' THEN vs.schedule_id END) as vaccinations_given,
    COUNT(CASE WHEN u.role = 'doctor' THEN cmr.record_id END) as medical_records_added
    FROM users u
    JOIN hospitals h ON u.hospital_id = h.hospital_id
    LEFT JOIN children c ON u.user_id = c.registered_by
    LEFT JOIN vaccination_schedule vs ON u.user_id = vs.administered_by
    LEFT JOIN child_medical_records cmr ON u.user_id = cmr.recorded_by
    WHERE u.role IN ('doctor', 'nurse')
    GROUP BY u.user_id
    ORDER BY (children_registered + vaccinations_given + medical_records_added) DESC
    LIMIT 15";

$top_staff_stmt = $db->prepare($top_staff_query);
$top_staff_stmt->execute();
$top_staff = $top_staff_stmt->fetchAll(PDO::FETCH_ASSOC);

// Vaccine-wise global coverage
$vaccine_coverage_query = "SELECT 
    v.vaccine_name,
    v.child_age_weeks,
    COUNT(vs.schedule_id) as scheduled,
    COUNT(CASE WHEN vs.status = 'completed' THEN vs.schedule_id END) as completed,
    ROUND((COUNT(CASE WHEN vs.status = 'completed' THEN vs.schedule_id END) / NULLIF(COUNT(vs.schedule_id), 0)) * 100, 1) as coverage_percentage
    FROM vaccines v
    LEFT JOIN vaccination_schedule vs ON v.vaccine_id = vs.vaccine_id
    GROUP BY v.vaccine_id
    ORDER BY v.child_age_weeks, v.dose_number";

$vaccine_coverage_stmt = $db->prepare($vaccine_coverage_query);
$vaccine_coverage_stmt->execute();
$vaccine_coverage = $vaccine_coverage_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate global coverage percentage
$global_coverage_percentage = $vaccination_stats['total_scheduled'] > 0 ? 
    round(($vaccination_stats['total_completed'] / $vaccination_stats['total_scheduled']) * 100, 1) : 0;
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Global System Reports</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button onclick="window.print()" class="btn btn-outline-secondary me-2">
            <i class="fas fa-print me-1"></i>Print Report
        </button>
        <button onclick="exportGlobalData()" class="btn btn-primary">
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

<!-- Global Overview -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center border-primary">
            <div class="card-body">
                <i class="fas fa-hospital fa-2x text-primary mb-2"></i>
                <h4 class="text-primary"><?php echo $overview['total_hospitals']; ?></h4>
                <p class="card-text">Hospitals</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center border-success">
            <div class="card-body">
                <i class="fas fa-baby fa-2x text-success mb-2"></i>
                <h4 class="text-success"><?php echo number_format($overview['total_children']); ?></h4>
                <p class="card-text">Children</p>
                <small class="text-muted"><?php echo $overview['children_this_year']; ?> this year</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center border-info">
            <div class="card-body">
                <i class="fas fa-users fa-2x text-info mb-2"></i>
                <h4 class="text-info"><?php echo $overview['total_staff']; ?></h4>
                <p class="card-text">Total Staff</p>
                <small class="text-muted"><?php echo $overview['total_admins']; ?> admins</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center border-warning">
            <div class="card-body">
                <i class="fas fa-user-md fa-2x text-warning mb-2"></i>
                <h4 class="text-warning"><?php echo $overview['total_doctors']; ?></h4>
                <p class="card-text">Doctors</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center border-secondary">
            <div class="card-body">
                <i class="fas fa-user-nurse fa-2x text-secondary mb-2"></i>
                <h4 class="text-secondary"><?php echo $overview['total_nurses']; ?></h4>
                <p class="card-text">Nurses</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center border-dark">
            <div class="card-body">
                <i class="fas fa-chart-pie fa-2x text-dark mb-2"></i>
                <h4 class="text-dark"><?php echo $global_coverage_percentage; ?>%</h4>
                <p class="card-text">Coverage</p>
                <small class="text-muted"><?php echo number_format($vaccination_stats['total_completed']); ?> vaccines</small>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <!-- Monthly Registration Trends -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-line me-2"></i>Monthly Registration Trends (Last 12 Months)</h5>
            </div>
            <div class="card-body">
                <canvas id="monthlyTrendsChart" width="600" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Vaccination Status Summary -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-syringe me-2"></i>Global Vaccination Status</h5>
            </div>
            <div class="card-body">
                <canvas id="vaccinationStatusChart" width="300" height="300"></canvas>
                <div class="mt-3 text-center">
                    <small class="text-muted">
                        Total: <?php echo number_format($vaccination_stats['total_scheduled']); ?> vaccines scheduled
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hospital Performance Comparison -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-hospital me-2"></i>Hospital Performance Comparison</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Hospital</th>
                                <th>Children</th>
                                <th>Staff</th>
                                <th>Vaccines Scheduled</th>
                                <th>Coverage Rate</th>
                                <th>Overdue</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hospital_performance as $hospital): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($hospital['hospital_name']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $hospital['total_children']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $hospital['total_staff']; ?></span>
                                </td>
                                <td>
                                    <?php echo number_format($hospital['total_vaccines_scheduled']); ?><br>
                                    <small class="text-success"><?php echo number_format($hospital['vaccines_completed']); ?> completed</small>
                                </td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-<?php echo $hospital['coverage_percentage'] >= 90 ? 'success' : ($hospital['coverage_percentage'] >= 70 ? 'warning' : 'danger'); ?>" 
                                             style="width: <?php echo $hospital['coverage_percentage']; ?>%">
                                            <?php echo $hospital['coverage_percentage']; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($hospital['overdue_vaccines'] > 0): ?>
                                        <span class="badge bg-danger"><?php echo $hospital['overdue_vaccines']; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($hospital['coverage_percentage'] >= 90): ?>
                                        <span class="badge bg-success">Excellent</span>
                                    <?php elseif ($hospital['coverage_percentage'] >= 80): ?>
                                        <span class="badge bg-warning">Good</span>
                                    <?php elseif ($hospital['coverage_percentage'] >= 70): ?>
                                        <span class="badge bg-secondary">Average</span>
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

<!-- Global Vaccine Coverage and Top Staff -->
<div class="row mb-4">
    <!-- Vaccine Coverage -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-bar me-2"></i>Global Vaccine Coverage by Type</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Vaccine</th>
                                <th>Age</th>
                                <th>Scheduled</th>
                                <th>Completed</th>
                                <th>Coverage</th>
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
                                <td><?php echo number_format($vaccine['scheduled']); ?></td>
                                <td><?php echo number_format($vaccine['completed']); ?></td>
                                <td>
                                    <div class="progress" style="height: 15px;">
                                        <div class="progress-bar bg-<?php echo $vaccine['coverage_percentage'] >= 90 ? 'success' : ($vaccine['coverage_percentage'] >= 70 ? 'warning' : 'danger'); ?>" 
                                             style="width: <?php echo $vaccine['coverage_percentage']; ?>%">
                                            <?php echo $vaccine['coverage_percentage']; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top Performing Staff -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-star me-2"></i>Top Performing Staff</h5>
            </div>
            <div class="card-body">
                <?php if (empty($top_staff)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-users fa-2x text-muted mb-2"></i>
                        <p class="text-muted">No staff activity data</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($top_staff as $index => $staff): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold">
                                        <?php if ($index < 3): ?>
                                            <i class="fas fa-medal text-<?php echo $index == 0 ? 'warning' : ($index == 1 ? 'secondary' : 'warning'); ?> me-1"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($staff['full_name']); ?>
                                    </div>
                                    <small class="text-muted"><?php echo htmlspecialchars($staff['hospital_name']); ?></small>
                                    <div class="mt-1">
                                        <?php if ($staff['role'] == 'nurse'): ?>
                                            <small class="text-success">
                                                <?php echo $staff['children_registered']; ?> children, 
                                                <?php echo $staff['vaccinations_given']; ?> vaccines
                                            </small>
                                        <?php else: ?>
                                            <small class="text-primary">
                                                <?php echo $staff['medical_records_added']; ?> medical records
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="badge bg-<?php echo $staff['role'] == 'doctor' ? 'primary' : 'success'; ?>">
                                    <?php echo ucfirst($staff['role']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- System Health Indicators -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-heartbeat me-2"></i>System Health Indicators</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <div class="text-center p-3 bg-light rounded">
                    <h4 class="<?php echo $global_coverage_percentage >= 90 ? 'text-success' : ($global_coverage_percentage >= 70 ? 'text-warning' : 'text-danger'); ?>">
                        <?php echo $global_coverage_percentage; ?>%
                    </h4>
                    <p class="mb-0">Global Coverage Rate</p>
                    <small class="text-muted">Target: 90%+</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center p-3 bg-light rounded">
                    <h4 class="<?php echo $vaccination_stats['total_overdue'] < 100 ? 'text-success' : ($vaccination_stats['total_overdue'] < 500 ? 'text-warning' : 'text-danger'); ?>">
                        <?php echo number_format($vaccination_stats['total_overdue']); ?>
                    </h4>
                    <p class="mb-0">Overdue Vaccines</p>
                    <small class="text-muted">Target: <100</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center p-3 bg-light rounded">
                    <h4 class="<?php echo $overview['children_this_year'] > 100 ? 'text-success' : 'text-warning'; ?>">
                        <?php echo number_format($overview['children_this_year']); ?>
                    </h4>
                    <p class="mb-0">New Registrations</p>
                    <small class="text-muted">This year</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center p-3 bg-light rounded">
                    <h4 class="text-info">
                        <?php echo number_format($vaccination_stats['completed_in_period']); ?>
                    </h4>
                    <p class="mb-0">Vaccines Given</p>
                    <small class="text-muted">In selected period</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extra_scripts = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
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

// Vaccination Status Chart
const vaccinationCtx = document.getElementById("vaccinationStatusChart").getContext("2d");
new Chart(vaccinationCtx, {
    type: "doughnut",
    data: {
        labels: ["Completed", "Overdue", "Upcoming"],
        datasets: [{
            data: [
                ' . $vaccination_stats['total_completed'] . ',
                ' . $vaccination_stats['total_overdue'] . ',
                ' . $vaccination_stats['total_upcoming'] . '
            ],
            backgroundColor: ["#28a745", "#dc3545", "#17a2b8"],
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

function exportGlobalData() {
    const reportData = {
        generated_at: new Date().toISOString(),
        period: "' . $start_date . ' to ' . $end_date . '",
        overview: ' . json_encode($overview) . ',
        vaccination_stats: ' . json_encode($vaccination_stats) . ',
        hospital_performance: ' . json_encode($hospital_performance) . ',
        vaccine_coverage: ' . json_encode($vaccine_coverage) . ',
        top_staff: ' . json_encode($top_staff) . '
    };
    
    const dataStr = JSON.stringify(reportData, null, 2);
    const dataBlob = new Blob([dataStr], {type: "application/json"});
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "global_system_report_" + new Date().toISOString().split("T")[0] + ".json";
    link.click();
    URL.revokeObjectURL(url);
}
</script>';

require_once '../includes/footer.php';
?>
