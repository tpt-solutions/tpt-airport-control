<?php

class Flight {
    private $id;
    private $flightNumber;
    private $airlineId;
    private $aircraftId;
    private $origin;
    private $destination;
    private $scheduledDeparture;
    private $scheduledArrival;
    private $actualDeparture;
    private $actualArrival;
    private $status;
    private $gate;
    private $terminal;
    private $createdAt;
    private $updatedAt;

    // Airline and Aircraft data (populated from joins)
    private $airlineName;
    private $airlineCode;
    private $aircraftModel;
    private $aircraftRegistration;
    private $aircraftCapacity;

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
    public function getFlightNumber() { return $this->flightNumber; }
    public function getAirlineId() { return $this->airlineId; }
    public function getAircraftId() { return $this->aircraftId; }
    public function getOrigin() { return $this->origin; }
    public function getDestination() { return $this->destination; }
    public function getScheduledDeparture() { return $this->scheduledDeparture; }
    public function getScheduledArrival() { return $this->scheduledArrival; }
    public function getActualDeparture() { return $this->actualDeparture; }
    public function getActualArrival() { return $this->actualArrival; }
    public function getStatus() { return $this->status; }
    public function getGate() { return $this->gate; }
    public function getTerminal() { return $this->terminal; }
    public function getCreatedAt() { return $this->createdAt; }
    public function getUpdatedAt() { return $this->updatedAt; }
    public function getAirlineName() { return $this->airlineName; }
    public function getAirlineCode() { return $this->airlineCode; }
    public function getAircraftModel() { return $this->aircraftModel; }
    public function getAircraftRegistration() { return $this->aircraftRegistration; }
    public function getAircraftCapacity() { return $this->aircraftCapacity; }

    // Setters
    public function setId($id) { $this->id = $id; }
    public function setFlightNumber($flightNumber) { $this->flightNumber = $flightNumber; }
    public function setAirlineId($airlineId) { $this->airlineId = $airlineId; }
    public function setAircraftId($aircraftId) { $this->aircraftId = $aircraftId; }
    public function setOrigin($origin) { $this->origin = $origin; }
    public function setDestination($destination) { $this->destination = $destination; }
    public function setScheduledDeparture($scheduledDeparture) { $this->scheduledDeparture = $scheduledDeparture; }
    public function setScheduledArrival($scheduledArrival) { $this->scheduledArrival = $scheduledArrival; }
    public function setActualDeparture($actualDeparture) { $this->actualDeparture = $actualDeparture; }
    public function setActualArrival($actualArrival) { $this->actualArrival = $actualArrival; }
    public function setStatus($status) { $this->status = $status; }
    public function setGate($gate) { $this->gate = $gate; }
    public function setTerminal($terminal) { $this->terminal = $terminal; }
    public function setCreatedAt($createdAt) { $this->createdAt = $createdAt; }
    public function setUpdatedAt($updatedAt) { $this->updatedAt = $updatedAt; }
    public function setAirlineName($airlineName) { $this->airlineName = $airlineName; }
    public function setAirlineCode($airlineCode) { $this->airlineCode = $airlineCode; }
    public function setAircraftModel($aircraftModel) { $this->aircraftModel = $aircraftModel; }
    public function setAircraftRegistration($aircraftRegistration) { $this->aircraftRegistration = $aircraftRegistration; }
    public function setAircraftCapacity($aircraftCapacity) { $this->aircraftCapacity = $aircraftCapacity; }

    // Business logic methods
    public function isDelayed() {
        return $this->status === 'delayed';
    }

    public function isDeparted() {
        return in_array($this->status, ['departed', 'arrived']);
    }

    public function isArrived() {
        return $this->status === 'arrived';
    }

    public function getDuration() {
        if (!$this->scheduledDeparture || !$this->scheduledArrival) {
            return null;
        }

        $departure = new DateTime($this->scheduledDeparture);
        $arrival = new DateTime($this->scheduledArrival);

        return $departure->diff($arrival);
    }

    public function toArray() {
        return [
            'id' => $this->id,
            'flight_number' => $this->flightNumber,
            'airline_id' => $this->airlineId,
            'aircraft_id' => $this->aircraftId,
            'origin' => $this->origin,
            'destination' => $this->destination,
            'scheduled_departure' => $this->scheduledDeparture,
            'scheduled_arrival' => $this->scheduledArrival,
            'actual_departure' => $this->actualDeparture,
            'actual_arrival' => $this->actualArrival,
            'status' => $this->status,
            'gate' => $this->gate,
            'terminal' => $this->terminal,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'airline_name' => $this->airlineName,
            'airline_code' => $this->airlineCode,
            'aircraft_model' => $this->aircraftModel,
            'aircraft_registration' => $this->aircraftRegistration,
            'aircraft_capacity' => $this->aircraftCapacity
        ];
    }

    public function toApiArray() {
        return $this->toArray();
    }
}
?>
