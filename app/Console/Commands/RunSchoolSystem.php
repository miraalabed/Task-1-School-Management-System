<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\SchoolClass;
use App\Services\AdminService;
use App\Services\StudentService;
use App\Services\TeacherService;

class RunSchoolSystem extends Command
{
    // Services for each role
    protected AdminService $adminService;
    protected StudentService $studentService;
    protected TeacherService $teacherService;

    // Command signature and description
    protected $signature = 'run:school-system';
    protected $description = 'Console-based School Management System';

    // Arrays to store objects
    protected $students = [];
    protected $classes = [];
    protected $teachers = [];

    // Current logged-in user info
    protected ?string $currentRole = null;
    protected ?string $currentUserName = null;

    // Admin credentials (hardcoded for simplicity)
    private string $adminName = "Ahmad Al-Omar";
    private string $adminEmail = "ahmad@admin.com";
    private string $adminPassword = "Pass123";

    // Constructor runs when the command is created
    public function __construct()
    {
        parent::__construct();
        // Load all data from files at startup
        $this->loadClassesFromFile(storage_path('app\data\classes.txt'));
        $this->loadStudentsFromFile(storage_path('app\data\students.txt')); 
        $this->loadGradesFromFile(storage_path('app\data\grades.txt'));
        $this->loadTeachersFromFile(storage_path('app\data\teachers.txt'));
    }

