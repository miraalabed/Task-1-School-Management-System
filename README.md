# School Management System (Console-Based)

A simple **console-based school management system** built with Laravel.

------------------------------------------------------------------------

## Features

-   Manage students, teachers, and classes.
-   Assign grades to students.
-   View overall statistics (active/inactive students, classes,
    teachers).
-   Console-based commands for easy interaction.
-   Data stored in text files (`students.txt`, `teachers.txt`,
    `classes.txt`, `grades.txt`).

------------------------------------------------------------------------

## Requirements

-   PHP 8+
-   Laravel 10+
-   Data files located in `storage/app/data/`

------------------------------------------------------------------------

## Installation & Usage

1.  Clone the repository:

    ``` bash
    git clone 
    https://github.com/miraalabed/Task-1-School-Management-System.git
    ```

2.  Navigate to the project folder:

    ``` bash
    cd school-management-system
    ```

3.  Run migrations (if needed):

    ``` bash
    php artisan migrate
    ```

4.  Run the system:

    ``` bash
    php artisan run:school-system
    ```

------------------------------------------------------------------------

## File Structure

-   `app/Models/` → Contains core models (Student, Teacher, SchoolClass)
-   `app/Services/` → Business logic (AdminService, StudentService,
    TeacherService)
-   `app/Console/Commands/` → Main console entry point
-   `storage/app/data/` → Text-based data files

------------------------------------------------------------------------

## Example Data Files

-   `students.txt` → Contains student records
-   `teachers.txt` → Contains teacher records
-   `classes.txt` → Contains class information
-   `grades.txt` → Stores student grades

------------------------------------------------------------------------

## Contact

For inquiries or contributions, feel free to reach out: 
- **Email**: miraalabed21@gmail.com
- **GitHub**: [Mira Al-Abed](https://github.com/miraalabed)

------------------------------------------------------------------------

© 2025 School Management System. All rights reserved.
