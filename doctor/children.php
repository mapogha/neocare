<?php
$page_title = 'View Children';
require_once '../includes/header.php';

$session->requireRole('doctor');

$database = new Database();
$db = $database->getConnection();

// ADD THIS LINE: Initialize NeoCareUtils
require_once '../includes/functions.php';

$current_user = $session->getCurrentUser();
$hospital_id = $current_user['hospital_id'];

// Handle search
$search = isset($_GET['search']) ? $utils->cleanInput($_GET['search']) : '';
$age_filter = isset($_GET['age_filter']) ? $_GET['age_filter'] : '';
$gender_filter = isset($_GET['gender_filter']) ? $_GET['gender_filter'] : '';

$search_conditions = ["c.hospital_id = :hospital_id"];
$search_params = [':hospital_id' => $hospital_id];

if (!empty($search)) {
    $search_conditions[] = "(c.child_name LIKE :search OR c.registration_number LIKE :search OR c.parent_name LIKE :search)";
    $search_params[':search'] = "%$search%";
}

if (!empty($age_filter)) {
    switch ($age_filter) {
        case 'infant':
            $search_conditions[] = "TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) <= 12";
            break;
        case 'toddler':
            $search_conditions[] = "TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) BETWEEN 13 AND 36";
            break;
        case 'preschool':
            $search_conditions[] = "TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) BETWEEN 37 AND 60";
            break;
        case 'school':
            $search_conditions[] = "TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) > 60";
            break;
    }
}

if (!empty($gender_filter)) {
    $search_conditions[] = "c.gender = :gender";
    $search_params[':gender'] = $gender_filter;
}

$where_clause = "WHERE " . implode(" AND ", $search_conditions);

// Get children with their latest medical records and vaccination status
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Count total children
$count_query = "SELECT COUNT(DISTINCT c.child_id) as total 
                FROM children c 
                $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($search_params as $key => $value) {
    $count_stmt->bindParam($key, $value);
}
$count_stmt->execute();
$total_children = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_children / $per_page);

// Get children data
$children_query = "SELECT c.*,
    TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) as age_months,
    COUNT(vs.schedule_id) as total_vaccines,
    SUM(CASE WHEN vs.status = 'completed' THEN 1 ELSE 0 END) as completed_vaccines,
    SUM(CASE WHEN vs.status = 'pending' AND vs.scheduled_date < CURDATE() THEN 1 ELSE 0 END) as overdue_vaccines,
    (SELECT cmr.visit_date FROM child_medical_records cmr WHERE cmr.child_id = c.child_id ORDER BY cmr.visit_date DESC LIMIT 1) as last_visit,
    (SELECT cmr.weight_kg FROM child_medical_records cmr WHERE cmr.child_id = c.child_id ORDER BY cmr.visit_date DESC LIMIT 1) as latest_weight,
    (SELECT cmr.height_cm FROM child_medical_records cmr WHERE cmr.child_id = c.child_id ORDER BY cmr.visit_date DESC LIMIT 1) as latest_height,
    (SELECT COUNT(*) FROM child_medical_records cmr WHERE cmr.child_id = c.child_id) as total_visits
    FROM children c
    LEFT JOIN vaccination_schedule vs ON c.child_id = vs.child_id
    $where_clause
    GROUP BY c.child_id
    ORDER BY c.child_name
    LIMIT :offset, :per_page";

$children_stmt = $db->prepare($children_query);
foreach ($search_params as $key => $value) {
    $children_stmt->bindParam($key, $value);
}
$children_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$children_stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
$children_stmt->execute();
$children = $children_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stats_query = "SELECT 
    COUNT(DISTINCT c.child_id) as total_children,
    COUNT(DISTINCT CASE WHEN cmr.visit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN c.child_id END) as recent_visits,
    AVG(TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE())) as avg_age_months,
    COUNT(DISTINCT CASE WHEN vs.status = 'pending' AND vs.scheduled_date < CURDATE() THEN c.child_id END) as children_with_overdue
    FROM children c
    LEFT JOIN child_medical_records cmr ON c.child_id = cmr.child_id
    LEFT JOIN vaccination_schedule vs ON c.child_id = vs.child_id
    WHERE c.hospital_id = :hospital_id";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':hospital_id', $hospital_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-baby me-2 text-primary"></i>View Children</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="medical_records.php" class="btn btn-primary me-2">
            <i class="fas fa-plus me-1"></i>Add Medical Record
        </a>
        <a href="growth_charts.php" class="btn btn-outline-secondary">
            <i class="fas fa-chart-area me-1"></i>Growth Charts
        </a>
        <a href="dashboard.php" class="btn btn-outline-info">
            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center border-primary">
            <div class="card-body">
                <i class="fas fa-baby fa-2x text-primary mb-2"></i>
                <h4 class="text-primary"><?php echo $stats['total_children']; ?></h4>
                <p class="card-text">Total Children</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-success">
            <div class="card-body">
                <i class="fas fa-calendar-check fa-2x text-success mb-2"></i>
                <h4 class="text-success"><?php echo $stats['recent_visits']; ?></h4>
                <p class="card-text">Recent Visits (30 days)</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-info">
            <div class="card-body">
                <i class="fas fa-clock fa-2x text-info mb-2"></i>
                <h4 class="text-info"><?php echo $stats['avg_age_months'] ? round($stats['avg_age_months']) : '0'; ?></h4>
                <p class="card-text">Average Age (months)</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-warning">
            <div class="card-body">
                <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                <h4 class="text-warning"><?php echo $stats['children_with_overdue']; ?></h4>
                <p class="card-text">With Overdue Vaccines</p>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="fas fa-filter me-2"></i>Search & Filter Children</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by name, registration, or parent...">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="age_filter">
                    <option value="">All Ages</option>
                    <option value="infant" <?php echo $age_filter == 'infant' ? 'selected' : ''; ?>>Infant (0-12 months)</option>
                    <option value="toddler" <?php echo $age_filter == 'toddler' ? 'selected' : ''; ?>>Toddler (1-3 years)</option>
                    <option value="preschool" <?php echo $age_filter == 'preschool' ? 'selected' : ''; ?>>Preschool (3-5 years)</option>
                    <option value="school" <?php echo $age_filter == 'school' ? 'selected' : ''; ?>>School age (5+ years)</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="gender_filter">
                    <option value="">All Genders</option>
                    <option value="male" <?php echo $gender_filter == 'male' ? 'selected' : ''; ?>>Male</option>
                    <option value="female" <?php echo $gender_filter == 'female' ? 'selected' : ''; ?>>Female</option>
                </select>
            </div>
            <div class="col-md-3">
                <div class="btn-group w-100" role="group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                    <a href="children.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Quick Filters -->
