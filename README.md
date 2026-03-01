# Hospital Management System

A comprehensive web-based Hospital Management System built with PHP, MySQL, HTML, CSS, and JavaScript.

## Features

### Patient Features
- ✅ Patient registration and profile management
- ✅ Secure login with session management
- ✅ Search and filter doctors by specialization
- ✅ View doctor availability and time slots
- ✅ Online appointment booking
- ✅ Cancel and reschedule appointments
- ✅ View appointment history and status
- ✅ View and download prescriptions
- ✅ View invoices after payment

### Doctor Features
- ✅ Doctor dashboard with daily/weekly appointments
- ✅ Manage appointments (confirm, reschedule, cancel)
- ✅ View patient medical history
- ✅ Add diagnosis notes
- ✅ Create and manage prescriptions
- ✅ Manage availability and time slots
- ✅ Set leave/unavailable dates

### Receptionist Features
- ✅ Register walk-in patients
- ✅ Book appointments on behalf of patients
- ✅ Generate bills and invoices
- ✅ Print invoices for patients
- ✅ Manage overall appointment schedules

## Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript
- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Server**: Apache (XAMPP/WAMP/LAMP)

## Installation Instructions

### Prerequisites
- XAMPP, WAMP, or LAMP installed on your system
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web browser (Chrome, Firefox, Safari, etc.)

### Step 1: Install XAMPP (if not already installed)
1. Download XAMPP from https://www.apachefriends.org/
2. Install XAMPP on your system
3. Start Apache and MySQL from XAMPP Control Panel

### Step 2: Setup Database
1. Open your web browser and go to `http://localhost/phpmyadmin`
2. Click on "SQL" tab
3. Copy the entire content of `database.sql` file
4. Paste it in the SQL query box and click "Go"
5. The database and all tables will be created automatically

### Step 3: Setup Application Files
1. Copy the entire `hospital_management_system` folder
2. Paste it into your web server directory:
   - XAMPP: `C:\xampp\htdocs\`
   - WAMP: `C:\wamp\www\`
   - LAMP: `/var/www/html/`

### Step 4: Configure Database Connection
1. Open `includes/config.php`
2. Update the database credentials if needed (default settings work with XAMPP):
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'hospital_management');
   ```

### Step 5: Access the Application
1. Open your web browser
2. Go to `http://localhost/hospital_management_system/`
3. You should see the homepage

## Default Login Credentials

### Admin
- **Email**: admin@hospital.com
- **Password**: admin123

### Receptionist
- **Email**: receptionist@hospital.com
- **Password**: receptionist123

### Doctor Accounts
1. **Dr. John Smith** (Cardiologist)
   - **Email**: john.smith@hospital.com
   - **Password**: doctor123

2. **Dr. Emily Davis** (Dermatologist)
   - **Email**: emily.davis@hospital.com
   - **Password**: doctor123

3. **Dr. Michael Brown** (Orthopedic)
   - **Email**: michael.brown@hospital.com
   - **Password**: doctor123

### Patient Account
- Register a new patient account from the registration page

## Project Structure

```
hospital_management_system/
│
├── css/
│   └── style.css                 # Main stylesheet
│
├── js/
│   └── main.js                   # Main JavaScript file
│
├── includes/
│   └── config.php                # Database configuration
│
├── patient/
│   ├── dashboard.php             # Patient dashboard
│   ├── doctors.php               # Find doctors
│   ├── book_appointment.php      # Book appointments
│   ├── appointments.php          # View appointments
│   ├── appointment_details.php   # Appointment details
│   ├── prescriptions.php         # View prescriptions
│   ├── invoices.php              # View invoices
│   └── profile.php               # Update profile
│
├── doctor/
│   ├── dashboard.php             # Doctor dashboard
│   ├── appointments.php          # Manage appointments
│   ├── appointment_details.php   # Appointment details
│   ├── patients.php              # View patients
│   ├── schedule.php              # Manage schedule
│   └── profile.php               # Update profile
│
├── receptionist/
│   ├── dashboard.php             # Receptionist dashboard
│   ├── appointments.php          # View appointments
│   ├── walkin.php                # Walk-in registration
│   ├── patients.php              # View patients
│   ├── generate_invoice.php      # Generate invoices
│   └── invoices.php              # View invoices
│
├── uploads/
│   ├── prescriptions/            # Uploaded prescriptions
│   └── invoices/                 # Generated invoices
│
├── index.php                     # Homepage
├── login.php                     # Login page
├── register.php                  # Patient registration
├── logout.php                    # Logout functionality
└── database.sql                  # Database schema
```

## Database Schema

### Tables
1. **users** - Stores all user accounts (patients, doctors, receptionists)
2. **patients** - Extended patient information
3. **doctors** - Doctor details and specializations
4. **doctor_schedules** - Doctor availability by day
5. **doctor_leaves** - Doctor leave dates
6. **appointments** - All appointments
7. **prescriptions** - Medical prescriptions
8. **invoices** - Billing information

## Key Features Explained

### Appointment Booking System
- Patients can search for doctors by name or specialization
- View real-time doctor availability
- Book appointments based on doctor's schedule
- Automatic validation prevents double-booking
- Respects doctor's leave dates

### Appointment Status Flow
1. **Requested** - Patient books appointment
2. **Confirmed** - Doctor confirms the appointment
3. **Paid** - Payment processed at reception
4. **Completed** - Appointment finished
5. **Cancelled** - Cancelled by patient or doctor

### Security Features
- Password hashing using PHP password_hash()
- SQL injection prevention using prepared statements
- Session-based authentication
- Role-based access control
- Input sanitization

## Customization

### Adding New Specializations
Edit the doctors table and add specialization in the doctors.php file

### Changing Theme Colors
Edit `css/style.css` and modify the CSS variables in the `:root` selector:
```css
:root {
    --primary-color: #2c3e50;
    --secondary-color: #3498db;
    --success-color: #27ae60;
    --danger-color: #e74c3c;
    --warning-color: #f39c12;
}
```

### Adding Email Notifications
Integrate PHPMailer library and configure SMTP settings in config.php

## Troubleshooting

### Database Connection Error
- Verify MySQL is running in XAMPP
- Check database credentials in `includes/config.php`
- Ensure database `hospital_management` exists

### Page Not Found (404 Error)
- Verify the project folder is in the correct web directory
- Check that Apache is running in XAMPP
- Clear browser cache

### Login Not Working
- Verify you're using the correct credentials
- Check if session is started in config.php
- Clear browser cookies

### Styling Issues
- Check if `css/style.css` file exists
- Verify the CSS file path in HTML files
- Clear browser cache

## Future Enhancements

- [ ] Email notifications for appointments
- [ ] SMS reminders
- [ ] Online payment integration
- [ ] Lab test management
- [ ] Medicine inventory management
- [ ] Report generation (PDF)
- [ ] Advanced search and filters
- [ ] Multi-language support
- [ ] Mobile app integration
- [ ] Telemedicine features

