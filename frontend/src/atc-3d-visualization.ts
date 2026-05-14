/**
 * 3D ATC Visualization System
 *
 * Provides real-time 3D visualization of aircraft positions,
 * flight paths, and airspace for air traffic controllers.
 * Supports keyboard shortcuts, simulated radio communications,
 * status-based aircraft coloring, and bulk flight loading from API data.
 */

import * as THREE from 'three';
import type { Flight } from './dashboard/types.js';

interface Aircraft3D {
  id: string;
  position: THREE.Vector3;
  destinationPos: THREE.Vector3;
  mesh: THREE.Mesh;
  label: THREE.Sprite;
  trail: THREE.Line;
  trailPoints: THREE.Vector3[];
  altitude: number;
  heading: number;
  speed: number;
  callsign: string;
  status: string;
  paused: boolean;
}

interface ATCVisualizationConfig {
  container: HTMLElement;
  bounds: {
    north: number;
    south: number;
    east: number;
    west: number;
    minAltitude: number;
    maxAltitude: number;
  };
  showTerrain: boolean;
  showWeather: boolean;
  showAirports: boolean;
  showFlightPaths: boolean;
  updateInterval: number;
}

const RADIO_MESSAGES: Record<string, string[]> = {
  departing: [
    '{callsign} cleared for takeoff runway 27L, wind 270 at 8 knots',
    '{callsign} climb and maintain 5,000 feet, turn left heading 220',
    '{callsign} contact departure 124.3, good day',
    '{callsign} reduce speed to 250 knots',
    '{callsign} climb to flight level 280',
  ],
  arriving: [
    '{callsign} descend to 3,000 feet, QNH 1013',
    '{callsign} cleared for ILS approach runway 27L',
    '{callsign} reduce to approach speed, contact tower 118.5',
    '{callsign} number 2 in sequence, follow the Boeing 737 ahead 5 miles',
    '{callsign} wind 270 at 12 knots, cleared to land runway 27L',
  ],
  enroute: [
    '{callsign} maintain flight level 320, expect further clearance in 50 miles',
    `{callsign} traffic 3 o'clock, 5 miles, opposite direction, altitude unknown`,
    '{callsign} contact center on 128.7',
    '{callsign} deviate left 10 miles for weather',
    '{callsign} reduce to Mach 0.78 for traffic separation',
  ],
};

export class ATC3DVisualization {
  private scene!: THREE.Scene;
  private camera!: THREE.PerspectiveCamera;
  private renderer!: THREE.WebGLRenderer;
  private controls: any;
  private aircraft: Map<string, Aircraft3D> = new Map();
  private aircraftIds: string[] = [];
  private focusIndex = -1;
  private airports: THREE.Group = new THREE.Group();
  private weatherLayers: THREE.Group = new THREE.Group();
  private terrain: THREE.Mesh | null = null;
  private tower: THREE.Group = new THREE.Group();
  private config: ATCVisualizationConfig;
  private animationId: number | null = null;
  private websocket: WebSocket | null = null;
  private paused = false;
  private lastRadioTime = 0;
  private radioInterval = 8000;

  // Callbacks
  public onRadioComms: ((callsign: string, message: string) => void) | null = null;
  public onFlightCountChange: ((count: number) => void) | null = null;

  constructor(config: ATCVisualizationConfig) {
    this.config = config;
    this.init();
    this.setupWebSocket();
    this.startRenderLoop();
  }

