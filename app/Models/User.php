<?php
namespace App\Models;
// This is an abstract class for common user properties and methods
abstract class User
{
    // ---------- Properties ----------
    protected string $name;
    protected string $phone;
    protected string $email;
    protected string $password;
    protected string $role; // student or teacher

    // ---------- Constructor ----------
    public function __construct(string $name, string $phone, string $email, string $password, string $role = 'student')
    {
        // Use setter methods to assign values safely
        $this->setName($name);
        $this->setPhone($phone);
        $this->setEmail($email);
        $this->setPassword($password);
        $this->setRole($role);
    }

    // ---------- Getters (to read values) ----------
    public function getName(): string { return $this->name; }
    public function getPhone(): string { return $this->phone; }
    public function getEmail(): string { return $this->email; }
    public function getPassword(): string { return $this->password; }
    public function getRole(): string { return $this->role; }

    // ---------- Setters (to update values safely) ----------
    public function setName(string $name): void
    {
        if (empty($name)) throw new \InvalidArgumentException("Name cannot be empty.");
        $this->name = $name; // Save the name
    }

    public function setPhone(string $phone): void
    {
        if (empty($phone)) throw new \InvalidArgumentException("Phone cannot be empty.");
        // Phone must be exactly 10 digits
        if (!preg_match('/^\d{10}$/', $phone)) {
            throw new \InvalidArgumentException("Phone number must be exactly 10 digits.");
        }
        $this->phone = $phone;
    }

    public function setEmail(string $email): void
    {
        if (empty($email)) throw new \InvalidArgumentException("Email cannot be empty.");
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email format.");
        }
        $this->email = $email;
    }

    public function setPassword(string $password): void
    {
        if (empty($password)) throw new \InvalidArgumentException("Password cannot be empty.");
        // Password length should be between 4 and 8 characters
        if (strlen($password) < 4 || strlen($password) > 8) {
            throw new \InvalidArgumentException("Password must be between 4 and 8 characters.");
        }
        $this->password = $password;
    }

    public function setRole(string $role): void
    {
        // Only allow student or teacher roles
        if (!in_array(strtolower($role), ['student', 'teacher'])) {
            throw new \InvalidArgumentException("Role must be either 'student' or 'teacher'.");
        }
        $this->role = strtolower($role);
    }

    // ---------- Show Profile ----------
    // This method prints the basic user info in the console
    public function showProfile($command): void
    {
        $command->line("Name: " . $this->name);
        $command->line("Email: " . $this->email);
        $command->line("Phone: " . $this->phone);
    }
}
