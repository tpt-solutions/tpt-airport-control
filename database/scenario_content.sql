-- Airport Operations Simulator - Scenario Content Library
-- 50+ Realistic Scenarios for Educational Gameplay

-- Insert comprehensive scenario library
INSERT INTO demo_scenarios (scenario_id, title, description, category, difficulty, estimated_time, max_score, objectives, required_roles, prerequisites, config) VALUES

-- BEGINNER SCENARIOS (1-10)
('beginner_flight_processing', 'Basic Flight Processing', 'Learn the fundamentals of flight operations and passenger handling', 'general', 'beginner', 300, 500,
 '["Process 10 arriving flights", "Handle 5 boarding calls", "Maintain passenger flow"]',
 '["dispatcher", "passenger_services_rep"]', '[]',
 '{"tutorial_mode": true, "hints_enabled": true, "auto_complete": false}'),

('gate_management_basics', 'Gate Management Fundamentals', 'Master basic gate assignments and passenger flow control', 'general', 'beginner', 240, 400,
 '["Assign 8 gates correctly", "Handle 3 gate changes", "Minimize passenger delays"]',
 '["dispatcher", "passenger_services_rep"]', '[]',
 '{"focus_area": "gate_management", "time_pressure": "low"}'),

('baggage_basics', 'Baggage Handling Introduction', 'Learn baggage routing and lost item procedures', 'general', 'beginner', 360, 450,
 '["Route 15 bags correctly", "Handle 2 lost items", "Maintain baggage flow"]',
 '["baggage_handler", "passenger_services_rep"]', '[]',
 '{"baggage_volume": "low", "error_rate": "minimal"}'),

('security_screening_intro', 'Security Screening Basics', 'Introduction to passenger and baggage security procedures', 'security', 'beginner', 420, 550,
 '["Screen 20 passengers", "Identify 3 security threats", "Maintain security protocols"]',
 '["security_officer", "passenger_services_rep"]', '[]',
 '{"threat_level": "low", "training_mode": true}'),

('customs_processing_101', 'Customs Processing Fundamentals', 'Basic customs declarations and passenger processing', 'customs', 'beginner', 300, 400,
 '["Process 12 international passengers", "Review 8 declarations", "Handle 2 special cases"]',
 '["customs_officer", "passenger_services_rep"]', '[]',
 '{"passenger_volume": "low", "complexity": "basic"}'),

('cargo_loading_basics', 'Cargo Loading Fundamentals', 'Learn cargo handling and aircraft loading procedures', 'cargo', 'beginner', 480, 600,
 '["Load cargo on 3 aircraft", "Balance weight distribution", "Meet departure times"]',
 '["cargo_manager", "dispatcher"]', '[]',
 '{"cargo_types": ["standard", "perishable"], "time_constraints": "moderate"}'),

('emergency_response_intro', 'Emergency Response Basics', 'Introduction to basic emergency procedures and coordination', 'emergency', 'beginner', 360, 500,
 '["Respond to 2 minor emergencies", "Coordinate with teams", "Ensure passenger safety"]',
 '["emergency_coordinator", "security_officer"]', '[]',
 '{"emergency_types": ["medical", "lost_child"], "severity": "low"}'),

('commercial_operations_intro', 'Retail Operations Basics', 'Learn concession management and revenue optimization', 'commercial', 'beginner', 300, 350,
 '["Manage 5 retail outlets", "Optimize staffing", "Meet revenue targets"]',
 '["commercial_manager"]', '[]',
 '{"business_hours": "peak", "customer_volume": "moderate"}'),

('infrastructure_monitoring', 'Building Systems Monitoring', 'Monitor and maintain airport infrastructure systems', 'infrastructure', 'beginner', 420, 450,
 '["Monitor 6 building systems", "Respond to 3 alerts", "Maintain optimal conditions"]',
 '["infrastructure_manager"]', '[]',
 '{"system_types": ["hvac", "lighting", "security"], "alert_frequency": "low"}'),

