<?php

namespace App\Models;

// This class represents a school class (like Grade 1, Grade 2, etc.)
class SchoolClass
{
    public $name;         // Name of the class (e.g. Grade 5)
    public $subjects;     // Subjects assigned to this class
    public $supervisor;   // Name of the supervisor for this class

    // When creating a class, we can give it a name, subjects, and supervisor
    public function __construct($name, $subjects = [], $supervisor = null)
    {
        $this->name = $name;
        $this->subjects = $subjects;
        $this->supervisor = $supervisor;
    }

    // Add a new subject to the class if it's not already included
    public function addSubject($subject)
    {
        if (!in_array($subject, $this->subjects)) {
            $this->subjects[] = $subject;
        }
    }

    // Remove a subject from the class if it exists
    public function removeSubject($subject)
    {
        $this->subjects = array_filter($this->subjects, fn($s) => $s !== $subject);
    }

    // Return the list of subjects for this class
    public function getSubjects()
    {
        return $this->subjects;
    }

    // Return the name of the supervisor, or 'Unassigned' if not set
    public function getSupervisor()
    {
        return $this->supervisor ?? 'Unassigned';
    }

    // Set or change the supervisor's name
    public function setSupervisor($name)
    {
        $this->supervisor = $name;
    }
}
