/**
 * ATC Dashboard End-to-End Tests
 *
 * Tests the air traffic control dashboard functionality including
 * real-time flight monitoring, conflict detection, and airspace management
 */

describe('ATC Dashboard', () => {
  beforeEach(() => {
    // Clear cookies and local storage
    cy.clearCookies()
    cy.clearLocalStorage()

    // Login as ATC controller
    cy.login('atc_controller', 'atcpass')

    // Visit ATC dashboard
    cy.visit('/atc-dashboard')
  })

  it('should display real-time flight tracking', () => {
    // Verify dashboard loads
    cy.get('[data-cy="atc-dashboard"]').should('be.visible')
    cy.get('[data-cy="flight-tracking-map"]').should('be.visible')

    // Verify active flights are displayed
    cy.get('[data-cy="active-flights-list"]').should('be.visible')
    cy.get('[data-cy="flight-track"]').should('have.length.greaterThan', 0)

    // Verify flight information panels
    cy.get('[data-cy="flight-info-panel"]').should('be.visible')
    cy.get('[data-cy="altitude-display"]').should('be.visible')
    cy.get('[data-cy="speed-display"]').should('be.visible')
    cy.get('[data-cy="heading-display"]').should('be.visible')
  })

  it('should handle airspace sector management', () => {
    // Navigate to airspace management
    cy.get('[data-cy="airspace-management"]').click()

    // Verify sector display
    cy.get('[data-cy="airspace-sectors"]').should('be.visible')
    cy.get('[data-cy="sector-boundary"]').should('have.length.greaterThan', 0)

    // Test sector selection
    cy.get('[data-cy="sector-boundary"]').first().click()
    cy.get('[data-cy="sector-details"]').should('be.visible')
    cy.get('[data-cy="sector-altitude-limits"]').should('be.visible')
    cy.get('[data-cy="sector-capacity"]').should('be.visible')

    // Test sector capacity monitoring
    cy.get('[data-cy="sector-utilization"]').should('be.visible')
    cy.get('[data-cy="capacity-warning"]').should('not.exist') // Assuming normal conditions

    // Test airspace restrictions
    cy.get('[data-cy="airspace-restrictions"]').click()
    cy.get('[data-cy="restricted-area"]').should('have.length.greaterThan', 0)
  })

  it('should detect and display flight conflicts', () => {
    // Wait for conflict detection to run
    cy.wait(2000)

    // Check for conflict alerts
    cy.get('[data-cy="conflict-alerts"]').should('be.visible')

    // Simulate conflict scenario (if no real conflicts exist)
    cy.get('[data-cy="simulate-conflict"]').click()

    // Verify conflict detection
    cy.get('[data-cy="conflict-notification"]').should('be.visible')
    cy.get('[data-cy="conflict-details"]').should('be.visible')

    // Verify conflict information
    cy.get('[data-cy="conflict-aircraft"]').should('have.length', 2)
    cy.get('[data-cy="conflict-severity"]').should('be.visible')
    cy.get('[data-cy="time-to-conflict"]').should('be.visible')
    cy.get('[data-cy="conflict-resolution-options"]').should('be.visible')
  })

  it('should handle flight clearance management', () => {
    // Navigate to clearances
    cy.get('[data-cy="clearance-management"]').click()

    // Verify clearance queue
    cy.get('[data-cy="clearance-queue"]').should('be.visible')
    cy.get('[data-cy="pending-clearance"]').should('have.length.greaterThan', 0)

    // Test clearance issuance
    cy.get('[data-cy="pending-clearance"]').first().find('[data-cy="issue-clearance"]').click()

    // Fill clearance details
    cy.get('[data-cy="clearance-form"]').within(() => {
      cy.get('[data-cy="clearance-type"]').select('takeoff')
      cy.get('[data-cy="runway-assignment"]').select('27L')
      cy.get('[data-cy="heading-assignment"]').type('270')
      cy.get('[data-cy="altitude-assignment"]').type('5000')
      cy.get('[data-cy="speed-assignment"]').type('180')
    })

    // Issue clearance
    cy.get('[data-cy="submit-clearance"]').click()

    // Verify clearance issued
    cy.contains('Clearance issued successfully').should('be.visible')
    cy.get('[data-cy="active-clearance"]').should('be.visible')
  })

  it('should monitor runway status and assignments', () => {
    // Navigate to runway management
    cy.get('[data-cy="runway-management"]').click()

    // Verify runway display
    cy.get('[data-cy="runway-status"]').should('be.visible')
    cy.get('[data-cy="runway-occupancy"]').should('be.visible')

    // Test runway selection
    cy.get('[data-cy="runway-item"]').first().click()
    cy.get('[data-cy="runway-details"]').should('be.visible')
    cy.get('[data-cy="runway-length"]').should('be.visible')
    cy.get('[data-cy="runway-surface"]').should('be.visible')

    // Test runway assignment
    cy.get('[data-cy="assign-runway"]').click()
    cy.get('[data-cy="available-flights"]').first().click()
    cy.get('[data-cy="confirm-assignment"]').click()

    // Verify assignment
    cy.contains('Runway assigned successfully').should('be.visible')
    cy.get('[data-cy="runway-assigned-flight"]').should('be.visible')
  })

  it('should handle weather radar integration', () => {
    // Navigate to weather monitoring
    cy.get('[data-cy="weather-monitoring"]').click()

    // Verify weather radar display
    cy.get('[data-cy="weather-radar"]').should('be.visible')
    cy.get('[data-cy="radar-intensity"]').should('be.visible')

    // Test weather alert monitoring
    cy.get('[data-cy="weather-alerts"]').should('be.visible')

    // Simulate weather alert
    cy.get('[data-cy="simulate-weather-alert"]').click()

    // Verify alert display
    cy.get('[data-cy="weather-alert-notification"]').should('be.visible')
    cy.get('[data-cy="alert-severity"]').should('be.visible')
    cy.get('[data-cy="alert-location"]').should('be.visible')
    cy.get('[data-cy="alert-description"]').should('be.visible')

    // Test weather impact on flights
    cy.get('[data-cy="affected-flights"]').should('be.visible')
    cy.get('[data-cy="weather-diversion-options"]').should('be.visible')
  })

  it('should manage controller handoffs', () => {
    // Navigate to controller management
    cy.get('[data-cy="controller-management"]').click()

    // Verify active controllers
    cy.get('[data-cy="active-controllers"]').should('be.visible')
    cy.get('[data-cy="controller-workload"]').should('be.visible')

    // Test sector handoff
    cy.get('[data-cy="sector-handoff"]').click()
    cy.get('[data-cy="available-controllers"]').first().click()
    cy.get('[data-cy="target-sector"]').select('Sector 5')
    cy.get('[data-cy="initiate-handoff"]').click()

    // Verify handoff process
    cy.contains('Handoff initiated').should('be.visible')
    cy.get('[data-cy="handoff-progress"]').should('be.visible')

    // Accept handoff (as receiving controller)
    cy.get('[data-cy="accept-handoff"]').click()

    // Verify handoff completion
    cy.contains('Handoff completed').should('be.visible')
    cy.get('[data-cy="sector-responsibility"]').should('contain', 'Sector 5')
  })

  it('should handle emergency situations', () => {
    // Navigate to emergency management
    cy.get('[data-cy="emergency-management"]').click()

    // Verify emergency monitoring
    cy.get('[data-cy="emergency-alerts"]').should('be.visible')
    cy.get('[data-cy="emergency-protocols"]').should('be.visible')

    // Simulate emergency
    cy.get('[data-cy="simulate-emergency"]').click()

    // Verify emergency response
    cy.get('[data-cy="emergency-notification"]').should('be.visible')
    cy.get('[data-cy="emergency-type"]').should('be.visible')
    cy.get('[data-cy="affected-aircraft"]').should('be.visible')

    // Test emergency protocols
    cy.get('[data-cy="emergency-protocols"]').within(() => {
      cy.get('[data-cy="protocol-activation"]').click()
      cy.get('[data-cy="emergency-coordination"]').should('be.visible')
      cy.get('[data-cy="emergency-communication"]').should('be.visible')
    })

    // Test emergency clearance
    cy.get('[data-cy="emergency-clearance"]').click()
    cy.get('[data-cy="emergency-flight"]').first().click()
    cy.get('[data-cy="priority-clearance"]').select('EMERGENCY')
    cy.get('[data-cy="issue-emergency-clearance"]').click()

    // Verify emergency clearance
    cy.contains('Emergency clearance issued').should('be.visible')
  })

  it('should provide flight data analysis', () => {
    // Navigate to flight analysis
    cy.get('[data-cy="flight-analysis"]').click()

    // Verify analysis dashboard
    cy.get('[data-cy="flight-performance-metrics"]').should('be.visible')
    cy.get('[data-cy="traffic-density-map"]').should('be.visible')
    cy.get('[data-cy="delay-analysis"]').should('be.visible')

    // Test flight path analysis
    cy.get('[data-cy="flight-path-analysis"]').click()
    cy.get('[data-cy="select-flight-path"]').first().click()
    cy.get('[data-cy="path-trajectory"]').should('be.visible')
    cy.get('[data-cy="path-efficiency"]').should('be.visible')
    cy.get('[data-cy="path-optimization-suggestions"]').should('be.visible')

    // Test traffic flow analysis
    cy.get('[data-cy="traffic-flow-analysis"]').click()
    cy.get('[data-cy="flow-heatmap"]').should('be.visible')
    cy.get('[data-cy="bottleneck-identification"]').should('be.visible')
    cy.get('[data-cy="capacity-optimization"]').should('be.visible')
  })

  it('should handle communication logging', () => {
    // Navigate to communications
    cy.get('[data-cy="communications"]').click()

    // Verify communication log
    cy.get('[data-cy="communication-log"]').should('be.visible')
    cy.get('[data-cy="active-communications"]').should('be.visible')

    // Test new communication
    cy.get('[data-cy="new-communication"]').click()
    cy.get('[data-cy="select-flight"]').first().click()
    cy.get('[data-cy="communication-type"]').select('instruction')
    cy.get('[data-cy="communication-message"]').type('Turn left heading 270, descend to 8000 feet')
    cy.get('[data-cy="send-communication"]').click()

    // Verify communication sent
    cy.contains('Communication sent').should('be.visible')
    cy.get('[data-cy="communication-history"]').should('contain', 'Turn left heading 270')

    // Test communication acknowledgment
    cy.get('[data-cy="communication-item"]').last().find('[data-cy="mark-acknowledged"]').click()
    cy.get('[data-cy="acknowledgment-status"]').should('contain', 'Acknowledged')
  })

  it('should manage NOTAM and airspace restrictions', () => {
    // Navigate to NOTAM management
    cy.get('[data-cy="notam-management"]').click()

    // Verify NOTAM display
    cy.get('[data-cy="active-notams"]').should('be.visible')
    cy.get('[data-cy="notam-map"]').should('be.visible')

    // Test NOTAM details
    cy.get('[data-cy="notam-item"]').first().click()
    cy.get('[data-cy="notam-details"]').should('be.visible')
    cy.get('[data-cy="notam-classification"]').should('be.visible')
    cy.get('[data-cy="notam-effective-period"]').should('be.visible')
    cy.get('[data-cy="affected-airspace"]').should('be.visible')

    // Test airspace restriction monitoring
    cy.get('[data-cy="airspace-restrictions-tab"]').click()
    cy.get('[data-cy="restriction-zones"]').should('be.visible')
    cy.get('[data-cy="restriction-details"]').should('be.visible')

    // Test flight path through restricted area
    cy.get('[data-cy="flight-path-check"]').click()
    cy.get('[data-cy="select-flight-route"]').first().click()
    cy.get('[data-cy="restriction-conflicts"]').should('be.visible')
    cy.get('[data-cy="alternative-routing"]').should('be.visible')
  })

  it('should provide system health monitoring', () => {
    // Navigate to system monitoring
    cy.get('[data-cy="system-monitoring"]').click()

    // Verify system status
    cy.get('[data-cy="system-health"]').should('be.visible')
    cy.get('[data-cy="radar-status"]').should('be.visible')
    cy.get('[data-cy="communication-status"]').should('be.visible')
    cy.get('[data-cy="adsb-status"]').should('be.visible')

    // Test component status checks
    cy.get('[data-cy="component-status"]').within(() => {
      cy.get('[data-cy="radar-system"]').should('have.class', 'status-healthy')
      cy.get('[data-cy="communication-system"]').should('have.class', 'status-healthy')
      cy.get('[data-cy="adsb-system"]').should('have.class', 'status-healthy')
    })

    // Test system alerts
    cy.get('[data-cy="system-alerts"]').should('be.visible')

    // Simulate system alert
    cy.get('[data-cy="simulate-system-alert"]').click()
    cy.get('[data-cy="system-alert-notification"]').should('be.visible')
    cy.get('[data-cy="alert-severity"]').should('be.visible')
    cy.get('[data-cy="alert-description"]').should('be.visible')
  })

  it('should handle shift handover procedures', () => {
    // Navigate to shift management
    cy.get('[data-cy="shift-management"]').click()

    // Verify shift information
    cy.get('[data-cy="current-shift"]').should('be.visible')
    cy.get('[data-cy="shift-duration"]').should('be.visible')
    cy.get('[data-cy="active-sectors"]').should('be.visible')

    // Test shift handover preparation
    cy.get('[data-cy="prepare-handover"]').click()
    cy.get('[data-cy="handover-summary"]').should('be.visible')
    cy.get('[data-cy="active-flights-summary"]').should('be.visible')
    cy.get('[data-cy="pending-clearances"]').should('be.visible')
    cy.get('[data-cy="system-status-summary"]').should('be.visible')

    // Test handover to next controller
    cy.get('[data-cy="select-next-controller"]').select('Controller 2')
    cy.get('[data-cy="initiate-handover"]').click()

    // Verify handover process
    cy.get('[data-cy="handover-progress"]').should('be.visible')
    cy.contains('Handover initiated').should('be.visible')

    // Complete handover
    cy.get('[data-cy="complete-handover"]').click()
    cy.contains('Shift handover completed').should('be.visible')
  })

  it('should provide mobile ATC controller interface', () => {
    // Switch to mobile view
    cy.viewport('iphone-x')

    // Verify mobile interface loads
    cy.get('[data-cy="mobile-atc-dashboard"]').should('be.visible')
    cy.get('[data-cy="mobile-flight-list"]').should('be.visible')

    // Test touch interactions
    cy.get('[data-cy="mobile-flight-item"]').first().click()
    cy.get('[data-cy="mobile-flight-details"]').should('be.visible')

    // Test mobile clearance issuance
    cy.get('[data-cy="mobile-issue-clearance"]').click()
    cy.get('[data-cy="mobile-clearance-form"]').should('be.visible')
    cy.get('[data-cy="mobile-clearance-type"]').select('taxi')
    cy.get('[data-cy="mobile-submit-clearance"]').click()

    // Verify mobile notification
    cy.get('[data-cy="mobile-notification"]').should('be.visible')
    cy.contains('Clearance issued').should('be.visible')

    // Test mobile gesture controls
    cy.get('[data-cy="mobile-map"]').swipe('right')
    cy.get('[data-cy="mobile-sector-view"]').should('be.visible')
  })
})
