<?php
    namespace App\Console\Commands;
    use Illuminate\Console\Command;
    use App\Models\Student;
    use App\Models\SchoolClass;
    use App\Services\AdminService;
    use App\Services\StudentService;

    class RunSchoolSystem extends Command
    {
        protected AdminService $adminService;
        protected StudentService $studentService;
        protected $signature = 'run:school-system'; // Command name to run in console
        protected $description = 'Console-based School Management System'; // Short description
        protected $students = []; // Array to hold student objects
        protected $classes = []; // Array to hold class objects

        private string $adminEmail = "admin@gmail.com"; // Admin email for login
        private string $adminPassword = "Pass123"; // Admin password for login

        public function __construct()
        {
            parent::__construct();
            // Load classes, students, and grades from text files when the command starts
            $this->loadClassesFromFile(storage_path('app\data\classes.txt'));
            $this->loadUsersFromFile(storage_path('app\data\students.txt')); 
            $this->loadGradesFromFile(storage_path('app\data\grades.txt'));
        }

        // This function loads students data from a file and creates Student objects
        private function loadUsersFromFile($filePath)
        {
            if (!file_exists($filePath)) {
                $this->error("Students file not found: $filePath");
                return;
            }
            $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                // Split the line by commas to get student info
                [$name, $id, $role, $phone, $age, $className, $email, $password, $status] = explode(',', $line);

                // Only create student objects for those with role "student"
                if (strtolower($role) === 'student') {
                    $schoolClassObj = $this->classes[$className] ?? null;
                    if ($schoolClassObj) {
                        // Create a new Student object and add to students list
                        $student = new Student($name, $id, $phone, (int)$age, $schoolClassObj, $email, $password, $status);
                        $this->students[] = $student;
                    } else {
                        $this->error("Class '$className' not found for student $name");
                    }
                }
            }
        }

        // This function loads school classes data from a file and creates SchoolClass objects
        private function loadClassesFromFile($filePath)
        {
            if (!file_exists($filePath)) {
                $this->error("Classes file not found: $filePath");
                return;
            }
            $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                // Each line contains class name, supervisor, and subjects separated by '-'
                [$className, $supervisor, $subjectsStr] = explode(',', $line);
                $subjects = explode('-', $subjectsStr);

                // Create a new SchoolClass object and add to classes array
                $schoolClass = new SchoolClass($className, $subjects, $supervisor);
                $this->classes[$className] = $schoolClass;
            }
        }

        // This function loads grades from a file and updates students' grades
        private function loadGradesFromFile($filePath)
        {
            if (!file_exists($filePath)) {
                $this->error("Grades file not found: $filePath");
                return;
            }
            $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                // Each line has student's email, subject, and grade
                [$email, $subject, $grade] = explode(',', $line);

                // Find the student by email and add/update the grade for the subject
                $student = collect($this->students)->first(fn($s) => $s->getEmail() === $email);
                if ($student) {
                    $student->addOrUpdateGrade($subject, $grade);
                }
            }
        }

        // This is the main method that runs when the command is executed
        public function handle()
        {
            $this->clearConsole(); // Clear the console screen at start
            
            // Initialize service classes with students and classes data
            $this->adminService = new AdminService($this, $this->students, $this->classes);
            $this->studentService = new StudentService($this, $this->classes);
            $this->info("Welcome to School Management System");
            $role = $this->choice("Please select your role", ['Admin', 'Student']); // Ask user to select role: Admin or Student
            // Loop for login attempts and validation
            while (true) {
                $email = $this->ask("Enter your email");
                if (empty(trim($email))) { // Validate email is not empty and in correct format
                    $this->error("Email field must not be empty.");
                    continue;
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->error("Invalid email format. Correct format: example@gmail.com");
                    continue;
                }
                // Admin email must match admin email defined
                if (strtolower($role) === 'admin' && $email !== $this->adminEmail) {
                    $this->error("Admin must login using: {$this->adminEmail}");
                    continue;
                }
                // For student role, email must exist and not be the admin's email
                if (strtolower($role) === 'student') {
                    if ($email === $this->adminEmail) {
                        $this->error("This email is reserved and cannot be used by students.");
                        continue;
                    }
                    $student = collect($this->students)->first(fn($s) => $s->getEmail() === $email);
                    if (!$student) {
                        $this->error("No student found with this email.");
                        continue;
                    }
                    if (strtolower($student->getStatus()) !== 'active') { //Deactivated accounts cannot log in.
                        $this->error("This account is not activated. Please contact the administration to activate it.");
                        return;
                    }
                }
                $attempts = 0;
                $maxAttempts = 3;  // Password validation, max 3 attempts
                $loginSuccess = false;
                while ($attempts < $maxAttempts) {
                    $password = $this->secret("Enter your password");
                    if (empty(trim($password))) {
                        $this->error("Password field must not be empty.");
                        $attempts++;
                        continue;
                    }
                    if (strtolower($role) === 'admin' && $password !== $this->adminPassword) {
                        $this->error("Incorrect password for admin.");
                        $attempts++;
                        continue;
                    }
                    if (strtolower($role) === 'student' && $student->getPassword() !== $password) {
                        $this->error("Incorrect password for student.");
                        $attempts++;
                        continue;
                    }
                    $loginSuccess = true;
                    break;
                }
                if (!$loginSuccess) {
                    $this->error("Too many failed attempts. Returning to main menu...");
                    return;
                }
                break; // Successful login, exit loop
        }
            $this->clearConsole();
            $this->line("You are logged in as [$role] with email: $email");
            $this->logAction("$role logged in using email: $email");
            // After logging in, the appropriate menu is displayed based on the role.        
            if (strtolower($role) === 'admin') {
                $this->runAsAdmin($email);
            } else {
                $this->runAsStudent($email);
            }
        }

        // This function shows the admin menu and handles admin actions
        private function runAsAdmin($email)
        {
            $this->info("Welcome to Admin Panel");
            while (true) {
                // Show choices for admin actions
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
                    'Exit'
                ]);
                // Call AdminService functions based on choice
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
                    'Exit' => $this->showExitMessage(),
                };
                $this->clearConsole(); // Clear console after each action
            }
        }

        // This function shows the student menu and handles student actions
        private function runAsStudent($email)
        {
            $this->info("Welcome to Student Home");
            $student = collect($this->students)->first(fn($s) => $s->getEmail() === $email);
            if (!$student) {
                $this->error("Student not found with email: $email");
                return;
            }
            while (true) {
                // Show choices for student actions
                $choice = $this->choice("Student Menu", [
                    'View Profile',
                    'Update Contact Info',
                    'View Subjects and grades',
                    'View Assigned Classroom',
                    'Exit'
                ]);
                // Call StudentService functions based on choice
                match ($choice) {
                    'View Profile' => $this->studentService->showProfile($student),
                    'Update Contact Info' => $this->studentService->updateContact($student, $this->students),
                    'View Subjects and grades' => $this->studentService->showSubjectsAndGrades($student),
                    'View Assigned Classroom' => $this->studentService->showClassroom($student),
                    'Exit' => $this->showExitMessage(),
                };
                  $this->clearConsole(); // Clear console after each action

            }
        }

    // This function stores the actions performed by users in a audit_log file along with the time when each action happened
        private function logAction($message)
        {
            $timestamp = now()->toDateTimeString();
            $logLine = "[$timestamp] $message\n";
            file_put_contents(storage_path('logs/audit_log.txt'), $logLine, FILE_APPEND);
        }

        // Clears the console screen using special characters
        private function clearConsole()
        {
            $this->output->write("\033[2J\033[;H");
        }

        // Shows exit message, logs exit action, and stops the program
        private function showExitMessage()
        {
            $this->logAction("User exited the system.");
            $this->info("Goodbye!");
            $this->line("Audit log saved at: " . storage_path('logs/audit_log.txt'));
            exit;
        }
    }