    // Load students from file
    private function loadStudentsFromFile($filePath)
    {
        if (!file_exists($filePath)) {
            $this->error("Students file not found: $filePath");
            return;
        }
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            [$name, $idNumber, $role, $phone, $age, $className, $email, $password, $status] = explode(',', $line);
            $schoolClassObj = $this->classes[$className] ?? null;
            if (!$schoolClassObj) {
                $this->error("Class '$className' not found for student $name");
                continue;
            }
            // Only allow student role here
            if (!in_array(strtolower($role), ['student', 'teacher'])) {
                $this->error("Invalid role '$role' for $name");
                continue;
            }
            if (strtolower($role) === 'student') {
                try {
                    $student = new Student(
                        $name,
                        $phone,
                        $idNumber,
                        (int)$age,
                        $email,
                        $password,
                        $schoolClassObj,
                        strtolower($status)
                    );
                    $this->students[] = $student; // Save student object
                } catch (\Exception $e) {
                    $this->error("Error creating student $name: " . $e->getMessage());
                }
            }
        }
    }

    // Load teachers from file
    private function loadTeachersFromFile(string $filePath): void
    {
        if (!file_exists($filePath)) return;
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $parts = explode(',', $line);
            [$name, $email, $phone, $password, $subject] = array_slice($parts, 0, 5);
            $classNames = array_slice($parts, 5);

            // Get class objects for this teacher
            $classObjects = [];
            foreach ($classNames as $className) {
                if (isset($this->classes[$className])) {
                    $classObjects[] = $this->classes[$className];
                }
            }
            $teacher = new Teacher($name, $phone, $email, $password, $subject, $classObjects);
            $this->teachers[$email] = $teacher; // Save teacher object by email
        }
    }

    // Load classes from file
    private function loadClassesFromFile($filePath)
    {
        if (!file_exists($filePath)) {
            $this->error("Classes file not found: $filePath");
            return;
        }
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            [$className, $supervisor, $subjectsStr] = explode(',', $line);
            $subjects = explode('-', $subjectsStr); // Subjects are separated by "-"
            $schoolClass = new SchoolClass($className, $subjects, $supervisor);
            $this->classes[$className] = $schoolClass; // Save class object
        }
    }

    // Load grades from file
    private function loadGradesFromFile($filePath)
    {
        if (!file_exists($filePath)) {
            $this->error("Grades file not found: $filePath");
            return;
        }
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            [$email, $subject, $grade] = array_map('trim', explode(',', $line));
            $student = collect($this->students)->first(fn($s) => $s->getEmail() === $email);
            if ($student) {
                $student->addOrUpdateGrade($subject, $grade); // Assign grade to student
            }
        }
    }

    // Main handle method, runs when command starts
    public function handle()
    {
        $this->clearConsole(); // Clear screen for better view
        if (!is_array($this->teachers)) $this->teachers = [];

        // Create services for each role
        $this->adminService = new AdminService($this, $this->students, $this->classes, $this->teachers);
        $this->studentService = new StudentService($this, $this->classes);
        $this->teacherService = new TeacherService($this, $this->teachers);
        $this->info("Welcome to School Management System");

        // Ask for email and validate
        while (true) {
            $email = $this->ask("Enter your email");
            if (empty(trim($email))) {
                $this->error("Email field must not be empty.");
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->error("Invalid email format.");
                continue;
            }
            break;
        }

        // Determine user role based on email domain
        $role = match (true) {
            str_ends_with($email, '@admin.com') => 'admin',
            str_ends_with($email, '@teacher.com') => 'teacher',
            str_ends_with($email, '@student.com') => 'student',
            default => null
        };
        if (!$role) {
            $this->error("Unknown email format. Please use a valid domain.");
            return;
        }

        // Check password with max attempts
        $attempts = 0;
        $maxAttempts = 3;
        $loginSuccess = false;
        while ($attempts < $maxAttempts) {
            $password = $this->secret("Enter your password");
            if (empty(trim($password))) {
                $this->error("Password field must not be empty.");
                $attempts++;
                continue;
            }

            // Validate credentials based on role
            if ($role === 'admin') {
                if ($email !== $this->adminEmail || $password !== $this->adminPassword) {
                    $this->error("Error in Password.");
                    $attempts++;
                    continue;
                }
                $userName = $this->adminName;
            } elseif ($role === 'teacher') {
                $teacher = $this->teachers[$email] ?? null;
                if (!$teacher || $teacher->getPassword() !== $password) {
                    $this->error("Error in Password.");
                    $attempts++;
                    continue;
                }
                $userName = $teacher->getName();
            } elseif ($role === 'student') {
                $student = collect($this->students)->first(fn($s) => $s->getEmail() === $email);
                if (!$student || $student->getPassword() !== $password) {
                    $this->error("Error in Password.");
                    $attempts++;
                    continue;
                }
                // Check if student is active
                if (strtolower($student->getStatus()) !== 'active') {
                    $this->error("This account is not activated. Please contact the administration.");
                    return;
                }
                $userName = $student->getName();
            }
            $this->currentRole = $role;
            $this->currentUserName = $userName;
            $loginSuccess = true;
            break;
        }
        if (!$loginSuccess) {
            $this->error("Too many failed attempts. Returning to main menu...");
            return;
        }
        $this->clearConsole();
        $this->logAction(ucfirst($role) . " $userName logged in using email: $email");

        // Run correct menu for user role
        if ($role === 'admin') {
            $this->adminService->setCurrentUser($role, $this->adminName);
            $this->runAsAdmin($email, $userName);
        } elseif ($role === 'teacher') {
            $this->teacherService->setCurrentUser($role, $teacher->getName());
            $this->runAsTeacher($email, $userName); 
        } elseif ($role === 'student') {
            $this->studentService->setCurrentUser($role, $student->getName());
            $this->runAsStudent($email, $userName);
        }
    }

    // Admin menu loop
    private function runAsAdmin($email, $userName)
    {
        $this->info("Welcome to Admin Panel");
        while (true) {
            $choice = $this->choice("Admin Menu", [
                'Create\Management Student',
                'Create\Management Teacher',
                'Create\Management Classroom',
                'View Classes Info',
                'Search for a user',
                'List Students',
                'List Teachers',
                'Report',
                'Exit'
            ]);

            // Run selected option
            match ($choice) {
                'Create\Management Student' => $this->adminService->manageStudents(),
                'Create\Management Teacher' => $this->adminService->manageTechers(),
                'Create\Management Classroom' => $this->adminService->manageClassroom(),
                'View Classes Info' => $this->adminService->viewClassesInfo(),
                'Search for a user' => $this->adminService->searchUserByEmail(),
                'List Students' => $this->adminService->listStudents(),
                'List Teachers' => $this->adminService->listTeachers(),
                'Report' => $this->adminService->Report(),
                'Exit' => $this->showExitMessage(),
            };
            $this->clearConsole(); 
        }
    }

    // Teacher menu loop
    private function runAsTeacher($email, $userName)
    {
        $this->info("Welcome to Teacher Home");
        $teacher = collect($this->teachers)->first(fn($s) => $s->getEmail() === $email);
        if (!$teacher) {
            $this->error("Teacher not found with email: $email");
            return;
        }
        while (true) {
            $choice = $this->choice("Teacher Menu", [
                'View Profile',
                'Update Contact Info',
                'Manage Student Grades',
                'Exit'
            ]);

            match ($choice) {
                'View Profile' => $teacher->showProfile($this),
                'Update Contact Info' => $this->teacherService->updateContact($teacher, $this->teachers),
                'Manage Student Grades' => $this->teacherService->manageStudentGrades($teacher,  $this->students),
                'Exit' => $this->showExitMessage(),
            };
            $this->clearConsole();
        }
    }

    // Student menu loop
    private function runAsStudent($email, $userName)
    {
        $this->info("Welcome to Student Home");
        $student = collect($this->students)->first(fn($s) => $s->getEmail() === $email);
        if (!$student) {
            $this->error("Student not found with email: $email");
            return;
        }
        while (true) {
            $choice = $this->choice("Student Menu", [
                'View Profile',
                'Update Contact Info',
                'View grades',
                'View Assigned Classroom',
                'Exit'
            ]);

            match ($choice) {
                'View Profile' => $student->showProfile($this),
                'Update Contact Info' => $this->studentService->updateContact($student, $this->students),
                'View grades' => $this->studentService->showGrades($student),
                'View Assigned Classroom' => $student->getClass()->showInfo(),
                'Exit' => $this->showExitMessage(),
            };
            $this->clearConsole();
        }
    }

    // Save action to log file
    private function logAction($message)
    {
        $timestamp = now()->toDateTimeString();
        $logLine = "[$timestamp] $message\n";
        file_put_contents(storage_path('logs/audit_log.txt'), $logLine, FILE_APPEND);
    }

    // Clear console screen
    private function clearConsole()
    {
        $this->output->write("\033[2J\033[;H"); // ANSI escape code for clearing screen
    }

    // Exit system with message and log
    private function showExitMessage()
    {
        $user = $this->currentRole && $this->currentUserName
            ? ucfirst($this->currentRole) . " " . $this->currentUserName
            : "Unknown user";
        $this->logAction("$user exited the system."); // Save log before exit
        $this->info("Goodbye!");
        $this->line("Audit log saved at: " . storage_path('logs/audit_log.txt'));
        exit;
    }
}
