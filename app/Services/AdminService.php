<?php 
namespace App\Services;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Teacher;
use Illuminate\Console\Command;
class AdminService
{
    protected Command $command;
    protected array $students;
    protected array $classes;
    protected array $teachers;
    protected ?string $currentUserName = null; // Name of the logged-in user
    protected ?string $currentRole = null;     // Role of the logged-in user
    // Paths to save student, class, and grade data
    protected string $usersFilePath;
    protected string $classesFilePath;
    protected string $gradesFilePath;

    // Constructor to initialize the service
    public function __construct($command, array &$students, array &$classes, array &$teachers)
    {
        $this->command = $command;
        $this->students = &$students;
        $this->classes = &$classes;
        $this->teachers = &$teachers;
        
        // Set file paths to store data
        $this->usersFilePath = storage_path('app\data\students.txt');
        $this->classesFilePath = storage_path('app\data\classes.txt');
        $this->gradesFilePath = storage_path('app\data\grades.txt');
        $this->gradesFilePath = storage_path('app\data\teachers.txt');
    }

    // Set the current user and role (like logging in)
    public function setCurrentUser(string $role, string $name)
    {
        $this->currentRole = $role;
        $this->currentUserName = $name;
    }

//------------------save to files--------------------   
    // Save student data to file (add\delete\edit)
    private function saveStudentsToFile(): void
    {
        $lines = [];
        foreach ($this->students as $student) {
            $lines[] = implode(',', [
                $student->getName(),
                $student->getIdNumber(),
                $student->getRole(), // student
                $student->getPhone(),
                $student->getAge(),
                $student->getClass()->getName(),
                $student->getEmail(),
                $student->getPassword(),
                $student->getStatus()
            ]);
        }
        $filePath = storage_path('app\data\students.txt');
        file_put_contents($filePath, implode(PHP_EOL, $lines));
    }

    // Save class data to file (add\delete\edit)
    private function saveClassesToFile(): void
    {
        $lines = [];
        foreach ($this->classes as $class) {
            $line = implode(',', [
                $class->getName(),
                $class->getSupervisor(),
                implode('-', $class->getSubjects())
            ]);
            $lines[] = $line;
        }
        file_put_contents($this->classesFilePath, implode("\n", $lines));
    }

    // Save student grades to file
    private function saveGradesToFile(): void
    {
        $lines = [];
        foreach ($this->students as $student) {
            $grades = $student->getGrades();
            foreach ($grades as $subject => $grade) {
                $lines[] = implode(',', [
                    $student->getEmail(), 
                    $subject,
                    $grade
                ]);
            }
        }
        file_put_contents($this->gradesFilePath, implode("\n", $lines));
    }

    // Save teacher data to file (add\delete\edit)
    private function saveTeachersToFile()
    {
        $filePath = storage_path('app\data\teachers.txt');
        $lines = [];
        foreach ($this->teachers as $teacher) {
            $classNames = array_map(fn($c) => $c->getName(), $teacher->getClasses());
            $line = implode(',', [
                $teacher->getName(),
                $teacher->getEmail(),
                $teacher->getPhone(),
                $teacher->getPassword(),
                $teacher->getSubject()
            ]);
            if (!empty($classNames)) {
                $line .= ',' . implode(',', $classNames);
            }
            $lines[] = $line;
        }
        file_put_contents($filePath, implode(PHP_EOL, $lines));
    }

//-------------------classes operation------------------

    // Shows class details, statistics, and list of students
    public function viewClassesInfo()
    {
        while (true) {
            $classList = array_keys($this->classes);  // Get all class names
            $classList[] = 'Exit';  // Add exit option
            $choice = $this->command->choice("Select a class to view details", $classList); // Ask user to pick
            if ($choice === 'Exit') return;  // Stop if Exit
            $class = $this->classes[$choice];  // Get selected class object
            $class->showInfo($this->command);  // Show class info
            $this->command->info("_________________________\n");
            $this->studentsStatisticsByClass($class->getName()); // Show stats of students
            $this->command->info("_________________________\n");
            $studentsFile = storage_path('app/data/students.txt'); // Path to students file
            if (!file_exists($studentsFile)) {
                $this->command->error("students.txt file not found."); // Error if file missing
            } else {
                $studentsLines = file($studentsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); // Read file
                $studentsInClass = [];
                foreach ($studentsLines as $line) {
                    $parts = explode(',', $line); // Split line by comma
                    $studentClass = $parts[5] ?? null; // Get class name from line
                    if ($studentClass === $class->getName()) {
                        $studentsInClass[] = $parts[0]; // Save student name
                    }
                }
                $this->command->line("Students in this class:"); // Show header
                if (empty($studentsInClass)) {
                    $this->command->line("  No students in this class."); //If No students
                } else {
                    foreach ($studentsInClass as $name) {
                        $this->command->line("  - " . $name); // Print each student
                    }
                }
            }
            $this->logAction("viewed class info and students for {$class->getName()}"); // Log action
        }
    }

