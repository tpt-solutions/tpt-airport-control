# Emergency Notification and Medical Services Integrations

This document describes the emergency notification systems and medical services APIs integration for the TPT Flight Control system.

## Overview

The emergency management system integrates with external emergency notification systems and medical services to provide comprehensive crisis response capabilities. The system automatically triggers appropriate notifications and medical assistance based on incident severity and type.

## Components

### 1. Emergency Notification Integration (`emergency-notification-integration.php`)

**Features:**
- Multi-channel emergency notifications (SMS, email, internal paging, mobile alerts, public address)
- Targeted alerts to specific personnel groups
- Automatic notifications for critical incidents
- Real-time delivery status tracking

**Supported Channels:**
- **Internal Paging System**: Airport-wide paging announcements
- **SMS Gateway**: Direct SMS alerts to personnel phones
- **Email System**: HTML-formatted emergency emails
- **Mobile Alert System**: WEA/EU-ALERT compatible mobile notifications
- **Public Address System**: Voice announcements over airport speakers

**API Endpoints:**
```
POST /api/emergency/alerts/targeted
- Send targeted alerts to specific groups
- Body: { "alert_title": "...", "target_groups": ["medical_team", "security"] }

POST /api/emergency/integrations/test?type=notifications
- Test notification system connectivity
```

### 2. Medical Services Integration (`medical-services-integration.php`)

**Features:**
- Emergency medical assistance coordination
- Hospital and ambulance service integration
- Medical supply chain management
- Telemedicine consultation platform
- Medical device monitoring
- Pharmacy system integration

**Supported Services:**
- **EMS (Emergency Medical Services)**: Ambulance dispatch and coordination
- **Hospital Systems**: Patient admission and specialist notification
- **Medical Supply Chain**: Automated supply requests and delivery
- **Telemedicine Platform**: Remote medical consultations
- **Medical Device Monitoring**: Real-time equipment status tracking
- **Pharmacy Systems**: Medication ordering and inventory management

**API Endpoints:**
```
POST /api/emergency/medical/supplies
- Request medical supplies
- Body: { "supplies": {"bandages": 50, "medication": 10} }

POST /api/emergency/medical/telemedicine
- Initiate telemedicine consultation
- Body: { "consultation_data": {"medical_issue": "...", "severity": "urgent"} }

POST /api/emergency/medical/devices
- Monitor medical device status
- Body: { "query": {"location": "airport", "device_types": ["defibrillator"]} }

POST /api/emergency/integrations/test?type=medical
- Test medical services connectivity
```

## Integration Workflow

### Automatic Incident Response

1. **Incident Reported**: When an incident is reported via `/api/emergency/incidents/report`
2. **Severity Assessment**: System evaluates incident severity and type
3. **Automatic Actions**:
   - Critical incidents → Emergency notifications sent to all personnel
   - Medical incidents → Medical assistance automatically requested
   - Fire/accident incidents → Both notification and medical services activated

### Manual Integration Control

Operators can manually trigger integrations:
- Send targeted alerts to specific teams
- Request additional medical supplies
- Initiate telemedicine consultations
- Monitor medical device status

## Configuration

### Environment Variables

```bash
# Emergency Notification Systems
PAGING_SYSTEM_API_URL=https://api.pagingsystem.com
PAGING_SYSTEM_API_KEY=your_paging_api_key

SMS_GATEWAY_API_URL=https://api.smsgateway.com
SMS_GATEWAY_API_KEY=your_sms_api_key

EMAIL_SYSTEM_API_URL=https://api.emailsystem.com
EMAIL_SYSTEM_API_KEY=your_email_api_key

MOBILE_ALERT_API_URL=https://api.mobilealert.com
MOBILE_ALERT_API_KEY=your_mobile_api_key

PUBLIC_ADDRESS_API_URL=https://api.publicaddress.com
PUBLIC_ADDRESS_API_KEY=your_pa_api_key

# Medical Services Systems
EMS_API_URL=https://api.ems-service.com
EMS_API_KEY=your_ems_api_key

HOSPITAL_API_URL=https://api.hospital-system.com
HOSPITAL_API_KEY=your_hospital_api_key

PHARMACY_API_URL=https://api.pharmacy-system.com
PHARMACY_API_KEY=your_pharmacy_api_key

TELEMEDICINE_API_URL=https://api.telemedicine-platform.com
TELEMEDICINE_API_KEY=your_telemedicine_api_key

MEDICAL_DEVICES_API_URL=https://api.medical-devices.com
MEDICAL_DEVICES_API_KEY=your_devices_api_key
```

### Database Configuration

The system uses PostgreSQL for data persistence. Required tables:
- `incident_reports`: Emergency incident tracking
- `emergency_alerts`: Alert delivery records
- `emergency_communications`: Communication logs
- `emergency_resources`: Resource allocation tracking
- `emergency_evacuations`: Evacuation records
- `emergency_audit_logs`: Audit trail

## Testing

### Integration Tests

Run the integration test script:
```bash
php integrations/test-emergency-integrations.php
```

This will test:
- Emergency notification system connectivity
- Medical services API availability
- Combined integration functionality

### API Testing

Test individual endpoints:
```bash
# Test notification integration
curl -X POST /api/emergency/integrations/test \
  -H "Content-Type: application/json" \
  -d '{"type": "notifications"}'

# Test medical services integration
curl -X POST /api/emergency/integrations/test \
  -H "Content-Type: application/json" \
  -d '{"type": "medical"}'
```

## Error Handling

The system includes comprehensive error handling:
- **API Failures**: Automatic retry with exponential backoff
- **Service Unavailability**: Fallback to alternative communication channels
- **Partial Failures**: Continue with available services, log failures
- **Timeout Handling**: Configurable timeouts with graceful degradation

## Monitoring and Logging

- All integration activities are logged with timestamps
- Real-time monitoring of service availability
- Performance metrics collection
- Alert delivery confirmation tracking
- Audit trail for compliance

## Security Considerations

- API keys stored securely in environment variables
- HTTPS-only communication with external services
- Input validation and sanitization
- Rate limiting to prevent abuse
- Authentication and authorization checks

## Future Enhancements

- Integration with additional notification platforms
- Advanced medical device integration (IoT)
- AI-powered incident severity assessment
- Predictive maintenance for medical equipment
- Multi-language support for international airports

## Support

For technical support or integration issues:
1. Check the integration test results
2. Review system logs in `backend/logs/`
3. Verify API credentials and network connectivity
4. Contact the development team with error details