<div class="card mb-4">
    <div class="card-body">
        <h6 class="card-title">Quick Filters</h6>
        <div class="btn-group btn-group-sm flex-wrap" role="group">
            <button type="button" class="btn btn-outline-danger" onclick="quickFilter('overdue')">
                <i class="fas fa-exclamation-triangle me-1"></i>With Overdue Vaccines
            </button>
            <button type="button" class="btn btn-outline-info" onclick="quickFilter('recent')">
                <i class="fas fa-baby me-1"></i>Infants (0-12m)
            </button>
            <button type="button" class="btn btn-outline-primary" onclick="quickFilter('male')">
                <i class="fas fa-mars me-1"></i>Male
            </button>
            <button type="button" class="btn btn-outline-warning" onclick="quickFilter('female')">
                <i class="fas fa-venus me-1"></i>Female
            </button>
        </div>
    </div>
</div>

<!-- Children List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5><i class="fas fa-list me-2"></i>Children List
            <?php if (!empty($search) || !empty($age_filter) || !empty($gender_filter)): ?>
                <small class="text-muted">- Filtered results</small>
            <?php endif; ?>
        </h5>
        <span class="badge bg-primary"><?php echo $total_children; ?> children</span>
    </div>
    <div class="card-body">
        <?php if (empty($children)): ?>
            <div class="text-center py-4">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No children found</h5>
                <?php if (!empty($search) || !empty($age_filter) || !empty($gender_filter)): ?>
                    <p>Try adjusting your search criteria or filters</p>
                    <a href="children.php" class="btn btn-outline-primary">View All Children</a>
                <?php else: ?>
                    <p>No children are registered in this hospital yet.</p>
                    <p class="text-muted">Children records are managed by nurses during registration.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($children as $child): ?>
                <div class="col-lg-6 mb-3">
                    <div class="card h-100 border-start border-<?php echo ($child['overdue_vaccines'] ?? 0) > 0 ? 'danger' : 'success'; ?> border-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="card-title mb-1">
                                        <i class="fas fa-<?php echo $child['gender'] == 'male' ? 'mars' : 'venus'; ?> 
                                           text-<?php echo $child['gender'] == 'male' ? 'info' : 'warning'; ?> me-2"></i>
                                        <?php echo htmlspecialchars($child['child_name']); ?>
                                    </h6>
                                    <small class="text-muted">
                                        <i class="fas fa-id-card me-1"></i><?php echo htmlspecialchars($child['registration_number']); ?>
                                    </small>
                                </div>
                                <span class="badge bg-info"><?php echo $child['age_months']; ?> months</span>
                            </div>
                            
                            <div class="row text-center mb-3">
                                <div class="col-4">
                                    <small class="text-muted d-block">Birth Date</small>
                                    <strong><?php echo $utils->formatDate($child['date_of_birth']); ?></strong>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted d-block">Visits</small>
                                    <strong><?php echo $child['total_visits'] ?? 0; ?></strong>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted d-block">Last Visit</small>
                                    <strong>
                                        <?php echo $child['last_visit'] ? $utils->formatDate($child['last_visit']) : 'None'; ?>
                                    </strong>
                                </div>
                            </div>
                            
                            <!-- Vaccination Progress -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="text-muted">Vaccination Progress</small>
                                    <small><?php echo $child['completed_vaccines'] ?? 0; ?>/<?php echo $child['total_vaccines'] ?? 0; ?></small>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <?php 
                                    $total_vaccines = $child['total_vaccines'] ?? 0;
                                    $completed_vaccines = $child['completed_vaccines'] ?? 0;
                                    $progress = $total_vaccines > 0 ? ($completed_vaccines / $total_vaccines) * 100 : 0;
                                    ?>
                                    <div class="progress-bar bg-success" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                                <?php $overdue_vaccines = $child['overdue_vaccines'] ?? 0; ?>
                                <?php if ($overdue_vaccines > 0): ?>
                                    <small class="text-danger">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        <?php echo $overdue_vaccines; ?> overdue vaccination<?php echo $overdue_vaccines > 1 ? 's' : ''; ?>
                                    </small>
                                <?php elseif ($total_vaccines == 0): ?>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        No vaccination schedule set up yet
                                    </small>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Latest Measurements -->
                            <?php if ($child['latest_weight'] || $child['latest_height']): ?>
                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">Latest Measurements</small>
                                <div class="row text-center">
                                    <?php if ($child['latest_weight']): ?>
                                    <div class="col-6">
                                        <small class="text-muted">Weight</small>
                                        <div><strong><?php echo $child['latest_weight']; ?> kg</strong></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($child['latest_height']): ?>
                                    <div class="col-6">
                                        <small class="text-muted">Height</small>
                                        <div><strong><?php echo $child['latest_height']; ?> cm</strong></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">Latest Measurements</small>
                                <small class="text-muted">No measurements recorded yet</small>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Parent Info -->
                            <div class="mb-3">
                                <small class="text-muted d-block">Parent/Guardian</small>
                                <strong><?php echo htmlspecialchars($child['parent_name']); ?></strong><br>
                                <small><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($child['parent_phone']); ?></small>
                                <?php if ($child['parent_email']): ?>
                                    <br><small><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($child['parent_email']); ?></small>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Actions -->
                            <div class="btn-group w-100" role="group">
                                <button class="btn btn-outline-primary btn-sm" onclick="viewChildDetails(<?php echo $child['child_id']; ?>)">
                                    <i class="fas fa-eye me-1"></i>View
                                </button>
                                <a href="medical_records.php?child_id=<?php echo $child['child_id']; ?>" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-plus me-1"></i>Record
                                </a>
                                <a href="growth_charts.php?child_id=<?php echo $child['child_id']; ?>" class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-chart-area me-1"></i>Charts
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Children pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&age_filter=<?php echo $age_filter; ?>&gender_filter=<?php echo $gender_filter; ?>">Previous</a>
                    </li>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&age_filter=<?php echo $age_filter; ?>&gender_filter=<?php echo $gender_filter; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&age_filter=<?php echo $age_filter; ?>&gender_filter=<?php echo $gender_filter; ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Child Details Modal -->
