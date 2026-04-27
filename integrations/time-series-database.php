<?php
/**
 * Time-Series Database for Positional Data
 *
 * Optimized storage and querying system for high-volume aircraft positional data
 * Based on InfluxDB/TimeScaleDB architecture concepts
 */

class TimeSeriesDatabase
{
    private $db;
    private $logger;
    private $retentionPolicies;
    private $chunkSize;
    private $isInitialized = false;

    public function __construct($database, $logger)
    {
        $this->db = $database;
        $this->logger = $logger;
        $this->chunkSize = 1000; // Batch size for bulk operations
        $this->retentionPolicies = $this->initializeRetentionPolicies();
    }

    /**
     * Initialize retention policies for different data types
     */
    private function initializeRetentionPolicies()
    {
        return [
            'aircraft_positions' => [
                'duration_days' => 30,
                'downsampling' => [
                    ['interval' => '1 hour', 'retention' => 90],
                    ['interval' => '1 day', 'retention' => 365],
                    ['interval' => '1 week', 'retention' => 730]
                ]
            ],
            'radar_tracks' => [
                'duration_days' => 7,
                'downsampling' => [
                    ['interval' => '5 minutes', 'retention' => 30],
                    ['interval' => '1 hour', 'retention' => 90]
                ]
            ],
            'satellite_positions' => [
                'duration_days' => 60,
                'downsampling' => [
                    ['interval' => '30 minutes', 'retention' => 180],
                    ['interval' => '4 hours', 'retention' => 365]
                ]
            ],
            'weather_data' => [
                'duration_days' => 90,
                'downsampling' => [
                    ['interval' => '1 hour', 'retention' => 180],
                    ['interval' => '1 day', 'retention' => 730]
                ]
            ],
            'flight_trajectories' => [
                'duration_days' => 365,
                'downsampling' => [
                    ['interval' => '1 minute', 'retention' => 90],
                    ['interval' => '5 minutes', 'retention' => 365]
                ]
            ]
        ];
    }