  private init() {
    this.scene = new THREE.Scene();
    this.scene.background = new THREE.Color(0x1a1a2e);

    this.camera = new THREE.PerspectiveCamera(
      60,
      this.config.container.clientWidth / this.config.container.clientHeight,
      1,
      200000
    );

    this.camera.position.set(0, 30000, 60000);
    this.camera.lookAt(0, 0, 0);

    this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false });
    this.renderer.setSize(
      this.config.container.clientWidth,
      this.config.container.clientHeight
    );
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    this.renderer.shadowMap.enabled = true;
    this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    this.config.container.appendChild(this.renderer.domElement);

    this.setupLighting();
    this.setupControls();
    this.setupReferenceGrid();

    if (this.config.showTerrain) this.loadTerrain();
    if (this.config.showAirports) this.loadAirports();
    if (this.config.showWeather) this.loadWeatherLayers();

    this.buildTower();

    window.addEventListener('resize', () => this.onWindowResize());
  }

  private setupLighting() {
    const ambientLight = new THREE.AmbientLight(0x404060, 0.6);
    this.scene.add(ambientLight);

    const hemisphereLight = new THREE.HemisphereLight(0x87CEEB, 0x3a3a5e, 0.5);
    this.scene.add(hemisphereLight);

    const directionalLight = new THREE.DirectionalLight(0xffffff, 0.8);
    directionalLight.position.set(10000, 20000, 5000);
    directionalLight.castShadow = true;
    directionalLight.shadow.mapSize.width = 2048;
    directionalLight.shadow.mapSize.height = 2048;
    directionalLight.shadow.camera.near = 500;
    directionalLight.shadow.camera.far = 40000;
    directionalLight.shadow.camera.left = -10000;
    directionalLight.shadow.camera.right = 10000;
    directionalLight.shadow.camera.top = 10000;
    directionalLight.shadow.camera.bottom = -10000;
    this.scene.add(directionalLight);
  }

  private setupControls() {
    import('three/examples/jsm/controls/OrbitControls.js').then(({ OrbitControls }) => {
      this.controls = new OrbitControls(this.camera, this.renderer.domElement);
      this.controls.enableDamping = true;
      this.controls.dampingFactor = 0.08;
      this.controls.maxPolarAngle = Math.PI * 0.49;
      this.controls.minDistance = 500;
      this.controls.maxDistance = 120000;
      this.controls.target.set(0, 0, 0);
    });
  }

  private setupReferenceGrid() {
    const gridHelper = new THREE.GridHelper(200000, 100, 0x444466, 0x333355);
    gridHelper.position.y = -500;
    this.scene.add(gridHelper);

    const altitudes = [10000, 20000, 30000, 40000];
    altitudes.forEach(altitude => {
      const geometry = new THREE.RingGeometry(altitude * 0.3048 - 200, altitude * 0.3048 + 200, 64);
      const material = new THREE.MeshBasicMaterial({
        color: 0x00ccff,
        transparent: true,
        opacity: 0.06,
        side: THREE.DoubleSide,
        depthWrite: false,
      });
      const ring = new THREE.Mesh(geometry, material);
      ring.rotation.x = -Math.PI / 2;
      ring.position.y = altitude * 0.3048;
      this.scene.add(ring);

      this.addTextLabel(`${altitude}ft`, new THREE.Vector3(-55000, altitude * 0.3048 + 200, 0), 0x888899);
    });
  }

  private loadTerrain() {
    const geometry = new THREE.PlaneGeometry(200000, 200000, 128, 128);
    const material = new THREE.MeshLambertMaterial({
      color: 0x1a3a2a,
      wireframe: false,
      transparent: true,
      opacity: 0.6,
    });

    const vertices = geometry.attributes.position.array;
    for (let i = 0; i < vertices.length; i += 3) {
      const x = vertices[i];
      const z = vertices[i + 1];
      const dist = Math.sqrt(x * x + z * z);
      if (dist > 30000) {
        vertices[i + 2] = (Math.random() - 0.5) * 800;
      }
    }
    geometry.attributes.position.needsUpdate = true;
    geometry.computeVertexNormals();

    this.terrain = new THREE.Mesh(geometry, material);
    this.terrain.rotation.x = -Math.PI / 2;
    this.terrain.position.y = -500;
    this.terrain.receiveShadow = true;
    this.scene.add(this.terrain);
  }

  private buildTower() {
    const towerGroup = new THREE.Group();

    // Base
    const baseGeo = new THREE.CylinderGeometry(200, 250, 600, 8);
    const baseMat = new THREE.MeshLambertMaterial({ color: 0x888899 });
    const base = new THREE.Mesh(baseGeo, baseMat);
    base.position.y = 300;
    base.castShadow = true;
    towerGroup.add(base);

    // Tower shaft
    const shaftGeo = new THREE.CylinderGeometry(60, 100, 800, 8);
    const shaftMat = new THREE.MeshLambertMaterial({ color: 0xccccdd });
    const shaft = new THREE.Mesh(shaftGeo, shaftMat);
    shaft.position.y = 1000;
    shaft.castShadow = true;
    towerGroup.add(shaft);

    // Cab (glass section)
    const cabGeo = new THREE.CylinderGeometry(120, 100, 120, 8);
    const cabMat = new THREE.MeshLambertMaterial({
      color: 0x88ccff,
      transparent: true,
      opacity: 0.5,
    });
    const cab = new THREE.Mesh(cabGeo, cabMat);
    cab.position.y = 1500;
    this.tower.add(cab);

    // Roof
    const roofGeo = new THREE.ConeGeometry(140, 50, 8);
    const roofMat = new THREE.MeshLambertMaterial({ color: 0xdd4444 });
    const roof = new THREE.Mesh(roofGeo, roofMat);
    roof.position.y = 1585;
    towerGroup.add(roof);

    // Antenna
    const antGeo = new THREE.CylinderGeometry(2, 2, 100, 4);
    const antMat = new THREE.MeshBasicMaterial({ color: 0xff0000 });
    const ant = new THREE.Mesh(antGeo, antMat);
    ant.position.y = 1710;
    towerGroup.add(ant);

    // Red beacon light
    const beaconGeo = new THREE.SphereGeometry(8, 8, 8);
    const beaconMat = new THREE.MeshBasicMaterial({ color: 0xff0000 });
    const beacon = new THREE.Mesh(beaconGeo, beaconMat);
    beacon.position.y = 1720;
    towerGroup.add(beacon);

    // Animate beacon
    let beaconPhase = 0;
    setInterval(() => {
      beaconPhase = (beaconPhase + 1) % 120;
      beacon.material.color.setHSL(0, 1, beaconPhase < 30 ? 0.8 : 0.1);
    }, 100);

    this.scene.add(towerGroup);
  }

  private loadAirports() {
    const sampleAirports = [
      { code: 'JFK', name: 'John F. Kennedy', lat: 40.6413, lon: -73.7781 },
      { code: 'LAX', name: 'Los Angeles', lat: 33.9425, lon: -118.4081 },
      { code: 'ORD', name: "O'Hare", lat: 41.9742, lon: -87.9073 },
      { code: 'ATL', name: 'Hartsfield-Jackson', lat: 33.6407, lon: -84.4277 },
      { code: 'DFW', name: 'Dallas/Fort Worth', lat: 32.8998, lon: -97.0403 },
      { code: 'DEN', name: 'Denver', lat: 39.8561, lon: -104.6737 },
    ];

    sampleAirports.forEach(airport => this.addAirport(airport));
    this.scene.add(this.airports);
  }

  private addAirport(airport: any) {
    const position = this.latLonToVector3(airport.lat, airport.lon, 0);

    // Runway representation
    const runwayGeo = new THREE.BoxGeometry(300, 10, 3000);
    const runwayMat = new THREE.MeshLambertMaterial({ color: 0x444466 });
    const runway = new THREE.Mesh(runwayGeo, runwayMat);
    runway.position.copy(position);
    runway.position.y = 5;
    this.airports.add(runway);

    // Runway lights
    for (let i = -1400; i <= 1400; i += 200) {
      for (let side = -1; side <= 1; side += 2) {
        const lightGeo = new THREE.SphereGeometry(5, 4, 4);
        const lightMat = new THREE.MeshBasicMaterial({ color: 0xffff88 });
        const light = new THREE.Mesh(lightGeo, lightMat);
        light.position.copy(position);
        light.position.x += side * 180;
        light.position.z += i;
        light.position.y = 10;
        this.airports.add(light);
      }
    }

    // Terminal building
    const termGeo = new THREE.BoxGeometry(400, 80, 200);
    const termMat = new THREE.MeshLambertMaterial({ color: 0xccddee });
    const terminal = new THREE.Mesh(termGeo, termMat);
    terminal.position.copy(position);
    terminal.position.x += 500;
    terminal.position.y = 40;
    this.airports.add(terminal);

    // Airport label
    this.addTextLabel(airport.code, position.clone().add(new THREE.Vector3(0, 400, 0)), 0xffffff);
  }

  private loadWeatherLayers() {
    const weatherSystems = [
      { type: 'storm', lat: 41.0, lon: -74.0, radius: 40000, intensity: 0.7 },
      { type: 'turbulence', lat: 40.2, lon: -72.8, radius: 25000, intensity: 0.5 },
      { type: 'storm', lat: 39.5, lon: -76.0, radius: 30000, intensity: 0.6 },
    ];

    weatherSystems.forEach(system => this.addWeatherSystem(system));
    this.scene.add(this.weatherLayers);
  }

  private addWeatherSystem(system: any) {
    const position = this.latLonToVector3(system.lat, system.lon, 8000);

    const geometry = new THREE.SphereGeometry(system.radius, 24, 24);
    const material = new THREE.MeshBasicMaterial({
      color: system.type === 'storm' ? 0x8B0000 : 0xFFA500,
      transparent: true,
      opacity: system.intensity * 0.25,
      wireframe: false,
    });

    const weatherMesh = new THREE.Mesh(geometry, material);
    weatherMesh.position.copy(position);
    this.weatherLayers.add(weatherMesh);

    // Outer edge glow
    const edgeGeo = new THREE.SphereGeometry(system.radius * 1.1, 16, 16);
    const edgeMat = new THREE.MeshBasicMaterial({
      color: system.type === 'storm' ? 0xff0000 : 0xff8800,
      transparent: true,
      opacity: 0.1,
      wireframe: true,
    });
    const edgeMesh = new THREE.Mesh(edgeGeo, edgeMat);
    edgeMesh.position.copy(position);
    this.weatherLayers.add(edgeMesh);
  }

  private setupWebSocket() {
    try {
      this.websocket = new WebSocket('ws://localhost:8080');
      this.websocket.onopen = () => {
        console.log('[ATC3D] WebSocket connected');
        this.websocket?.send(JSON.stringify({ type: 'subscribe_flights', filters: {} }));
      };
      this.websocket.onmessage = (event) => {
        try {
          const data = JSON.parse(event.data);
          this.handleWebSocketMessage(data);
        } catch { /* skip non-JSON */ }
      };
      this.websocket.onclose = () => {
        console.log('[ATC3D] WebSocket disconnected');
        setTimeout(() => this.setupWebSocket(), 5000);
      };
      this.websocket.onerror = () => { /* ignore */ };
    } catch {
      console.log('[ATC3D] WebSocket unavailable — running in standalone mode');
    }
  }

  private handleWebSocketMessage(data: any) {
    switch (data.type) {
      case 'flight_update':
        if (data.flights) {
          data.flights.forEach((f: any) => this.updateAircraftPosition(f));
        } else if (data.update) {
          this.updateAircraftPosition(data.update);
        }
        break;
      case 'aircraft_removed':
        this.removeAircraft(data.aircraft_id);
        break;
      case 'announcement':
        this.showAnnouncement(data.message, data.level);
        break;
    }
  }

  /**
   * Bulk-load flights from the API data. Generates simulated 3D positions
   * based on origin/destination lat/lon mappings.
   */
  public loadFlightsFromData(flights: Flight[]): void {
    // Clear existing aircraft
    this.aircraft.forEach((_, id) => this.removeAircraft(id));
    this.aircraftIds = [];

    // Airport coordinate map for simulation
    const airportCoords: Record<string, { lat: number; lon: number }> = {
      JFK: { lat: 40.6413, lon: -73.7781 },
      LAX: { lat: 33.9425, lon: -118.4081 },
      ORD: { lat: 41.9742, lon: -87.9073 },
      ATL: { lat: 33.6407, lon: -84.4277 },
      DFW: { lat: 32.8998, lon: -97.0403 },
      DEN: { lat: 39.8561, lon: -104.6737 },
      SFO: { lat: 37.6213, lon: -122.3790 },
      SEA: { lat: 47.4502, lon: -122.3088 },
      MIA: { lat: 25.7959, lon: -80.2870 },
      BOS: { lat: 42.3656, lon: -71.0096 },
      IAD: { lat: 38.9531, lon: -77.4565 },
      EWR: { lat: 40.6895, lon: -74.1745 },
    };

    flights.forEach((flight, index) => {
      const origin = flight.origin?.toUpperCase() || 'JFK';
      const dest = flight.destination?.toUpperCase() || 'LAX';

      const originCoord = airportCoords[origin] || { lat: 40.0, lon: -74.0 };
      const destCoord = airportCoords[dest] || { lat: 34.0, lon: -118.0 };

      // Interpolate position based on index for visual spread
      const t = (index + 1) / (flights.length + 1);
      const lat = originCoord.lat + (destCoord.lat - originCoord.lat) * t + (Math.random() - 0.5) * 2;
      const lon = originCoord.lon + (destCoord.lon - originCoord.lon) * t + (Math.random() - 0.5) * 2;
      const alt = 15000 + Math.random() * 25000;

      const statusColor = this.getStatusColor(flight.status);
      this.createAircraftFromData(
        flight.flight_number || `FLT${flight.id}`,
        lat,
        lon,
        alt,
        flight.airline_name || 'Unknown',
        flight.status,
        statusColor,
      );
    });

    this.aircraftIds = Array.from(this.aircraft.keys());
    this.onFlightCountChange?.(this.aircraft.size);
  }

  private getStatusColor(status: string): number {
    switch (status) {
      case 'scheduled': return 0xffdd44;
      case 'boarding': return 0x4488ff;
      case 'departed': return 0x44dd88;
      case 'arrived': return 0x888899;
      case 'cancelled': return 0xff4444;
      default: return 0x00ccff;
    }
  }

  private createAircraftFromData(
    id: string,
    lat: number,
    lon: number,
    alt: number,
    _airline: string,
    status: string,
    statusColor: number,
  ) {
    const position = this.latLonToVector3(lat, lon, alt * 0.3048);

    // Create destination position (slightly offset so they move)
    const destLat = lat + (Math.random() - 0.5) * 4;
    const destLon = lon + (Math.random() - 0.5) * 4;
    const destAlt = alt * 0.3048;
    const destinationPos = this.latLonToVector3(destLat, destLon, destAlt);

    const mesh = this.createAircraftMesh(statusColor);
    mesh.position.copy(position);

    const label = this.createLabel(id, statusColor);
    label.position.copy(position).add(new THREE.Vector3(0, 400, 0));

    const trailPoints: THREE.Vector3[] = [position.clone()];
    const trailGeo = new THREE.BufferGeometry().setFromPoints(trailPoints);
    const trailMat = new THREE.LineBasicMaterial({
      color: statusColor,
      transparent: true,
      opacity: 0.5,
    });
    const trail = new THREE.Line(trailGeo, trailMat);

    this.scene.add(mesh);
    this.scene.add(label);
    this.scene.add(trail);

    const aircraft: Aircraft3D = {
      id,
      position,
      destinationPos,
      mesh,
      label,
      trail,
      trailPoints,
      altitude: alt,
      heading: Math.random() * 360,
      speed: 200 + Math.random() * 300,
      callsign: id,
      status,
      paused: false,
    };

    this.aircraft.set(id, aircraft);
  }

  private createAircraftMesh(color: number): THREE.Mesh {
    const group = new THREE.Group();

    // Fuselage
    const fuseGeo = new THREE.CylinderGeometry(20, 30, 120, 6);
    const fuseMat = new THREE.MeshLambertMaterial({ color });
    const fuselage = new THREE.Mesh(fuseGeo, fuseMat);
    fuselage.rotation.z = Math.PI / 2;
    fuselage.castShadow = true;
    group.add(fuselage);

    // Wings
    const wingGeo = new THREE.BoxGeometry(10, 4, 120);
    const wingMat = new THREE.MeshLambertMaterial({ color: 0xccccdd });  
    const wings = new THREE.Mesh(wingGeo, wingMat);
    wings.position.x = -10;
    group.add(wings);

    // Tail
    const tailGeo = new THREE.BoxGeometry(30, 4, 20);
    const tailMat = new THREE.MeshLambertMaterial({ color: 0xccccdd });
    const tail = new THREE.Mesh(tailGeo, tailMat);
    tail.position.x = 60;
    tail.position.z = 10;
    group.add(tail);

    const container = new THREE.Mesh(new THREE.BoxGeometry(1, 1, 1), new THREE.MeshBasicMaterial({ visible: false }));
    container.add(group);

    return container;
  }

  private createLabel(text: string, color: number): THREE.Sprite {
    const canvas = document.createElement('canvas');
    canvas.width = 256;
    canvas.height = 64;
    const ctx = canvas.getContext('2d')!;

    ctx.fillStyle = 'rgba(0, 0, 0, 0.6)';
    ctx.fillRect(0, 0, 256, 64);

    ctx.font = 'Bold 22px monospace';
    ctx.fillStyle = `#${color.toString(16).padStart(6, '0')}`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(text, 128, 32);

    const texture = new THREE.CanvasTexture(canvas);
    texture.needsUpdate = true;
    const spriteMaterial = new THREE.SpriteMaterial({ map: texture, transparent: true });
    const sprite = new THREE.Sprite(spriteMaterial);
    sprite.scale.set(800, 200, 1);

    return sprite;
  }

  private updateAircraftPosition(aircraftData: any) {
    const aircraftId = aircraftData.icao24 || aircraftData.id;
    if (!this.aircraft.has(aircraftId)) {
      this.createAircraftFromData(
        aircraftId,
        aircraftData.latitude || 40,
        aircraftData.longitude || -74,
        aircraftData.baro_altitude || 30000,
        aircraftData.airline || '',
        aircraftData.status || 'enroute',
        this.getStatusColor(aircraftData.status || 'enroute'),
      );
    }

    const aircraft = this.aircraft.get(aircraftId)!;
    if (aircraftData.latitude && aircraftData.longitude) {
      const alt = (aircraftData.baro_altitude || aircraftData.geo_altitude || 0) * 0.3048;
      const newPos = this.latLonToVector3(aircraftData.latitude, aircraftData.longitude, alt);

      aircraft.trailPoints.push(newPos.clone());
      if (aircraft.trailPoints.length > 100) aircraft.trailPoints.shift();
      this.updateTrail(aircraft);

      aircraft.position.lerp(newPos, 0.1);
      aircraft.mesh.position.copy(aircraft.position);
      aircraft.label.position.copy(aircraft.position).add(new THREE.Vector3(0, 400, 0));

      if (aircraftData.true_track) {
        const heading = (aircraftData.true_track * Math.PI) / 180;
        aircraft.mesh.rotation.y = heading;
      }
    }

    aircraft.altitude = aircraftData.baro_altitude || aircraftData.geo_altitude || 0;
    aircraft.heading = aircraftData.true_track || 0;
    aircraft.speed = aircraftData.velocity || 0;
    aircraft.callsign = aircraftData.callsign || aircraftId;
  }

  private updateTrail(aircraft: Aircraft3D) {
    if (aircraft.trailPoints.length < 2) return;
    const geometry = new THREE.BufferGeometry().setFromPoints(aircraft.trailPoints);
    aircraft.trail.geometry.dispose();
    aircraft.trail.geometry = geometry;
  }

  private removeAircraft(id: string) {
    const aircraft = this.aircraft.get(id);
    if (aircraft) {
      this.scene.remove(aircraft.mesh);
      this.scene.remove(aircraft.label);
      this.scene.remove(aircraft.trail);
      aircraft.mesh.geometry?.dispose();
      aircraft.label.material.map?.dispose();
      aircraft.label.material.dispose();
      aircraft.trail.geometry?.dispose();
      this.aircraft.delete(id);
    }
  }

  private latLonToVector3(lat: number, lon: number, alt: number): THREE.Vector3 {
    const centerLon = (this.config.bounds.west + this.config.bounds.east) / 2;
    const centerLat = (this.config.bounds.south + this.config.bounds.north) / 2;
    const x = (lon - centerLon) * 111000 * Math.cos((centerLat * Math.PI) / 180);
    const z = (lat - centerLat) * 111000;
    return new THREE.Vector3(x, alt, z);
  }

  private addTextLabel(text: string, position: THREE.Vector3, color: number) {
    const canvas = document.createElement('canvas');
    canvas.width = 256;
    canvas.height = 64;
    const ctx = canvas.getContext('2d')!;
    ctx.font = 'Bold 20px monospace';
    ctx.fillStyle = `#${color.toString(16).padStart(6, '0')}`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(text, 128, 32);

    const texture = new THREE.CanvasTexture(canvas);
    const material = new THREE.SpriteMaterial({ map: texture, transparent: true });
    const sprite = new THREE.Sprite(material);
    sprite.position.copy(position);
    sprite.scale.set(800, 200, 1);
    this.scene.add(sprite);
  }

  private showAnnouncement(message: string, _level: string = 'info') {
    console.log(`[ATC3D] ${message}`);
  }

  private startRenderLoop() {
    let lastUpdate = performance.now();

    const animate = () => {
      this.animationId = requestAnimationFrame(animate);

      if (this.controls) this.controls.update();

      const now = performance.now();
      const dt = Math.min((now - lastUpdate) / 1000, 0.1);

      // Simulate aircraft movement
      if (!this.paused) {
        this.aircraft.forEach(aircraft => {
          if (aircraft.paused) return;

          // Move toward destination
          const dir = new THREE.Vector3()
            .copy(aircraft.destinationPos)
            .sub(aircraft.position)
            .normalize();
          const speed = (aircraft.speed / 100) * dt * 300;
          aircraft.position.add(dir.multiplyScalar(speed));

          // If close to destination, pick a new one
          if (aircraft.position.distanceTo(aircraft.destinationPos) < 1000) {
            const newLat = 35 + Math.random() * 10;
            const newLon = -105 + Math.random() * 30;
            const newAlt = 5000 + Math.random() * 25000;
            aircraft.destinationPos = this.latLonToVector3(newLat, newLon, newAlt * 0.3048);
          }

          aircraft.mesh.position.copy(aircraft.position);
          aircraft.label.position.copy(aircraft.position).add(new THREE.Vector3(0, 400, 0));

          // Update trail
          aircraft.trailPoints.push(aircraft.position.clone());
          if (aircraft.trailPoints.length > 100) aircraft.trailPoints.shift();
          this.updateTrail(aircraft);

          // Face direction of travel
          if (aircraft.trailPoints.length > 1) {
            const prev = aircraft.trailPoints[aircraft.trailPoints.length - 2];
            const curr = aircraft.trailPoints[aircraft.trailPoints.length - 1];
            const angle = Math.atan2(curr.x - prev.x, curr.z - prev.z);
            aircraft.mesh.rotation.y = angle;
          }
        });

        // Simulated radio calls
        if (now - this.lastRadioTime > this.radioInterval && this.aircraft.size > 0) {
          this.lastRadioTime = now;
          this.simulateRadioCall();
        }

        // Update label facing
        this.aircraft.forEach(aircraft => {
          aircraft.label.lookAt(this.camera.position);
        });
      }

      this.renderer.render(this.scene, this.camera);
      lastUpdate = now;
    };

    animate();
  }

  private simulateRadioCall(): void {
    const ids = Array.from(this.aircraft.keys());
    if (ids.length === 0) return;

    const randomId = ids[Math.floor(Math.random() * ids.length)];
    const aircraft = this.aircraft.get(randomId);
    if (!aircraft) return;

    const phase = aircraft.status || 'enroute';
    const messages = RADIO_MESSAGES[phase] || RADIO_MESSAGES.enroute;
    const msg = messages[Math.floor(Math.random() * messages.length)]
      .replace('{callsign}', aircraft.callsign);

    this.onRadioComms?.(aircraft.callsign, msg);
  }

  private onWindowResize() {
    const w = this.config.container.clientWidth;
    const h = this.config.container.clientHeight;
    if (w === 0 || h === 0) return;
    this.camera.aspect = w / h;
    this.camera.updateProjectionMatrix();
    this.renderer.setSize(w, h);
  }

  // ─── Public API ─────────────────────────────────────────

  public setViewMode(mode: 'plan' | 'side' | '3d') {
    const target = this.controls?.target || new THREE.Vector3();
    switch (mode) {
      case 'plan':
        this.camera.position.set(0, 60000, 1);
        this.camera.lookAt(target);
        break;
      case 'side':
        this.camera.position.set(75000, 0, 0);
        this.camera.lookAt(target);
        break;
      case '3d':
        this.camera.position.set(30000, 30000, 50000);
        this.camera.lookAt(target);
        break;
    }
    if (this.controls) {
      this.controls.target.copy(target);
      this.controls.update();
    }
  }

  public resetCamera(): void {
    this.camera.position.set(0, 30000, 60000);
    if (this.controls) {
      this.controls.target.set(0, 0, 0);
      this.controls.update();
    }
  }

  public focusNextAircraft(): void {
    if (this.aircraftIds.length === 0) return;
    this.focusIndex = (this.focusIndex + 1) % this.aircraftIds.length;
    const id = this.aircraftIds[this.focusIndex];
    const aircraft = this.aircraft.get(id);
    if (aircraft && this.controls) {
      this.controls.target.copy(aircraft.position);
      this.camera.position.copy(aircraft.position).add(new THREE.Vector3(5000, 3000, 5000));
      this.controls.update();

      this.onRadioComms?.(aircraft.callsign, `you have been selected for tracking on radar`);
    }
  }

  public focusOnAircraft(aircraftId: string) {
    const aircraft = this.aircraft.get(aircraftId);
    if (aircraft && this.controls) {
      this.controls.target.copy(aircraft.position);
      this.camera.position.copy(aircraft.position).add(new THREE.Vector3(5000, 3000, 5000));
      this.controls.update();
    }
  }

  public toggleWeather(): void {
    this.weatherLayers.visible = !this.weatherLayers.visible;
  }

  public toggleTerrain(): void {
    if (this.terrain) {
      this.terrain.visible = !this.terrain.visible;
    }
  }

  public toggleFlightPaths(): void {
    const firstAircraft = this.aircraft.values().next().value;
    const show = firstAircraft ? !firstAircraft.trail.visible : false;
    this.aircraft.forEach(aircraft => {
      aircraft.trail.visible = show;
    });
  }

  public togglePause(): void {
    this.paused = !this.paused;
    console.log(`[ATC3D] ${this.paused ? 'Paused' : 'Resumed'}`);
  }

  public destroy() {
    if (this.animationId) {
      cancelAnimationFrame(this.animationId);
    }
    if (this.websocket) {
      this.websocket.close();
    }
    // Clean up all aircraft
    this.aircraft.forEach((_, id) => this.removeAircraft(id));
    this.aircraft.clear();
    this.aircraftIds = [];

    // Remove other objects
    this.scene.remove(this.airports);
    this.scene.remove(this.weatherLayers);
    this.scene.remove(this.tower);

    this.renderer.dispose();
    if (this.config.container.contains(this.renderer.domElement)) {
      this.config.container.removeChild(this.renderer.domElement);
    }
  }
}