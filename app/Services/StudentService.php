<?php

namespace App\Services;

use App\Models\Student;
use App\Models\SchoolClass;
use Illuminate\Console\Command;

class StudentService
{
    // This class handles student-related operations from the student dashboard

    protected Command $command;
    protected array $classes;

    // Constructor receives the console command instance and list of classes
    public function __construct(Command $command, array $classes)
    {
        $this->command = $command;
        $this->classes = $classes;
    }

    // Display basic profile info of a student
    public function showProfile(Student $student)
    {
        $this->command->info("Profile Info");
        $this->command->line("Name: {$student->name}");
        $this->command->line("Email: {$student->email}");
        $this->command->line("Phone: " . ($student->phone ?? 'N/A'));
        $this->command->line("Class: {$student->class}");
        $this->command->line("Status: " . ($student->active ? 'Active' : 'Inactive'));
$this->logAction("Student viewed their profile: {$student->email}");

    }

    // Allow the student to update their email and phone number
    public function updateContact(Student $student, array $students)
    {
        $oldEmail = $student->email;
        $oldPhone = $student->phone;

        // Validate the email input
        while (true) {
            $newEmail = $this->command->ask("Enter new email", $student->email);

            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $this->command->error("Invalid email format.");
                continue;
            }

            if ($newEmail === 'admin@gmail.com') {
                $this->command->error("This email is reserved and cannot be used.");
                continue;
            }

            // Check if the new email already exists for another student
            $exists = collect($students)->first(fn($s) => $s->email === $newEmail && $s !== $student);
            if ($exists) {
                $this->command->error("This email is already used by another student.");
                continue;
            }

            break;
        }

        // Validate the phone number (must be 10 digits)
        while (true) {
            $newPhone = $this->command->ask("Enter new phone number", $student->phone ?? '');
            if (!preg_match('/^\d{10}$/', $newPhone)) {
                $this->command->error("Phone number must be exactly 10 digits.");
                continue;
            }
            break;
        }

        // Apply updates
        $student->updateContact($newEmail, $newPhone);
        $this->command->info("Info updated!");

        // Log the update
        $this->logAction("Student updated contact info: OLD email=$oldEmail, phone=$oldPhone → NEW email=$newEmail, phone=$newPhone");
    }

    // Show subjects of the student and any available grades
    public function showSubjectsAndGrades(Student $student)
    {
        $className = $student->class;
        $classObj = $this->classes[$className] ?? null;

        if (!$classObj) {
            $this->command->line("Subjects: Not found (unknown class)");
            return;
        }

        $subjects = $classObj->getSubjects();

        if (empty($subjects)) {
            $this->command->line("No subjects assigned to this class.");
            return;
        }

        $this->command->info("Subjects and Grades:");

        foreach ($subjects as $subject) {
            $grade = $student->grades[$subject] ?? null;
            $this->command->line("- $subject: " . ($grade ?? '(No grade)'));
        }

        $this->logAction("Student viewed subjects and grades for class: $className");
    }

    // Show full classroom info including supervisor and subjects
    public function showClassroom(Student $student)
    {
        $className = $student->class;
        $classObj = $this->classes[$className] ?? null;

        $this->command->info("Assigned Classroom Info");
        $this->command->line("Class: $className");

        if ($classObj) {
            $this->command->line("Class Supervisor: " . $classObj->getSupervisor());

            if (!empty($classObj->subjects)) {
                $this->command->line("Subjects:");
                foreach ($classObj->subjects as $subject) {
                    $this->command->line("- $subject");
                }
            } else {
                $this->command->line("No subjects assigned.");
            }
        } else {
            $this->command->line("Class not found.");
        }
$this->logAction("Student viewed classroom info for class: {$className}");

    }

    // Log student actions for auditing
    private function logAction(string $message)
    {
        $timestamp = now()->toDateTimeString();
        $logLine = "[$timestamp] $message\n";
        file_put_contents(storage_path('logs/audit_log.txt'), $logLine, FILE_APPEND);
    }
}
