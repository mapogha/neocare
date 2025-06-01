<?php
require_once __DIR__ . '/../config/database.php';

class NeoCareUtils {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    // Generate unique registration number
    public function generateRegistrationNumber($hospital_id) {
        $year = date('Y');
        $hospital_code = str_pad($hospital_id, 2, '0', STR_PAD_LEFT);
        
        // Get next sequence number
        $query = "SELECT COUNT(*) as count FROM children WHERE hospital_id = :hospital_id AND YEAR(created_at) = :year";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':hospital_id', $hospital_id);
        $stmt->bindParam(':year', $year);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $sequence = str_pad(($result['count'] + 1), 4, '0', STR_PAD_LEFT);
        
        return "NC{$year}{$hospital_code}{$sequence}";
    }
    
    // Create vaccination schedule for child
    public function createVaccinationSchedule($child_id, $birth_date) {
        // Get all active vaccines
        $query = "SELECT * FROM vaccines WHERE is_active = 1 ORDER BY child_age_weeks";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $vaccines = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $birth_timestamp = strtotime($birth_date);
        
        foreach ($vaccines as $vaccine) {
            $weeks_to_add = $vaccine['child_age_weeks'];
            $scheduled_date = date('Y-m-d', $birth_timestamp + ($weeks_to_add * 7 * 24 * 60 * 60));
            
            $insert_query = "INSERT INTO vaccination_schedule (child_id, vaccine_id, scheduled_date) 
                           VALUES (:child_id, :vaccine_id, :scheduled_date)";
            $insert_stmt = $this->db->prepare($insert_query);
            $insert_stmt->bindParam(':child_id', $child_id);
            $insert_stmt->bindParam(':vaccine_id', $vaccine['vaccine_id']);
            $insert_stmt->bindParam(':scheduled_date', $scheduled_date);
            $insert_stmt->execute();
        }
    }
    
    // Get child's age in months
    public function getChildAgeInMonths($birth_date) {
        $birth = new DateTime($birth_date);
        $today = new DateTime();
        $diff = $today->diff($birth);
        
        return ($diff->y * 12) + $diff->m;
    }
    
    // Get vaccination status
    public function getVaccinationStatus($child_id) {
        $query = "SELECT 
                    COUNT(*) as total_vaccines,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_vaccines,
                    SUM(CASE WHEN status = 'pending' AND scheduled_date < CURDATE() THEN 1 ELSE 0 END) as overdue_vaccines
                  FROM vaccination_schedule 
                  WHERE child_id = :child_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':child_id', $child_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Send SMS reminder
    public function sendSMSReminder($phone, $message) {
        $api_key = SMSConfig::$api_key;
        $sender_id = SMSConfig::$sender_id;
        $api_url = SMSConfig::$api_url;
        
        $data = array(
            'apikey' => $api_key,
            'sender' => $sender_id,
            'to' => $phone,
            'message' => $message
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $http_code == 200;
    }
    
    // Get upcoming vaccinations for reminders
    public function getUpcomingVaccinations($days_ahead = 7) {
        $query = "SELECT vs.*, c.child_name, c.parent_name, c.parent_phone, v.vaccine_name
                  FROM vaccination_schedule vs
                  JOIN children c ON vs.child_id = c.child_id
                  JOIN vaccines v ON vs.vaccine_id = v.vaccine_id
                  WHERE vs.status = 'pending' 
                  AND vs.scheduled_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':days', $days_ahead);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Format date for display
    public function formatDate($date, $format = 'M d, Y') {
        return date($format, strtotime($date));
    }
    
    // Calculate BMI for age percentile (simplified)
    public function calculateBMI($weight_kg, $height_cm) {
        if ($height_cm <= 0) return 0;
        $height_m = $height_cm / 100;
        return round($weight_kg / ($height_m * $height_m), 2);
    }
    
    // Get growth chart data
    public function getGrowthChartData($child_id) {
        $query = "SELECT weight_kg, height_cm, age_months, visit_date 
                  FROM child_medical_records 
                  WHERE child_id = :child_id 
                  ORDER BY visit_date ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':child_id', $child_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Clean input data
    public function cleanInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }
    
    // Validate email
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    // Validate phone number (Tanzanian format)
    public function validatePhone($phone) {
        $pattern = '/^(\+255|0)[6-9]\d{8}$/';
        return preg_match($pattern, $phone);
    }
}

// Initialize utils
$utils = new NeoCareUtils();
?>