   // Create a new class
    public function createClass()
    {
        // 1. Ask for class name
        while (true) {
            $className = trim($this->command->ask("Enter the new class name"));
            if (empty($className)) {
                $this->command->error("Class name must not be empty.");
                continue;
            }
            if (isset($this->classes[$className])) {
                $this->command->error("Class '$className' already exists.");
                continue;
            }
            break;
        }
        // 2. Choose supervisor (from teachers.txt)
        $teacherNames = [];
        foreach ($this->teachers as $teacher) {
            $teacherNames[] = $teacher->getName(); 
        }
        if (empty($teacherNames)) {
            $this->command->warn("No teachers found in teachers.txt, supervisor will be set to None.");
            $selectedSupervisor = null;
        } else {
            $teacherNames[] = 'No supervisor';
            $selectedSupervisor = $this->command->choice("Select class supervisor", $teacherNames);

            if ($selectedSupervisor === 'No supervisor') {
                $selectedSupervisor = null;
            }
        }
        // 3. Enter subjects
        $subjectsInput = trim($this->command->ask("Enter subjects separated by hyphens (e.g., Math-English-Arabic)"));
        $subjects = array_filter(array_map('trim', explode('-', $subjectsInput)));
        // 4. Create and save new class
        $newClass = new \App\Models\SchoolClass($className, $subjects, $selectedSupervisor);
        $this->classes[$className] = $newClass;
        $this->saveClassesToFile();
        // 5. Feedback + logging
        $this->command->info("Class '$className' added successfully with supervisor: " . ($selectedSupervisor ?? 'None'));
        $this->logAction("added new class '$className' with supervisor '" . ($selectedSupervisor ?? 'None') . "'");
    }

    // Delete a class Confirm before deleting, then remove it    
    public function deleteClass()
    {
        if (empty($this->classes)) {
            $this->command->error("No classes available to delete."); // Nothing to delete
            return;
        }
        $classList = array_keys($this->classes); // Get class names
        $classList[] = 'Exit'; // Add exit
        $choice = $this->command->choice("Select a class to delete", $classList); // Ask user
        if ($choice === 'Exit') {
            $this->command->line("Class deletion cancelled."); // Cancel
            return;
        }
        $confirm = $this->command->confirm("Are you sure you want to delete the class '$choice'? This action cannot be undone."); // Confirm
        if (!$confirm) {
            $this->command->line("Class deletion cancelled."); // Cancel if no
            return;
        }
        unset($this->classes[$choice]); // Remove class
        $this->saveClassesToFile(); // Save changes
        $this->command->info("Class '$choice' deleted successfully."); 
        $this->logAction("deleted class '$choice'"); 
    }

    // Edit a class -Change name, supervisor, or subjects-
    public function editClass()
    {
        if (empty($this->classes)) {
            $this->command->error("No classes available to edit."); // No class
            return;
        }
        $classList = array_keys($this->classes); // Get classes
        $classList[] = 'Exit'; // Add exit
        $choice = $this->command->choice("Select a class to edit", $classList); // Ask user
        if ($choice === 'Exit') {
            $this->command->line("Class edit cancelled."); // Cancel
            return;
        }
        $classObj = $this->classes[$choice]; // Selected class object
        $shouldExit = false;
        while (!$shouldExit) {
            $action = $this->command->choice("What would you like to edit?", [
                'Class Name', 'Class Supervisor', 'Manage Subjects', 'Exit'
            ]); // Ask what to edit
            switch ($action) {
                case 'Class Name':
                    $newName = trim($this->command->ask("Enter new class name", $classObj->getName())); // New name
                    if ($newName === '') {
                        $this->command->error("Class name cannot be empty."); // Validate
                    } elseif (isset($this->classes[$newName])) {
                        $this->command->error("Class '$newName' already exists."); // Duplicate check
                    } else {
                        unset($this->classes[$choice]); // Remove old key
                        $classObj->setName($newName); // Update name
                        $this->classes[$newName] = $classObj; // Add with new key
                        $choice = $newName; // Update current choice
                        $this->command->info("Class name updated successfully."); // Success
                        $this->logAction("updated class name to '$newName'"); // Log
                        $this->saveClassesToFile(); // Save
                    }
                    break;
                    case 'Class Supervisor':
                        $currentSupervisor = $classObj->getSupervisor() === 'Unassigned' ? null : $classObj->getSupervisor();
                        $teacherNames = [];
                        foreach ($this->teachers as $teacher) {
                            $teacherNames[] = $teacher->getName();
                        }
                        if (empty($teacherNames)) {
                            $this->command->warn("No teachers found in teachers.txt, supervisor will be set to None.");
                            $selectedSupervisor = null;
                        } else {
                            $teacherNames[] = 'No supervisor';
                            $this->command->info("Current supervisor: " . ($currentSupervisor ?? 'No supervisor'));
                            $selectedSupervisor = $this->command->choice(
                                "Select new class supervisor",
                                $teacherNames,
                                $currentSupervisor ? array_search($currentSupervisor, $teacherNames) : null
                            );
                            if ($selectedSupervisor === 'No supervisor') {
                                $selectedSupervisor = null;
                            }
                        }
                        $classObj->setSupervisor($selectedSupervisor);
                        $this->command->info("Class supervisor updated successfully.");
                        $this->logAction("updated supervisor for class '{$classObj->getName()}' to '" . ($selectedSupervisor ?? 'No supervisor') . "'");
                        $this->saveClassesToFile();
                        break;
                case 'Manage Subjects':
                    $this->manageSubjectsForClass($classObj); // Call subject manager
                    break;
                case 'Exit':
                    $shouldExit = true; // Exit loop
                    $this->command->line("Exiting class edit."); // Message
                    break;
            }
        }
    }

