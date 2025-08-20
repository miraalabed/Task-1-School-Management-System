<?php
namespace App\Models;
class Teacher extends User
{
    private string $subject;       // The subject this teacher teaches
    private array $classes = [];   // Array of SchoolClass objects assigned to the teacher

    // ---------- Constructor ----------
    public function __construct(
        string $name,
        string $phone,
        string $email,
        string $password,
        string $subject,
        array $classes = []
    ) {
        // Call parent constructor to set basic user info
        parent::__construct($name, $phone, $email, $password, 'teacher');
        $this->setSubject($subject); // Set teacher's subject
        $this->setClasses($classes); // Set assigned classes
    }

    // -------------------- Setters --------------------
    public function setSubject(string $subject): void
    {
        // Subject should not be empty
        if (empty(trim($subject))) {
            throw new \InvalidArgumentException("Subject must not be empty.");
        }
        $this->subject = $subject;
    }

    public function setClasses(array $classes): void
    {
        // Make sure every item in the array is a SchoolClass object
        foreach ($classes as $classObj) {
            if (!$classObj instanceof SchoolClass) {
                throw new \InvalidArgumentException("All items in classes must be instances of SchoolClass.");
            }
        }
        $this->classes = $classes;
    }

    // -------------------- Getters --------------------
    public function getSubject(): string { return $this->subject; }
    public function getClasses(): array { return $this->classes; }

    // -------------------- Show Profile --------------------
    // This prints the teacher's info and their classes in the console
    public function showProfile($command): void
    {
        parent::showProfile($command); // Show basic info from User
        $command->line("Subject: " . $this->subject);
        if (!empty($this->classes)) {
            $command->line("Classes:");
            foreach ($this->classes as $class) {
                $command->line("- " . $class->getName()); // Print class names
            }
        } else {
            $command->line("No classes assigned.");
        }
    }
}