('passenger_services_fundamentals', 'Passenger Services Essentials', 'Handle passenger inquiries and special assistance requests', 'general', 'beginner', 360, 400,
 '["Assist 15 passengers", "Handle 4 special requests", "Maintain satisfaction above 80%"]',
 '["passenger_services_rep"]', '[]',
 '{"service_types": ["information", "assistance", "complaints"], "complexity": "basic"}'),

-- INTERMEDIATE SCENARIOS (11-25)
('peak_hour_challenge', 'Peak Hour Operations', 'Manage high-volume operations during peak travel times', 'general', 'intermediate', 600, 800,
 '["Process 25 flights", "Handle 8 gate changes", "Maintain on-time performance above 85%"]',
 '["dispatcher", "controller"]', '["beginner_flight_processing"]',
 '{"peak_load": "high", "time_pressure": "high", "random_events": true}'),

('weather_disruption', 'Weather Disruption Management', 'Handle flight delays and diversions due to weather conditions', 'weather', 'intermediate', 720, 900,
 '["Divert 6 flights safely", "Rebook 150 passengers", "Communicate with airlines effectively"]',
 '["controller", "dispatcher", "emergency_coordinator"]', '["gate_management_basics"]',
 '{"weather_conditions": ["thunderstorm", "fog"], "disruption_level": "moderate"}'),

('security_incident_response', 'Security Incident Response', 'Respond to security threats and coordinate emergency protocols', 'security', 'intermediate', 540, 750,
 '["Secure incident area within 3 minutes", "Evacuate 200 passengers", "Coordinate with authorities"]',
 '["security_officer", "emergency_coordinator"]', '["security_screening_intro"]',
 '{"threat_type": "suspicious_package", "passenger_count": 200, "time_critical": true}'),

('international_flight_wave', 'International Flight Wave', 'Process high volume of international arrivals efficiently', 'customs', 'intermediate', 480, 650,
 '["Process 80 international passengers", "Review 25 customs declarations", "Handle 5 high-risk cases"]',
 '["customs_officer", "security_officer"]', '["customs_processing_101"]',
 '{"flight_volume": "high", "risk_profiles": ["low", "medium", "high"]}'),

('perishable_cargo_crisis', 'Perishable Cargo Emergency', 'Handle temperature-sensitive cargo requiring immediate attention', 'cargo', 'intermediate', 600, 700,
 '["Identify 12 affected containers", "Coordinate alternative routing", "Minimize losses under $50K"]',
 '["cargo_manager", "infrastructure_manager"]', '["cargo_loading_basics"]',
 '{"cargo_value": 200000, "temperature_sensitivity": "high", "time_window": 120}'),

('medical_emergency', 'Medical Emergency Response', 'Coordinate response to passenger medical emergency', 'emergency', 'intermediate', 480, 600,
 '["Activate emergency protocols", "Coordinate medical response", "Manage passenger communications"]',
 '["emergency_coordinator", "passenger_services_rep"]', '["emergency_response_intro"]',
 '{"medical_severity": "serious", "passenger_impact": "high", "coordination_required": true}'),

('vip_arrival_protocol', 'VIP Arrival Protocol', 'Manage high-profile passenger arrival with special requirements', 'general', 'intermediate', 420, 550,
 '["Coordinate security detail", "Manage media presence", "Ensure privacy protocols"]',
 '["security_officer", "passenger_services_rep", "commercial_manager"]', '["passenger_services_fundamentals"]',
 '{"vip_profile": "celebrity", "media_presence": "high", "security_level": "elevated"}'),

('baggage_system_failure', 'Baggage System Failure', 'Handle complete baggage handling system breakdown', 'general', 'intermediate', 660, 750,
 '["Implement manual procedures", "Track 100+ bags manually", "Communicate with passengers"]',
 '["baggage_handler", "passenger_services_rep", "dispatcher"]', '["baggage_basics"]',
 '{"system_down": true, "manual_capacity": "limited", "passenger_communication": "critical"}'),

('drone_incident', 'UAV Airspace Violation', 'Handle unauthorized drone activity in controlled airspace', 'drones', 'intermediate', 540, 650,
 '["Detect drone intrusion", "Coordinate interception", "Ensure flight safety"]',
 '["drone_operator", "controller", "security_officer"]', '["infrastructure_monitoring"]',
 '{"drone_type": "commercial", "altitude": "restricted", "response_time": "critical"}'),

