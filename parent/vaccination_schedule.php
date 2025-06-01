<?php
$page_title = 'Vaccination Schedule';
require_once '../includes/header.php';

// Ensure parent is logged in
if (!$session->isParentLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

$child = $session->getCurrentChild();
$child_id = $child['child_id'];

$database = new Database();
$db = $database->getConnection();

// Get complete vaccination schedule
$schedule_query = "SELECT vs.*, v.vaccine_name, v.description, v.child_age_weeks, v.dose_number,
                          u.full_name as administered_by_name
                   FROM vaccination_schedule vs
                   JOIN vaccines v ON vs.vaccine_id = v.vaccine_id
                   LEFT JOIN users u ON vs.administered_by = u.user_id
                   WHERE vs.child_id = :child_id
                   ORDER BY v.child_age_weeks, v.dose_number";
$schedule_stmt = $db->prepare($schedule_query);
$schedule_stmt->bindParam(':child_id', $child_id);
$schedule_stmt->execute();
$vaccination_schedule = $schedule_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get vaccination statistics
$stats_query = "SELECT 
                COUNT(*) as total_vaccines,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_vaccines,
                SUM(CASE WHEN status = 'pending' AND scheduled_date < CURDATE() THEN 1 ELSE 0 END) as overdue_vaccines,
                SUM(CASE WHEN status = 'pending' AND scheduled_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming_vaccines
                FROM vaccination_schedule 
                WHERE child_id = :child_id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':child_id', $child_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate completion percentage
$completion_percentage = $stats['total_vaccines'] > 0 ? 
    round(($stats['completed_vaccines'] / $stats['total_vaccines']) * 100) : 0;

// Get next upcoming vaccination
$next_query = "SELECT vs.*, v.vaccine_name 
               FROM vaccination_schedule vs
               JOIN vaccines v ON vs.vaccine_id = v.vaccine_id
               WHERE vs.child_id = :child_id AND vs.status = 'pending'
               ORDER BY vs.scheduled_date ASC
               LIMIT 1";
$next_stmt = $db->prepare($next_query);
$next_stmt->bindParam(':child_id', $child_id);
$next_stmt->execute();
$next_vaccination = $next_stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="row">
        <main class="col-md-12 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Vaccination Schedule - <?php echo htmlspecialchars($child['child_name']); ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="../dashboard.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                    </a>
                    <button onclick="window.print()" class="btn btn-outline-primary">
                        <i class="fas fa-print me-1"></i>Print Schedule
                    </button>
                </div>
            </div>

            <!-- Child Information Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h5><i class="fas fa-baby me-2"></i>Child Information</h5>
                            <div class="row">
                                <div class="col-sm-6">
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($child['child_name']); ?></p>
                                    <p><strong>Registration Number:</strong> <?php echo htmlspecialchars($child['registration_number']); ?></p>
                                    <p><strong>Date of Birth:</strong> <?php echo $utils->formatDate($child['date_of_birth']); ?></p>
                                </div>
                                <div class="col-sm-6">
                                    <p><strong>Age:</strong> <?php echo $utils->getChildAgeInMonths($child['date_of_birth']); ?> months</p>
                                    <p><strong>Gender:</strong> <?php echo ucfirst($child['gender']); ?></p>
                                    <p><strong>Hospital:</strong> <?php echo htmlspecialchars($child['hospital_name']); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <div class="progress mb-3" style="height: 20px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $completion_percentage; ?>%">
                                        <?php echo $completion_percentage; ?>% Complete
                                    </div>
                                </div>
                                <h6>Vaccination Progress</h6>
                                <p class="text-muted"><?php echo $stats['completed_vaccines']; ?> of <?php echo $stats['total_vaccines']; ?> vaccines completed</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center border-success">
                        <div class="card-body">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <h4 class="text-success"><?php echo $stats['completed_vaccines']; ?></h4>
                            <p class="card-text">Completed</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-primary">
                        <div class="card-body">
                            <i class="fas fa-calendar-check fa-2x text-primary mb-2"></i>
                            <h4 class="text-primary"><?php echo $stats['upcoming_vaccines']; ?></h4>
                            <p class="card-text">Upcoming</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-danger">
                        <div class="card-body">
                            <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                            <h4 class="text-danger"><?php echo $stats['overdue_vaccines']; ?></h4>
                            <p class="card-text">Overdue</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-info">
                        <div class="card-body">
                            <i class="fas fa-syringe fa-2x text-info mb-2"></i>
                            <h4 class="text-info"><?php echo $stats['total_vaccines']; ?></h4>
                            <p class="card-text">Total Vaccines</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Next Vaccination Alert -->
            <?php if ($next_vaccination): ?>
            <div class="alert alert-<?php echo strtotime($next_vaccination['scheduled_date']) < time() ? 'danger' : 'info'; ?> mb-4">
                <i class="fas fa-calendar-alt me-2"></i>
                <strong>Next Vaccination:</strong> <?php echo htmlspecialchars($next_vaccination['vaccine_name']); ?> 
                scheduled for <?php echo $utils->formatDate($next_vaccination['scheduled_date']); ?>
                <?php if (strtotime($next_vaccination['scheduled_date']) < time()): ?>
                    <span class="badge bg-danger ms-2">OVERDUE</span>
                <?php elseif (strtotime($next_vaccination['scheduled_date']) <= strtotime('+7 days')): ?>
                    <span class="badge bg-warning ms-2">DUE SOON</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Vaccination Timeline -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-timeline me-2"></i>Vaccination Timeline</h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php foreach ($vaccination_schedule as $index => $vaccine): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker <?php echo $vaccine['status'] == 'completed' ? 'bg-success' : ($vaccine['status'] == 'pending' && strtotime($vaccine['scheduled_date']) < time() ? 'bg-danger' : 'bg-secondary'); ?>">
                                <i class="fas fa-<?php echo $vaccine['status'] == 'completed' ? 'check' : 'syringe'; ?>"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($vaccine['vaccine_name']); ?>
                                            <?php if ($vaccine['dose_number'] > 1): ?>
                                                <span class="badge bg-secondary">Dose <?php echo $vaccine['dose_number']; ?></span>
                                            <?php endif; ?>
                                        </h6>
                                        <p class="mb-1 text-muted"><?php echo htmlspecialchars($vaccine['description']); ?></p>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            Scheduled: <?php echo $utils->formatDate($vaccine['scheduled_date']); ?>
                                            (At <?php echo $vaccine['child_age_weeks']; ?> weeks)
                                        </small>
                                        
                                        <?php if ($vaccine['status'] == 'completed'): ?>
                                            <br><small class="text-success">
                                                <i class="fas fa-check me-1"></i>
                                                Administered: <?php echo $utils->formatDate($vaccine['administered_date']); ?>
                                                <?php if ($vaccine['administered_by_name']): ?>
                                                    by <?php echo htmlspecialchars($vaccine['administered_by_name']); ?>
                                                <?php endif; ?>
                                            </small>
                                            <?php if ($vaccine['notes']): ?>
                                                <br><small class="text-muted">
                                                    <i class="fas fa-sticky-note me-1"></i>
                                                    <?php echo htmlspecialchars($vaccine['notes']); ?>
                                                </small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <?php
                                        switch ($vaccine['status']) {
                                            case 'completed':
                                                echo '<span class="badge bg-success">Completed</span>';
                                                break;
                                            case 'pending':
                                                if (strtotime($vaccine['scheduled_date']) < time()) {
                                                    $days_overdue = ceil((time() - strtotime($vaccine['scheduled_date'])) / (24 * 60 * 60));
                                                    echo '<span class="badge bg-danger">Overdue (' . $days_overdue . ' days)</span>';
                                                } elseif (strtotime($vaccine['scheduled_date']) <= strtotime('+7 days')) {
                                                    echo '<span class="badge bg-warning">Due Soon</span>';
                                                } else {
                                                    echo '<span class="badge bg-secondary">Scheduled</span>';
                                                }
                                                break;
                                            case 'missed':
                                                echo '<span class="badge bg-dark">Missed</span>';
                                                break;
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Important Notes -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle me-2"></i>Important Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-bell me-2"></i>Reminders</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i>You will receive SMS reminders before vaccination dates</li>
                                <li><i class="fas fa-check text-success me-2"></i>Please bring your child's health card to each visit</li>
                                <li><i class="fas fa-check text-success me-2"></i>Ensure your contact information is up to date</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-phone me-2"></i>Contact Information</h6>
                            <p><strong>Hospital:</strong> <?php echo htmlspecialchars($child['hospital_name']); ?></p>
                            <p><strong>Emergency:</strong> Contact your nearest health facility</p>
                            <p><strong>Questions:</strong> Speak with healthcare providers during visits</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 12px;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 3px solid var(--primary-color);
}

@media print {
    .btn-toolbar, .navbar, .sidebar {
        display: none !important;
    }
    
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .timeline-marker {
        background: #000 !important;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>
