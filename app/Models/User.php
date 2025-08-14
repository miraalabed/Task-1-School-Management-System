<?php
    namespace App\Models;
    class User
    {
        protected string $name;
        protected string $idNumber;
        protected string $phone;
        protected int $age;
        protected string $email;
        protected string $password;

        public function __construct(
            string $name,
            string $idNumber,
            string $phone,
            int $age,
            string $email,
            string $password
        ) {
            $this->setName($name);
            $this->setIdNumber($idNumber);
            $this->setPhone($phone);
            $this->setAge($age);
            $this->setEmail($email);
            $this->setPassword($password);
        }

        // ---------- Validation ----------
        protected function validateNotEmpty($field, $value): void
        {
            if (empty(trim($value))) {
                throw new \InvalidArgumentException("$field must not be empty.");
            }
        }

        protected function validateEmailFormat($email): void
        {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException("Email must be in format test@gmail.com.");
            }
        }

        protected function validateIdNumber($idNumber): void
        {
            if (!preg_match('/^\d{9}$/', $idNumber)) {
                throw new \InvalidArgumentException("ID number must be exactly 9 digits.");
            }
        }

        protected function validatePhone($phone): void
        {
            if (!preg_match('/^\d{10}$/', $phone)) {
                throw new \InvalidArgumentException("Phone number must be exactly 10 digits.");
            }
        }

        // ---------- Setters ----------
        public function setName(string $name): void
        {
            $this->validateNotEmpty('Name', $name);
            $this->name = $name;
        }

        public function setIdNumber(string $idNumber): void
        {
            $this->validateNotEmpty('ID Number', $idNumber);
            $this->validateIdNumber($idNumber);
            $this->idNumber = $idNumber;
        }

        public function setPhone(string $phone): void
        {
            $this->validateNotEmpty('Phone', $phone);
            $this->validatePhone($phone);
            $this->phone = $phone;
        }

        public function setAge(int $age): void
        {
            $this->validateNotEmpty('Age', $age);
            if ($age < 5 || $age > 100) {
                throw new \InvalidArgumentException("Age must be realistic.");
            }
            $this->age = $age;
        }

        public function setEmail(string $email): void
        {
            $this->validateNotEmpty('Email', $email);
            $this->validateEmailFormat($email);
            $this->email = $email;
        }

        public function setPassword(string $password): void
        {
            $this->validateNotEmpty('Password', $password);
            if (strlen($password) < 4 || strlen($password) > 8) {
                throw new \InvalidArgumentException("Password must be between 4 and 8 characters.");
            }
            $this->password = $password;
        }

        // ---------- Getters ----------
        public function getName(): string { return $this->name; }
        public function getIdNumber(): string { return $this->idNumber; }
        public function getPhone(): string { return $this->phone; }
        public function getAge(): int { return $this->age; }
        public function getEmail(): string { return $this->email; }
        public function getPassword(): string { return $this->password; }
        
        // ---------- Profile Info ----------
        // show basic user info
        public function showProfile($command): void
        {
            $command->line("Name: " . $this->getName());
            $command->line("Email: " . $this->getEmail());
            $command->line("Phone: " . $this->getPhone());
            $command->line("Age: " . $this->getAge());
            $command->line("ID Number: " . $this->getIdNumber());
        }

    }