('commercial_peak_season', 'Holiday Shopping Rush', 'Manage retail operations during peak holiday shopping season', 'commercial', 'intermediate', 480, 550,
 '["Optimize staff scheduling", "Manage inventory levels", "Maximize revenue during peak hours"]',
 '["commercial_manager"]', '["commercial_operations_intro"]',
 '{"season": "holiday", "customer_volume": "peak", "revenue_target": 50000}'),

('infrastructure_power_failure', 'Power System Failure', 'Respond to airport-wide power outage and backup systems', 'infrastructure', 'intermediate', 600, 700,
 '["Activate backup generators", "Prioritize critical systems", "Minimize operational disruption"]',
 '["infrastructure_manager", "emergency_coordinator"]', '["infrastructure_monitoring"]',
 '{"power_outage": "complete", "backup_capacity": "limited", "critical_systems": ["lighting", "communications", "security"]}'),

('crew_scheduling_crisis', 'Crew Scheduling Emergency', 'Handle sudden crew unavailability and flight cancellations', 'general', 'intermediate', 720, 800,
 '["Reassign available crew", "Cancel non-critical flights", "Communicate with passengers"]',
 '["dispatcher", "passenger_services_rep"]', '["peak_hour_challenge"]',
 '{"crew_shortage": "severe", "flight_impacts": "multiple", "passenger_notifications": "mass"}'),

('fuel_contamination', 'Fuel Contamination Incident', 'Respond to aircraft fuel contamination and safety concerns', 'general', 'intermediate', 480, 600,
 '["Ground affected aircraft", "Coordinate fuel testing", "Manage passenger rebooking"]',
 '["dispatcher", "cargo_manager", "emergency_coordinator"]', '["cargo_loading_basics"]',
 '{"contamination_type": "chemical", "aircraft_affected": 3, "safety_risk": "high"}'),

('lost_child_protocol', 'Lost Child Emergency', 'Handle missing child report and family reunification', 'emergency', 'intermediate', 360, 450,
 '["Activate lost child protocol", "Search airport facilities", "Reunite child with family"]',
 '["security_officer", "passenger_services_rep"]', '["emergency_response_intro"]',
 '{"child_age": 6, "search_area": "terminal_a", "emotional_state": "distressed"}'),

-- ADVANCED SCENARIOS (26-40)
('hurricane_evacuation', 'Hurricane Evacuation Protocol', 'Coordinate airport evacuation during major weather emergency', 'emergency', 'advanced', 900, 1200,
 '["Evacuate 1000+ passengers", "Secure all aircraft", "Coordinate with emergency services"]',
 '["emergency_coordinator", "controller", "security_officer"]', '["weather_disruption", "security_incident_response"]',
 '{"storm_category": 4, "wind_speed": 140, "evacuation_time": 60}'),

('cyber_security_breach', 'Cyber Security Breach', 'Respond to airport network compromise and system vulnerabilities', 'security', 'advanced', 720, 950,
 '["Isolate compromised systems", "Implement backup procedures", "Coordinate with cybersecurity team"]',
 '["security_officer", "infrastructure_manager", "ai_analyst"]', '["infrastructure_power_failure"]',
 '{"breach_type": "ransomware", "systems_affected": ["checkin", "gate", "baggage"], "data_risk": "high"}'),

('mass_casualty_incident', 'Mass Casualty Incident', 'Handle major accident with multiple injuries and fatalities', 'emergency', 'advanced', 1200, 1500,
 '["Activate mass casualty protocol", "Triage 50+ casualties", "Manage media and family communications"]',
 '["emergency_coordinator", "passenger_services_rep", "security_officer"]', '["medical_emergency", "lost_child_protocol"]',
 '{"casualties": 75, "fatalities": 8, "media_presence": "intense", "family_notifications": "mass"}'),

('airline_bankruptcy', 'Major Airline Bankruptcy', 'Handle sudden airline bankruptcy affecting multiple flights', 'general', 'advanced', 960, 1100,
 '["Cancel 15 affected flights", "Rebook 800+ passengers", "Manage passenger compensation"]',
 '["dispatcher", "passenger_services_rep", "commercial_manager"]', '["crew_scheduling_crisis", "international_flight_wave"]',
 '{"affected_flights": 15, "passengers_impacted": 1200, "compensation_required": true}'),

