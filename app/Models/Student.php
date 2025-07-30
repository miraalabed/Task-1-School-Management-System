<?php

namespace App\Models;

// This class represents a student in the school
class Student
{
    public $name;     // Student's full name
    public $email;    // Student's email address
    public $age;      // Student's age
    public $class;    // The class (grade) the student belongs to
    public $active;   // Whether the student is active or not
    public $phone;    // Student's phone number (optional)
    public array $grades = [];  // Subjects and their grades for the student

    // Constructor: runs when creating a new student
    public function __construct($name, $email, $age, $class, $active = true, $phone = null)
    {
        $this->name = $name;
        $this->email = $email;
        $this->age = $age;
        $this->class = $class;
        $this->active = $active;
        $this->phone = $phone;
    }

    // Update the student's email and phone number
    public function updateContact($newEmail, $newPhone)
    {
        $this->email = $newEmail;
        $this->phone = $newPhone;
    }

    // Mark the student as inactive
    public function deactivate()
    {
        $this->active = false;
    }

    // Convert the student's data into an array (for exporting or displaying)
    public function toArray()
    {
        return get_object_vars($this);
    }

    // Add or update the student's grade for a specific subject
    public function addOrUpdateGrade($subject, $grade)
    {
        $this->grades[$subject] = $grade;
    }

    // Remove the grade of a specific subject from the student's records
    public function removeGrade($subject)
    {
        unset($this->grades[$subject]);
    }
}
