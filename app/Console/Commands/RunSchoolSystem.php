<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Services\AdminService;
use App\Services\StudentService;

class RunSchoolSystem extends Command
{
    // Declare services for admin and student tasks
    protected AdminService $adminService;
    protected StudentService $studentService;

    protected $signature = 'run:school-system';
    protected $description = 'Console-based School Management System';

    // Arrays to store students and class data
    protected $students = [];
    protected $classes = [];

    public function __construct()
    {
        parent::__construct();

        // Assign supervisors and subjects for each grade from 1 to 12
        $supervisors = [
            1 => 'Ms. Lina', 2 => 'Mr. Ziad', 3 => 'Ms. Abeer', 4 => 'Mr. Khaled',
            5 => 'Ms. Rasha', 6 => 'Mr. Sami', 7 => 'Ms. Noor', 8 => 'Mr. Fadi',
            9 => 'Ms. Huda', 10 => 'Mr. Tarek', 11 => 'Ms. Diana', 12 => 'Dr. Nabil'
        ];

        for ($i = 1; $i <= 12; $i++) {
            $gradeName = "Grade $i";
            $defaultSubjects = match ($i) {
                1, 2 => ['Arabic', 'Math', 'Art'],
                3, 4, 5 => ['Arabic', 'Math', 'Science'],
                6, 7, 8 => ['Math', 'Science', 'English', 'Geography'],
                9 => ['Math', 'Biology', 'History'],
                10 => ['Math', 'Science', 'English'],
                11 => ['Physics', 'Chemistry', 'English'],
                12 => ['History', 'Economics', 'Philosophy'],
                default => ['General Knowledge']
            };
            $supervisor = $supervisors[$i];
            $this->classes[$gradeName] = new SchoolClass($gradeName, $defaultSubjects, $supervisor);
        }

        // Add some sample students
        $this->students = [
            new Student('Ali Ahmad', 'ali@gmail.com', 16, 'Grade 10', true, '0777000111'),
            new Student('Sara Younes', 'sara@gmail.com', 17, 'Grade 11', false, '0799887766'),
        ];
    }

    // Main entry point for the system
    public function handle()
    {
        // Initialize services
        $this->adminService = new AdminService($this, $this->students, $this->classes);
        $this->studentService = new StudentService($this, $this->classes);

        $this->info("Welcome to School Management System");

        // Let user choose role
        $role = $this->choice("Please select your role", ['Admin', 'Student']);

        // Ask for email and validate it
        while (true) {
            $email = $this->ask("Enter your email");

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->error("Invalid email format.");
                continue;
            }

            if (strtolower($role) === 'admin' && $email !== 'admin@gmail.com') {
                $this->error("Admin must login using: admin@gmail.com");
                continue;
            }

            if (strtolower($role) === 'student' && $email === 'admin@gmail.com') {
                $this->error("This email is reserved and cannot be used by students.");
                continue;
            }

            break; // email is valid
        }

        // Show login success and continue to the correct dashboard
        $this->line("You are logged in as [$role] with email: $email");
        $this->logAction("$role logged in using email: $email");

        if (strtolower($role) === 'admin') {
            $this->runAsAdmin($email);
        } else {
            $this->runAsStudent($email);
        }
    }

    // Admin dashboard with menu options
    private function runAsAdmin($email)
    {
        $this->info("Welcome to Admin Panel");

        while (true) {
            $choice = $this->choice("Admin Menu", [
                'Create New Student',
                'View Student Profile',
                'Edit Student',
                'Delete Student',
                'Activate/Deactivate Student Account',
                'View All Students',
                'View Active Students',
                'Manage Subjects and Grades',
                'View Classes Info',
                'Switch account',
                'Exit'
            ]);

            match ($choice) {
                'Create New Student' => $this->adminService->createStudent(),
                'View Student Profile' => $this->adminService->viewStudentByEmail(),
                'Edit Student' => $this->adminService->editStudent(),
                'Delete Student' => $this->adminService->deleteStudent(),
                'Activate/Deactivate Student Account' => $this->adminService->toggleStudentStatus(),
                'View All Students' => $this->adminService->listAllStudents(),
                'View Active Students' => $this->adminService->listActiveStudents(),
                'Manage Subjects and Grades' => $this->adminService->manageSubjectsAndGrades(),
                'View Classes Info' => $this->adminService->viewClassesInfo(),
               'Switch account' =>  $this->SwitchAccount(),
 // restarts the login process
                'Exit' => $this->showExitMessage(),
            };
        }
    }

    // Student dashboard with their available actions
    private function runAsStudent($email)
    {
        $student = collect($this->students)->firstWhere('email', $email);

        if (!$student) {
            $this->error("Student not found with email: $email");
            return;
        }

        while (true) {
            $choice = $this->choice("Student Dashboard", [
                'View Profile',
                'Update Contact Info',
                'View Subjects and grades',
                'View Assigned Classroom',
                'Switch account',
                'Exit'
            ]);

            match ($choice) {
                'View Profile' => $this->studentService->showProfile($student),
                'Update Contact Info' => $this->studentService->updateContact($student, $this->students),
                'View Subjects and grades' => $this->studentService->showSubjectsAndGrades($student),
                'View Assigned Classroom' => $this->studentService->showClassroom($student),
                              'Switch account' =>  $this->SwitchAccount(),


                'Exit' => $this->showExitMessage(),
            };
        }
    }

    // Method to log actions to audit file
    private function logAction($message)
    {
        $timestamp = now()->toDateTimeString();
        $logLine = "[$timestamp] $message\n";
        file_put_contents(storage_path('logs/audit_log.txt'), $logLine, FILE_APPEND);
    }

    // This shows a goodbye message and audit log path before exiting the app
    private function showExitMessage()
    {
$this->logAction("User exited the system.");
        $this->info("Goodbye!");
        $this->line("Audit log saved at: " . storage_path('logs/audit_log.txt'));
        exit;
    }
private function SwitchAccount(){
$this->logAction("User switches his account.");
$this->handle();
}
}
