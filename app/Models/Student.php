<?php
namespace App\Models;
class Student extends User
{
    private string $idNumber;        // Student's ID number
    private int $age;                // Student's age
    private SchoolClass $class;      // Assigned class object
    private string $status;          // Account status: active or deactive
    private array $grades = [];      // Array to store subjects and grades

    // ---------- Constructor ----------
    public function __construct(
        string $name,
        string $phone,
        string $idNumber,
        int $age,
        string $email,
        string $password,
        SchoolClass $class,
        string $status = 'active'
    ) {
        // Call parent constructor to set basic user info
        parent::__construct($name, $phone, $email, $password, 'student'); // role is always student
        $this->setIdNumber($idNumber);
        $this->setAge($age);
        $this->setClass($class);
        $this->setStatus($status);
    }

    // ---------- Setters ----------
    public function setIdNumber(string $idNumber): void
    {
        if (empty($idNumber)) throw new \InvalidArgumentException("Id-Number cannot be empty.");
        if (!preg_match('/^\d{9}$/', $idNumber)) {
            throw new \InvalidArgumentException("ID Number must be exactly 9 digits.");
        }
        $this->idNumber = $idNumber;
    }

    public function setAge(int $age): void
    {
        if (empty($age)) throw new \InvalidArgumentException("Age cannot be empty.");
        if ($age < 5 || $age > 20) {
            throw new \InvalidArgumentException("Age must be between 5 and 20.");
        }
        $this->age = $age;
    }

    public function setClass(SchoolClass $class): void
    {
        $this->class = $class;
    }

    public function setStatus(string $status): void
    {
        // Status should be either active or deactive
        if (!in_array(strtolower($status), ['active', 'deactive'])) {
            throw new \InvalidArgumentException("Status must be either 'active' or 'deactive'.");
        }
        $this->status = strtolower($status);
    }

    // ---------- Getters ----------
    public function getIdNumber(): string { return $this->idNumber; }
    public function getAge(): int { return $this->age; }
    public function getClass(): SchoolClass { return $this->class; }
    public function getStatus(): string { return $this->status; }
    public function getGrades(): array { return $this->grades; }

    // ---------- Grades ----------
    // Add or update a grade for a subject
    public function addOrUpdateGrade(string $subject, $grade): void
    {
        $this->grades[$subject] = $grade;
    }

    // Remove a grade for a subject
    public function removeGrade(string $subject): void
    {
        unset($this->grades[$subject]);
    }

    // ---------- Profile ----------
    // Show student info in the console
    public function showProfile($command): void
    {
        parent::showProfile($command); // Show basic user info
        $command->line("ID Number: " . $this->idNumber);
        $command->line("Age: " . $this->age);
        $command->line("Class: " . $this->class->getName());
    }
}
