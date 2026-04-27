/**
 * 3D ATC Visualization System
 *
 * Provides real-time 3D visualization of aircraft positions,
 * flight paths, and airspace for air traffic controllers
 */

import * as THREE from 'three';

interface Aircraft3D {
  id: string;
  position: THREE.Vector3;
  mesh: THREE.Mesh;
  label: THREE.Sprite;
  trail: THREE.Line;
  trailPoints: THREE.Vector3[];
  altitude: number;
  heading: number;
  speed: number;
  callsign: string;
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

export class ATC3DVisualization {
  private scene!: THREE.Scene;
  private camera!: THREE.PerspectiveCamera;
  private renderer!: THREE.WebGLRenderer;
  private controls: any; // OrbitControls
  private aircraft: Map<string, Aircraft3D> = new Map();
  private airports: THREE.Group = new THREE.Group();
  private weatherLayers: THREE.Group = new THREE.Group();
  private terrain: THREE.Mesh | null = null;
  private config: ATCVisualizationConfig;
  private animationId: number | null = null;
  private websocket: WebSocket | null = null;
  private lastUpdate: number = 0;

  constructor(config: ATCVisualizationConfig) {
    this.config = config;
    this.init();
    this.setupWebSocket();
    this.startRenderLoop();
  }

  private init() {
    // Scene setup
    this.scene = new THREE.Scene();
    this.scene.background = new THREE.Color(0x87CEEB); // Sky blue

    // Camera setup
    this.camera = new THREE.PerspectiveCamera(
      75,
      this.config.container.clientWidth / this.config.container.clientHeight,
      0.1,
      100000
    );

    // Position camera for ATC view (top-down with slight angle)
    this.camera.position.set(0, 50000, 50000);
    this.camera.lookAt(0, 0, 0);

    // Renderer setup
    this.renderer = new THREE.WebGLRenderer({ antialias: true });
    this.renderer.setSize(
      this.config.container.clientWidth,
      this.config.container.clientHeight
    );
    this.renderer.shadowMap.enabled = true;
    this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    this.config.container.appendChild(this.renderer.domElement);

    // Lighting
    this.setupLighting();

    // Controls
    this.setupControls();

    // Grid and reference elements
    this.setupReferenceGrid();

    // Terrain (optional)
    if (this.config.showTerrain) {
      this.loadTerrain();
    }

    // Airports
    if (this.config.showAirports) {
      this.loadAirports();
    }

    // Weather layers
    if (this.config.showWeather) {
      this.loadWeatherLayers();
    }

    // Handle window resize
    window.addEventListener('resize', () => this.onWindowResize());
  }

  private setupLighting() {
    // Ambient light
    const ambientLight = new THREE.AmbientLight(0x404040, 0.6);
    this.scene.add(ambientLight);

    // Directional light (sun)
    const directionalLight = new THREE.DirectionalLight(0xffffff, 0.8);
    directionalLight.position.set(10000, 10000, 5000);
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
    // Import OrbitControls dynamically to avoid bundling issues
    import('three/examples/jsm/controls/OrbitControls.js').then(({ OrbitControls }) => {
      this.controls = new OrbitControls(this.camera, this.renderer.domElement);
      this.controls.enableDamping = true;
      this.controls.dampingFactor = 0.05;
      this.controls.maxPolarAngle = Math.PI * 0.45; // Limit vertical rotation
      this.controls.minDistance = 1000;
      this.controls.maxDistance = 100000;
    });
  }

  private setupReferenceGrid() {
    // Altitude reference planes
    const altitudes = [10000, 20000, 30000, 40000]; // feet

    altitudes.forEach(altitude => {
      const geometry = new THREE.PlaneGeometry(100000, 100000);
      const material = new THREE.MeshBasicMaterial({
        color: 0x00ff00,
        transparent: true,
        opacity: 0.1,
        side: THREE.DoubleSide
      });
      const plane = new THREE.Mesh(geometry, material);
      plane.rotation.x = -Math.PI / 2;
      plane.position.y = altitude * 0.3048; // Convert feet to meters
      this.scene.add(plane);

      // Altitude label
      this.addTextLabel(`${altitude}ft`, new THREE.Vector3(-50000, altitude * 0.3048 + 100, 0), 0xffffff);
    });

    // Ground reference
    const groundGeometry = new THREE.PlaneGeometry(200000, 200000);
    const groundMaterial = new THREE.MeshLambertMaterial({ color: 0x228B22 });
    const ground = new THREE.Mesh(groundGeometry, groundMaterial);
    ground.rotation.x = -Math.PI / 2;
    ground.receiveShadow = true;
    this.scene.add(ground);
  }