('active_shooter_scenario', 'Active Shooter Incident', 'Respond to active shooter threat in terminal area', 'security', 'advanced', 600, 1000,
 '["Lock down affected areas", "Evacuate safe zones", "Coordinate SWAT response"]',
 '["security_officer", "emergency_coordinator", "controller"]', '["security_incident_response"]',
 '{"threat_location": "terminal_b", "hostage_situation": true, "civilian_casualties": "potential"}'),

('fuel_shortage_crisis', 'National Fuel Shortage', 'Manage operations during nationwide fuel supply crisis', 'general', 'advanced', 1080, 1300,
 '["Prioritize fuel allocation", "Cancel non-essential flights", "Communicate with airlines"]',
 '["dispatcher", "cargo_manager", "commercial_manager"]', '["fuel_contamination", "crew_scheduling_crisis"]',
 '{"fuel_availability": "20%", "critical_flights_only": true, "economic_impact": "severe"}'),

('pandemic_outbreak', 'Pandemic Health Crisis', 'Implement health protocols during infectious disease outbreak', 'emergency', 'advanced', 840, 1000,
 '["Implement quarantine procedures", "Screen arriving passengers", "Manage isolation areas"]',
 '["emergency_coordinator", "passenger_services_rep", "infrastructure_manager"]', '["medical_emergency"]',
 '{"infection_rate": "high", "quarantine_zones": 3, "international_flights": "suspended"}'),

('terrorism_threat', 'Terrorism Threat Assessment', 'Evaluate and respond to credible terrorism intelligence', 'security', 'advanced', 780, 1100,
 '["Assess threat credibility", "Implement security protocols", "Coordinate with federal agencies"]',
 '["security_officer", "emergency_coordinator", "controller"]', '["active_shooter_scenario", "cyber_security_breach"]',
 '{"threat_level": "severe", "target_specific": true, "intelligence_source": "credible"}'),

('earthquake_response', 'Major Earthquake Response', 'Handle airport operations following major seismic event', 'emergency', 'advanced', 1020, 1250,
 '["Assess structural damage", "Evacuate unsafe areas", "Coordinate rescue operations"]',
 '["emergency_coordinator", "infrastructure_manager", "security_officer"]', '["infrastructure_power_failure", "hurricane_evacuation"]',
 '{"magnitude": 7.2, "structural_damage": "extensive", "aftershocks": "expected"}'),

('wildfire_threat', 'Wildfire Evacuation', 'Respond to approaching wildfire threatening airport operations', 'emergency', 'advanced', 720, 900,
 '["Monitor fire progression", "Implement evacuation procedures", "Protect aircraft assets"]',
 '["emergency_coordinator", "infrastructure_manager", "dispatcher"]', '["hurricane_evacuation"]',
 '{"fire_distance": 5, "wind_direction": "towards_airport", "evacuation_priority": "aircraft"}'),

-- EXPERT SCENARIOS (41-50)
('multi_modal_crisis', 'Multi-Modal Transportation Crisis', 'Handle simultaneous disruptions across air, ground, and rail transport', 'general', 'expert', 1500, 2000,
 '["Coordinate intermodal transport", "Manage 2000+ stranded passengers", "Balance resource allocation"]',
 '["dispatcher", "emergency_coordinator", "passenger_services_rep"]', '["airline_bankruptcy", "fuel_shortage_crisis"]',
 '{"transport_modes": ["air", "bus", "rail"], "stranded_passengers": 2500, "resource_constraints": "severe"}'),

('international_diplomatic_crisis', 'International Diplomatic Incident', 'Manage diplomatic tensions affecting international flights', 'general', 'expert', 1200, 1600,
 '["Navigate diplomatic protocols", "Secure diplomatic flights", "Manage international media"]',
 '["controller", "security_officer", "passenger_services_rep"]', '["vip_arrival_protocol", "international_flight_wave"]',
 '{"involved_countries": 3, "diplomatic_level": "ambassador", "media_attention": "global"}'),

