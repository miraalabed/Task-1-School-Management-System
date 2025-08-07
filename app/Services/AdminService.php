<?php 
    namespace App\Services;
    use App\Models\Student;
    use App\Models\SchoolClass;
    use Illuminate\Console\Command;

    class AdminService
    {
        protected Command $command;
        protected array $students;
        protected array $classes;

        // Paths to save student, class, and grade data
        protected string $usersFilePath;
        protected string $classesFilePath;
        protected string $gradesFilePath;

        // Constructor to initialize the service
        public function __construct(Command $command, array &$students, array &$classes)
        {
            $this->command = $command;
            $this->students = &$students;
            $this->classes = &$classes;

            // Set file paths to store data
            $this->usersFilePath = storage_path('app\data\students.txt');
            $this->classesFilePath = storage_path('app\data\classes.txt');
            $this->gradesFilePath = storage_path('app\data\grades.txt');
        }

        // Save student data to file
        private function saveStudentsToFile()
        {
            $lines = [];
            foreach ($this->students as $student) {
                // Convert student object to CSV line
                $line = implode(',', [
                    $student->getName(),
                    $student->getIdNumber(),
                    'student',
                    $student->getPhone(),
                    $student->getAge(),
                    $student->getClass()->getName(),
                    $student->getEmail(),
                    $student->getPassword(),
                    $student->getStatus(),
                ]);
                $lines[] = $line;
            }
            file_put_contents($this->usersFilePath, implode("\n", $lines));
        }

        // Save class data to file
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

        // Create a new student with input validation
        public function createStudent()
        {
            $this->command->info("Creating a New Student...");

            // Ask for name
            while (true) {
                $name = trim($this->command->ask("Enter full name"));
                if (empty($name)) {
                    $this->command->error("Name must not be empty.");
                    continue;
                }
                break;
            }

            // Ask for ID number and validate
            while (true) {
                $idNumber = trim($this->command->ask("Enter ID Number (exactly 9 digits)"));
                if (empty($idNumber)) {
                    $this->command->error("ID Number must not be empty.");
                    continue;
                }
                if (!preg_match('/^\d{9}$/', $idNumber)) {
                    $this->command->error("ID Number must be exactly 9 digits.");
                    continue;
                }
                foreach ($this->students as $student) {
                    if ($student->getIdNumber() === $idNumber) {
                        $this->command->error("ID Number already exists. Please use a different one.");
                        continue 2;
                    }
                }
                break;
            }

            // Ask for phone number and validate
            while (true) {
                $phone = trim($this->command->ask("Enter phone number (exactly 10 digits)"));
                if (empty($phone)) {
                    $this->command->error("Phone number must not be empty.");
                    continue;
                }
                if (!preg_match('/^\d{10}$/', $phone)) {
                    $this->command->error("Phone number must be exactly 10 digits.");
                    continue;
                }
                break;
            }

            // Ask for age and validate
            while (true) {
                $ageInput = trim($this->command->ask("Enter age (between 5 and 20)"));
                if (empty($ageInput)) {
                    $this->command->error("Age must not be empty.");
                    continue;
                }
                if (!ctype_digit($ageInput)) {
                    $this->command->error("Age must be a number.");
                    continue;
                }
                $age = (int)$ageInput;
                if ($age < 5 || $age > 20) {
                    $this->command->error("Age must be between 5 and 20.");
                    continue;
                }
                break;
            }

            // Select class
            $className = $this->command->choice("Assign class", array_keys($this->classes));
            $classObj = $this->classes[$className];
            $email = $this->askForValidEmail("Enter email"); // Ask for valid and unique email

            // Ask for password
            while (true) {
                $password = trim($this->command->secret("Enter password (4-8 characters)"));
                if (empty($password)) {
                    $this->command->error("Password must not be empty.");
                    continue;
                }
                if (strlen($password) < 4 || strlen($password) > 8) {
                    $this->command->error("Password must be between 4 and 8 characters.");
                    continue;
                }
                break;
            }
            $status = 'active';

            // Create new student object
            $newStudent = new Student($name, $idNumber, $phone, $age, $classObj, $email, $password, $status);
            $this->students[] = $newStudent;
            $this->saveStudentsToFile();
            $this->command->info("Student created successfully!");
            $this->logAction("Admin created new student: name=$name, email=$email, class=$className");
        }

        // Show student info by email
        public function viewStudentByEmail()
        {
            while (true) {
                $email = trim($this->command->ask("Enter student's email to view"));
                if ($this->validateEmailInput($email)) {
                    break;
                }
            }
            // Search for student
            $student = collect($this->students)->first(fn ($s) => $s->getEmail() === $email);
            if (!$student) {
                $this->command->error("Student not found.");
                return;
            }
            // Display student details
            $this->command->info("Student Profile");
            $this->command->line("Name: " . $student->getName());
            $this->command->line("Email: " . $student->getEmail());
            $this->command->line("Phone: " . $student->getPhone());
            $this->command->line("Age: " . $student->getAge());
            $this->command->line("ID Number: " . $student->getIdNumber());
            $this->command->line("Class: " . $student->getClass()->getName());
            $this->command->line("Status: " . ucfirst($student->getStatus()));
            $this->logAction("Admin viewed profile for student: " . $student->getEmail());
        }
        // Edit student info like name, email, class, etc.
        public function editStudent()
            {
            // Ask for the student's email to start editing
            while (true) {
                $email = trim($this->command->ask("Enter student's email to edit"));
                if ($this->validateEmailInput($email)) {
                    break;
                }
            }
            // Find the student by email
            $student = collect($this->students)->first(fn ($s) => $s->getEmail() === $email);
            if (!$student) {
                $this->command->error("Student not found.");
                return;
            }
            $shouldExitEdit = false;
            while (!$shouldExitEdit) {   // Keep editing until the admin chooses "Exit"

                // Show choices for what can be edited
                $choice = $this->command->choice("What would you like to update?", [
                    'Name', 'Email', 'Age', 'Phone', 'Class', 'Password', 'ID Number', 'Exit'
                ]);

                switch ($choice) {
                    case 'Name':
                        // Show current name and ask for a new one
                        $this->command->line("Current name: " . $student->getName());
                        do {
                            $newName = $this->command->ask("Enter new name");
                            if (trim($newName) === '') {
                                $this->command->error("Name must not be empty.");
                            }
                        } while (trim($newName) === '');
                        $student->setName($newName);
                        $this->command->info("Name updated successfully.");
                        $this->logAction("Admin updated name for {$student->getEmail()}");
                        break;

                    case 'Email':
                        // Update email using helper function
                        $this->command->line("Current email: " . $student->getEmail());
                        $newEmail = $this->askForValidEmail("Enter new email");
                        $student->setEmail($newEmail);
                        $this->command->info("Email updated successfully.");
                        $this->logAction("Admin updated email for {$student->getEmail()}");
                        break;

                    case 'Age':
                        // Ask and validate new age input
                        $this->command->line("Current age: " . $student->getAge());
                        do {
                            $newAgeInput = $this->command->ask("Enter new age");
                            if (trim($newAgeInput) === '') {
                                $this->command->error("Age must not be empty.");
                                continue;
                            }
                            if (!ctype_digit($newAgeInput)) {
                                $this->command->error("Age must be a valid number.");
                                continue;
                            }
                            $newAge = (int)$newAgeInput;
                            try {
                                $student->setAge($newAge);
                                break;
                            } catch (\InvalidArgumentException $e) {
                                $this->command->error($e->getMessage());
                            }
                        } while (true);
                        $this->command->info("Age updated successfully.");
                        $this->logAction("Admin updated age for {$student->getEmail()}");
                        break;

                    case 'Phone':
                        // Ask and validate phone number
                        $this->command->line("Current phone: " . $student->getPhone());
                        do {
                            $newPhone = $this->command->ask("Enter new phone");
                            if (trim($newPhone) === '') {
                                $this->command->error("Phone must not be empty.");
                                continue;
                            }
                            try {
                                $student->setPhone($newPhone);
                                break;
                            } catch (\InvalidArgumentException $e) {
                                $this->command->error($e->getMessage());
                            }
                        } while (true);
                        $this->command->info("Phone updated successfully.");
                        $this->logAction("Admin updated phone for {$student->getEmail()}");
                        break;

                    case 'Class':
                        // Change the student's class
                        $this->command->line("Current class: " . $student->getClass()->getName());
                        $classNames = array_keys($this->classes);
                        $selectedIndex = array_search($student->getClass()->getName(), $classNames);
                        $newClassName = $this->command->choice("Choose new class", $classNames, $selectedIndex !== false ? $selectedIndex : 0);
                        $newClass = $this->classes[$newClassName];
                        $student->setClass($newClass);
                        $this->command->info("Class updated successfully.");
                        $this->logAction("Admin updated class for {$student->getEmail()}");
                        break;

                    case 'Password':
                        // Ask for new password and confirm it
                        do {
                            $newPassword = $this->command->secret("Enter new password (4-8 characters)");
                            $confirmPassword = $this->command->secret("Confirm new password");
                            if (trim($newPassword) === '' || trim($confirmPassword) === '') {
                                $this->command->error("Password must not be empty.");
                                continue;
                            }
                            if ($newPassword !== $confirmPassword) {
                                $this->command->error("Passwords do not match.");
                                continue;
                            }
                            try {
                                $student->setPassword($newPassword);
                                break;
                            } catch (\InvalidArgumentException $e) {
                                $this->command->error($e->getMessage());
                            }
                        } while (true);
                        $this->command->info("Password updated successfully.");
                        $this->logAction("Admin updated password for {$student->getEmail()}");
                        break;

                    case 'ID Number':
                        // Update ID number and ensure uniqueness
                        $this->command->line("Current ID Number: " . $student->getIdNumber());
                        do {
                            $newId = trim($this->command->ask("Enter new ID Number (9 digits)"));
                            if (empty($newId)) {
                                $this->command->error("ID Number must not be empty.");
                                continue;
                            }
                            if (!preg_match('/^\d{9}$/', $newId)) {
                                $this->command->error("ID Number must be exactly 9 digits.");
                                continue;
                            }
                            $duplicate = collect($this->students)->first(fn ($s) => $s->getIdNumber() === $newId && $s !== $student);
                            if ($duplicate) {
                                $this->command->error("This ID Number is already in use.");
                                continue;
                            }
                            $student->setIdNumber($newId);
                            break;
                        } while (true);
                        $this->command->info("ID Number updated successfully.");
                        $this->logAction("Admin updated ID Number for {$student->getEmail()}");
                        break;
                    
                    case 'Exit':
                        // Exit editing loop
                        $shouldExitEdit = true;
                        $this->command->info("Exiting edit mode.");
                        break;
                }
                // Save changes if not exiting
                if (!$shouldExitEdit) {
                    $this->saveStudentsToFile();
                }
            }
        }

        // Delete student after confirmation
        public function deleteStudent()
        {
            while (true) {
                $email = trim($this->command->ask("Enter student's email to delete"));
                // Check if the email is valid
                if ($this->validateEmailInput($email)) {
                    break; // Exit the loop if the email is valid
                }
            }
            $index = null; // This will store the index of the student

            // Search for the student by email
            foreach ($this->students as $i => $student) {
                if ($student->getEmail() === $email) {
                    $index = $i; // Save the index if the student is found
                    break;
                }
            }
            // If no student found with that email
            if ($index === null) {
                $this->command->error("Student not found.");
                return;
            }
            // Ask the user if they are sure about deleting the student
            $confirmed = $this->command->confirm("Are you sure you want to delete student: {$this->students[$index]->getName()}?");
            if (!$confirmed) {
                $this->command->line("Deletion cancelled."); // Show message if cancelled
                return;
            }

            $this->logAction("Admin deleted student: {$this->students[$index]->getEmail()}");    // Log the action for records
            unset($this->students[$index]);    // Delete the student from the list
            $this->students = array_values($this->students);    // Reorder the array indexes after deletion
            $this->saveStudentsToFile();    // Save the updated students list to the file
            $this->command->info("Student deleted successfully.");    // Show success message
        }

        public function toggleStudentStatus()
        {
            while (true) {
                // Ask the admin to enter the student's email
                $email = trim($this->command->ask("Enter student's email to activate/deactivate"));

                // Check if the email is valid
                if ($this->validateEmailInput($email)) {
                    break; // Exit the loop if email is okay
                }
            }
            // Go through each student in the list
            foreach ($this->students as $student) {
                // Check if this student's email matches the one entered
                if ($student->getEmail() === $email) {
                    // Get the current status of the student (active or deactive)
                    $currentStatus = $student->getStatus();

                    $this->command->info("Current status: " . ucfirst($currentStatus));  // Show the current status to the admin

                    // Ask the admin if they really want to change the status
                    $confirm = $this->command->confirm("Do you want to " . ($currentStatus === 'active' ? 'deactivate' : 'activate') . " this student?");

                    // If admin says no, cancel the process
                    if (!$confirm) {
                        $this->command->line("Operation cancelled.");
                        return;
                    }

                    // Change the status: if it was active, make it deactive, and vice versa
                    $newStatus = ($currentStatus === 'active') ? 'deactive' : 'active';
                    $student->setStatus($newStatus); // Save the new status
                    $this->saveStudentsToFile();  // Save the updated student list to the file
                    $this->command->info("Student status changed to " . ucfirst($newStatus));    // Show message that the status was changed
                    $this->logAction("Admin changed status for {$email} to $newStatus");   // Save this action to the log file
                    return;
                }
            }
            $this->command->error("Student not found.");    // If student was not found in the list, show error
        }

        // Choose to manage subjects or grades
        public function manageSubjectsAndGrades()
        {
            // Ask the admin what they want to manage: Subjects, Grades, or Exit
            $choice = $this->command->choice("What do you want to manage?", ['Subjects', 'Grades', 'Exit']);
            // Call the correct function based on the choice
            if ($choice === 'Subjects') {
                $this->manageSubjects(); // Manage subjects if chosen
            } elseif ($choice === 'Grades') {
                $this->manageGrades(); // Manage grades if chosen
            }
        }

        // Add or remove subjects from a class
        private function manageSubjects()
        {
            // Ask the admin to select a class from the list
            $className = $this->command->choice("Select class", array_keys($this->classes));
            $classObj = $this->classes[$className];
            $subjects = $classObj->getSubjects();  // Get all subjects of the selected clas

            // Show current subjects or say no subjects assigned
            if (empty($subjects)) {
                $this->command->line("No subjects currently assigned to $className.");
            } else {
                $this->command->line("Current subjects for $className:");
                foreach ($subjects as $s) {
                    $this->command->line("- $s");
                }
            }

            // Ask if admin wants to add or remove a subject
            $action = $this->command->choice("Do you want to add or remove a subject?", ['Add', 'Remove']);
            $subject = $this->command->ask("Enter subject name");

            try {
                // If add, call addSubject method and log the action
                if ($action === 'Add') {
                    $classObj->addSubject($subject);
                    $this->command->info("Subject '$subject' added to $className.");
                    $this->logAction("Admin added subject '$subject' to $className.");
                } else {
                    // If remove, call removeSubject method and log the action
                    $classObj->removeSubject($subject);
                    $this->command->info("Subject '$subject' removed from $className.");
                    $this->logAction("Admin removed subject '$subject' from $className.");
                }
                $this->saveClassesToFile();    // Save the updated classes list to file
            } catch (\InvalidArgumentException $e) {
                // Show error message if there is an exception
                $this->command->error($e->getMessage());
            }
        }

        // Add, update or remove a student's grade
        private function manageGrades()
        {
            // Loop to get a valid student email
            while (true) {
                $email = trim($this->command->ask("Enter student's email"));
                if ($this->validateEmailInput($email)) {
                    break; // Exit loop if email is valid
                }
            }

            // Find the student by email
            $student = collect($this->students)->first(fn ($s) => $s->getEmail() === $email);

            // Show error if student not found
            if (!$student) {
                $this->command->error("Student not found.");
                return;
            }

            // Get the class of the student and its subjects
            $classObj = $student->getClass();
            $subjects = $classObj->getSubjects();

            // If no subjects assigned, inform the admin and return
            if (empty($subjects)) {
                $this->command->line("No subjects assigned to {$classObj->getName()}.");
                return;
            }

            // Show current grades if any
            if (!empty($student->getGrades())) {
                $this->command->line("Current Grades for {$student->getName()}:");
                foreach ($student->getGrades() as $subj => $grade) {
                    $this->command->line("- $subj: $grade");
                }
            } else {
                $this->command->line("No grades assigned yet.");
            }

            $selectedSubject = $this->command->choice("Choose subject to manage grade", $subjects);  // Let admin choose subject to manage grade

            // Ask if admin wants to add/update or remove a grade
            $gradeAction = $this->command->choice("Add/Update or Remove grade?", ['Add/Update', 'Remove']);
            if ($gradeAction === 'Add/Update') {
                $grade = $this->command->ask("Enter grade for $selectedSubject");  // Ask for the grade value
                if (!is_numeric($grade) || $grade < 0 || $grade > 100) {  // Check if grade is a number between 0 and 100
                    $this->command->error("Grade must be a number between 0 and 100.");
                    return;
                }

                // Add or update the grade for the student
                $student->addOrUpdateGrade($selectedSubject, $grade);
                $this->command->info("Grade saved.");
                $this->logAction("Admin set grade for {$student->getEmail()}: $selectedSubject → $grade");
                $this->saveGradesToFile();     // Save grades to file
            } else {
                // If remove, check if grade exists
                $grades = $student->getGrades();
                if (!isset($grades[$selectedSubject])) {
                    $this->command->error("No grade found for $selectedSubject to remove.");
                    return;
                }
                // Remove the grade for the subject
                $student->removeGrade($selectedSubject);
                $this->command->info("Grade removed.");
                $this->logAction("Admin removed grade for {$student->getEmail()} in $selectedSubject");
                $this->saveGradesToFile();   // Save grades to file
            }
        }

        // List only students with active status
        public function listActiveStudents()
        {
            $this->command->info("Active Students:");
            // Get only students with status 'active'
            $activeStudents = collect($this->students)->filter(fn ($s) => $s->getStatus() === 'active');

            // If no active students found, show message and return
            if ($activeStudents->isEmpty()) {
                $this->command->line("No active students found.");
                return;
            }
            // Display each active student's name and class
            foreach ($activeStudents as $student) {
                $this->command->line("- Name: {$student->getName()} | Class: {$student->getClass()->getName()}");
            }
            $this->logAction("Admin viewed list of active students.");  // Log this action for tracking
        }

        // List all students regardless of status
        public function listAllStudents()
        {
            $this->command->info("All Students:");

            // If no students in list, show message and return
            if (empty($this->students)) {
                $this->command->line("No students found.");
                return;
            }
            // Loop through all students and show their name, class, and status
            foreach ($this->students as $student) {
                $status = strtolower($student->getStatus()) === 'active' ? 'Active' : 'Inactive';
                $this->command->line("- Name: {$student->getName()} | Class: {$student->getClass()->getName()} | Status: $status");
            }
            $this->logAction("Admin viewed list of all students.");   // Log this action for tracking
        }

        // View class info like supervisor and subjects
        public function viewClassesInfo()
        {
            while (true) 
                $classList = array_keys($this->classes);   // Get all class names as a list
                $classList[] = 'Exit';  // Add an option to exit the loop
                // Ask the user to select a class or exit
                $choice = $this->command->choice("Select a class to view details", $classList);
                if ($choice === 'Exit') return;  // If user chooses 'Exit', stop the function
                $class = $this->classes[$choice];   // Get the selected class object

                // Show the class ifo
                $this->command->info("Class: {$class->getName()}");
                $this->command->line("Supervisor: " . ($class->getSupervisor() ?? 'Unassigned'));   //say 'Unassigned' if none
                $subjects = $class->getSubjects();
                if (!empty($subjects)) {
                    $this->command->line("Subjects:");
                    foreach ($subjects as $subject) {
                        $this->command->line("- $subject");
                    }
                } else {
                    $this->command->line("No subjects assigned.");
                }
                $this->logAction("Admin viewed class info for {$class->getName()}");  // Log that admin viewed this class info
            }
        
        // Validate if email is not empty and has correct format
        private function validateEmailInput(string $email): bool
            {
                if (empty($email)) {
                    $this->command->error("Email must not be empty.");
                    return false;
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->command->error("Email must be in format example@gmail.com");
                    return false;
                }
                return true;
            }

        // Ask admin to enter a valid and unused email
        private function askForValidEmail(string $prompt): string
            {
                while (true) {
                    $email = trim($this->command->ask($prompt));

                    if (!$this->validateEmailInput($email)) {
                        continue;
                    }

                    foreach ($this->students as $student) {
                        if ($student->getEmail() === $email) {
                            $this->command->error("Email already exists. Please use a different one.");
                            continue 2; 
                        }
                    }

                    return $email; 
                }
            }

        // Save any action to the log file
        private function logAction(string $message)
        {
            $timestamp = date('Y-m-d H:i:s');
            $logLine = "[$timestamp] $message\n";
            file_put_contents(storage_path('logs/audit_log.txt'), $logLine, FILE_APPEND);
        }
     }