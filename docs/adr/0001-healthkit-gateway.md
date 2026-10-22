# ADR 0001: iPhone HealthKit gateway

The iPhone alone reads HealthKit through a future local Expo Swift module. The backend accepts dated snapshots and source identifiers; it never queries Apple Health directly. Daily cumulative metrics will use HealthKit statistics queries to avoid cross-device double counting. Sync retries will upsert by stable source identity. Sleep overlaps will be merged and assigned to the local wake date. Background delivery is opportunistic; a 22:30 notification opens review and triggers sync.