('space_launch_coordination', 'Commercial Space Launch', 'Coordinate airport operations during commercial space launch', 'general', 'expert', 900, 1200,
 '["Manage airspace restrictions", "Coordinate with space agency", "Handle VIP and media presence"]',
 '["controller", "security_officer", "commercial_manager"]', '["drone_incident", "vip_arrival_protocol"]',
 '{"launch_window": 30, "airspace_radius": 50, "spectator_count": 10000}'),

('arctic_rescue_operation', 'Arctic Search and Rescue', 'Coordinate international SAR operation in arctic conditions', 'emergency', 'expert', 1800, 2200,
 '["Coordinate international teams", "Manage extreme weather operations", "Handle diplomatic coordination"]',
 '["emergency_coordinator", "controller", "dispatcher"]', '["hurricane_evacuation", "earthquake_response"]',
 '{"temperature": -40, "visibility": "zero", "international_teams": 5}'),

('presidential_visit', 'Presidential State Visit', 'Manage airport operations during presidential arrival', 'security', 'expert', 1440, 1800,
 '["Implement presidential security", "Manage motorcade coordination", "Handle international delegation"]',
 '["security_officer", "dispatcher", "passenger_services_rep"]', '["vip_arrival_protocol", "terrorism_threat"]',
 '{"security_detail": 500, "motorcade_size": 25, "international_delegation": 20}'),

('climate_change_adaptation', 'Climate Change Emergency', 'Respond to extreme weather patterns caused by climate change', 'emergency', 'expert', 1320, 1700,
 '["Implement climate adaptation protocols", "Manage prolonged weather events", "Coordinate long-term recovery"]',
 '["emergency_coordinator", "infrastructure_manager", "sustainability_officer"]', '["pandemic_outbreak", "wildfire_threat"]',
 '{"weather_duration": 72, "infrastructure_damage": "widespread", "recovery_timeline": 30}'),

('cyber_warfare_attack', 'State-Sponsored Cyber Attack', 'Defend against coordinated cyber warfare targeting critical infrastructure', 'security', 'expert', 1680, 2100,
 '["Isolate cyber threats", "Maintain critical operations", "Coordinate with national cybersecurity"]',
 '["security_officer", "infrastructure_manager", "ai_analyst"]', '["cyber_security_breach", "power_grid_failure"]',
 '{"attack_vector": "multi_stage", "critical_systems": "compromised", "nation_state_actor": true}'),

('global_pandemic_peak', 'Global Pandemic Peak', 'Manage airport during peak of global health crisis', 'emergency', 'expert', 1920, 2400,
 '["Implement maximum health protocols", "Manage international travel restrictions", "Coordinate global health response"]',
 '["emergency_coordinator", "passenger_services_rep", "infrastructure_manager"]', '["pandemic_outbreak", "international_flight_wave"]',
 '{"infection_peak": true, "travel_bans": "global", "vaccine_distribution": "ongoing"}'),

('meteorological_perfect_storm', 'Perfect Storm Scenario', 'Handle multiple simultaneous weather systems creating perfect storm', 'emergency', 'expert', 2100, 2800,
 '["Monitor multiple weather systems", "Make critical operational decisions", "Balance safety vs operations"]',
 '["controller", "emergency_coordinator", "dispatcher"]', '["hurricane_evacuation", "earthquake_response", "wildfire_threat"]',
 '{"weather_systems": ["hurricane", "tornado", "flooding"], "simultaneous_events": true, "decision_pressure": "extreme"}'),

('ultimate_crisis_management', 'Ultimate Crisis Management', 'Handle airport operations during maximum complexity scenario', 'general', 'expert', 2400, 3000,
 '["Manage all crisis types simultaneously", "Coordinate all airport departments", "Make life-critical decisions"]',
 '["emergency_coordinator", "controller", "security_officer", "dispatcher", "infrastructure_manager"]', '["multi_modal_crisis", "meteorological_perfect_storm"]',
 '{"crisis_types": ["security", "weather", "medical", "cyber", "structural"], "stakeholders": "maximum", "consequences": "global"}')