    /**
     * Initialize time-series tables and indexes
     */
    public function initialize()
    {
        if ($this->isInitialized) {
            return true;
        }

        try {
            $this->logger->info("Initializing time-series database");

            // Create hypertables for time-series data
            $this->createHypertables();

            // Create continuous aggregates for downsampling
            $this->createContinuousAggregates();

            // Create optimized indexes
            $this->createTimeSeriesIndexes();

            // Set up retention policies
            $this->setupRetentionPolicies();

            $this->isInitialized = true;
            $this->logger->info("Time-series database initialized successfully");

            return true;

        } catch (Exception $e) {
            $this->logger->error("Failed to initialize time-series database", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Create hypertables for time-series data
     */
    private function createHypertables()
    {
        // These would be created using TimescaleDB extension in PostgreSQL
        // For now, we'll create optimized regular tables with time-based partitioning

        $hypertables = [
            'aircraft_positions_ts' => 'aircraft_positions',
            'radar_tracks_ts' => 'radar_tracks',
            'satellite_positions_ts' => 'satellite_positions',
            'weather_data_ts' => 'weather_data',
            'flight_trajectories_ts' => 'flight_trajectories'
        ];

        foreach ($hypertables as $hypertable => $baseTable) {
            $this->createHypertable($hypertable, $baseTable);
        }
    }

    /**
     * Create a hypertable for time-series data
     */
    private function createHypertable($tableName, $baseTable)
    {
        // In TimescaleDB, this would be: SELECT create_hypertable('table_name', 'time_column');
        // For regular PostgreSQL, we'll create partitioned tables

        $sql = "
            CREATE TABLE IF NOT EXISTS {$tableName} (
                time TIMESTAMPTZ NOT NULL,
                icao24 VARCHAR(6),
                callsign VARCHAR(8),
                latitude DECIMAL(10,6),
                longitude DECIMAL(10,6),
                altitude DECIMAL(7,1),
                speed DECIMAL(5,1),
                heading DECIMAL(5,1),
                vertical_rate DECIMAL(5,1),
                on_ground BOOLEAN,
                data_source VARCHAR(20),
                quality_score DECIMAL(3,2),
                metadata JSONB,
                created_at TIMESTAMPTZ DEFAULT NOW()
            ) PARTITION BY RANGE (time);
        ";

        $this->db->exec($sql);

        // Create initial partitions (current month + next 3 months)
        $this->createMonthlyPartitions($tableName);
    }

    /**
     * Create monthly partitions for time-series data
     */
    private function createMonthlyPartitions($tableName)
    {
        $currentDate = new DateTime();
        for ($i = 0; $i < 6; $i++) {
            $startDate = clone $currentDate;
            $startDate->modify("+{$i} months");
            $startDate->setDate($startDate->format('Y'), $startDate->format('m'), 1);
            $startDate->setTime(0, 0, 0);

            $endDate = clone $startDate;
            $endDate->modify('+1 month');

            $partitionName = $tableName . '_' . $startDate->format('Y_m');

            $sql = "
                CREATE TABLE IF NOT EXISTS {$partitionName}
                PARTITION OF {$tableName}
                FOR VALUES FROM ('{$startDate->format('Y-m-d H:i:sO')}')
                TO ('{$endDate->format('Y-m-d H:i:sO')}');
            ";

            try {
                $this->db->exec($sql);
            } catch (Exception $e) {
                // Partition might already exist
                $this->logger->info("Partition {$partitionName} may already exist");
            }
        }
    }

    /**
     * Create continuous aggregates for downsampling
     */
    private function createContinuousAggregates()
    {
        // Create downsampled views for different time intervals
        $this->createDownsampledView('aircraft_positions_ts', '5_minutes');
        $this->createDownsampledView('aircraft_positions_ts', '1_hour');
        $this->createDownsampledView('aircraft_positions_ts', '1_day');
    }

    /**
     * Create downsampled view for a specific interval
     */
    private function createDownsampledView($tableName, $interval)
    {
        $viewName = "{$tableName}_{$interval}_agg";

        $timeBucket = $this->getTimeBucketFunction($interval);

        $sql = "
            CREATE OR REPLACE VIEW {$viewName} AS
            SELECT
                {$timeBucket}(time) as bucket,
                icao24,
                callsign,
                AVG(latitude) as avg_latitude,
                AVG(longitude) as avg_longitude,
                AVG(altitude) as avg_altitude,
                AVG(speed) as avg_speed,
                AVG(heading) as avg_heading,
                MIN(altitude) as min_altitude,
                MAX(altitude) as max_altitude,
                COUNT(*) as sample_count,
                data_source,
                AVG(quality_score) as avg_quality
            FROM {$tableName}
            WHERE time >= NOW() - INTERVAL '30 days'
            GROUP BY {$timeBucket}(time), icao24, callsign, data_source
            ORDER BY bucket DESC;
        ";

        $this->db->exec($sql);
    }

    /**
     * Get time bucket function for different intervals
     */
    private function getTimeBucketFunction($interval)
    {
        switch ($interval) {
            case '5_minutes':
                return "date_trunc('hour', time) + INTERVAL '5 minute' * ROUND(EXTRACT(minute FROM time) / 5.0)";
            case '1_hour':
                return "date_trunc('hour', time)";
            case '1_day':
                return "date_trunc('day', time)";
            default:
                return "date_trunc('hour', time)";
        }
    }

    /**
     * Create optimized indexes for time-series queries
     */
    private function createTimeSeriesIndexes()
    {
        $indexes = [
            'aircraft_positions_ts' => [
                'CREATE INDEX idx_aircraft_positions_ts_time ON aircraft_positions_ts (time DESC)',
                'CREATE INDEX idx_aircraft_positions_ts_icao24_time ON aircraft_positions_ts (icao24, time DESC)',
                'CREATE INDEX idx_aircraft_positions_ts_location ON aircraft_positions_ts USING gist (point(longitude, latitude))',
                'CREATE INDEX idx_aircraft_positions_ts_callsign ON aircraft_positions_ts (callsign)',
                'CREATE INDEX idx_aircraft_positions_ts_altitude ON aircraft_positions_ts (altitude)',
                'CREATE INDEX idx_aircraft_positions_ts_source ON aircraft_positions_ts (data_source)'
            ],
            'radar_tracks_ts' => [
                'CREATE INDEX idx_radar_tracks_ts_time ON radar_tracks_ts (time DESC)',
                'CREATE INDEX idx_radar_tracks_ts_location ON radar_tracks_ts USING gist (point(longitude, latitude))'
            ],
            'satellite_positions_ts' => [
                'CREATE INDEX idx_satellite_positions_ts_time ON satellite_positions_ts (time DESC)',
                'CREATE INDEX idx_satellite_positions_ts_icao24_time ON satellite_positions_ts (icao24, time DESC)'
            ],
            'weather_data_ts' => [
                'CREATE INDEX idx_weather_data_ts_time ON weather_data_ts (time DESC)',
                'CREATE INDEX idx_weather_data_ts_location ON weather_data_ts USING gist (point(longitude, latitude))'
            ]
        ];

        foreach ($indexes as $table => $tableIndexes) {
            foreach ($tableIndexes as $indexSql) {
                try {
                    $this->db->exec($indexSql);
                } catch (Exception $e) {
                    // Index might already exist
                    $this->logger->info("Index may already exist for {$table}");
                }
            }
        }
    }

    /**
     * Set up retention policies
     */
    private function setupRetentionPolicies()
    {
        // In TimescaleDB, this would use add_retention_policy
        // For regular PostgreSQL, we'll create a cleanup function
        $this->createRetentionCleanupFunction();
    }

    /**
     * Create retention cleanup function
     */
    private function createRetentionCleanupFunction()
    {
        $sql = "
            CREATE OR REPLACE FUNCTION cleanup_old_data()
            RETURNS void AS $$
            BEGIN
                -- Clean up aircraft positions older than 30 days
                DELETE FROM aircraft_positions_ts
                WHERE time < NOW() - INTERVAL '30 days';

                -- Clean up radar tracks older than 7 days
                DELETE FROM radar_tracks_ts
                WHERE time < NOW() - INTERVAL '7 days';

                -- Clean up satellite positions older than 60 days
                DELETE FROM satellite_positions_ts
                WHERE time < NOW() - INTERVAL '60 days';

                -- Clean up weather data older than 90 days
                DELETE FROM weather_data_ts
                WHERE time < NOW() - INTERVAL '90 days';
            END;
            $$ LANGUAGE plpgsql;
        ";

        $this->db->exec($sql);
    }

    /**
     * Insert time-series data
     */
    public function insert($tableName, $data)
    {
        if (!$this->isInitialized) {
            $this->initialize();
        }

        try {
            // Handle single record or batch
            if (!isset($data[0])) {
                $data = [$data];
            }

            $this->bulkInsert($tableName, $data);
            return true;

        } catch (Exception $e) {
            $this->logger->error("Failed to insert time-series data", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Bulk insert time-series data
     */
    private function bulkInsert($tableName, $data)
    {
        if (empty($data)) {
            return;
        }

        $columns = ['time', 'icao24', 'callsign', 'latitude', 'longitude', 'altitude',
                   'speed', 'heading', 'vertical_rate', 'on_ground', 'data_source',
                   'quality_score', 'metadata'];

        $values = [];
        $params = [];
        $paramIndex = 1;

        foreach ($data as $record) {
            $rowValues = [];
            foreach ($columns as $column) {
                if (isset($record[$column])) {
                    $rowValues[] = '$' . $paramIndex;
                    $params[] = $record[$column];
                    $paramIndex++;
                } else {
                    $rowValues[] = 'NULL';
                }
            }
            $values[] = '(' . implode(',', $rowValues) . ')';
        }

        $sql = "INSERT INTO {$tableName} (" . implode(',', $columns) . ")
                VALUES " . implode(',', $values);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Query time-series data with time range
     */
    public function query($tableName, $startTime, $endTime, $filters = [], $aggregation = null)
    {
        try {
            $query = $this->buildTimeSeriesQuery($tableName, $startTime, $endTime, $filters, $aggregation);
            $stmt = $this->db->prepare($query['sql']);
            $stmt->execute($query['params']);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $this->logger->error("Failed to query time-series data", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Build time-series query
     */
    private function buildTimeSeriesQuery($tableName, $startTime, $endTime, $filters, $aggregation)
    {
        $params = [$startTime, $endTime];
        $whereClause = "WHERE time >= ? AND time <= ?";

        // Add filters
        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                $placeholders = str_repeat('?,', count($value) - 1) . '?';
                $whereClause .= " AND {$field} IN ({$placeholders})";
                $params = array_merge($params, $value);
            } else {
                $whereClause .= " AND {$field} = ?";
                $params[] = $value;
            }
        }

        if ($aggregation) {
            // Use downsampled view
            $viewName = "{$tableName}_{$aggregation['interval']}_agg";
            $sql = "SELECT * FROM {$viewName} {$whereClause} ORDER BY bucket DESC";
        } else {
            // Query raw data
            $sql = "SELECT * FROM {$tableName} {$whereClause} ORDER BY time DESC";
        }

        // Add LIMIT for performance
        $sql .= " LIMIT 10000";

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * Get aircraft trajectory for a specific time range
     */
    public function getAircraftTrajectory($icao24, $startTime, $endTime, $maxPoints = 1000)
    {
        $sql = "
            SELECT time, latitude, longitude, altitude, speed, heading
            FROM aircraft_positions_ts
            WHERE icao24 = ?
            AND time >= ?
            AND time <= ?
            ORDER BY time ASC
            LIMIT ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$icao24, $startTime, $endTime, $maxPoints]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get airspace traffic density
     */
    public function getTrafficDensity($bounds, $startTime, $endTime, $gridSize = 0.1)
    {
        $sql = "
            SELECT
                ROUND(latitude / ?) * ? as lat_grid,
                ROUND(longitude / ?) * ? as lon_grid,
                COUNT(*) as aircraft_count,
                AVG(altitude) as avg_altitude,
                MIN(time) as first_seen,
                MAX(time) as last_seen
            FROM aircraft_positions_ts
            WHERE time >= ?
            AND time <= ?
            AND latitude >= ?
            AND latitude <= ?
            AND longitude >= ?
            AND longitude <= ?
            GROUP BY lat_grid, lon_grid
            ORDER BY aircraft_count DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $gridSize, $gridSize,
            $gridSize, $gridSize,
            $startTime, $endTime,
            $bounds['lat_min'], $bounds['lat_max'],
            $bounds['lon_min'], $bounds['lon_max']
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get historical flight patterns
     */
    public function getFlightPatterns($icao24, $days = 30)
    {
        $sql = "
            SELECT
                DATE(time) as date,
                MIN(time) as first_seen,
                MAX(time) as last_seen,
                COUNT(*) as position_count,
                AVG(speed) as avg_speed,
                AVG(altitude) as avg_altitude,
                ST_MakeLine(ST_Point(longitude, latitude) ORDER BY time) as flight_path
            FROM aircraft_positions_ts
            WHERE icao24 = ?
            AND time >= NOW() - INTERVAL '? days'
            GROUP BY DATE(time)
            ORDER BY date DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$icao24, $days]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics()
    {
        $metrics = [];

        // Table sizes
        $tables = ['aircraft_positions_ts', 'radar_tracks_ts', 'satellite_positions_ts', 'weather_data_ts'];
        foreach ($tables as $table) {
            $metrics[$table] = $this->getTableMetrics($table);
        }

        // Query performance
        $metrics['query_performance'] = $this->getQueryPerformanceMetrics();

        return $metrics;
    }

    /**
     * Get table metrics
     */
    private function getTableMetrics($tableName)
    {
        $sql = "
            SELECT
                schemaname,
                tablename,
                attname,
                n_distinct,
                correlation
            FROM pg_stats
            WHERE tablename = ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tableName]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get query performance metrics
     */
    private function getQueryPerformanceMetrics()
    {
        // This would typically query PostgreSQL's pg_stat_statements
        // For now, return basic metrics
        return [
            'avg_query_time' => 0.05,
            'total_queries' => 1000,
            'slow_queries' => 5,
            'cache_hit_ratio' => 0.95
        ];
    }

    /**
     * Optimize database for time-series workloads
     */
    public function optimize()
    {
        try {
            $this->logger->info("Optimizing time-series database");

            // Vacuum analyze tables
            $this->vacuumAnalyzeTables();

            // Reindex tables
            $this->reindexTables();

            // Update statistics
            $this->updateStatistics();

            $this->logger->info("Time-series database optimization completed");

            return true;

        } catch (Exception $e) {
            $this->logger->error("Failed to optimize time-series database", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Vacuum analyze tables
     */
    private function vacuumAnalyzeTables()
    {
        $tables = ['aircraft_positions_ts', 'radar_tracks_ts', 'satellite_positions_ts', 'weather_data_ts'];

        foreach ($tables as $table) {
            $this->db->exec("VACUUM ANALYZE {$table}");
        }
    }

    /**
     * Reindex tables
     */
    private function reindexTables()
    {
        $tables = ['aircraft_positions_ts', 'radar_tracks_ts', 'satellite_positions_ts', 'weather_data_ts'];

        foreach ($tables as $table) {
            $this->db->exec("REINDEX TABLE {$table}");
        }
    }

    /**
     * Update statistics
     */
    private function updateStatistics()
    {
        $this->db->exec("ANALYZE");
    }

    /**
     * Clean up old data based on retention policies
     */
    public function cleanup()
    {
        try {
            $this->logger->info("Running time-series data cleanup");

            // Call the cleanup function
            $this->db->exec("SELECT cleanup_old_data()");

            $this->logger->info("Time-series data cleanup completed");

            return true;

        } catch (Exception $e) {
            $this->logger->error("Failed to cleanup time-series data", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get database status
     */
    public function getStatus()
    {
        return [
            'initialized' => $this->isInitialized,
            'tables' => $this->getTableStatus(),
            'performance' => $this->getPerformanceMetrics(),
            'retention_policies' => $this->retentionPolicies,
            'last_cleanup' => $this->getLastCleanupTime()
        ];
    }

    /**
     * Get table status
     */
    private function getTableStatus()
    {
        $tables = ['aircraft_positions_ts', 'radar_tracks_ts', 'satellite_positions_ts', 'weather_data_ts'];

        $status = [];
        foreach ($tables as $table) {
            $sql = "SELECT COUNT(*) as record_count, MIN(time) as oldest_record, MAX(time) as newest_record FROM {$table}";
            $stmt = $this->db->query($sql);
            $status[$table] = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $status;
    }

    /**
     * Get last cleanup time
     */
    private function getLastCleanupTime()
    {
        // This would typically be stored in a metadata table
        return date('Y-m-d H:i:s', strtotime('-1 hour'));
    }
}
