# Car Rental Management System (CRMS)

A web-based Car Rental Management System designed to simplify vehicle rental operations, customer management, reservations, payments, and reporting through a centralized platform.

## Project Overview

The Car Rental Management System (CRMS) aims to improve the efficiency, accuracy, and reliability of car rental services by digitizing daily business operations. It provides a structured way to manage vehicles, customers, bookings, rentals, payments, maintenance, and business reports.

## Objectives

- Manage vehicle information and availability.
- Register and maintain customer records.
- Create and manage vehicle reservations.
- Track rental agreements and vehicle returns.
- Record and manage rental payments.
- Monitor vehicle maintenance.
- Generate reports to support business decisions.
- Improve data organization, security, and operational efficiency.

## Key Features

- **User Authentication:** Secure login and role-based access.
- **Vehicle Management:** Add, update, view, and manage vehicle records.
- **Customer Management:** Maintain customer information.
- **Reservation Management:** Create and track bookings.
- **Rental Management:** Monitor rentals, returns, and rental status.
- **Payment Management:** Record payments and monitor payment status.
- **Maintenance Management:** Track vehicle maintenance activities.
- **Dashboard and Reports:** View operational summaries and generate reports.

## Technology Stack

| Technology | Purpose |
|---|---|
| HTML5 | Web page structure |
| CSS3 | Styling and responsive layouts |
| JavaScript | Client-side interactivity |
| PHP | Server-side application logic |
| MySQL | Database management |
| Apache | Local web server |
| Git and GitHub | Version control and team collaboration |

## System Requirements

- Windows, Linux, or macOS
- XAMPP or another PHP development environment
- PHP
- MySQL or MariaDB
- A modern web browser
- Git
- Visual Studio Code or another code editor

## Installation and Setup

### 1. Clone the Repository

```bash
git clone https://github.com/cracker38/CarRental.git
```

### 2. Enter the Project Directory

```bash
cd CarRental
```

### 3. Move the Project into XAMPP

Place the project folder inside XAMPP's `htdocs` directory.

For example:

```text
C:\xampp\htdocs\CarRental
```

### 4. Start XAMPP Services

Open the XAMPP Control Panel and start:

- Apache
- MySQL

### 5. Configure the Database

1. Open `http://localhost/phpmyadmin/`.
2. Create the database specified by the project configuration.
3. Import the project's SQL file if one is available.
4. Configure the database connection settings in the project.

**Note:** The database name, SQL filename, and configuration file depend on the actual project implementation.

### 6. Run the Application

Open the project in your browser:

```text
http://localhost/CarRental/
```

## Team Members

This project is developed collaboratively by the following team members:

| No. | Name | Student ID |
|---:|---|---|
| 1 | DUKUZUMUREMYI Elias | 26RP00182 |
| 2 | TUYIZERE Aboudhari | 26RP00647 |
| 3 | DUSENGIMANA J. De Dieu | 26RP01798 |
| 4 | NSINGIMANA Jonathan | 26RP00696 |
| 5 | NIYOMUGENDA Eugenie | 26RP02927 |

## Team Collaboration Workflow

The team uses Git and GitHub to collaborate on development.

1. Each member works on an assigned feature branch.
2. Members commit their changes regularly.
3. Changes are pushed to GitHub.
4. Members create pull requests for code review.
5. Approved changes are merged into the main branch.

Example:

```bash
git switch -c feature/your-feature
git add .
git commit -m "Implement your feature"
git push -u origin feature/your-feature
```

Replace `your-feature` with your assigned feature name.

## Project Scope

The system covers vehicle and customer records, reservations, rental transactions, payments, maintenance tracking, and reporting. Final functionality depends on the implemented and approved system requirements.

## Security Considerations

- Validate user input on both the client and server.
- Use password hashing for stored passwords.
- Use prepared SQL statements to help prevent SQL injection.
- Enforce authorization checks for protected operations.
- Protect sensitive configuration files and credentials.
- Validate rental dates, payment amounts, and vehicle availability.

## Project Status

**Development:** In progress.

Features and documentation may change as the team develops and tests the system.

## License

No license has been specified yet. All rights remain with the project owners until a license is selected.

---

**Car Rental Management System (CRMS)**  
Developed as a collaborative software development project.
