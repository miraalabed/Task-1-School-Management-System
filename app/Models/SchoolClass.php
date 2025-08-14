<?php
namespace App\Models;

class SchoolClass
{
    private string $name;        // The name of the class (e.g., Grade 9A)
    private array $subjects;     // List of subjects assigned to the class
    private ?string $supervisor; // Name of the class supervisor (can be null)

    // Constructor: creates a new SchoolClass object and sets its name, subjects, and supervisor
    public function __construct(string $name, array $subjects = [], ?string $supervisor = null)
    {
        $this->setName($name);
        $this->setSubjects($subjects);
        $this->setSupervisor($supervisor);
    }

    // --------------------------------------------------- Setters functions------------------------------------------------------------

    // Sets the class name after checking it's not empty
    public function setName(string $name): void
    {
        if (empty(trim($name))) {
            throw new \InvalidArgumentException("Class name must not be empty.");
        }
        $this->name = $name;
    }

    // Sets the list of subjects for the class
    public function setSubjects(array $subjects): void
    {
        $this->subjects = $subjects;
    }
   
    // Sets the name of the class supervisor (can be null if unassigned)
    public function setSupervisor(?string $name): void
    {
        $this->supervisor = $name;
    }

    // --------------------------------------------------- Getters functions------------------------------------------------------------

    public function getName(): string { return $this->name; }
    public function getSubjects(): array { return $this->subjects; }
    public function getSupervisor(): string{ return $this->supervisor ?? 'Unassigned'; }

     // --------------------------------------------------- Subjects operations------------------------------------------------------------

    // Adds a new subject to the class after checking it's not already added
    public function addSubject(string $subject): void
    {
        $subjectLower = strtolower(trim($subject));
        foreach ($this->subjects as $s) {
            if (strtolower($s) === $subjectLower) {
                throw new \InvalidArgumentException("Subject '$subject' already exists.");
            }
        }
        $this->subjects[] = $subject;
    }

    // Removes a subject from the class if it exists
    public function removeSubject(string $subject): void
    {
        $subjectLower = strtolower(trim($subject));
        foreach ($this->subjects as $key => $s) {
            if (strtolower($s) === $subjectLower) {
                unset($this->subjects[$key]); // Remove the subject
                $this->subjects = array_values($this->subjects); // Reindex the array
                return;
            }
        }
        throw new \InvalidArgumentException("Subject '$subject' does not exist.");
    }
   // Shows all information about the classroom
    public function showInfo(): void
    {
        echo "Assigned Classroom Info\n";                   
        echo "Class: " . $this->getName() . "\n";        
        echo "Class Supervisor: " . $this->getSupervisor() . "\n"; 

        $subjects = $this->getSubjects(); // Get the list of subjects
        if (!empty($subjects)) {   // Check if there are any subjects
            echo "Subjects:\n";                          
            foreach ($subjects as $subject) {  // Loop through each subject
                echo "- $subject\n";                     
            }
        } else {
            echo "No subjects assigned.\n";             
        }
    }


}
