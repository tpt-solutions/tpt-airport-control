<?php

class Booking {
    private $id;
    private $passengerId;
    private $flightId;
    private $seatNumber;
    private $bookingReference;
    private $status;
    private $totalAmount;
    private $currency;
    private $paymentStatus;
    private $createdAt;
    private $updatedAt;

    // Joined data from related tables
    private $passengerFirstName;
    private $passengerLastName;
    private $passengerEmail;
    private $passengerPhone;
    private $passengerPassportNumber;
    private $passengerNationality;
    private $passengerDateOfBirth;

    private $flightNumber;
    private $flightOrigin;
    private $flightDestination;
    private $flightScheduledDeparture;
    private $flightScheduledArrival;
    private $flightActualDeparture;
    private $flightActualArrival;
    private $flightStatus;
    private $flightGate;
    private $flightTerminal;

    private $airlineName;
    private $airlineCode;
    private $aircraftModel;
    private $aircraftRegistration;

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
    public function getPassengerId() { return $this->passengerId; }
    public function getFlightId() { return $this->flightId; }
    public function getSeatNumber() { return $this->seatNumber; }
    public function getBookingReference() { return $this->bookingReference; }
    public function getStatus() { return $this->status; }
    public function getTotalAmount() { return $this->totalAmount; }
    public function getCurrency() { return $this->currency; }
    public function getPaymentStatus() { return $this->paymentStatus; }
    public function getCreatedAt() { return $this->createdAt; }
    public function getUpdatedAt() { return $this->updatedAt; }

    // Passenger getters
    public function getPassengerFirstName() { return $this->passengerFirstName; }
    public function getPassengerLastName() { return $this->passengerLastName; }
    public function getPassengerEmail() { return $this->passengerEmail; }
    public function getPassengerPhone() { return $this->passengerPhone; }
    public function getPassengerPassportNumber() { return $this->passengerPassportNumber; }
    public function getPassengerNationality() { return $this->passengerNationality; }
    public function getPassengerDateOfBirth() { return $this->passengerDateOfBirth; }

    // Flight getters
    public function getFlightNumber() { return $this->flightNumber; }
    public function getFlightOrigin() { return $this->flightOrigin; }
    public function getFlightDestination() { return $this->flightDestination; }
    public function getFlightScheduledDeparture() { return $this->flightScheduledDeparture; }
    public function getFlightScheduledArrival() { return $this->flightScheduledArrival; }
    public function getFlightActualDeparture() { return $this->flightActualDeparture; }
    public function getFlightActualArrival() { return $this->flightActualArrival; }
    public function getFlightStatus() { return $this->flightStatus; }
    public function getFlightGate() { return $this->flightGate; }
    public function getFlightTerminal() { return $this->flightTerminal; }

    // Airline/Aircraft getters
    public function getAirlineName() { return $this->airlineName; }
    public function getAirlineCode() { return $this->airlineCode; }
    public function getAircraftModel() { return $this->aircraftModel; }
    public function getAircraftRegistration() { return $this->aircraftRegistration; }

    // Setters
    public function setId($id) { $this->id = $id; }
    public function setPassengerId($passengerId) { $this->passengerId = $passengerId; }
    public function setFlightId($flightId) { $this->flightId = $flightId; }
    public function setSeatNumber($seatNumber) { $this->seatNumber = $seatNumber; }
    public function setBookingReference($bookingReference) { $this->bookingReference = $bookingReference; }
    public function setStatus($status) { $this->status = $status; }
    public function setTotalAmount($totalAmount) { $this->totalAmount = $totalAmount; }
    public function setCurrency($currency) { $this->currency = $currency; }
    public function setPaymentStatus($paymentStatus) { $this->paymentStatus = $paymentStatus; }
    public function setCreatedAt($createdAt) { $this->createdAt = $createdAt; }
    public function setUpdatedAt($updatedAt) { $this->updatedAt = $updatedAt; }

    // Passenger setters
    public function setPassengerFirstName($firstName) { $this->passengerFirstName = $firstName; }
    public function setPassengerLastName($lastName) { $this->passengerLastName = $lastName; }
    public function setPassengerEmail($email) { $this->passengerEmail = $email; }
    public function setPassengerPhone($phone) { $this->passengerPhone = $phone; }
    public function setPassengerPassportNumber($passportNumber) { $this->passengerPassportNumber = $passportNumber; }
    public function setPassengerNationality($nationality) { $this->passengerNationality = $nationality; }
    public function setPassengerDateOfBirth($dateOfBirth) { $this->passengerDateOfBirth = $dateOfBirth; }

