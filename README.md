# Flight Control Software

A comprehensive flight control software for airports with TypeScript frontend, PHP backend, and PostgreSQL database.

## Features

### Core Aviation Management
- **Modular Design**: Enable/disable modules based on airport needs
- **Flexible RBAC**: Complete Role-Based Access Control with granular permissions
  - Super Admin: Full system access
  - Admin: Airport administration
  - Operator: Daily operations staff
  - Passenger: Self-service access
- **Flight Management**: Scheduling, real-time status, gate assignments
- **Passenger Services**: Booking system with Paddle payments, baggage tracking
- **Security & Check-in**: Boarding passes, security screening
- **Ground Operations**: Maintenance, crew assignments

### Advanced ATC Modernization
- **ADS-B Integration**: Real-time aircraft tracking with conflict detection
- **Satellite Connectivity**: Starlink/Iridium support for oceanic/polar operations
- **3D Airspace Visualization**: Immersive Three.js-powered ATC displays
- **AI Conflict Prediction**: Machine learning-powered safety enhancements
- **Real-time WebSocket Updates**: Live flight data broadcasting
- **Performance Analytics**: Comprehensive KPIs with AI recommendations

### Cutting-Edge Technology
- **Mobile Controller App**: Touch-optimized interface with voice commands
- **Voice Recognition**: Hands-free ATC operations
- **Haptic Feedback**: Tactile notifications for critical alerts
- **Progressive Web App**: Offline-capable, installable web application
- **Advanced Security**: Quantum-resistant encryption preparation

### Enterprise Features
- **Automated Backups**: Scheduled database backups with verification
- **Health Monitoring**: Real-time system diagnostics and alerting
- **Audit Trails**: Complete operational logging for compliance
- **Data Export**: CSV/JSON export capabilities for reporting
- **Multi-tenant Architecture**: Support for multiple airports

## Tech Stack

- **Frontend**: Vanilla TypeScript + Vite, Tailwind CSS, PWA
- **Backend**: Plain PHP with custom JWT, WebSockets
- **Database**: PostgreSQL
- **Payment**: Paddle API
- **Real-time**: WebSockets

## Setup Instructions

### Prerequisites
- Node.js 18+
- PHP 7.4+ with PDO extension
- PostgreSQL 13+
- Composer

### Installation

1. **Clone/Download the project**

2. **Database Setup**
   - Create a PostgreSQL database named `flight_control`
   - Run the schema: `psql -d flight_control -f database/schema.sql`
   - Run the seed data: `psql -d flight_control -f database/seed.sql`
   - Update `backend/config/database.php` with your DB credentials

3. **Backend Setup**
   - `cd backend`
   - `composer install`
   - Update JWT secret key in `backend/src/Auth.php`

4. **Frontend Setup**
   - `cd frontend`
   - `npm install`
   - `npm run dev` (for development)

5. **Start Backend Server**
   - Use PHP built-in server: `php -S localhost:8000 -t backend/api`
   - Or configure Apache/Nginx

### Development

- Frontend dev server: `cd frontend && npm run dev`
- Backend API: Access via `http://localhost:8000`
- Database: Connect to PostgreSQL instance

### API Endpoints

- `POST /auth.php` - Authentication (login/register)
- `GET/POST/PUT/DELETE /flights.php` - Flight management
- `GET/POST/PUT/DELETE /rbac.php` - RBAC management
- `GET /health.php` - System health check

### Real-time Features

- **WebSocket Server**: Run `php backend/websocket-server.php` for real-time updates
- **Flight Updates**: Automatic broadcasting when flight status changes
- **Client Connection**: Frontend automatically connects to ws://localhost:8080
- **Live Dashboard**: Real-time flight status updates in the UI

### Default Credentials

- **Username**: admin
- **Password**: admin123
- **Role**: Super Admin (full access)

### Project Structure

```
/
├── frontend/          # TypeScript PWA
├── backend/           # PHP API
│   ├── api/           # Endpoints
│   ├── config/        # DB config
│   └── src/           # Core classes
├── database/          # SQL schema
├── integrations/      # External APIs
├── analytics/         # Reporting
├── docs/              # Documentation
└── todo.md            # Development checklist
```

## Contributing

This is a foundational implementation. Further development includes:
- Completing all modules
- Implementing PWA features
- Adding real-time WebSockets
- Integrating Paddle payments
- Building comprehensive UI

## License

Proprietary - Airport Flight Control Software