    // Main menu to manage all classes
    public function manageClassroom()
    {
        $shouldExit = false;
        while (!$shouldExit) {
            $choice = $this->command->choice("What do you want to do with classes?", [
                'Create Class', 'Edit Class', 'Delete Class', 'Exit'
            ]); // Main menu
            switch ($choice) {
                case 'Create Class':
                    $this->createClass(); // Create new class
                    break;
                case 'Edit Class':
                    $this->editClass(); // Edit existing
                    break;
                case 'Delete Class':
                    $this->deleteClass(); // Delete
                    break;
                case 'Exit':
                    $shouldExit = true; // Exit
                    $this->command->line("Exiting class management."); // Message
                    break;
            }
        }
    }

    // Add or remove subjects for a class
    private function manageSubjectsForClass(\App\Models\SchoolClass $classObj)
    {
        $className = $classObj->getName(); // Name of class
        $subjects = $classObj->getSubjects(); // Current subjects
        if (empty($subjects)) {
            $this->command->line("No subjects currently assigned to $className."); // None yet
        } else {
            $this->command->line("Current subjects for $className:"); // Show subjects
            foreach ($subjects as $s) {
                $this->command->line("- $s"); // Print each
            }
        }
        $action = $this->command->choice("Do you want to add or remove a subject?", ['Add', 'Remove','Exit']); // Choose action
        $subject = $this->command->ask("Enter subject name"); // Subject name
        try 
        {
            if ($action === 'Add') {
                $classObj->addSubject($subject); // Add
                $this->command->info("Subject '$subject' added to $className."); // Message
                $this->logAction("added subject '$subject' to $className."); // Log
            } 
            else if ($action === 'Remove'){
                $classObj->removeSubject($subject); // Remove
                $this->command->info("Subject '$subject' removed from $className."); // Message
                $this->logAction("removed subject '$subject' from $className."); // Log
            }
            else{
                return; // Exit
            }
            $this->saveClassesToFile(); // Save updated classes
        } 
        catch (\InvalidArgumentException $e) {
            $this->command->error($e->getMessage()); // Show error
        }
    }

    // Show statistics for students in a class
    public function studentsStatisticsByClass(string $className)
    {
        $studentsInClass = array_filter($this->students, fn($s) => $s->getClass()->getName() === $className); // Get students   
        $totalInClass = count($studentsInClass); // Total
        $activeInClass = count(array_filter($studentsInClass, fn($s) => $s->getStatus() === 'active')); // Active
        $inactiveInClass = count(array_filter($studentsInClass, fn($s) => $s->getStatus() === 'deactive')); // Inactive
        $this->command->line("Total students in $className : $totalInClass"); // Show total
        $this->command->line("Active students: $activeInClass"); // Show active
        $this->command->line("Inactive students: $inactiveInClass"); // Show inactive
    }

    //---------------Users operation -----------------

    // This method lets the admin search for a user (student or teacher) by their email
    public function searchUserByEmail()
    {
        // Ask admin to enter email
        while (true) {
            $email = trim($this->command->ask("Enter email to search")); // Get input and trim spaces
            if ($email === '') {
                $this->command->error("Email must not be empty."); // Check empty
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->command->error("Invalid email format."); // Validate email format
                continue;
            }
            break; // Valid email
        }
        if (str_ends_with($email, '@teacher.com')) { // Check if teacher email
            $teacher = $this->teachers[$email] ?? null; // Get teacher by email
            if ($teacher) {
                $this->command->info("Teacher Profile"); // Show header
                $teacher->showProfile($this->command); // Show teacher profile
            } else {
                $this->command->error("No teacher found with this email."); // Not found
            }
        } 
        elseif (str_ends_with($email, '@student.com')) { // Check if student email
            $student = collect($this->students)->first(fn($s) => $s->getEmail() === $email); // Get student
            if ($student) {
                $this->command->info("Student Profile"); // Show header
                $student->showProfile($this->command); // Show student profile
            } else {
                $this->command->error("No student found with this email."); // Not found
            }
        } 
        else {
            $this->command->error("Invalid Email."); // Invalid type
            return;
        }
        $this->logAction("searched for user: $email"); // Save action in log
    }

//----------------student operation ----------------
    // This method shows a menu to manage students: create, edit, delete, or exit
    public function manageStudents()
    {
        $exitMenu = false;
        while (!$exitMenu) {
            // Show menu choices
            $choice = $this->command->choice(
                "Choose an action:",
                [
                    'Create Student',
                    'Edit Student',
                    'Delete Student',
                    'Exit'
                ]
            );
            // Execute action based on admin choice
            switch ($choice) {
                case 'Create Student':
                    $this->createStudent(); // Call method to create student
                    break;
                case 'Edit Student':
                    $this->editStudent(); // Call method to edit student
                    break;
                case 'Delete Student':
                    $this->deleteStudent(); // Call method to delete student
                    break;
                case 'Exit':
                    $exitMenu = true; // Exit the menu loop
                    break;
            }
        }
    }