    // Flight setters
    public function setFlightNumber($flightNumber) { $this->flightNumber = $flightNumber; }
    public function setFlightOrigin($origin) { $this->flightOrigin = $origin; }
    public function setFlightDestination($destination) { $this->flightDestination = $destination; }
    public function setFlightScheduledDeparture($scheduledDeparture) { $this->flightScheduledDeparture = $scheduledDeparture; }
    public function setFlightScheduledArrival($scheduledArrival) { $this->flightScheduledArrival = $scheduledArrival; }
    public function setFlightActualDeparture($actualDeparture) { $this->flightActualDeparture = $actualDeparture; }
    public function setFlightActualArrival($actualArrival) { $this->flightActualArrival = $actualArrival; }
    public function setFlightStatus($status) { $this->flightStatus = $status; }
    public function setFlightGate($gate) { $this->flightGate = $gate; }
    public function setFlightTerminal($terminal) { $this->flightTerminal = $terminal; }

    // Airline/Aircraft setters
    public function setAirlineName($airlineName) { $this->airlineName = $airlineName; }
    public function setAirlineCode($airlineCode) { $this->airlineCode = $airlineCode; }
    public function setAircraftModel($aircraftModel) { $this->aircraftModel = $aircraftModel; }
    public function setAircraftRegistration($aircraftRegistration) { $this->aircraftRegistration = $aircraftRegistration; }

    // Business logic methods
    public function isConfirmed() {
        return $this->status === 'confirmed';
    }

    public function isCancelled() {
        return $this->status === 'cancelled';
    }

    public function isCheckedIn() {
        return $this->status === 'checked-in';
    }

    public function isPaid() {
        return $this->paymentStatus === 'paid';
    }

    public function canBeCancelled() {
        return in_array($this->status, ['confirmed', 'checked-in']) && !$this->isFlightDeparted();
    }

    public function canCheckIn() {
        return $this->status === 'confirmed' && $this->isFlightAvailableForCheckIn();
    }

    private function isFlightDeparted() {
        if (!$this->flightScheduledDeparture) {
            return false;
        }
        return strtotime($this->flightScheduledDeparture) < time();
    }

    private function isFlightAvailableForCheckIn() {
        if (!$this->flightScheduledDeparture) {
            return false;
        }

        $departureTime = strtotime($this->flightScheduledDeparture);
        $currentTime = time();
        $hoursBeforeDeparture = ($departureTime - $currentTime) / 3600;

        // Allow check-in 24 hours before departure, up to 1 hour before
        return $hoursBeforeDeparture <= 24 && $hoursBeforeDeparture >= 1;
    }

    public function getPassengerFullName() {
        return trim($this->passengerFirstName . ' ' . $this->passengerLastName);
    }

    public function getFlightRoute() {
        return $this->flightOrigin . ' → ' . $this->flightDestination;
    }

    public function getFormattedTotalAmount() {
        return number_format($this->totalAmount, 2) . ' ' . $this->currency;
    }

    public function toArray() {
        return [
            'id' => $this->id,
            'passenger_id' => $this->passengerId,
            'flight_id' => $this->flightId,
            'seat_number' => $this->seatNumber,
            'booking_reference' => $this->bookingReference,
            'status' => $this->status,
            'total_amount' => $this->totalAmount,
            'currency' => $this->currency,
            'payment_status' => $this->paymentStatus,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'passenger_first_name' => $this->passengerFirstName,
            'passenger_last_name' => $this->passengerLastName,
            'passenger_email' => $this->passengerEmail,
            'passenger_phone' => $this->passengerPhone,
            'passenger_passport_number' => $this->passengerPassportNumber,
            'passenger_nationality' => $this->passengerNationality,
            'passenger_date_of_birth' => $this->passengerDateOfBirth,
            'flight_number' => $this->flightNumber,
            'flight_origin' => $this->flightOrigin,
            'flight_destination' => $this->flightDestination,
            'flight_scheduled_departure' => $this->flightScheduledDeparture,
            'flight_scheduled_arrival' => $this->flightScheduledArrival,
            'flight_actual_departure' => $this->flightActualDeparture,
            'flight_actual_arrival' => $this->flightActualArrival,
            'flight_status' => $this->flightStatus,
            'flight_gate' => $this->flightGate,
            'flight_terminal' => $this->flightTerminal,
            'airline_name' => $this->airlineName,
            'airline_code' => $this->airlineCode,
            'aircraft_model' => $this->aircraftModel,
            'aircraft_registration' => $this->aircraftRegistration
        ];
    }

    public function toApiArray() {
        return $this->toArray();
    }
}
?>
