# ADR 0003: Server-authoritative application data

PostgreSQL behind Laravel is the sole application database. Mobile keeps short-lived query cache and SecureStore session material. Food and settings writes require connectivity and show errors with safe retry. No SQLite mirror or offline write queue is planned for the first release. HealthKit date ranges can be queried and uploaded again after a network failure.
