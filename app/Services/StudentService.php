<?php
    namespace App\Services;
    use App\Models\Student;
    use Illuminate\Console\Command;

    class StudentService
    {
        // This class helps a student to view and update their profile, grades, and classroom

        protected Command $command;
        protected string $studentsFile;
        protected string $gradesFile;
        protected string $classesFile;

        // Constructor is called when the object is created
        public function __construct(Command $command)
        {
            $this->command = $command;
            // These are the file paths where student, grade, and class data is saved
            $this->studentsFile = storage_path('app\data\students.txt');
            $this->gradesFile = storage_path('app\data\grades.txt');
            $this->classesFile = storage_path('app\data\classes.txt');
        }

        // Save all students' info into the students file
        private function saveStudentsToFile(array $students)
        {
            $lines = [];
            foreach ($students as $student) {
                // Save all student details in a line
                $lines[] = implode(',', [
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
            }
            file_put_contents($this->studentsFile, implode("\n", $lines));
        }

        // Save all grades of students into the grades file
        private function saveGradesToFile(array $students)
        {
            $lines = [];
            foreach ($students as $student) {
                foreach ($student->getGrades() as $subject => $grade) {
                    $lines[] = implode(',', [
                        $student->getEmail(),
                        $subject,
                        $grade
                    ]);
                }
            }
            file_put_contents($this->gradesFile, implode("\n", $lines));
        }
        
        // Update the student's contact information (Email, Phone, or Password)
        public function updateContact(Student $student, array $students)
        {
            $choice = $this->command->choice(
                "What would you like to update?",
                ['Email', 'Phone', 'Password']
            );

            switch ($choice) {
                case 'Email':
                    $oldEmail = $student->getEmail(); // Save old email
                    $this->command->line("Current email is: {$oldEmail}");

                    while (true) {
                        $newEmail = trim($this->command->ask("Enter your new email"));

                        // Check if it's empty
                        if (empty($newEmail)) {
                            $this->command->error("Email cannot be empty.");
                            continue;
                        }

                        // Check if email is valid format
                        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                            $this->command->error("Invalid email format.");
                            continue;
                        }

                        // Make sure no other student has this email
                        $emailExists = collect($students)->contains(function ($s) use ($newEmail, $student) {
                            return $s->getEmail() === $newEmail && $s !== $student;
                        });

                        if ($emailExists) {
                            $this->command->error("Email already exists. Please use a different one.");
                            continue;
                        }
                        try {
                            // Update the email
                            $student->setEmail($newEmail);

                            // If old grades exist, move them to the new email
                            if (isset($this->grades[$oldEmail])) {
                                $this->grades[$newEmail] = $this->grades[$oldEmail];
                                unset($this->grades[$oldEmail]);
                                $this->saveGradesToFile($students); // Save new grades file
                            }
                            $this->command->info("Email updated successfully!");
                            $this->logAction("Student updated email for {$student->getName()}");
                            $this->saveStudentsToFile($students);
                            break;
                        } catch (\InvalidArgumentException $e) {
                            $this->command->error($e->getMessage());
                        }
                    }
                    break;

                case 'Phone':
                    $this->command->line("Current phone is: {$student->getPhone()}");
                    while (true) {
                        $newPhone = $this->command->ask("Enter your new phone number");
                        if (empty(trim($newPhone))) {
                            $this->command->error("Phone number cannot be empty.");
                            continue;
                        }
                        try {
                            $student->setPhone($newPhone);
                            $this->command->info("Phone updated successfully!");
                            $this->logAction("Student updated phone for {$student->getName()}");
                            $this->saveStudentsToFile($students);
                            break;
                        } catch (\InvalidArgumentException $e) {
                            $this->command->error($e->getMessage());
                        }
                    }
                    break;

                case 'Password':
                    // Ask for current password
                    while (true) {
                        $oldPassword = $this->command->secret("Enter your current password");
                        if ($oldPassword !== $student->getPassword()) {
                            $this->command->error("Current password is incorrect.");
                            continue;
                        }
                        break;
                    }

                    // Set new password
                    while (true) {
                        $newPassword = $this->command->secret("Enter new password");
                        try {
                            $student->setPassword($newPassword);
                        } catch (\InvalidArgumentException $e) {
                            $this->command->error($e->getMessage());
                            continue;
                        }
                        $confirmPassword = $this->command->secret("Confirm new password");
                        if ($newPassword !== $confirmPassword) {
                            $this->command->error("Passwords do not match.");
                            continue;
                        }
                        $this->command->info("Password updated successfully!");
                        $this->logAction("Student updated password for {$student->getName()}");
                        $this->saveStudentsToFile($students);
                        break;
                    }
                    break;
            }
        }

        // Show all subjects and grades of the student
        public function showSubjectsAndGrades(Student $student)
        {
            $classObj = $student->getClass();
            // If student doesn't have a class
            if (!$classObj) {
                $this->command->line("Student is not assigned to a valid class.");
                return;
            }
            $subjects = $classObj->getSubjects();

            // If no subjects found
            if (empty($subjects)) {
                $this->command->line("No subjects assigned to this class.");
                return;
            }
            $this->command->info("Subjects and Grades:");
            $grades = $student->getGrades();

            // Show each subject and its grade (or say "No grade")
            foreach ($subjects as $subject) {
                $grade = $grades[$subject] ?? '(No grade)';
                $this->command->line("- $subject: $grade");
            }
            $this->logAction("Student viewed subjects and grades for class: {$classObj->getName()}");
        }

        // Save the action to a log file with date and time
        private function logAction(string $message)
        {
            $timestamp = now()->toDateTimeString();
            $logLine = "[$timestamp] $message\n";
            file_put_contents(storage_path('logs/audit_log.txt'), $logLine, FILE_APPEND);
        }
    }