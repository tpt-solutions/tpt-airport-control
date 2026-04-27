/**
 * Flight Booking Flow End-to-End Tests
 *
 * Tests the complete flight booking journey from search to payment
 * and boarding pass generation
 */

describe('Flight Booking Flow', () => {
  beforeEach(() => {
    // Clear cookies and local storage before each test
    cy.clearCookies()
    cy.clearLocalStorage()

    // Login as test user
    cy.login('testuser', 'testpass')

    // Visit the flight booking page
    cy.visit('/flights')
  })

  it('should allow searching and selecting flights', () => {
    // Fill search form
    cy.get('[data-cy="origin"]').type('JFK')
    cy.get('[data-cy="destination"]').type('LAX')
    cy.get('[data-cy="departure-date"]').type('2025-09-20')
    cy.get('[data-cy="return-date"]').type('2025-09-25')
    cy.get('[data-cy="passengers"]').select('2')

    // Submit search
    cy.get('[data-cy="search-flights"]').click()

    // Verify search results
    cy.get('[data-cy="flight-results"]').should('be.visible')
    cy.get('[data-cy="flight-card"]').should('have.length.greaterThan', 0)

    // Select first flight
    cy.get('[data-cy="flight-card"]').first().find('[data-cy="select-flight"]').click()

    // Verify flight details page
    cy.url().should('include', '/flight/')
    cy.get('[data-cy="flight-details"]').should('be.visible')
    cy.get('[data-cy="flight-number"]').should('be.visible')
    cy.get('[data-cy="departure-time"]').should('be.visible')
    cy.get('[data-cy="arrival-time"]').should('be.visible')
  })

  it('should handle flight booking with seat selection', () => {
    // Search for flights
    cy.searchFlights('JFK', 'LAX', '2025-09-20')

    // Select flight
    cy.get('[data-cy="flight-card"]').first().find('[data-cy="select-flight"]').click()

    // Select seats
    cy.get('[data-cy="seat-map"]').should('be.visible')
    cy.get('[data-cy="available-seat"]').first().click()
    cy.get('[data-cy="available-seat"]').eq(1).click()

    // Continue to passenger details
    cy.get('[data-cy="continue-to-passengers"]').click()

    // Fill passenger details
    cy.get('[data-cy="passenger-form"]').within(() => {
      // Passenger 1
      cy.get('[data-cy="first-name"]').eq(0).type('John')
      cy.get('[data-cy="last-name"]').eq(0).type('Doe')
      cy.get('[data-cy="email"]').eq(0).type('john.doe@example.com')
      cy.get('[data-cy="phone"]').eq(0).type('+1-555-0123')
      cy.get('[data-cy="passport"]').eq(0).type('P123456789')
      cy.get('[data-cy="nationality"]').eq(0).select('USA')
      cy.get('[data-cy="date-of-birth"]').eq(0).type('1990-01-15')

      // Passenger 2
      cy.get('[data-cy="first-name"]').eq(1).type('Jane')
      cy.get('[data-cy="last-name"]').eq(1).type('Doe')
      cy.get('[data-cy="email"]').eq(1).type('jane.doe@example.com')
      cy.get('[data-cy="phone"]').eq(1).type('+1-555-0124')
      cy.get('[data-cy="passport"]').eq(1).type('P987654321')
      cy.get('[data-cy="nationality"]').eq(1).select('USA')
      cy.get('[data-cy="date-of-birth"]').eq(1).type('1992-03-20')
    })

    // Continue to payment
    cy.get('[data-cy="continue-to-payment"]').click()

    // Verify booking summary
    cy.get('[data-cy="booking-summary"]').should('be.visible')
    cy.get('[data-cy="total-amount"]').should('be.visible')
    cy.get('[data-cy="passenger-count"]').should('contain', '2')
  })

  it('should process payment and generate boarding passes', () => {
    // Complete booking up to payment
    cy.searchFlights('JFK', 'LAX', '2025-09-20')
    cy.get('[data-cy="flight-card"]').first().find('[data-cy="select-flight"]').click()
    cy.selectSeats(['12A', '12B'])
    cy.fillPassengerDetails([
      { firstName: 'John', lastName: 'Doe', email: 'john@example.com' },
      { firstName: 'Jane', lastName: 'Doe', email: 'jane@example.com' }
    ])
    cy.get('[data-cy="continue-to-payment"]').click()

    // Fill payment details
    cy.get('[data-cy="payment-form"]').within(() => {
      cy.get('[data-cy="card-number"]').type('4111111111111111')
      cy.get('[data-cy="expiry-month"]').select('12')
      cy.get('[data-cy="expiry-year"]').select('2026')
      cy.get('[data-cy="cvv"]').type('123')
      cy.get('[data-cy="cardholder-name"]').type('John Doe')
      cy.get('[data-cy="billing-address"]').type('123 Main St')
      cy.get('[data-cy="billing-city"]').type('New York')
      cy.get('[data-cy="billing-zip"]').type('10001')
    })

    // Accept terms and conditions
    cy.get('[data-cy="accept-terms"]').check()

    // Submit payment
    cy.get('[data-cy="submit-payment"]').click()

    // Verify payment processing
    cy.contains('Processing payment').should('be.visible')

    // Verify booking confirmation
    cy.contains('Booking confirmed').should('be.visible')
    cy.get('[data-cy="booking-reference"]').should('be.visible')

    // Verify boarding passes are generated
    cy.get('[data-cy="boarding-pass"]').should('have.length', 2)
    cy.get('[data-cy="boarding-pass"]').first().within(() => {
      cy.get('[data-cy="passenger-name"]').should('contain', 'John Doe')
      cy.get('[data-cy="flight-number"]').should('be.visible')
      cy.get('[data-cy="seat-number"]').should('contain', '12A')
      cy.get('[data-cy="qr-code"]').should('be.visible')
    })
  })

  it('should handle booking modifications', () => {
    // Create a booking first
    cy.searchFlights('JFK', 'LAX', '2025-09-20')
    cy.get('[data-cy="flight-card"]').first().find('[data-cy="select-flight"]').click()
    cy.selectSeats(['15C'])
    cy.fillPassengerDetails([{ firstName: 'Alice', lastName: 'Smith', email: 'alice@example.com' }])
    cy.get('[data-cy="continue-to-payment"]').click()
    cy.processPayment()
    cy.get('[data-cy="booking-reference"]').invoke('text').as('bookingRef')

    // Navigate to bookings
    cy.visit('/bookings')

    // Find and modify booking
    cy.get('@bookingRef').then(bookingRef => {
      cy.contains(bookingRef).parent().find('[data-cy="modify-booking"]').click()
    })

    // Change seat
    cy.get('[data-cy="change-seat"]').click()
    cy.get('[data-cy="available-seat"]').contains('16A').click()
    cy.get('[data-cy="confirm-seat-change"]').click()

    // Verify seat change
    cy.contains('Seat changed successfully').should('be.visible')
    cy.get('[data-cy="current-seat"]').should('contain', '16A')
  })

  it('should handle booking cancellations', () => {
    // Create a booking
    cy.searchFlights('JFK', 'LAX', '2025-09-20')
    cy.get('[data-cy="flight-card"]').first().find('[data-cy="select-flight"]').click()
    cy.selectSeats(['20F'])
    cy.fillPassengerDetails([{ firstName: 'Bob', lastName: 'Johnson', email: 'bob@example.com' }])
    cy.get('[data-cy="continue-to-payment"]').click()
    cy.processPayment()
    cy.get('[data-cy="booking-reference"]').invoke('text').as('bookingRef')

    // Navigate to bookings and cancel
    cy.visit('/bookings')
    cy.get('@bookingRef').then(bookingRef => {
      cy.contains(bookingRef).parent().find('[data-cy="cancel-booking"]').click()
    })

    // Confirm cancellation
    cy.get('[data-cy="confirm-cancellation"]').click()

    // Verify cancellation
    cy.contains('Booking cancelled').should('be.visible')
    cy.get('@bookingRef').then(bookingRef => {
      cy.contains(bookingRef).parent().should('contain', 'Cancelled')
    })
  })

  it('should handle multi-city bookings', () => {
    // Add multiple destinations
    cy.get('[data-cy="add-destination"]').click()
    cy.get('[data-cy="add-destination"]').click()

    // Fill multi-city form
    cy.get('[data-cy="segment-0"]').within(() => {
      cy.get('[data-cy="origin"]').type('JFK')
      cy.get('[data-cy="destination"]').type('LAX')
      cy.get('[data-cy="departure-date"]').type('2025-09-20')
    })

    cy.get('[data-cy="segment-1"]').within(() => {
      cy.get('[data-cy="origin"]').type('LAX')
      cy.get('[data-cy="destination"]').type('SFO')
      cy.get('[data-cy="departure-date"]').type('2025-09-25')
    })

    cy.get('[data-cy="segment-2"]').within(() => {
      cy.get('[data-cy="origin"]').type('SFO')
      cy.get('[data-cy="destination"]').type('JFK')
      cy.get('[data-cy="departure-date"]').type('2025-09-30')
    })

    // Search multi-city flights
    cy.get('[data-cy="search-multi-city"]').click()

    // Verify multi-city results
    cy.get('[data-cy="multi-city-results"]').should('be.visible')
    cy.get('[data-cy="segment-result"]').should('have.length', 3)

    // Select all segments
    cy.get('[data-cy="select-all-segments"]').click()

    // Continue with booking
    cy.get('[data-cy="continue-booking"]').click()

    // Verify multi-city booking summary
    cy.get('[data-cy="multi-city-summary"]').should('be.visible')
    cy.get('[data-cy="total-segments"]').should('contain', '3')
  })

  it('should handle flight changes and rebooking', () => {
    // Create initial booking
    cy.searchFlights('JFK', 'LAX', '2025-09-20')
    cy.get('[data-cy="flight-card"]').first().find('[data-cy="select-flight"]').click()
    cy.selectSeats(['10D'])
    cy.fillPassengerDetails([{ firstName: 'Charlie', lastName: 'Brown', email: 'charlie@example.com' }])
    cy.get('[data-cy="continue-to-payment"]').click()
    cy.processPayment()

    // Simulate flight delay/cancellation
    cy.visit('/flight-status')
    cy.get('[data-cy="flight-search"]').type('AA101')
    cy.get('[data-cy="check-status"]').click()

    // Flight is delayed
    cy.contains('Flight Delayed').should('be.visible')
    cy.get('[data-cy="rebooking-options"]').should('be.visible')

    // Choose rebooking option
    cy.get('[data-cy="rebook-next-flight"]').click()

    // Select alternative flight
    cy.get('[data-cy="alternative-flight"]').first().find('[data-cy="select-alternative"]').click()

    // Confirm rebooking
    cy.get('[data-cy="confirm-rebooking"]').click()

    // Verify rebooking confirmation
    cy.contains('Rebooking confirmed').should('be.visible')
    cy.get('[data-cy="new-flight-details"]').should('be.visible')
  })

  it('should handle special assistance requests', () => {
    // Search for flight
    cy.searchFlights('JFK', 'LAX', '2025-09-20')
    cy.get('[data-cy="flight-card"]').first().find('[data-cy="select-flight"]').click()

    // Select seats
    cy.selectSeats(['5A']) // Wheelchair accessible seat

    // Request special assistance
    cy.get('[data-cy="special-assistance"]').check()
    cy.get('[data-cy="assistance-type"]').select('wheelchair')
    cy.get('[data-cy="assistance-details"]').type('Need wheelchair assistance from check-in to gate')

    // Continue to passenger details
    cy.get('[data-cy="continue-to-passengers"]').click()

    // Fill passenger details with special needs
    cy.fillPassengerDetails([{
      firstName: 'David',
      lastName: 'Wilson',
      email: 'david@example.com',
      specialAssistance: true
    }])

    // Continue to payment
    cy.get('[data-cy="continue-to-payment"]').click()

    // Verify special assistance is noted
    cy.get('[data-cy="special-assistance-notice"]').should('be.visible')
    cy.contains('Wheelchair assistance requested').should('be.visible')

    // Complete booking
    cy.processPayment()

    // Verify assistance confirmation
    cy.contains('Special assistance confirmed').should('be.visible')
  })

  it('should handle group bookings', () => {
    // Select group booking option
    cy.get('[data-cy="group-booking"]').check()

    // Set group size
    cy.get('[data-cy="group-size"]').select('10')

    // Fill group details
    cy.get('[data-cy="group-name"]').type('Tech Conference Attendees')
    cy.get('[data-cy="group-contact"]').type('organizer@techconf.com')
    cy.get('[data-cy="group-phone"]').type('+1-555-0199')

    // Search for flights
    cy.searchFlights('JFK', 'LAX', '2025-09-20', '10')

    // Select flight for group
    cy.get('[data-cy="flight-card"]').first().find('[data-cy="select-group-flight"]').click()

    // Bulk seat selection
    cy.get('[data-cy="bulk-seat-selection"]').should('be.visible')
    cy.get('[data-cy="select-block-seats"]').click()
    cy.get('[data-cy="seat-block-15"]').click() // Select block of 10 seats

    // Continue to group passenger details
    cy.get('[data-cy="continue-to-group-details"]').click()

    // Bulk upload passenger data (simulated)
    cy.get('[data-cy="upload-passenger-csv"]').selectFile('cypress/fixtures/group-passengers.csv')

    // Verify group booking summary
    cy.get('[data-cy="group-summary"]').should('be.visible')
    cy.get('[data-cy="total-passengers"]').should('contain', '10')
    cy.get('[data-cy="group-discount"]').should('be.visible')

    // Process group payment
    cy.get('[data-cy="group-payment"]').click()
    cy.processGroupPayment()

    // Verify group confirmation
    cy.contains('Group booking confirmed').should('be.visible')
    cy.get('[data-cy="group-reference"]').should('be.visible')
  })

  it('should handle international flight bookings', () => {
    // Search international flight
    cy.searchFlights('JFK', 'LHR', '2025-09-20')

    // Select international flight
    cy.get('[data-cy="international-flight"]').first().find('[data-cy="select-flight"]').click()

    // Verify international requirements
    cy.get('[data-cy="visa-requirements"]').should('be.visible')
    cy.get('[data-cy="passport-validity"]').should('be.visible')
    cy.get('[data-cy="customs-info"]').should('be.visible')

    // Fill international passenger details
    cy.fillPassengerDetails([{
      firstName: 'Emma',
      lastName: 'Thompson',
      email: 'emma@example.com',
      passport: 'UK123456789',
      nationality: 'British',
      visaRequired: true,
      visaNumber: 'V987654321'
    }])

    // Continue to payment
    cy.get('[data-cy="continue-to-payment"]').click()

    // Verify international fees
    cy.get('[data-cy="international-fees"]').should('be.visible')
    cy.get('[data-cy="visa-fee"]').should('be.visible')
    cy.get('[data-cy="international-tax"]').should('be.visible')

    // Process payment
    cy.processPayment()

    // Verify international booking confirmation
    cy.contains('International booking confirmed').should('be.visible')
    cy.get('[data-cy="travel-documents"]').should('be.visible')
    cy.get('[data-cy="embassy-contacts"]').should('be.visible')
  })
})
