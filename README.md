# NeoCare System 💉👶

**Child Immunization & Healthcare Management System**

A comprehensive web-based system for managing child immunization and healthcare across multiple hospitals, designed to ensure complete and timely vaccination coverage with automated scheduling, SMS reminders, and growth monitoring.

![PHP](https://img.shields.io/badge/PHP-8.0%2B-blue)
![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-orange)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.1.3-purple)
![License](https://img.shields.io/badge/License-MIT-green)

## 🌟 Features

### 👥 **Multi-Role Access System**
- **Super Admin**: Global system management and cross-hospital oversight
- **Hospital Admin**: Staff and facility management with comprehensive reporting
- **Doctor**: Medical records, growth monitoring, and patient care
- **Nurse**: Child registration, vaccination administration, and schedule monitoring
- **Parent**: Child health tracking and vaccination schedule access

### 🏥 **Multi-Hospital Support**
- Centralized system supporting multiple healthcare facilities
- Hospital-specific data isolation and management
- Cross-hospital reporting and analytics for system administrators

### 📱 **Automated Communication**
- **SMS Integration** with mshastra.com API
- Welcome messages for new registrations
- Vaccination reminder notifications
- Overdue vaccination alerts
- Delivery status tracking

### 📊 **Comprehensive Reporting**
- Real-time vaccination coverage statistics
- Interactive charts and data visualizations
- Staff performance analytics
- Growth monitoring and BMI tracking
- Exportable reports in multiple formats

### 🔍 **Advanced Search & Filtering**
- Multi-criteria search across all child listings
- Age group, gender, and status-based filtering
- Real-time search with debounced input
- Pagination for large datasets
- Quick action filters and bulk operations

## 🚀 Quick Start

### Prerequisites
- **PHP 8.0+** with PDO and cURL extensions
- **MySQL 8.0+** database server
- **Web Server** (Apache/Nginx) or WAMP/XAMPP for development
- **Internet Connection** for SMS API functionality

### Installation

1. **Clone or Download**
   ```bash
   git clone [repository-url]
   cd neocare-system
   ```

2. **Database Setup**
   ```sql
   CREATE DATABASE neocare_system;
   ```
   - Import the provided SQL schema
   - Default admin account: `superadmin` / `admin123`

3. **Configuration**
   ```php
   // config/database.php
   private $host = 'localhost';
   private $db_name = 'neocare_system';
   private $username = 'root';
   private $password = 'your_password';
   
   // SMS API Configuration
   public static $api_key = 'your_mshastra_api_key';
   ```

4. **File Permissions**
   - Ensure web server has read/write permissions to all directories

5. **Access System**
   - Navigate to `http://localhost/neocare/`
   - Login with default credentials or register new users

## 📁 Project Structure

```
neocare/
├── 📂 config/
│   └── database.php              # Database and system configuration
├── 📂 includes/
│   ├── header.php               # Common header template
│   ├── footer.php               # Common footer template
│   ├── session.php              # Session management
│   └── functions.php            # Utility functions
├── 📂 super_admin/
│   ├── hospitals.php            # Hospital management
│   ├── admins.php              # Hospital admin management
│   └── reports.php             # Global reports
├── 📂 admin/
│   ├── staff.php               # Staff management
│   ├── children.php            # Children management
│   ├── vaccines.php            # Vaccine management
│   └── reports.php             # Hospital reports
├── 📂 doctor/
│   ├── children.php            # View children
│   ├── medical_records.php     # Medical records management
│   └── growth_charts.php       # Growth monitoring
├── 📂 nurse/
│   ├── register_child.php      # Child registration
│   ├── vaccination.php         # Vaccination management
│   ├── children_list.php       # Children listing
│   └── reports.php             # Nursing reports
├── 📂 parent/
│   ├── vaccination_schedule.php # Vaccination timeline
│   └── growth_records.php      # Growth tracking
├── index.php                   # Login page
├── dashboard.php              # Role-based dashboard
└── logout.php                 # Logout functionality
```

## 🎯 User Guide

### For Super Administrators
- **Hospital Management**: Add, edit, and monitor hospitals
- **Admin Assignment**: Assign hospital administrators
- **Global Analytics**: View system-wide reports and statistics
- **System Oversight**: Monitor vaccination coverage across all facilities

### For Hospital Administrators
- **Staff Management**: Create and manage doctor/nurse accounts
- **Children Oversight**: Monitor all children in the hospital
- **Vaccine Management**: Manage vaccine inventory and schedules
- **Performance Reports**: Generate hospital-specific analytics

### For Doctors
- **Patient Care**: Access complete child health records
- **Medical Records**: Add weight, height, vital signs, and clinical notes
- **Growth Analysis**: View interactive growth charts and BMI tracking
- **Vaccination History**: Monitor immunization progress

### For Nurses
- **Child Registration**: Register new children with auto-generated schedules
- **Vaccination Administration**: Record vaccine administration
- **Schedule Monitoring**: Track due and overdue vaccinations
- **Communication**: Send SMS reminders to parents

### For Parents
- **Child Access**: Login with child name and registration number
- **Vaccination Schedule**: View complete immunization timeline
- **Growth Tracking**: Monitor development progress with charts
- **SMS Notifications**: Receive automatic reminders and updates

## 🔧 Technical Specifications

### System Architecture
- **Frontend**: Bootstrap 5.1.3, Font Awesome 6.0, Chart.js
- **Backend**: PHP 8.0+ with PDO for database interactions
- **Database**: MySQL 8.0+ with optimized schema and indexes
- **Security**: Session management, input validation, SQL injection prevention

### Key Features
- **Responsive Design**: Mobile-friendly interface across all devices
- **Real-time Updates**: Dynamic dashboards with live statistics
- **Automated Workflows**: Schedule generation and reminder systems
- **Data Visualization**: Interactive charts and progress indicators
- **Export Capabilities**: JSON and print-friendly report formats

### Color Scheme
- **Background**: Pure White (#FFFFFF)
- **Navigation**: Custom Purple (rgb(53, 50, 84))
- **Accents**: Bootstrap color palette for status indicators

## 📋 Default Data

### Sample Hospitals
- Central Hospital (ID: 1)
- Community Health Center (ID: 2)

### Default Accounts
```
Super Admin:
- Username: superadmin
- Password: admin123

Hospital Admins:
- Username: central_admin / Password: admin123
- Username: community_admin / Password: admin123
```

### Standard Vaccines
- BCG (At birth)
- OPV 1, DPT 1 (6 weeks)
- OPV 2, DPT 2 (10 weeks)
- OPV 3, DPT 3 (14 weeks)
- Measles (36 weeks)
- MMR (52 weeks)

## 🔐 Security Features

### Authentication
- Role-based access control with permission hierarchies
- Secure session management with timeout handling
- Plain text password storage (as per specifications)
- Input validation and sanitization throughout

### Data Protection
- Hospital-specific data isolation
- SQL injection prevention with prepared statements
- XSS protection with HTML entity encoding
- Audit trails for critical operations

## 📞 API Integration

### SMS Service (mshastra.com)
```php
// Configuration
public static $api_key = 'your_api_key';
public static $sender_id = 'NEOCARE';
public static $api_url = 'https://api.mshastra.com/sendsms';

// Message Types
- Welcome: Child registration confirmation
- Reminder: Upcoming vaccination alerts
- Overdue: Missed vaccination notifications
- Confirmation: Vaccination completion updates
```

## 🚨 Troubleshooting

### Common Issues

**Database Connection Error**
```
Solution: Check MySQL service and verify credentials in config/database.php
```

**Login Problems**
```
Solution: Verify default credentials (superadmin/admin123) and check user status
```

**SMS Not Working**
```
Solution: Verify API credentials and internet connection
```

**Permission Errors**
```
Solution: Check user roles and clear browser sessions
```

## 📈 System Requirements

### Minimum Requirements
- **RAM**: 2GB
- **Storage**: 1GB available space
- **PHP Memory**: 128MB
- **MySQL**: 5.7+ (8.0+ recommended)

### Recommended Requirements
- **RAM**: 4GB+
- **Storage**: 5GB+ available space
- **PHP Memory**: 256MB+
- **SSD Storage**: For better performance

## 🔄 Backup & Maintenance

### Database Backup
```bash
# Create backup
mysqldump -u username -p neocare_system > backup_$(date +%Y%m%d).sql

# Restore backup
mysql -u username -p neocare_system < backup_file.sql
```

### Regular Maintenance
- **Weekly**: Database backup and log cleanup
- **Monthly**: Performance optimization and user account review
- **Quarterly**: Security updates and system health checks

## 🌟 Future Enhancements

### Planned Features
- **Two-Factor Authentication**: Enhanced security for admin accounts
- **Mobile Applications**: Native iOS/Android apps for parents
- **Advanced Analytics**: Machine learning for health predictions
- **Telemedicine Integration**: Video consultation capabilities
- **Multi-language Support**: Swahili and English interfaces

### Technical Improvements
- **RESTful API**: For third-party integrations
- **Real-time Notifications**: WebSocket implementation
- **Cloud Deployment**: AWS/Azure compatibility
- **Performance Monitoring**: Application metrics and alerting

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 📧 Support

For technical support or questions:
- **Documentation**: Check the comprehensive system documentation
- **Issues**: Report bugs through the issue tracker
- **Updates**: Follow releases for new features and improvements

## 🏆 Acknowledgments

- **Bootstrap Team**: For the responsive framework
- **Chart.js**: For data visualization capabilities
- **Font Awesome**: For comprehensive icon library
- **mshastra.com**: For SMS API services
- **Healthcare Professionals**: For requirements validation

---

**NeoCare System v1.0.0** - *Ensuring every child receives timely immunization care* 💙

Built with ❤️ for healthcare providers and families worldwide.
