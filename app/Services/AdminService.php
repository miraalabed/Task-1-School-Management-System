<?php

namespace App\Services;

use App\Models\Student;
use App\Models\SchoolClass;
use Illuminate\Console\Command;

class AdminService
{
    // This service class helps the Admin manage students and classes

    protected Command $command;
    protected array $students;
    protected array $classes;

    // Constructor receives console instance and data arrays
    public function __construct(Command $command, array &$students, array &$classes)
    {
        $this->command = $command;
        $this->students = &$students;
        $this->classes = &$classes;
    }

    // Create a new student after asking for input and validating email and phone
    public function createStudent()
    {
        $this->command->info("Creating a New Student...");
        $name = $this->command->ask("Enter full name");

        // Email and phone inputs are validated with helper functions
        do {
            $email = $this->command->ask("Enter email");
        } while (!$this->validateEmail($email));

        $age = $this->command->ask("Enter age");
        $class = $this->command->choice("Assign class", array_keys($this->classes));

        do {
            $phone = $this->command->ask("Enter phone number");
        } while (!$this->validatePhone($phone));

        $newStudent = new Student($name, $email, $age, $class, true, $phone);
        $this->students[] = $newStudent;

        $this->command->info("Student created successfully!");
        $this->logAction("Admin created new student: name=$name, email=$email, class=$class");
    }

    // Show full info of a student by searching with email
    public function viewStudentByEmail()
    {
        $email = $this->command->ask("Enter student's email to view");
        $student = collect($this->students)->firstWhere('email', $email);

        if (!$student) {
            $this->command->error("Student not found.");
            return;
        }

        $this->command->info("Student Profile");
        $this->command->line("Name: {$student->name}");
        $this->command->line("Email: {$student->email}");
        $this->command->line("Phone: " . ($student->phone ?? 'N/A'));
        $this->command->line("Age: {$student->age}");
        $this->command->line("Class: {$student->class}");
        $this->command->line("Status: " . ($student->active ? 'Active' : 'Inactive'));

        $this->logAction("Admin viewed profile for student: {$student->email}");
    }

    // Edit student information (name, email, age, phone, class)
    public function editStudent()
    {
        $email = $this->command->ask("Enter student's email to edit");

        foreach ($this->students as $student) {
            if ($student->email === $email) {
                $shouldExitEdit = false;

                // Loop until admin chooses to exit
                while (!$shouldExitEdit) {
                    $choice = $this->command->choice("What would you like to update?", [
                        'Name', 'Email', 'Age', 'Phone', 'Class', 'Exit'
                    ]);

                    // Perform the update depending on the chosen field
                    $action = match ($choice) {
                        'Name' => function () use ($student) {
                            $old = $student->name;
                            $student->name = $this->command->ask("Enter new name", $student->name);
                            $this->logAction("Admin updated name: $old → {$student->name}");
                            $this->command->info("Name updated.");
                        },
                        'Email' => function () use ($student) {
                            $old = $student->email;
                            do {
                                $newEmail = $this->command->ask("Enter new email", $student->email);
                            } while (!$this->validateEmail($newEmail, $student));
                            $student->email = $newEmail;
                            $this->logAction("Admin updated email: $old → {$student->email}");
                            $this->command->info("Email updated.");
                        },
                        'Age' => function () use ($student) {
                            $old = $student->age;
                            $student->age = $this->command->ask("Enter new age", $student->age);
                            $this->logAction("Admin updated age: $old → {$student->age}");
                            $this->command->info("Age updated.");
                        },
                        'Phone' => function () use ($student) {
                            $old = $student->phone;
                            do {
                                $newPhone = $this->command->ask("Enter new phone", $student->phone);
                            } while (!$this->validatePhone($newPhone));
                            $student->phone = $newPhone;
                            $this->logAction("Admin updated phone: $old → {$student->phone}");
                            $this->command->info("Phone updated.");
                        },
                        'Class' => function () use ($student) {
                            $old = $student->class;
                            $student->class = $this->command->choice("Choose new class", array_keys($this->classes), array_search($student->class, array_keys($this->classes)));
                            $this->logAction("Admin updated class: $old → {$student->class}");
                            $this->command->info("Class updated.");
                        },
                        'Exit' => function () use (&$shouldExitEdit) {
                            $this->command->info("Update session ended.");
                            $shouldExitEdit = true;
                        },
                    };

                    $action();
                }
                return;
            }
        }

        $this->command->error("Student not found.");
    }

    // Delete a student after confirmation
    public function deleteStudent()
    {
        $email = $this->command->ask("Enter student's email to delete");
        $index = null;

        foreach ($this->students as $i => $student) {
            if ($student->email === $email) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            $this->command->error("Student not found.");
            return;
        }

        $confirmed = $this->command->confirm("Are you sure you want to delete student: {$this->students[$index]->name}?");
        if (!$confirmed) {
            $this->command->line("Deletion cancelled.");
            return;
        }

        $this->logAction("Admin deleted student: {$this->students[$index]->email}");
        unset($this->students[$index]);
        $this->command->info("Student deleted successfully.");
    }

    // Toggle student account active/inactive
    public function toggleStudentStatus()
    {
        $email = $this->command->ask("Enter student's email to activate/deactivate");

        foreach ($this->students as $student) {
            if ($student->email === $email) {
                $status = $student->active ? 'Active' : 'Inactive';
                $this->command->info("Current status: $status");

                $confirm = $this->command->confirm("Do you want to " . ($student->active ? 'deactivate' : 'activate') . " this student?");
                if (!$confirm) {
                    $this->command->line("Operation cancelled.");
                    return;
                }

                $student->active = !$student->active;
                $newStatus = $student->active ? 'Active' : 'Inactive';

                $this->command->info("Student status changed to: $newStatus");
                $this->logAction("Admin changed status of {$student->email} → $newStatus");
                return;
            }
        }

        $this->command->error("Student not found.");
    }