    // This method creates a new student by asking admin for all student details
    public function createStudent()
    {
        $this->command->info("Creating a New Student...");
        // Ask for full name
        while (true) {
            $name = trim($this->command->ask("Enter full name")); // Get name input
            if ($name === '') {
                $this->command->error("Name must not be empty."); // Validate name
                continue;
            }
            break; // Valid name
        }
        // Ask for ID number
        while (true) {
            $idNumber = trim($this->command->ask("Enter ID Number (exactly 9 digits)")); // Get ID input
            if ($idNumber === '') {
                $this->command->error("idNumber must not be empty."); 
                continue;
            }
            if (!preg_match('/^\d{9}$/', $idNumber)) {
                $this->command->error("ID Number must be exactly 9 digits."); // Validate format
                continue;
            }
            $exists = collect($this->students)->first(fn($s) => $s->getIdNumber() === $idNumber); // Check duplicates
            if ($exists) {
                $this->command->error("ID Number already exists. Please use a different one."); // Duplicate found
                continue;
            }
            break; // Valid ID
        }
        // Ask for phone number
        while (true) {
            $phone = trim($this->command->ask("Enter phone number (exactly 10 digits)"));
            if ($phone === '') {
                $this->command->error("Phone must not be empty."); 
                continue;
            }
            if (!preg_match('/^\d{10}$/', $phone)) {
                $this->command->error("Phone number must be exactly 10 digits."); // Validate phone
                continue;
            }
            break; // Valid phone
        }
        // Ask for age
        while (true) {
            $ageInput = trim($this->command->ask("Enter age (between 5 and 20)")); // Get age input
            if ($ageInput === '') {
                $this->command->error("Age  must not be empty."); 
                continue;
            }
            if (!ctype_digit($ageInput) || (int)$ageInput < 5 || (int)$ageInput > 20) {
                $this->command->error("Age must be a number between 5 and 20."); // Validate age
                continue;
            }
            $age = (int)$ageInput;
            break; // Valid age
        }
        // Select class from available classes
        $className = $this->command->choice("Assign class", array_keys($this->classes));
        $classObj = $this->classes[$className];
        // Ask for email
        while (true) {
            $email = trim($this->command->ask("Enter student's email"));
            if ($email === '') {
                $this->command->error("Email  must not be empty."); 
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->command->error("Invalid email format."); // Validate email format
                continue;
            }
            if (!str_ends_with($email, '@student.com')) {
                $this->command->error("Email must be 'example@student.com'."); // Check email domain
                continue;
            }
            $exists = collect($this->students)->first(fn($s) => $s->getEmail() === $email); // Check duplicates
            if ($exists) {
                $this->command->error("This email already exists. Please use a different one."); // Duplicate
                continue;
            }
            break; // Valid email
        }
        // Ask for password
        while (true) {
            $password = trim($this->command->secret("Enter password (4-8 characters)")); // Get password
            if ($password === '') {
                $this->command->error("Password  must not be empty."); 
                continue;
            }
            if (strlen($password) < 4 || strlen($password) > 8) {
                $this->command->error("Password must be between 4 and 8 characters."); // Validate length
                continue;
            }
            break; // Valid password
        }
        $status = 'active'; // Default status
        $role = 'student'; // Role
        // Create new Student object and save it
        $newStudent = new Student($name, $phone, $idNumber, $age, $email, $password, $classObj, $status, $role);
        $this->students[] = $newStudent;
        $this->saveStudentsToFile(); // Save to file
        $this->command->info("Student created successfully!"); // Show success message
        $this->logAction("created new student: name=$name, email=$email, class=$className"); // Log action
    }
    // This method edits a student's information based on email
    public function editStudent()
    {
        while (true) {
            $email = trim($this->command->ask("Enter student's email to edit")); // Ask for email
            if ($email === '') {
                $this->command->error("Email  must not be empty."); 
                continue;
            }
            if ($this->validateEmailInput($email)) { // Validate email
                break;
            }
        }
        // Find the student object
        $student = collect($this->students)->first(fn($s) => $s->getEmail() === $email);
        if (!$student) {
            $this->command->error("Student not found."); // Email not found
            return;
        }
        $shouldExitEdit = false; // Flag for exiting edit loop
        while (!$shouldExitEdit) {
            // Show options for editing
            $choice = $this->command->choice("What would you like to update?", [
                'Name', 'Email', 'Age', 'Phone', 'Class', 'Password', 'ID Number', 'Status', 'Exit'
            ]);
            // Handle each edit choice
            switch ($choice) {
                case 'Name':
                    $this->command->line("Current name: " . $student->getName());
                    do {
                        $newName = $this->command->ask("Enter new name");
                        if (trim($newName) === '') {
                            $this->command->error("Name must not be empty.");
                        }
                    } while (trim($newName) === '');
                    $student->setName($newName); // Update name
                    $this->command->info("Name updated successfully.");
                    $this->logAction("updated name for {$student->getEmail()}");
                    break;
                case 'Email':
                    $this->command->line("Current email: " . $student->getEmail());
                    while (true) {
                        $newEmail = trim($this->command->ask("Enter new email"));
                        if ($newEmail === '') {
                            $this->command->error("Email  must not be empty."); 
                            continue;
                        }   
                        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                            $this->command->error("Invalid email format."); continue;
                        }
                        if (!str_ends_with($newEmail, '@student.com')) {
                            $this->command->error("Email must be 'example@student.com'."); continue;
                        }
                        $exists = collect($this->students)->first(fn($s) => $s->getEmail() === $newEmail && $s !== $student);
                        if ($exists) { $this->command->error("This email is already in use by another student."); continue; }
                        break;
                    }
                    $oldEmail = $student->getEmail();
                    $student->setEmail($newEmail); // Update email
                    $this->saveGradesToFile(); // Update grades file if email changed
                    $this->command->info("Email updated successfully.");
                    $this->logAction("updated email from $oldEmail to {$student->getEmail()}");
                    break;
                case 'Age':
                    $this->command->line("Current age: " . $student->getAge());
                    do {
                        $newAgeInput = $this->command->ask("Enter new age");
                        if (trim($newAgeInput) === '') { $this->command->error("Age must not be empty."); continue; }
                        if (!ctype_digit($newAgeInput)) { $this->command->error("Age must be a valid number."); continue; }
                        $newAge = (int)$newAgeInput;
                        try { $student->setAge($newAge); break; } catch (\InvalidArgumentException $e) { $this->command->error($e->getMessage()); }
                    } while (true);
                    $this->command->info("Age updated successfully.");
                    $this->logAction("updated age for {$student->getEmail()}");
                    break;
                case 'Phone':
                    $this->command->line("Current phone: " . $student->getPhone());
                    do {
                        $newPhone = $this->command->ask("Enter new phone");
                        if (trim($newPhone) === '') { $this->command->error("Phone must not be empty."); continue; }
                        try { $student->setPhone($newPhone); break; } catch (\InvalidArgumentException $e) { $this->command->error($e->getMessage()); }
                    } while (true);
                    $this->command->info("Phone updated successfully.");
                    $this->logAction("updated phone for {$student->getEmail()}");
                    break;
                case 'Class':
                    $this->command->line("Current class: " . $student->getClass()->getName());
                    $classNames = array_keys($this->classes);
                    $selectedIndex = array_search($student->getClass()->getName(), $classNames);
                    $newClassName = $this->command->choice(
                        "Choose new class",
                        $classNames,
                        $selectedIndex !== false ? $selectedIndex : 0
                    );
                    $newClass = $this->classes[$newClassName];
                    $student->setClass($newClass); // Update class
                    $this->command->info("Class updated successfully.");
                    $this->logAction("updated class for {$student->getEmail()}");
                    break;
                case 'Password':
                    do {
                        $newPassword = $this->command->secret("Enter new password (4-8 characters)");
                        if ($newPassword === '') {
                            $this->command->error("Password  must not be empty."); 
                            continue;
                        }
                        $confirmPassword = $this->command->secret("Confirm new password");
                        if ($confirmPassword === '') {
                            $this->command->error("Confirm Password  must not be empty."); 
                            continue;
                        }
                        if ($newPassword !== $confirmPassword) { $this->command->error("Passwords do not match."); continue; }
                        try { $student->setPassword($newPassword); break; } catch (\InvalidArgumentException $e) { $this->command->error($e->getMessage()); }
                    } while (true);
                    $this->command->info("Password updated successfully.");
                    $this->logAction("updated password for {$student->getEmail()}");
                    break;
                case 'ID Number':
                    $this->command->line("Current ID Number: " . $student->getIdNumber());
                    do {
                        $newId = trim($this->command->ask("Enter new ID Number (9 digits)"));
                        if (empty($newId)) { $this->command->error("ID Number must not be empty."); continue; }
                        if (!preg_match('/^\d{9}$/', $newId)) { $this->command->error("ID Number must be exactly 9 digits."); continue; }
                        $duplicate = collect($this->students)->first(fn($s) => $s->getIdNumber() === $newId && $s !== $student);
                        if ($duplicate) { $this->command->error("This ID Number is already in use."); continue; }
                        $student->setIdNumber($newId); break;
                    } while (true);
                    $this->command->info("ID Number updated successfully.");
                    $this->logAction("updated ID Number for {$student->getEmail()}");
                    break;
                case 'Status':
                    $currentStatus = $student->getStatus();
                    $this->command->info("Current status: " . ucfirst($currentStatus));
                    $confirm = $this->command->confirm(
                        "Do you want to " . ($currentStatus === 'active' ? 'deactivate' : 'activate') . " this student?"
                    );
                    if ($confirm) {
                        $newStatus = ($currentStatus === 'active') ? 'deactive' : 'active';
                        $student->setStatus($newStatus); // Update status
                        $this->command->info("Student status changed to " . ucfirst($newStatus));
                        $this->logAction("changed status for {$student->getEmail()} to $newStatus");
                    } else {
                        $this->command->line("Status change cancelled.");
                    }
                    break;
                case 'Exit':
                    $shouldExitEdit = true; // Exit editing loop
                    $this->command->info("Exiting edit mode.");
                    break;
            }
            // Save changes if not exiting
            if (!$shouldExitEdit) {
                $this->saveStudentsToFile();
            }
        }
    }

    // This method deletes a student after confirming email and action
    public function deleteStudent()
    {
        while (true) {
            $email = trim($this->command->ask("Enter student's email to delete")); // Get email
            if ($email === '') {
                $this->command->error("Email  must not be empty."); 
                continue;
            }
            if ($this->validateEmailInput($email)) { break; } // Validate email
        }
        $index = null; // Store student index
        foreach ($this->students as $i => $student) { // Search for student
            if ($student->getEmail() === $email) {
                $index = $i; break; // Found student
            }
        }
        if ($index === null) {
            $this->command->error("Student not found."); return; // Not found
        }
        // Confirm deletion
        $confirmed = $this->command->confirm("Are you sure you want to delete student: {$this->students[$index]->getName()}?");
        if (!$confirmed) { $this->command->line("Deletion cancelled."); return; }
        $this->logAction("deleted student: {$this->students[$index]->getEmail()}"); // Log action
        unset($this->students[$index]); // Remove student
        $this->students = array_values($this->students); // Reindex array
        $this->saveStudentsToFile(); // Save changes
        $this->command->info("Student deleted successfully."); // Show success
    }
    
    // Magic method to handle dynamic calls like listStudents
    public function __call($name, $arguments)
    {
        if ($name === 'listStudents') {
            // Show choice menu for filtering students
            $status = $this->command->choice(
                "Which students do you want to view?",
                ['All', 'Active', 'Inactive']
            );
            $status = strtolower($status);
            $this->listStudentsUnified($status); // Call private unified listing method
        } else {
            throw new \BadMethodCallException("Method $name does not exist."); // Invalid method
        }
    }

    // Private method to show students based on status
    private function listStudentsUnified(string $status)
    {
        $this->command->info(ucfirst($status) . " Students:");
        $studentsToShow = collect($this->students);
        if ($status === 'active') { $studentsToShow = $studentsToShow->filter(fn($s) => $s->getStatus() === 'active'); }
        elseif ($status === 'inactive') { $studentsToShow = $studentsToShow->filter(fn($s) => $s->getStatus() === 'deactive'); }
        if ($studentsToShow->isEmpty()) {
            $this->command->line("No students found."); return; // No students
        }
        // Show each student with details
        foreach ($studentsToShow as $student) {
            $statusText = strtolower($student->getStatus()) === 'active' ? 'Active' : 'Inactive';
            $this->command->line("- Name: {$student->getName()} | Class: {$student->getClass()->getName()} | Status: $statusText");
        }
        $this->logAction("viewed list of $status students."); // Log action
    }