  private loadTerrain() {
    // In production, load actual terrain data
    // For demo, create simple terrain mesh
    const geometry = new THREE.PlaneGeometry(200000, 200000, 256, 256);
    const material = new THREE.MeshLambertMaterial({
      color: 0x8B4513,
      wireframe: false
    });

    // Add some height variation
    const vertices = geometry.attributes.position.array;
    for (let i = 0; i < vertices.length; i += 3) {
      vertices[i + 2] = Math.random() * 1000; // Random height
    }
    geometry.attributes.position.needsUpdate = true;
    geometry.computeVertexNormals();

    this.terrain = new THREE.Mesh(geometry, material);
    this.terrain.rotation.x = -Math.PI / 2;
    this.terrain.position.y = -500;
    this.scene.add(this.terrain);
  }

  private loadAirports() {
    // Sample airports - in production, load from database
    const sampleAirports = [
      { code: 'JFK', name: 'John F. Kennedy', lat: 40.6413, lon: -73.7781 },
      { code: 'LAX', name: 'Los Angeles', lat: 33.9425, lon: -118.4081 },
      { code: 'ORD', name: 'O\'Hare', lat: 41.9742, lon: -87.9073 }
    ];

    sampleAirports.forEach(airport => {
      this.addAirport(airport);
    });

    this.scene.add(this.airports);
  }

  private addAirport(airport: any) {
    const position = this.latLonToVector3(airport.lat, airport.lon, 0);

    // Airport marker
    const geometry = new THREE.CylinderGeometry(500, 500, 100, 8);
    const material = new THREE.MeshLambertMaterial({ color: 0xff0000 });
    const marker = new THREE.Mesh(geometry, material);
    marker.position.copy(position);
    marker.position.y = 50;
    this.airports.add(marker);

    // Airport label
    this.addTextLabel(airport.code, position.clone().add(new THREE.Vector3(0, 200, 0)), 0xffffff);
  }

  private loadWeatherLayers() {
    // Sample weather data - in production, load from weather APIs
    const weatherSystems = [
      { type: 'storm', lat: 40.0, lon: -74.0, radius: 50000, intensity: 0.8 },
      { type: 'turbulence', lat: 41.0, lon: -73.0, radius: 30000, intensity: 0.6 }
    ];

    weatherSystems.forEach(system => {
      this.addWeatherSystem(system);
    });

    this.scene.add(this.weatherLayers);
  }

  private addWeatherSystem(system: any) {
    const position = this.latLonToVector3(system.lat, system.lon, 10000);
    const geometry = new THREE.SphereGeometry(system.radius, 32, 32);
    const material = new THREE.MeshBasicMaterial({
      color: system.type === 'storm' ? 0x8B0000 : 0xFFA500,
      transparent: true,
      opacity: system.intensity * 0.3
    });

    const weatherMesh = new THREE.Mesh(geometry, material);
    weatherMesh.position.copy(position);
    this.weatherLayers.add(weatherMesh);
  }

  private setupWebSocket() {
    this.websocket = new WebSocket('ws://localhost:8080');

    this.websocket.onopen = () => {
      console.log('ATC 3D Visualization connected to WebSocket');
      this.websocket?.send(JSON.stringify({
        type: 'subscribe_flights',
        filters: {} // Subscribe to all aircraft
      }));
    };

    this.websocket.onmessage = (event) => {
      const data = JSON.parse(event.data);
      this.handleWebSocketMessage(data);
    };

    this.websocket.onclose = () => {
      console.log('ATC 3D Visualization WebSocket disconnected');
      // Attempt reconnection
      setTimeout(() => this.setupWebSocket(), 5000);
    };

    this.websocket.onerror = (error) => {
      console.error('ATC 3D Visualization WebSocket error:', error);
    };
  }

