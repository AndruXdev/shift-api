 # Shift API

## Architecture & Key Design Decisions

The application follows a simple layered Laravel architecture. The controller handles HTTP concerns and delegates shift creation to a dedicated ShiftCreationService, while StoreShiftRequest handles input validation and ShiftResource defines the API response format. Business rules such as time ordering, maximum shift duration, overlapping shifts, and public holiday validation are kept outside the controller, making the code easier to test and maintain.

MySQL is used as the primary application database, while SQLite in-memory is used for automated tests to provide fast and isolated test execution. Shift creation is performed inside a database transaction. A dedicated PublicHolidaysService integrates with the Nager API and caches holiday data by country and year to reduce external requests. External API failures are translated into a 503 Service Unavailable response rather than allowing a shift to be created without verifying the holiday restriction.

Successful shift creation also triggers a Laravel Notification. For this assignment, the notification is delivered by email and uses Mailpit during local development. The notification recipient is configured through an environment variable rather than introducing an employee/contact model that is outside the scope of the assignment.

Total time spent for implementation, setting up local enviromment, documentation - ~4 hours.

## Known Trade-offs & Limitations

The implementation intentionally keeps the domain model small because the assignment only requires creating shifts. There is no employee management, authentication/authorization, shift update/delete functionality, pagination, or broader scheduling domain model.

The overlap check is implemented at the application level. This is simple and readable, but under high concurrency two requests could theoretically pass the overlap check simultaneously before either shift is persisted. The current database transaction does not completely eliminate that race condition.

The public holiday API is an external dependency. Caching reduces latency and API usage, but the application still depends on the availability and correctness of that service when cache expires or a previously uncached year is requested. The current implementation fails creation when the service is unavailable, meaning the shift is not created rather than risking creation on a holiday.

Notifications are currently sent synchronously after successful creation. This keeps the implementation simple for the assignment, but email delivery could increase API response time and a temporary mail provider failure could affect the request.

## Production-Ready Evolution

In a production environment, I would introduce authentication and authorization, a proper employee/domain model, and stronger database-level protection for scheduling conflicts. Depending on the concurrency requirements, overlap detection could use appropriate locking or a database-specific constraint/locking strategy.

Notifications should be queued using Laravel’s queue system so email delivery does not block the API request. A durable queue such as Redis or a managed queue service would be preferable, with retries, failure handling, and monitoring.

The public holiday integration would benefit from stronger resilience patterns such as configurable timeouts, retries with backoff, monitoring, and potentially a more durable holiday-data cache or local data source. The external dependency should also be observable so failures and unusual response patterns can be detected quickly.

For a production deployment, MySQL would typically be a managed/high-availability database with automated backups, migrations executed through the deployment pipeline, and appropriate indexing based on real workload. The application would also require structured logging, metrics, distributed tracing where appropriate, centralized error monitoring, rate limiting, secrets management, health checks, and CI/CD with separate environments.

The current architecture deliberately avoids these additional components because they would add operational complexity without providing significant value for the scope of the assignment. The main design goal is to keep the implementation small while maintaining clear separation between HTTP handling, validation, business rules, persistence, external integrations, and notifications.

## Setup

1. Clone the repository and open the project directory:

	```bash
	git clone <repository-url>
	cd shift-api
	```

2. Install Docker Desktop

3. Create a local environment file from the provided example, if available:

	```bash
	cp .env.example .env
	```

4. Install dependencies:
    ```bash
    docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install
    ```

4. Run Sail command to build containers:

    ```bash
	./vendor/bin/sail up -d
	```

5. Generate application key:

    ``` bash
    ./vendor/bin/sail artisan key:generate
    ```

6. Run migration scripts to create tables

    ``` bash
    ./vendor/bin/sail artisan migrate
    ```

7. Configure notification email
   In .env. set:
   ``` .env
   SHIFT_NOTIFICATION_EMAIL=developer_email
   ```
   For local environemnt, your Mailpit configuration should be:
   ``` .env
    MAIL_MAILER=smtp
    MAIL_SCHEME=null
    MAIL_HOST=mailpit
    MAIL_PORT=1025
    MAIL_USERNAME=null
    MAIL_PASSWORD=null
    MAIL_FROM_ADDRESS="hello@example.com"
    MAIL_FROM_NAME="${APP_NAME}"
    ```

    Then restart/reload configuration:
    ``` bash
    ./vendor/bin/sail artisan config:clear
    ```
8. Open Mailpit

Mailpit is include in Docker setup and you can open it on http://localhost:8025

When shift is successfully created, the notification meail should appear there.

9. Run tests

    ``` bash
    ./vendor/bin/sail artisan test
    ```

    You should see all tests passing. 
    For example:
    ``` output
    Tests:    25 passed (83 assertions)
    Duration: 0.7s
    ```

10. Test API

API is accessible on http://localhost/api/shifts
Use Postman or curl to send POST request.
You should get 201 Created returned in the response.