<div class="modal fade" id="childDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-baby me-2"></i>Child Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="childDetailsContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print me-1"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function viewChildDetails(childId) {
    // Show loading
    document.getElementById("childDetailsContent").innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading child details...</p>
        </div>
    `;
    
    new bootstrap.Modal(document.getElementById("childDetailsModal")).show();
    
    // Simple fallback since API might not exist yet
    setTimeout(() => {
        document.getElementById("childDetailsContent").innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Child details API is under development.
            </div>
            <div class="row">
                <div class="col-md-6">
                    <h6>Available Actions</h6>
                    <div class="d-grid gap-2">
                        <a href="medical_records.php?child_id=${childId}" class="btn btn-outline-success">
                            <i class="fas fa-plus me-1"></i>Add Medical Record
                        </a>
                        <a href="growth_charts.php?child_id=${childId}" class="btn btn-outline-info">
                            <i class="fas fa-chart-area me-1"></i>View Growth Charts
                        </a>
                    </div>
                </div>
                <div class="col-md-6">
                    <h6>Quick Info</h6>
                    <p class="text-muted">Detailed child information including medical history, vaccination records, and growth tracking will be shown here in future updates.</p>
                </div>
            </div>
        `;
    }, 1000);
}

// Auto search on typing (debounced)
let searchTimeout;
document.querySelector("input[name=search]").addEventListener("input", function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        if (this.value.length >= 3 || this.value.length === 0) {
            this.form.submit();
        }
    }, 500);
});

// Quick filter buttons
function quickFilter(filter) {
    const url = new URL(window.location);
    url.searchParams.delete('search');
    url.searchParams.delete('age_filter');
    url.searchParams.delete('gender_filter');
    
    switch(filter) {
        case "overdue":
            // This would need custom logic to filter by overdue vaccines
            alert('Filter by overdue vaccines - Feature enhancement needed');
            return;
        case "recent":
            url.searchParams.set("age_filter", "infant");
            break;
        case "male":
            url.searchParams.set("gender_filter", "male");
            break;
        case "female":
            url.searchParams.set("gender_filter", "female");
            break;
    }
    
    window.location.href = url.toString();
}

// Auto-clear search on escape key
document.querySelector("input[name=search]").addEventListener("keydown", function(e) {
    if (e.key === 'Escape') {
        this.value = '';
        this.form.submit();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>