  private handleWebSocketMessage(data: any) {
    switch (data.type) {
      case 'flight_update':
        if (data.flights) {
          data.flights.forEach((flight: any) => this.updateAircraftPosition(flight));
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

  private updateAircraftPosition(aircraftData: any) {
    const aircraftId = aircraftData.icao24 || aircraftData.id;

    if (!this.aircraft.has(aircraftId)) {
      this.createAircraft(aircraftId, aircraftData);
    }

    const aircraft = this.aircraft.get(aircraftId)!;

    // Update position
    if (aircraftData.latitude && aircraftData.longitude) {
      const altitude = (aircraftData.baro_altitude || aircraftData.geo_altitude || 0) * 0.3048; // Convert feet to meters
      const newPosition = this.latLonToVector3(aircraftData.latitude, aircraftData.longitude, altitude);

      // Update trail
      aircraft.trailPoints.push(newPosition.clone());
      if (aircraft.trailPoints.length > 100) {
        aircraft.trailPoints.shift();
      }
      this.updateTrail(aircraft);

      // Smooth position update
      aircraft.position.lerp(newPosition, 0.1);
      aircraft.mesh.position.copy(aircraft.position);

      // Update label position
      aircraft.label.position.copy(aircraft.position).add(new THREE.Vector3(0, 100, 0));

      // Update orientation based on heading
      if (aircraftData.true_track) {
        const heading = (aircraftData.true_track * Math.PI) / 180;
        aircraft.mesh.rotation.z = heading;
      }
    }

    // Update aircraft data
    aircraft.altitude = aircraftData.baro_altitude || aircraftData.geo_altitude || 0;
    aircraft.heading = aircraftData.true_track || 0;
    aircraft.speed = aircraftData.velocity || 0;
    aircraft.callsign = aircraftData.callsign || aircraftId;
  }

  private createAircraft(id: string, data: any) {
    const aircraft: Aircraft3D = {
      id,
      position: new THREE.Vector3(),
      mesh: this.createAircraftMesh(),
      label: this.createLabel(data.callsign || id),
      trail: new THREE.Line(),
      trailPoints: [],
      altitude: 0,
      heading: 0,
      speed: 0,
      callsign: data.callsign || id
    };

    this.scene.add(aircraft.mesh);
    this.scene.add(aircraft.label);
    this.scene.add(aircraft.trail);

    this.aircraft.set(id, aircraft);
  }

  private createAircraftMesh(): THREE.Mesh {
    // Simple aircraft representation
    const geometry = new THREE.ConeGeometry(50, 200, 8);
    const material = new THREE.MeshLambertMaterial({ color: 0x00ff00 });
    const mesh = new THREE.Mesh(geometry, material);
    mesh.castShadow = true;
    mesh.rotation.x = Math.PI / 2; // Point forward
    return mesh;
  }

  private createLabel(text: string): THREE.Sprite {
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d')!;
    context.font = 'Bold 20px Arial';
    context.fillStyle = 'rgba(255, 255, 255, 0.8)';
    context.fillRect(0, 0, 200, 50);
    context.fillStyle = 'black';
    context.fillText(text, 10, 30);

    const texture = new THREE.CanvasTexture(canvas);
    const spriteMaterial = new THREE.SpriteMaterial({ map: texture });
    const sprite = new THREE.Sprite(spriteMaterial);
    sprite.scale.set(1000, 500, 1);

    return sprite;
  }

  private updateTrail(aircraft: Aircraft3D) {
    if (aircraft.trailPoints.length < 2) return;

    const geometry = new THREE.BufferGeometry().setFromPoints(aircraft.trailPoints);
    const material = new THREE.LineBasicMaterial({ color: 0x00ff00, opacity: 0.7, transparent: true });
    aircraft.trail.geometry.dispose();
    aircraft.trail.geometry = geometry;
    aircraft.trail.material = material;
  }

  private removeAircraft(id: string) {
    const aircraft = this.aircraft.get(id);
    if (aircraft) {
      this.scene.remove(aircraft.mesh);
      this.scene.remove(aircraft.label);
      this.scene.remove(aircraft.trail);
      this.aircraft.delete(id);
    }
  }

  private latLonToVector3(lat: number, lon: number, alt: number): THREE.Vector3 {
    // Convert lat/lon to 3D coordinates (simplified projection)
    const x = (lon - (this.config.bounds.west + this.config.bounds.east) / 2) * 111000; // Rough meters per degree
    const z = (lat - (this.config.bounds.south + this.config.bounds.north) / 2) * 111000;
    const y = alt;

    return new THREE.Vector3(x, y, z);
  }

  private addTextLabel(text: string, position: THREE.Vector3, color: number) {
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d')!;
    context.font = 'Bold 16px Arial';
    context.fillStyle = `rgba(${(color >> 16) & 0xff}, ${(color >> 8) & 0xff}, ${color & 0xff}, 0.8)`;
    context.fillRect(0, 0, 100, 30);
    context.fillStyle = 'white';
    context.fillText(text, 5, 20);

    const texture = new THREE.CanvasTexture(canvas);
    const spriteMaterial = new THREE.SpriteMaterial({ map: texture });
    const sprite = new THREE.Sprite(spriteMaterial);
    sprite.position.copy(position);
    sprite.scale.set(500, 150, 1);
    this.scene.add(sprite);
  }

  private showAnnouncement(message: string, level: string = 'info') {
    console.log(`ATC Announcement [${level.toUpperCase()}]: ${message}`);
    // Could add on-screen notification
  }

  private startRenderLoop() {
    const animate = () => {
      this.animationId = requestAnimationFrame(animate);

      if (this.controls) {
        this.controls.update();
      }

      // Update aircraft labels to face camera
      this.aircraft.forEach(aircraft => {
        aircraft.label.lookAt(this.camera.position);
      });

      this.renderer.render(this.scene, this.camera);
    };

    animate();
  }

  private onWindowResize() {
    this.camera.aspect = this.config.container.clientWidth / this.config.container.clientHeight;
    this.camera.updateProjectionMatrix();
    this.renderer.setSize(
      this.config.container.clientWidth,
      this.config.container.clientHeight
    );
  }

  // Public API methods
  public setViewMode(mode: 'plan' | 'side' | '3d') {
    switch (mode) {
      case 'plan':
        this.camera.position.set(0, 50000, 0);
        this.camera.lookAt(0, 0, 0);
        break;
      case 'side':
        this.camera.position.set(0, 0, 50000);
        this.camera.lookAt(0, 0, 0);
        break;
      case '3d':
        this.camera.position.set(30000, 30000, 30000);
        this.camera.lookAt(0, 0, 0);
        break;
    }
  }

  public focusOnAircraft(aircraftId: string) {
    const aircraft = this.aircraft.get(aircraftId);
    if (aircraft && this.controls) {
      this.controls.target.copy(aircraft.position);
      this.camera.position.copy(aircraft.position).add(new THREE.Vector3(5000, 5000, 5000));
    }
  }

  public toggleWeather(show: boolean) {
    this.weatherLayers.visible = show;
  }

  public toggleTerrain(show: boolean) {
    if (this.terrain) {
      this.terrain.visible = show;
    }
  }

  public toggleFlightPaths(show: boolean) {
    this.aircraft.forEach(aircraft => {
      aircraft.trail.visible = show;
    });
  }

  public destroy() {
    if (this.animationId) {
      cancelAnimationFrame(this.animationId);
    }
    if (this.websocket) {
      this.websocket.close();
    }
    this.renderer.dispose();
    this.config.container.removeChild(this.renderer.domElement);
  }
}

// Usage example:
/*
const container = document.getElementById('atc-3d-container');
const atcViz = new ATC3DVisualization({
  container,
  bounds: {
    north: 45,
    south: 35,
    east: -70,
    west: -80,
    minAltitude: 0,
    maxAltitude: 50000
  },
  showTerrain: true,
  showWeather: true,
  showAirports: true,
  showFlightPaths: true,
  updateInterval: 1000
});

// Switch views
atcViz.setViewMode('3d');

// Focus on specific aircraft
atcViz.focusOnAircraft('ABC123');

// Toggle layers
atcViz.toggleWeather(false);
atcViz.toggleFlightPaths(true);
*/