//-------------- Teacher operation ----------------
    public function createTeacher(): void
    {
        // Ask teacher's name
        while (true) {
            $name = trim($this->command->ask("Enter teacher name"));
            if ($name === '') {
                $this->command->error("Name cannot be empty.");
                continue;
            }
            break;
        }
        // Ask teacher's email
        while (true) {
            $email = trim($this->command->ask("Enter teacher email"));
            if ($email === '') {
                $this->command->error("Email cannot be empty.");
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->command->error("Email is not correct.");
                continue;
            }
            if (!str_ends_with($email, '@teacher.com')) {
                $this->command->error("Email must end with @teacher.com");
                continue;
            }
            if (isset($this->teachers[$email])) {
                $this->command->error("This teacher already exists!");
                continue;
            }
            break;
        }
        // Ask phone number
        while (true) {
            $phone = trim($this->command->ask("Enter phone (10 digits)"));
            if ($phone === '') {
                $this->command->error("Phone cannot be empty.");
                continue;
            }
            if (!preg_match('/^\d{10}$/', $phone)) {
                $this->command->error("Phone must be 10 digits.");
                continue;
            }
            break;
        }
        // Ask password
        while (true) {
            $password = trim($this->command->secret("Enter password (4-8 chars)"));
            if ($password === '') {
                $this->command->error("Password cannot be empty.");
                continue;
            }
            if (strlen($password) < 4 || strlen($password) > 8) {
                $this->command->error("Password must be 4-8 characters.");
                continue;
            }
            break;
        }
        // Ask subject
        while (true) {
            $subject = trim($this->command->ask("Enter subject"));
            if ($subject === '') {
                $this->command->error("Subject cannot be empty.");
                continue;
            }
            break;
        }
        // Choose classes
        $classNames = array_keys($this->classes);
        $selectedClasses = $this->command->choice(
            "Select up to 4 classes",
            $classNames,
            null,
            null,
            true // allow multiple choice
        );
        if (count($selectedClasses) > 4) {
            $this->command->error("A teacher cannot have more than 4 classes.");
            return;
        }
        // Convert names to class objects
        $classObjects = [];
        foreach ($selectedClasses as $className) {
            if (isset($this->classes[$className])) {
                $classObjects[] = $this->classes[$className];
            }
        }
        // Create teacher object
        try {
            $teacher = new Teacher($name, $phone, $email, $password, $subject, $classObjects);
            $this->teachers[$email] = $teacher;
            $this->saveTeachersToFile();
            $this->command->info("Teacher [$name] created!");
        } catch (\Exception $e) {
            $this->command->error("Error: " . $e->getMessage());
        }
    }

    // Delete teacher
    public function deleteTeacher()
    {
        while (true) {
            $email = trim($this->command->ask("Enter teacher email to delete"));
            if ($email === '') {
                $this->command->error("Email cannot be empty.");
                continue;
            }
            if ($this->validateEmailInput($email)) {
                break;
            }
        }
        if (!isset($this->teachers[$email])) {
            $this->command->error("Teacher not found.");
            return;
        }
        $teacherName = $this->teachers[$email]->getName();
        $confirmed = $this->command->confirm("Are you sure you want to delete $teacherName?");
        if (!$confirmed) {
            $this->command->line("Deletion cancelled.");
            return;
        }
        $this->logAction("deleted teacher: $email");
        unset($this->teachers[$email]);
        $this->saveTeachersToFile();
        $this->command->info("Teacher deleted!");
    }

    // Edit teacher
    public function editTeacher()
    {
        while (true) {
            $email = trim($this->command->ask("Enter teacher email to edit"));
            if ($email === '') {
                $this->command->error("Email cannot be empty.");
                continue;
            }
            if ($this->validateEmailInput($email)) {
                break;
            }
        }
        $teacher = collect($this->teachers)->first(fn($t) => $t->getEmail() === $email);
        if (!$teacher) {
            $this->command->error("Teacher not found.");
            return;
        }
        $exit = false;
        while (!$exit) {
            $choice = $this->command->choice("What do you want to change?", [
                'Name', 'Email', 'Phone', 'Password', 'Subject', 'Classes', 'Exit'
            ]);
            switch ($choice) {
                case 'Name':
                    $this->command->line("Old name: " . $teacher->getName());
                    $newName = trim($this->command->ask("Enter new name"));
                    if ($newName !== '') {
                        $teacher->setName($newName);
                        $this->command->info("Name updated!");
                        $this->logAction("name changed for {$teacher->getEmail()}");
                    }
                    if ($newName === '') {
                        $this->command->error("Name cannot be empty.");
                    }
                    break;
                case 'Email':
                    $this->command->line("Old email: " . $teacher->getEmail());
                    while (true) {
                        $newEmail = trim($this->command->ask("Enter new email"));
                        if ($newEmail === '') {
                            $this->command->error("Email cannot be empty.");
                            continue;
                        }
                        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                            $this->command->error("Email is wrong.");
                            continue;
                        }
                        if (!str_ends_with($newEmail, '@teacher.com')) {
                            $this->command->error("Email must end with @teacher.com");
                            continue;
                        }
                        if (isset($this->teachers[$newEmail]) && $newEmail !== $teacher->getEmail()) {
                            $this->command->error("Email already exists!");
                            continue;
                        }
                        break;
                    }
                    $oldEmail = $teacher->getEmail();
                    $teacher->setEmail($newEmail);
                    $this->command->info("Email updated!");
                    $this->logAction("email changed from $oldEmail to {$teacher->getEmail()}");
                    break;
                case 'Phone':
                    $this->command->line("Old phone: " . $teacher->getPhone());
                    $newPhone = trim($this->command->ask("Enter new phone"));
                    if ($newPhone !== '') {
                        $teacher->setPhone($newPhone);
                        $this->command->info("Phone updated!");
                        $this->logAction("phone changed for {$teacher->getEmail()}");
                    }
                    if ($newPhone === '') {
                        $this->command->error("Phone cannot be empty.");
                    }
                    break;
                case 'Password':
                   do {
                        $newPassword = $this->command->secret("Enter new password (4-8 characters)");
                        if ($newPassword === '') {
                            $this->command->error("Password  must not be empty."); 
                            continue;
                        }
                        $confirmPassword = $this->command->secret("Confirm new password");
                        if ($confirmPassword === '') {
                            $this->command->error("Confirm Password  must not be empty."); 
                            continue;
                        }
                        if ($newPassword !== $confirmPassword) { $this->command->error("Passwords do not match."); continue; }
                        try { $teacher->setPassword($newPassword); break; } catch (\InvalidArgumentException $e) { $this->command->error($e->getMessage()); }
                    } while (true);
                    $this->command->info("Password updated successfully.");
                    $this->logAction("updated password for {$teacher->getEmail()}");
                    break;
                case 'Subject':
                    $this->command->line("Old subject: " . $teacher->getSubject());
                    $newSubject = trim($this->command->ask("Enter new subject"));
                    if ($newSubject !== '') {
                        $teacher->setSubject($newSubject);
                        $this->command->info("Subject updated!");
                        $this->logAction("subject changed for {$teacher->getEmail()}");
                    }
                    break;
                case 'Classes':
                    $this->command->info("Current classes:");
                    foreach ($teacher->getClasses() as $c) {
                        $this->command->line("- " . $c->getName());
                    }
                    $classNames = array_keys($this->classes);
                    $selectedClasses = $this->command->choice(
                        "Select up to 4 classes",
                        $classNames,
                        null,
                        null,
                        true // allow multiple selection
                    );
                    if (count($selectedClasses) > 4) {
                        $this->command->error("A teacher cannot have more than 4 classes.");
                        break;
                    }
                    $classObjects = [];
                    foreach ($selectedClasses as $className) {
                        if (isset($this->classes[$className])) {
                            $classObjects[] = $this->classes[$className];
                        }
                    }
                    if (!empty($classObjects)) {
                        $teacher->setClasses($classObjects);
                        $this->command->info("Classes updated!");
                        $this->logAction("Updated classes for {$teacher->getEmail()} to: " . implode(', ', $selectedClasses));
                        $this->saveTeachersToFile();
                    }
                    break;
                case 'Exit':
                    $exit = true;
                    $this->command->info("Exit edit mode.");
                    break;
            }
            if (!$exit) $this->saveTeachersToFile(); // save changes
        }
    }

    // Manage teachers menu
    public function manageTechers()
    {
        $exit = false;
        while (!$exit) {
            $choice = $this->command->choice(
                "Choose action:",
                ['Create Teacher', 'Edit Teacher', 'Delete Teacher', 'Exit']
            );
            switch ($choice) {
                case 'Create Teacher': $this->createTeacher(); break;
                case 'Edit Teacher': $this->editTeacher(); break;
                case 'Delete Teacher': $this->deleteTeacher(); break;
                case 'Exit': $exit = true; break;
            }
        }
    }

    // List teachers
    public function listTeachers()
    {
        if (empty($this->teachers)) {
            $this->command->info("No teachers.");
            return;
        }
        $this->command->info("Teachers list:");
        foreach ($this->teachers as $teacher) {
            $classes = $teacher->getClasses();
            $classNames = array_map(fn($c) => $c->getName(), $classes);
            $classList = !empty($classNames) ? implode(", ", $classNames) : "No classes";
            $this->command->line(
                "Name: " . $teacher->getName() .
                " | Subject: " . $teacher->getSubject() .
                " | Classes: " . $classList
            );
        }
    }

    //----------------Report------------------------
    
    //Shows total students, active and inactive students.
    public function totalStudentsStatistics()
    {
        $totalStudents = count($this->students);
        $activeStudents = count(array_filter($this->students, fn($s) => $s->getStatus() === 'active'));
        $inactiveStudents = count(array_filter($this->students, fn($s) => $s->getStatus() === 'deactive'));
        $this->command->info("Overall Students Statistics:");
        $this->command->line("Total students: $totalStudents");
        $this->command->line("Active students: $activeStudents");
        $this->command->line("Inactive students: $inactiveStudents");
    }    

    //This method generates a full school report:
    public function Report()
    {
        $this->command->info("SCHOOL REPORT\n");
        //Shows total number of teachers and lists them.
        $totalTeachers = count($this->teachers);  
        $this->command->info("Total teachers: $totalTeachers\n");
        $this->listTeachers();
        $this->command->info("\n");
        //Shows total students
        $this->totalStudentsStatistics();
        $this->command->info("\n");
        //For each class, shows class info, statistics, and list of students in that class
        if (empty($this->classes)) {
            $this->command->warn("No classes available.");
        } else {
            foreach ($this->classes as $className => $class) {
                $this->command->info("Class: " . $class->getName());
                $class->showInfo($this->command);
                $this->studentsStatisticsByClass($class->getName());
                $studentsFile = storage_path('app/data/students.txt');
                $studentsInClass = [];
                if (file_exists($studentsFile)) {
                    $studentsLines = file($studentsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($studentsLines as $line) {
                        $parts = explode(',', $line);
                        $studentClass = $parts[5] ?? null;
                        if ($studentClass === $class->getName()) {
                            $studentsInClass[] = $parts[0]; 
                        }
                    }
                }
                $this->command->line("Students in this class:");
                if (empty($studentsInClass)) {
                    $this->command->line("  No students in this class.");
                } else {
                    foreach ($studentsInClass as $name) {
                        $this->command->line(" - " . $name);
                    }
                }
                $this->command->info("------------------------------\n");
            }
        }
        $this->logAction("generated full school report.");//Logs that the report was generated.
    }

//------------------ validation -------------------

    // Validate if email is not empty and has correct format
    private function validateEmailInput(string $email): bool
        {
            if (empty($email)) {
                $this->command->error("Email must not be empty.");
                return false;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->command->error("Invalid Email");
                return false;
            }
            return true;
        }

    // Save any action to the log file
    private function logAction(string $message)
    {
        $user = $this->currentRole && $this->currentUserName
            ? ucfirst($this->currentRole) . " " . $this->currentUserName
            : "Unknown user";
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[$timestamp] $user: $message\n";
        file_put_contents(storage_path('logs/audit_log.txt'), $logLine, FILE_APPEND);
    }
}