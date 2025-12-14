<?php
// File: ../classes/Student.php
require_once "Database.php";
require_once "Transaction.php"; // Assuming this is needed for methods not shown

// IMPORTANT: This class relies on 'Database.php' having a 'connect()' method
// which returns a valid PDO object on every call.

class Student extends Database {

    // Check if a student with the same student_id already exists
    public function isStudentIdExist($student_id) {
        $sql = "SELECT COUNT(*) AS total 
                FROM users 
                WHERE student_id = :student_id";
        $query = $this->connect()->prepare($sql);
        $query->bindParam(":student_id", $student_id);

        if ($query->execute()) {
            $result = $query->fetch(PDO::FETCH_ASSOC); 
            return $result["total"] > 0;
        }
        return false;
    }

    // Check if a user with the same email already exists
    public function isEmailExist($email) {
        $sql = "SELECT COUNT(*) AS total 
                FROM users 
                WHERE email = :email";
        $query = $this->connect()->prepare($sql);
        $query->bindParam(":email", $email);

        if ($query->execute()) {
            $result = $query->fetch(PDO::FETCH_ASSOC);
            return $result["total"] > 0;
        }
        return false;
    }

    // Register a new student (MODIFIED: Uses 6-digit code in $token)
    public function registerStudent($student_id, $firstname, $lastname, $course, $contact_number, $email, $password, $token) {
        // Hash password for security
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // MODIFIED SQL: Added verification_token and is_verified=0
        $sql = "INSERT INTO users (student_id, firstname, lastname, course, contact_number, email, password, verification_token, is_verified, role)
                VALUES (:student_id, :firstname, :lastname, :course, :contact_number, :email, :password, :token, 0, 'student')";

        $query = $this->connect()->prepare($sql);
        $query->bindParam(":student_id", $student_id);
        $query->bindParam(":firstname", $firstname);
        $query->bindParam(":lastname", $lastname);
        $query->bindParam(":course", $course);
        $query->bindParam(":contact_number", $contact_number);
        $query->bindParam(":email", $email);
        $query->bindParam(":password", $hashed_password);
        $query->bindParam(":token", $token); // NEW: Bind the 6-digit code

        return $query->execute();
    }
    
    /**
     * NEW: Method to verify account using a 6-digit code (Corrected implementation)
     * NOTE: This replaces the previously partial verifyAccount() implementation.
     */
    public function verifyStudentAccountByCode($email, $code) {
        $conn = $this->connect();
        $code = trim($code);
        
        // 1. Find and update the user in one query: 
        // We look for the email, the matching token, and ensure they are NOT already verified (is_verified = 0)
        $sql = "UPDATE users 
                SET is_verified = 1, verification_token = NULL 
                WHERE email = :email 
                AND verification_token = :code 
                AND is_verified = 0 
                LIMIT 1";

        try {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':code', $code);
            $stmt->execute();

            // Check if exactly one row was updated (verification successful)
            return $stmt->rowCount() === 1;

        } catch (PDOException $e) {
            error_log("Database error during account verification: " . $e->getMessage());
            return false;
        }
    }


    public function getContactDetails($user_id) {
        $conn = $this->connect();
        $stmt = $conn->prepare("SELECT contact_number FROM users WHERE id = :id");
        $stmt->execute([':id' => $user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateStudentProfile($user_id, $firstname, $lastname, $course, $contact_number, $email) {
        $conn = $this->connect();
        $sql = "UPDATE users SET 
                    firstname = :firstname, 
                    lastname = :lastname, 
                    course = :course, 
                    contact_number = :contact_number, 
                    email = :email 
                WHERE id = :id";
                
        $stmt = $conn->prepare($sql);
        
        $stmt->bindParam(":firstname", $firstname);
        $stmt->bindParam(":lastname", $lastname);
        $stmt->bindParam(":course", $course);
        $stmt->bindParam(":contact_number", $contact_number);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":id", $user_id);
        
        return $stmt->execute();
    }
    
    public function getUserById(int $userId): ?array {
    try {
        $conn = $this->connect(); // Assuming 'connect()' method provides the PDO connection
        $sql = "SELECT id, email, firstname, lastname FROM users WHERE id = :id LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (\PDOException $e) {
        // Log the error for staff review, but return gracefully
        error_log("Database error fetching user ID {$userId}: " . $e->getMessage());
        return null;
    }
}
// Note: The redundant verifyAccount function has been removed from the bottom 
// and replaced by the correct verifyStudentAccountByCode above.
}