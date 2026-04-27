# Disaster Recovery Runbook
# TPT Flight Control System
# Version 1.0 | Effective Date: 2026-04-24

---

## 1. OVERVIEW

This document defines formal disaster recovery procedures, recovery objectives, and step-by-step response protocols for the TPT Flight Control System.

### 1.1 Recovery Objectives
| Metric | Definition | Target |
|--------|------------|--------|
| RTO (Recovery Time Objective) | Maximum allowable system downtime | **< 5 minutes** |
| RPO (Recovery Point Objective) | Maximum allowable data loss | **< 1 second** |
| MTTR (Mean Time To Recovery) | Average time to restore service | **< 2 minutes** |
| Failover Time | Automatic cluster failover | **< 500ms** |

### 1.2 Severity Classification
| Severity | Description | Response Time | Escalation Threshold |
|----------|-------------|---------------|----------------------|
| SEV 0 | Total system outage, safety systems offline | Immediate | 5 minutes |
| SEV 1 | Critical subsystem failure, partial outage | 15 minutes | 30 minutes |
| SEV 2 | Degraded performance, non-critical failure | 1 hour | 4 hours |
| SEV 3 | Minor issue, cosmetic bug | 24 hours | 72 hours |

---

## 2. DISASTER SCENARIOS & RESPONSE PROCEDURES

### 2.1 Total Cluster Failure
**Trigger**: All 3 Raft nodes offline, quorum lost

1.  **Immediate Actions (0-1 minute)**:
    - Activate failsafe mode automatically through Watchdog Monitor
    - All aircraft vectors are held at current positions
    - ATC controllers are notified with audio + visual alerts
    - System enters degraded manual operation mode

2.  **Recovery Procedure**:
    ```bash
    # Step 1: Verify node health
    curl http://node1:8000/health.php
    curl http://node2:8000/health.php
    curl http://node3:8000/health.php

    # Step 2: Force leader election if quorum cannot be established
    php backend/services/RaftConsensusService.php --force-recovery

    # Step 3: Restore from latest WAL checkpoint
    php backend/services/WriteAheadLog.php --restore --latest

    # Step 4: Verify safety boundaries are reloaded
    php backend/services/SafetyBoundaryEngine.php --verify
    ```

3.  **Post Recovery Validation**:
    - Confirm sensor health scores > 95%
    - Verify at least 2 independent sensors per track
    - Run full safety interlock validation
    - Confirm all alerts are acknowledged

### 2.2 Database Failure
**Trigger**: PostgreSQL primary node unavailable

1.  Automatic failover to replica occurs within 2 seconds
2.  Write operations are queued during failover
3.  WAL ensures zero transaction loss
4.  Manual failback procedure executed during next maintenance window

### 2.3 Sensor Network Outage
**Trigger**: > 30% of sensors reporting failed status

1.  System automatically switches to multi-sensor fusion fallback mode
2.  Only tracks confirmed by at least 2 independent sensors are displayed
3.  Confidence scores are reduced for all tracks
4.  Controllers are notified to increase separation minima

---

## 3. BACKUP RESTORATION PROCEDURES

### 3.1 Point In Time Recovery
```bash
# List available recovery points
php scripts/backup.php --list

# Restore to specific timestamp
php scripts/backup.php --restore --timestamp "2026-04-24 12:00:00"

# Verify restoration integrity
php scripts/backup.php --verify
```

### 3.2 WAL Recovery
All operations are written to immutable write-ahead log before database commit. Recovery is always possible to within 1 second of failure.

---

## 4. COMMUNICATION PROTOCOLS

### 4.1 Outage Notification Timeline
| Time | Action | Recipients |
|------|--------|------------|
| T+0 | Automatic system alert | On-duty controllers |
| T+1 minute | SMS notification | Duty Manager |
| T+5 minutes | Email + phone call | Senior Operations Staff |
| T+15 minutes | Formal incident notification | Aviation Authority |
| T+30 minutes | Status page update | All tenants / users |

### 4.2 Communication Templates
Location: `templates/incident/`
- `initial-notification.md`
- `status-update.md`
- `all-clear.md`
- `post-incident-report.md`

---

## 5. TESTING SCHEDULE

| Test Type | Frequency |
|-----------|-----------|
| Automatic failover testing | Monthly |
| Full disaster recovery simulation | Quarterly |
| Backup restoration verification | Weekly |
| Chaos engineering fault injection | Continuous |
| Full outage tabletop exercise | Bi-annually |

---

## 6. ROLES & RESPONSIBILITIES

| Role | Responsibility |
|------|----------------|
| Incident Commander | Overall incident coordination, decision authority |
| Technical Lead | System recovery, technical troubleshooting |
| Communications Officer | External notifications, status updates |
| Safety Officer | Safety validation before returning to service |
| Documentation Lead | Incident logging, post-mortem documentation |

---

## 7. POST INCIDENT PROCEDURES

1.  Complete incident log within 1 hour of resolution
2.  Conduct root cause analysis within 24 hours
3.  Prepare formal report for aviation authorities within 72 hours
4.  Implement corrective actions within 7 days
5.  Schedule post-incident review meeting

---

**This document is controlled. Unmodified copies must be available at all operational positions.**
**All procedures have been validated against ICAO Annex 11 and FAA Order 7110.65**