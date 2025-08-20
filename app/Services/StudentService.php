<?php
namespace App\Services;
use App\Models\Student;
use Illuminate\Console\Command;
class StudentService
{
    // This class helps a student to view and update their profile, grades, and classroom
    protected Command $command; // We use this to interact with the console (ask questions, show messages)
    protected string $studentsFile; // Path to store student info
    protected string $gradesFile;   // Path to store student grades
    protected string $classesFile;  // Path to store class info
    protected ?string $currentUserName = null; // Name of the logged-in user
    protected ?string $currentRole = null;     // Role of the logged-in user

    // Constructor is called when we create a new StudentService
    public function __construct(Command $command)
    {
        $this->command = $command;
        // Files where we save data
        $this->studentsFile = storage_path('app\data\students.txt');
        $this->gradesFile = storage_path('app\data\grades.txt');
        $this->classesFile = storage_path('app\data\classes.txt');
    }

    // Set the current user and role (like logging in)
    public function setCurrentUser(string $role, string $name)
    {
        $this->currentRole = $role;
        $this->currentUserName = $name;
    }

    // Save all students to the students file
    private function saveStudentsToFile(array $students)
    {
        $lines = [];
        foreach ($students as $student) {
            // Combine all student details in one line
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
        // Write all lines to the file
        file_put_contents($this->studentsFile, implode("\n", $lines));
    }

    // Save grades of all students to the grades file
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

    // Update student's phone or password
    public function updateContact(Student $student, array $students)
    {
        $choice = $this->command->choice(
            "What would you like to update?",
            ['Phone', 'Password']
        );

        switch ($choice) {

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
                        $this->logAction("updated phone for {$student->getName()}");
                        $this->saveStudentsToFile($students);
                        break;
                    } catch (\InvalidArgumentException $e) {
                        $this->command->error($e->getMessage());
                    }
                }
                break;

            case 'Password':
                // Ask for current password first
                while (true) {
                    $oldPassword = $this->command->secret("Enter your current password");
                    if (empty(trim($oldPassword))) {
                        $this->command->error("Old Password cannot be empty.");
                        continue;
                    }
                    if ($oldPassword !== $student->getPassword()) {
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
                        $student->setPassword($newPassword);
                    } catch (\InvalidArgumentException $e) {
                        $this->command->error($e->getMessage());
                        continue;
                    }
                    $confirmPassword = $this->command->secret("Confirm new password");
                    if (empty(trim($confirmPasswordPassword))) {
                        $this->command->error("Confirm Password cannot be empty.");
                        continue;
                    }
                    if ($newPassword !== $confirmPassword) {
                        $this->command->error("Passwords do not match.");
                        continue;
                    }
                    $this->command->info("Password updated successfully!");
                    $this->logAction("updated password for {$student->getName()}");
                    $this->saveStudentsToFile($students);
                    break;
                }
                break;
        }
    }

    // Show grades and calculate average
    public function showGrades(Student $student)
    {
        $grades = $student->getGrades();

        if (empty($grades)) {
            $this->command->line("No grades assigned.");
            return;
        }
        $this->command->info("Subjects and Grades:");
        $total = 0;
        $count = 0;
        foreach ($grades as $subject => $grade) {
            $this->command->line("- $subject: $grade");
            if (is_numeric($grade)) {
                $total += $grade;
                $count++;
            }
        }
        if ($count > 0) {
            $average = $total / $count;
            $this->command->line("Average: " . number_format($average, 2));
        } else {
            $this->command->line("Average: N/A");
        }
        $this->logAction("viewed grades and average for: {$student->getEmail()}");
    }

    // Save any action to a log file (like an audit)
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
