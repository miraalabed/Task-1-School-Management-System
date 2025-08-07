<?php
namespace App\Models;

class Student
{
    private string $name;
    private string $idNumber;
    private string $phone;
    private int $age;
    private SchoolClass $class; 
    private string $email;
    private string $password;
    private string $status; // Active or Deactive
    private array $grades = [];

    // Constructor: creates a new student object and sets all required properties with validation
    public function __construct(
        string $name,
        string $idNumber,
        string $phone,
        int $age,
        SchoolClass $class,
        string $email,
        string $password,
        string $status = 'active' 
    ) {
        $this->setName($name);
        $this->setIdNumber($idNumber);
        $this->setPhone($phone);
        $this->setAge($age);
        $this->setClass($class);
        $this->setEmail($email);
        $this->setPassword($password);
        $this->setStatus($status);
    }

    // --------------------------------------------------- Validation methods -------------------------------------------------------

    // Checks if a field value is not empty
    private function validateNotEmpty($field, $value): void
    {
        if (empty(trim($value))) {
            throw new \InvalidArgumentException("$field must not be empty.");
        }
    }

    // Validates email format using built-in filter
    private function validateEmailFormat($email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Email must be in format test@gmail.com.");
        }
    }

    // Validates ID number to be exactly 9 digits
    private function validateIdNumber($idNumber): void
    {
        if (!preg_match('/^\d{9}$/', $idNumber)) {
            throw new \InvalidArgumentException("ID number must be exactly 9 digits.");
        }
    }

    // Validates phone number to be exactly 10 digits
    private function validatePhone($phone): void
    {
        if (!preg_match('/^\d{10}$/', $phone)) {
            throw new \InvalidArgumentException("Phone number must be exactly 10 digits.");
        }
    }

    // --------------------------------------------------- Setters functions ------------------------------------------------------------

    // Sets the student's name after checking it's not empty
    public function setName(string $name): void
    {
        $this->validateNotEmpty('Name', $name);
        $this->name = $name;
    }

    // Sets and validates the student's ID number
    public function setIdNumber(string $idNumber): void
    {
        $this->validateNotEmpty('ID Number', $idNumber);
        $this->validateIdNumber($idNumber);
        $this->idNumber = $idNumber;
    }

    // Sets and validates the student's phone number
    public function setPhone(string $phone): void
    {
        $this->validateNotEmpty('Phone', $phone);
        $this->validatePhone($phone);
        $this->phone = $phone;
    }

    // Sets and validates student's age (should be between 5 and 20)
    public function setAge(int $age): void
    {
        $this->validateNotEmpty('Age', $age);
        if ($age < 5 || $age > 20) {
            throw new \InvalidArgumentException("Age must be between 5 and 20 for students.");
        }
        $this->age = $age;
    }

    // Assigns the student to a class
    public function setClass(SchoolClass $class): void
    {
        $this->class = $class;
    }

    // Sets and validates the student's email
    public function setEmail(string $email): void
    {
        $this->validateNotEmpty('Email', $email);
        $this->validateEmailFormat($email);
        $this->email = $email;
    }

    // Sets and validates the student's password (length between 4 and 8)
    public function setPassword(string $password): void
    {
        $this->validateNotEmpty('Password', $password);
        if (strlen($password) < 4 || strlen($password) > 8) {
            throw new \InvalidArgumentException("Password must be between 4 and 8 characters.");
        }
        $this->password = $password;
    }

    // Sets the student's status (e.g., active or deactive)
    public function setStatus(string $status): void
    {
        $this->status = strtolower(trim($status));
    }

    // --------------------------------------------------- Getters functions ------------------------------------------------------------

    // These functions return student details
    public function getName(): string { return $this->name; }
    public function getIdNumber(): string { return $this->idNumber; }
    public function getPhone(): string { return $this->phone; }
    public function getAge(): int { return $this->age; }
    public function getClass(): SchoolClass { return $this->class; }
    public function getEmail(): string { return $this->email; }
    public function getPassword(): string { return $this->password; }
    public function getStatus(): string { return $this->status; }
    public function getGrades(): array { return $this->grades; }

    // --------------------------------------------------- Grades operations ------------------------------------------------------------

    // Adds or updates the student's grade for a specific subject
    public function addOrUpdateGrade(string $subject, $grade): void
    {
        $this->grades[$subject] = $grade;
    }

    // Removes the grade for a given subject
    public function removeGrade(string $subject): void
    {
        unset($this->grades[$subject]);
    }
}