ON CONFLICT (scenario_id) DO NOTHING;

-- Insert scenario events for dynamic gameplay
INSERT INTO demo_scenario_events (scenario_id, event_type, event_data, trigger_time, sequence_order) VALUES

-- Morning Rush events
('morning_rush', 'flight_arrival', '{"flight": "AA101", "gate": "A1", "passengers": 150}', 60, 1),
('morning_rush', 'flight_departure', '{"flight": "UA202", "gate": "B2", "passengers": 140}', 120, 2),
('morning_rush', 'gate_change', '{"flight": "DL303", "old_gate": "C3", "new_gate": "C5", "reason": "maintenance"}', 180, 3),
('morning_rush', 'passenger_complaint', '{"type": "delay", "severity": "medium", "gate": "A1"}', 300, 4),
('morning_rush', 'flight_arrival', '{"flight": "SW404", "gate": "D4", "passengers": 120}', 420, 5),
('morning_rush', 'gate_change', '{"flight": "AA505", "old_gate": "A2", "new_gate": "A4", "reason": "equipment"}', 480, 6),
('morning_rush', 'crew_issue', '{"type": "late_crew", "flight": "UA606", "delay": 45}', 600, 7),
('morning_rush', 'baggage_delay', '{"flight": "DL707", "delay": 30, "bags_affected": 25}', 720, 8),
('morning_rush', 'passenger_complaint', '{"type": "gate_change", "severity": "high", "gate": "C5"}', 780, 9),

-- Weather Diversion events
('weather_diversion', 'weather_alert', '{"type": "thunderstorm", "severity": "severe", "winds": 65}', 30, 1),
('weather_diversion', 'flight_diversion_request', '{"flight": "AA101", "reason": "weather", "alternative": "ORD"}', 60, 2),
('weather_diversion', 'passenger_panic', '{"gate": "A1", "passengers": 150, "severity": "moderate"}', 120, 3),
('weather_diversion', 'flight_diversion_request', '{"flight": "UA202", "reason": "weather", "alternative": "MDW"}', 180, 4),
('weather_diversion', 'airline_call', '{"airline": "American", "issue": "diversion_coordination", "urgency": "high"}', 240, 5),
('weather_diversion', 'flight_diversion_request', '{"flight": "DL303", "reason": "weather", "alternative": "IND"}', 300, 6),
('weather_diversion', 'passenger_rebooking', '{"passengers": 80, "gate": "A1", "priority": "high"}', 360, 7),
('weather_diversion', 'flight_diversion_request', '{"flight": "SW404", "reason": "weather", "alternative": "STL"}', 420, 8),
('weather_diversion', 'airline_call', '{"airline": "United", "issue": "crew_reassignment", "urgency": "critical"}', 480, 9),
('weather_diversion', 'passenger_rebooking', '{"passengers": 120, "gate": "B2", "priority": "medium"}', 540, 10),
('weather_diversion', 'flight_diversion_request', '{"flight": "AA505", "reason": "weather", "alternative": "CLE"}', 600, 11),
('weather_diversion', 'emergency_meeting', '{"topic": "weather_response_coordination", "attendees": ["dispatcher", "controller", "emergency_coordinator"]}', 660, 12),
('weather_diversion', 'passenger_rebooking', '{"passengers": 100, "gate": "C3", "priority": "low"}', 720, 13),

-- Security Threat events
('security_threat', 'security_alert', '{"type": "suspicious_package", "location": "Terminal A", "size": "backpack"}', 30, 1),
('security_threat', 'evacuation_order', '{"area": "Terminal A", "passengers": 300, "reason": "security"}', 60, 2),
('security_threat', 'police_notification', '{"response_time": 180, "units": 3}', 90, 3),
('security_threat', 'passenger_panic', '{"area": "Terminal A", "severity": "high", "injuries": 2}', 120, 4),
('security_threat', 'security_teams_deployed', '{"teams": 3, "area": "Terminal A", "equipment": "bomb_suit"}', 180, 5),
('security_threat', 'evacuation_progress', '{"evacuated": 200, "total": 500, "time_elapsed": 120}', 240, 6),
('security_threat', 'bomb_squad_arrival', '{"assessment_time": 300, "risk_level": "unknown"}', 300, 7),
('security_threat', 'media_inquiry', '{"outlets": 5, "response_required": true, "tone": "urgent"}', 360, 8),
('security_threat', 'evacuation_progress', '{"evacuated": 350, "total": 500, "time_elapsed": 240}', 420, 9),
('security_threat', 'false_alarm_confirmed', '{"threat_level": "none", "package_contents": "personal_items"}', 480, 10),
('security_threat', 'reopen_terminal', '{"area": "Terminal A", "inspection_complete": true, "safe": true}', 540, 11),
('security_threat', 'passenger_reentry', '{"passengers": 500, "controlled_entry": true, "screening": "enhanced"}', 600, 12),

