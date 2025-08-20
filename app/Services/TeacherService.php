<?php
namespace App\Services;
use App\Models\Teacher;
use Illuminate\Console\Command;
class TeacherService
{
    protected Command $command;         
    protected string $teachersFile;      // Path to save teacher data
    protected ?string $currentUserName = null; 
    protected ?string $currentRole = null;

    public function __construct(Command $command)
    {
        $this->command = $command;
        $this->teachersFile = storage_path('app\data\teachers.txt');
        $this->gradesFile = storage_path('app\data\grades.txt'); // file for student grades
    }

    // Set the current logged-in user (role and name)
    public function setCurrentUser(string $role, string $name)
    {
        $this->currentRole = $role;
        $this->currentUserName = $name;
    }

    // Allow teacher to update phone or password
    public function updateContact(Teacher $teacher, array $teachers): void
    {
        $choice = $this->command->choice(
            "What would you like to update?",
            ['Phone', 'Password', 'Exit'] 
        );

        switch ($choice) {
            case 'Phone':
                $this->command->line("Current phone is: {$teacher->getPhone()}");
                while (true) {
                    $newPhone = $this->command->ask("Enter your new phone number");
                    if (empty(trim($newPhone))) {
                        $this->command->error("Phone number cannot be empty.");
                        continue;
                    }
                    try {
                        $teacher->setPhone($newPhone);
                        $this->command->info("Phone updated successfully!");
                        $this->logAction("updated phone for {$teacher->getName()}");
                        $this->saveTeachersToFile($teachers);
                        break;
                    } catch (\InvalidArgumentException $e) {
                        $this->command->error($e->getMessage());
                    }
                }
                break;

            case 'Password':
                // Check current password
                while (true) {
                    $oldPassword = $this->command->secret("Enter your current password");
                    if (empty(trim($oldPassword))) {
                        $this->command->error("old password cannot be empty.");
                        continue;
                    }
                    if ($oldPassword !== $teacher->getPassword()) {
                        $this->command->error("Current password is incorrect.");
                        continue;
                    }
                    break;
                }

                // Set new password
                while (true) {
                    $newPassword = $this->command->secret("Enter new password");
                    if (empty(trim($newPassword))) {
                        $this->command->error("Password cannot be empty.");
                        continue;
                    }
                    try {
                        $teacher->setPassword($newPassword);
                    } catch (\InvalidArgumentException $e) {
                        $this->command->error($e->getMessage());
                        continue;
                    }
                    $confirmPassword = $this->command->secret("Confirm new password");
                    if (empty(trim($confirmPassword))) {
                        $this->command->error("Password cannot be empty.");
                        continue;
                    }
                    if ($newPassword !== $confirmPassword) {
                        $this->command->error("Passwords do not match.");
                        continue;
                    }
                    $this->command->info("Password updated successfully!");
                    $this->logAction("updated password for {$teacher->getName()}");
                    $this->saveTeachersToFile($teachers);
                    break;
                }
                break;

            case 'Exit':
                return; // exit without changing anything
        }
    }

    // Teacher can manage student grades
    public function manageStudentGrades($teacher, array $students)
    {
        $classes = $teacher->getClasses();
        if (empty($classes)) {
            $this->command->error("You are not assigned to any classes.");
            return;
        }

        // Ask teacher to select a class
        $classNames = array_map(fn($c) => $c->getName(), $classes);
        $classChoice = $this->command->choice("Select a class:", $classNames);
        $selectedClass = collect($classes)->first(fn($c) => $c->getName() === $classChoice);

        // Get students in this class
        $studentsInClass = collect($students)
            ->filter(fn($s) => $s->getClass()->getName() === $selectedClass->getName())
            ->all();

        if (empty($studentsInClass)) {
            $this->command->error("No students found in this class.");
            return;
        }

        // Choose student
        $studentNames = array_map(fn($s) => $s->getName() . " ({$s->getEmail()})", $studentsInClass);
        $studentChoice = $this->command->choice("Select a student:", $studentNames);
        $selectedStudent = $studentsInClass[array_search($studentChoice, $studentNames)];
        $subject = $teacher->getSubject();
        $grades = $selectedStudent->getGrades();
        $existingGrade = $grades[$subject] ?? null;

        // Decide action based on whether grade exists
        if ($existingGrade === null) {
            $this->command->info("No grade assigned yet for $subject.");
            $action = $this->command->choice("Choose action:", ["Add", "Exit"]);
        } else {
            $this->command->info("Current grade for $subject: $existingGrade");
            $action = $this->command->choice("Choose action:", ["Update", "Delete", "Exit"]);
        }

        // Handle grade actions
        switch ($action) {
            case 'Add':
                $newGrade = $this->askValidGrade("Enter grade for $subject");
                $selectedStudent->addOrUpdateGrade($subject, $newGrade);
                $this->command->info("Grade added successfully.");
                $this->logAction("Added grade $newGrade for $subject to student {$selectedStudent->getName()}");
                break;

            case 'Update':
                $updatedGrade = $this->askValidGrade("Enter new grade for $subject");
                $selectedStudent->addOrUpdateGrade($subject, $updatedGrade);
                $this->command->info("Grade updated successfully.");
                $this->logAction("Updated grade $updatedGrade for $subject to student {$selectedStudent->getName()}");
                break;

            case 'Delete':
                $selectedStudent->removeGrade($subject);
                $this->command->info("Grade deleted successfully.");
                $this->logAction("Deleted grade for $subject from student {$selectedStudent->getName()}");
                break;

            case 'Exit':
                return; // exit without changes
        }

        // Save updated grades to file
        $this->saveGradesToFile($students);
    }

    // Ask for a valid grade number between 0 and 100
    private function askValidGrade(string $prompt): float
    {
        while (true) {
            $input = $this->command->ask($prompt);
            if (!is_numeric($input)) {
                $this->command->error("Grade must be a number.");
                continue;
            }
            $grade = floatval($input);
            if ($grade < 0 || $grade > 100) {
                $this->command->error("Grade must be between 0 and 100.");
                continue;
            }
            return $grade;
        }
    }

    // Save all teachers to file
    private function saveTeachersToFile(array $teachers): void
    {
        $lines = [];
        foreach ($teachers as $teacher) {
            $classNames = array_map(fn($c) => $c->getName(), $teacher->getClasses());
            $lines[] = implode(',', [
                $teacher->getName(),
                $teacher->getEmail(),
                $teacher->getPhone(),
                $teacher->getPassword(),
                $teacher->getSubject(),
                ...$classNames
            ]);
        }
        file_put_contents($this->teachersFile, implode("\n", $lines));
    }

    // Save all student grades to file
    private function saveGradesToFile(array $students): void
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

    // Log any action done by teacher for auditing
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