    // List all currently active students
    public function listActiveStudents()
    {
        $this->command->info("Active Students:");
        $activeStudents = collect($this->students)->filter(fn($s) => $s->active);

        if ($activeStudents->isEmpty()) {
            $this->command->line("No active students found.");
            return;
        }

        foreach ($activeStudents as $student) {
            $this->command->line("- {$student->name} | {$student->email} | {$student->class}");
        }

        $this->logAction("Admin viewed list of active students.");
    }

    // List all students regardless of status
    public function listAllStudents()
    {
        $this->command->info("All Students:");

        if (empty($this->students)) {
            $this->command->line("No students found.");
            return;
        }

        foreach ($this->students as $student) {
            $status = $student->active ? 'Active' : 'Inactive';
            $this->command->line("- {$student->name} | {$student->email} | {$student->class} | Status: $status");
        }

        $this->logAction("Admin viewed list of all students.");
    }

    // Handle adding/removing subjects and managing grades
    public function manageSubjectsAndGrades()
    {
        $choice = $this->command->choice("What do you want to manage?", ['Subjects', 'Grades', 'Exit']);

        if ($choice === 'Subjects') {
            // Admin can add/remove subjects for a class
            $class = $this->command->choice("Select class", array_keys($this->classes));
            $classObj = $this->classes[$class];

            $subjects = $classObj->getSubjects();
            if (empty($subjects)) {
                $this->command->line("No subjects currently assigned to $class.");
            } else {
                $this->command->line("Current subjects for $class:");
                foreach ($subjects as $s) {
                    $this->command->line("- $s");
                }
            }

            $action = $this->command->choice("Do you want to add or remove a subject?", ['Add', 'Remove']);
            $subject = $this->command->ask("Enter subject name");

            if ($action === 'Add') {
                $classObj->addSubject($subject);
                $this->command->info("Subject '$subject' added to $class.");
                $this->logAction("Admin added subject '$subject' to $class.");
            } else {
                $classObj->removeSubject($subject);
                $this->command->info("Subject '$subject' removed from $class.");
                $this->logAction("Admin removed subject '$subject' from $class.");
            }

            return;
        }

        if ($choice === 'Grades') {
            // Admin can set or remove grades for a student
            $email = $this->command->ask("Enter student's email");
            $student = collect($this->students)->firstWhere('email', $email);

            if (!$student) {
                $this->command->error("Student not found.");
                return;
            }

            $classObj = $this->classes[$student->class] ?? null;
            if (!$classObj) {
                $this->command->error("Class not found.");
                return;
            }

            $subjects = $classObj->getSubjects();
            if (empty($subjects)) {
                $this->command->line("No subjects assigned to {$student->class}.");
                return;
            }

            if (!empty($student->grades)) {
                $this->command->line("Current Grades for {$student->name}:");
                foreach ($student->grades as $subj => $grade) {
                    $this->command->line("- $subj: $grade");
                }
            } else {
                $this->command->line("No grades assigned yet.");
            }

            $selectedSubject = $this->command->choice("Choose subject to manage grade", $subjects);
            $gradeAction = $this->command->choice("Add/Update or Remove grade?", ['Add/Update', 'Remove']);

            if ($gradeAction === 'Add/Update') {
                $grade = $this->command->ask("Enter grade for $selectedSubject");
                $student->addOrUpdateGrade($selectedSubject, $grade);
                $this->command->info("Grade saved.");
                $this->logAction("Admin set grade for {$student->email}: $selectedSubject → $grade");
            } else {
                $student->removeGrade($selectedSubject);
                $this->command->info("Grade removed.");
                $this->logAction("Admin removed grade for {$student->email} in $selectedSubject");
            }
        }
    }

    // Show list of classes and their subject/supervisor info
    public function viewClassesInfo()
    {
        while (true) {
            $classList = array_keys($this->classes);
            $classList[] = 'Exit';

            $choice = $this->command->choice("Select a class to view details", $classList);
            if ($choice === 'Exit') return;

            $class = $this->classes[$choice];

            $this->command->info("Class: {$class->name}");
            $this->command->line("Supervisor: " . ($class->getSupervisor() ?? 'Unassigned'));

            $subjects = $class->getSubjects();
            if (!empty($subjects)) {
                $this->command->line("Subjects:");
                foreach ($subjects as $subject) {
                    $this->command->line("- $subject");
                }
            } else {
                $this->command->line("No subjects assigned.");
            }

            $this->logAction("Admin viewed class info for {$class->name}");
        }
    }

    // Validate email for student creation/editing
    private function validateEmail(string $email, ?Student $currentStudent = null): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->command->error("Invalid email format.");
            return false;
        }

        if ($email === 'admin@gmail.com') {
            $this->command->error("This email is reserved and cannot be used.");
            return false;
        }

        $exists = collect($this->students)->first(fn($s) =>
            $s->email === $email && $s !== $currentStudent
        );

        if ($exists) {
            $this->command->error("This email is already used by another student.");
            return false;
        }

        return true;
    }

    // Validate phone format
    private function validatePhone(string $phone): bool
    {
        if (!preg_match('/^\d{10}$/', $phone)) {
            $this->command->error("Phone number must be exactly 10 digits.");
            return false;
        }

        return true;
    }

    // Log admin actions to file
    private function logAction(string $message)
    {
        $timestamp = now()->toDateTimeString();
        $logLine = "[$timestamp] $message\n";
        file_put_contents(storage_path('logs/audit_log.txt'), $logLine, FILE_APPEND);
    }
}
