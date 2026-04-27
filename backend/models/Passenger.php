<?php

class Passenger {
    private $id;
    private $firstName;
    private $lastName;
    private $email;
    private $phone;
    private $passportNumber;
    private $nationality;
    private $dateOfBirth;
    private $createdAt;
    private $updatedAt;

    // Aggregated data
    private $totalBookings;
    private $activeBookings;
    private $lastBookingDate;
    private $flightCount;

    public function __construct(array $data = []) {
        $this->hydrate($data);
    }

    public function hydrate(array $data) {
        foreach ($data as $key => $value) {
            $method = 'set' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }

    // Getters
    public function getId() { return $this->id; }
    public function getFirstName() { return $this->firstName; }
    public function getLastName() { return $this->lastName; }
    public function getEmail() { return $this->email; }
    public function getPhone() { return $this->phone; }
    public function getPassportNumber() { return $this->passportNumber; }
    public function getNationality() { return $this->nationality; }
    public function getDateOfBirth() { return $this->dateOfBirth; }
    public function getCreatedAt() { return $this->createdAt; }
    public function getUpdatedAt() { return $this->updatedAt; }

    // Aggregated data getters
    public function getTotalBookings() { return $this->totalBookings; }
    public function getActiveBookings() { return $this->activeBookings; }
    public function getLastBookingDate() { return $this->lastBookingDate; }
    public function getFlightCount() { return $this->flightCount; }

    // Setters
    public function setId($id) { $this->id = $id; }
    public function setFirstName($firstName) { $this->firstName = $firstName; }
    public function setLastName($lastName) { $this->lastName = $lastName; }
    public function setEmail($email) { $this->email = $email; }
    public function setPhone($phone) { $this->phone = $phone; }
    public function setPassportNumber($passportNumber) { $this->passportNumber = $passportNumber; }
    public function setNationality($nationality) { $this->nationality = $nationality; }
    public function setDateOfBirth($dateOfBirth) { $this->dateOfBirth = $dateOfBirth; }
    public function setCreatedAt($createdAt) { $this->createdAt = $createdAt; }
    public function setUpdatedAt($updatedAt) { $this->updatedAt = $updatedAt; }

    // Aggregated data setters
    public function setTotalBookings($totalBookings) { $this->totalBookings = $totalBookings; }
    public function setActiveBookings($activeBookings) { $this->activeBookings = $activeBookings; }
    public function setLastBookingDate($lastBookingDate) { $this->lastBookingDate = $lastBookingDate; }
    public function setFlightCount($flightCount) { $this->flightCount = $flightCount; }

    // Business logic methods
    public function getFullName() {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getAge() {
        if (!$this->dateOfBirth) {
            return null;
        }

        $birthDate = new DateTime($this->dateOfBirth);
        $today = new DateTime();
        return $today->diff($birthDate)->y;
    }

    public function isAdult() {
        $age = $this->getAge();
        return $age !== null && $age >= 18;
    }

    public function hasValidEmail() {
        return !empty($this->email) && filter_var($this->email, FILTER_VALIDATE_EMAIL);
    }

    public function hasValidPassport() {
        return !empty($this->passportNumber) && strlen($this->passportNumber) >= 6;
    }

    public function isCompleteProfile() {
        return !empty($this->firstName) &&
               !empty($this->lastName) &&
               $this->hasValidEmail() &&
               !empty($this->phone) &&
               $this->hasValidPassport() &&
               !empty($this->nationality) &&
               !empty($this->dateOfBirth);
    }

    public function canBookFlights() {
        return $this->isCompleteProfile() && $this->isAdult();
    }

    public function getProfileCompletionPercentage() {
        $fields = [
            'firstName', 'lastName', 'email', 'phone',
            'passportNumber', 'nationality', 'dateOfBirth'
        ];

        $completed = 0;
        foreach ($fields as $field) {
            $value = $this->$field;
            if (!empty($value)) {
                if ($field === 'email' && !$this->hasValidEmail()) {
                    continue;
                }
                $completed++;
            }
        }

        return round(($completed / count($fields)) * 100);
    }

    public function getFormattedDateOfBirth() {
        if (!$this->dateOfBirth) {
            return null;
        }

        return date('M j, Y', strtotime($this->dateOfBirth));
    }

    public function getMaskedPassportNumber() {
        if (!$this->passportNumber) {
            return null;
        }

        // Show only last 4 characters for security
        return '****' . substr($this->passportNumber, -4);
    }

    public function getMaskedPhoneNumber() {
        if (!$this->phone) {
            return null;
        }

        // Show only last 4 digits for security
        return '****' . substr(preg_replace('/\D/', '', $this->phone), -4);
    }

    public function toArray() {
        return [
            'id' => $this->id,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'passport_number' => $this->passportNumber,
            'nationality' => $this->nationality,
            'date_of_birth' => $this->dateOfBirth,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'total_bookings' => $this->totalBookings,
            'active_bookings' => $this->activeBookings,
            'last_booking_date' => $this->lastBookingDate,
            'flight_count' => $this->flightCount
        ];
    }

    public function toPublicArray() {
        $data = $this->toArray();

        // Mask sensitive information for public display
        if (isset($data['passport_number'])) {
            $data['passport_number'] = $this->getMaskedPassportNumber();
        }
        if (isset($data['phone'])) {
            $data['phone'] = $this->getMaskedPhoneNumber();
        }

        return $data;
    }

    public function toApiArray() {
        return $this->toArray();
    }

    // Validation methods
    public function validateForCreation() {
        $errors = [];

        if (empty($this->firstName)) {
            $errors[] = 'First name is required';
        }
        if (empty($this->lastName)) {
            $errors[] = 'Last name is required';
        }
        if (!empty($this->email) && !$this->hasValidEmail()) {
            $errors[] = 'Invalid email format';
        }
        if (!empty($this->dateOfBirth)) {
            $dob = DateTime::createFromFormat('Y-m-d', $this->dateOfBirth);
            if (!$dob) {
                $errors[] = 'Invalid date of birth format (YYYY-MM-DD)';
            }
        }

        return $errors;
    }

    public function validateForUpdate() {
        $errors = [];

        if (!empty($this->email) && !$this->hasValidEmail()) {
            $errors[] = 'Invalid email format';
        }
        if (!empty($this->dateOfBirth)) {
            $dob = DateTime::createFromFormat('Y-m-d', $this->dateOfBirth);
            if (!$dob) {
                $errors[] = 'Invalid date of birth format (YYYY-MM-DD)';
            }
        }

        return $errors;
    }
}
?>
