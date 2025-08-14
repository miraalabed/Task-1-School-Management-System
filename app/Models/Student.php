<?php
    namespace App\Models;
    class Student extends User
    {
        private SchoolClass $class;
        private string $status; // Active or Deactive
        private array $grades = [];

        public function __construct(
            string $name,
            string $idNumber,
            string $phone,
            int $age,
            string $email,
            string $password,
            SchoolClass $class,
            string $status = 'active'
        ) {
            parent::__construct($name, $idNumber, $phone, $age, $email, $password);
            $this->setClass($class);
            $this->setStatus($status);
        }

        // ---------- Setters ----------
        public function setClass(SchoolClass $class): void
        {
            $this->class = $class;
        }

        public function setStatus(string $status): void
        {
            $this->status = strtolower(trim($status));
        }

        // ---------- Getters ----------
        public function getClass(): SchoolClass { return $this->class; }
        public function getStatus(): string { return $this->status; }
        public function getGrades(): array { return $this->grades; }

        // ---------- Grades ----------
        public function addOrUpdateGrade(string $subject, $grade): void
        {
            $this->grades[$subject] = $grade;
        }

        public function removeGrade(string $subject): void
        {
            unset($this->grades[$subject]);
        }
        
        // ---------- Profile Info ----------
       // Shows the profile info of the student
        public function showProfile($command): void
        {
            parent::showProfile($command);     // Call the parent method to show basic user info

            $command->line("Class: " . $this->class->getName()); // Show the name of the student's class
            $command->line("Status: " . ucfirst($this->status)); // Show the student's status (Active/Deactive)

            if (!empty($this->grades)) {                         // Check if the student has any grades
                $command->line("Grades:");                      
                foreach ($this->grades as $subject => $grade) {  // Loop through each subject and grade
                    $command->line("- $subject: $grade");  // Display subject and its grade
                }
            } else {
                $command->line("No grades assigned.");          
            }
        }


    }