-- Cargo Crisis events
('cargo_crisis', 'temperature_alert', '{"container": "C001", "temperature": 8, "threshold": 2, "contents": "vaccines"}', 30, 1),
('cargo_crisis', 'cargo_inspection', '{"container": "C001", "contents": "vaccines", "value": 50000, "urgency": "critical"}', 60, 2),
('cargo_crisis', 'temperature_alert', '{"container": "C002", "temperature": 6, "threshold": 2, "contents": "organs"}', 90, 3),
('cargo_crisis', 'supplier_contact', '{"supplier": "PharmaCorp", "urgency": "critical", "response_time": 30}', 120, 4),
('cargo_crisis', 'alternative_routing', '{"container": "C001", "new_route": "express_air", "cost_increase": 2000, "time_saved": 6}', 150, 5),
('cargo_crisis', 'temperature_alert', '{"container": "C003", "temperature": 7, "threshold": 2, "contents": "blood_products"}', 180, 6),
('cargo_crisis', 'cargo_inspection', '{"container": "C002", "contents": "organs", "value": 75000, "shelf_life": 4}', 210, 7),
('cargo_crisis', 'insurance_claim', '{"container": "C001", "estimated_loss": 10000, "coverage": 80}', 240, 8),
('cargo_crisis', 'alternative_routing', '{"container": "C002", "new_route": "priority_ground", "cost_increase": 1500, "time_saved": 8}', 270, 9),
('cargo_crisis', 'temperature_alert', '{"container": "C004", "temperature": 5, "threshold": 2, "contents": "medications"}', 300, 10),
('cargo_crisis', 'supplier_contact', '{"supplier": "BioTech", "urgency": "high", "response_time": 45}', 330, 11),
('cargo_crisis', 'cargo_inspection', '{"container": "C003", "contents": "blood_products", "value": 25000, "shelf_life": 21}', 360, 12),

-- VIP Handling events
('vip_handling', 'vip_arrival_alert', '{"passenger": "John Smith", "title": "CEO", "company": "TechCorp", "security_level": "high"}', 30, 1),
('vip_handling', 'security_clearance', '{"level": "high", "escort_required": true, "advance_team": 4}', 60, 2),
('vip_handling', 'special_services_request', '{"service": "private_lounge", "duration": 120, "attendees": 8}', 90, 3),
('vip_handling', 'media_presence', '{"photographers": 5, "reporters": 3, "networks": ["CNN", "Fox", "NBC"]}', 120, 4),
('vip_handling', 'privacy_concerns', '{"paparazzi_alert": true, "crowd_control_needed": true, "secure_route": true}', 150, 5),
('vip_handling', 'ground_transport', '{"vehicle": "limousine", "security_detail": 2, "route": "secure"}', 180, 6),
('vip_handling', 'flight_status_update', '{"flight": "AA001", "status": "on_time", "gate": "VIP1", "aircraft": "G650ER"}', 210, 7),
('vip_handling', 'press_release', '{"coordination_required": true, "timing": "post_arrival", "distribution": "immediate"}', 240, 8),
('vip_handling', 'security_briefing', '{"threat_assessment": "low", "additional_measures": false, "contingency": "ready"}', 270, 9),
('vip_handling', 'arrival_procedure', '{"private_jetbridge": true, "customs_fast_track": true, "ground_crew": "specialized"}', 300, 10)

ON CONFLICT (scenario_id, sequence_order) DO NOTHING;
