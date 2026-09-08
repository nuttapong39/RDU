# ARCHITECTURE
## RDU Dashboard — System Architecture

---

## Final System Architecture

```
                         ┌─────────────────────┐
                         │      Browser        │
                         └──────────┬──────────┘
                                    │
                                    ▼
                         ┌─────────────────────┐
                         │       Apache        │
                         │       XAMPP         │
                         └──────────┬──────────┘
                                    │
                                    ▼
                         ┌─────────────────────┐
                         │      Laravel        │
                         │                     │
                         │ Authentication      │
                         │ Authorization       │
                         │ Controllers         │
                         │ Services            │
                         │ Query Layer         │
                         └───────┬───────┬─────┘
                                 │       │
                    Application  │       │ RDU Read Only
                       DB        │       │
                                 ▼       ▼
                        ┌──────────┐  ┌──────────────────┐
                        │PostgreSQL│  │ HOSxP /          │
                        │ RDU DB   │  │ PostgreSQL       │
                        │          │  │ SELECT ONLY      │
                        └──────────┘  └──────────────────┘
```

---

## Database Security Architecture

```
HOSxP / Hospital Database
            │
            │
            ▼
     READ-ONLY DB USER
            │
            │ SELECT ONLY
            ▼
      Laravel Application
            │
            ▼
     Service / Query Layer
            │
            ▼
       Cache
            │
            ▼
       Dashboard
```

---

## Data Flow — Dashboard

```
HOSxP
  ↓
SELECT ONLY
  ↓
RduDataSource
  ↓
RduQueryService
  ↓
RduCalculationService
  ↓
Cache
  ↓
Controller
  ↓
Blade
  ↓
Chart.js
```

---

## Data Flow — Configuration

```
Admin
  ↓
Database Configuration Page
  ↓
Form Request
  ↓
DatabaseConfigService
  ↓
Validate
  ↓
Encrypt Password
  ↓
JSON
  ↓
Test Connection
  ↓
Verify Read-Only
  ↓
Save
  ↓
Audit Log
```

---

## Defensive Database Design (Multi-Layer)

```
Application
      ↓
Laravel Permission
      ↓
Read-Only DB User
      ↓
Database Permission
```

แม้ Developer จะเขียน Code ผิดและพยายาม `DB::connection('rdu')->update(...)` — Database Server ต้อง Reject Operation

---

## Two Layers of Protection

### Layer 1 — Laravel
```
Readonly Model
Query Guard
Service Architecture
Tests
```

### Layer 2 — PostgreSQL
```
SELECT ONLY
```

หาก Layer 1 ผิดพลาด → PostgreSQL ต้องยังคง Block Write

---

## Database Connection Structure

```
default
    ↓
RDU Application Database

rdu
    ↓
HOSxP Read-Only Database
```

```php
DB::connection('pgsql')   // Application DB
DB::connection('rdu')     // HOSxP (Read-Only)
```

---

## Directory Structure

```
app/
├── DataSources/
│   ├── RduDataSourceInterface.php
│   ├── MockRduDataSource.php
│   └── HosxpRduDataSource.php
│
├── Database/
│   ├── ReadonlyConnection.php
│   └── RduDatabaseConnectionFactory.php
│
├── Models/
│   ├── RduIndicator.php
│   ├── RduIndicatorResult.php
│   ├── AuditLog.php
│   └── Hosxp/
│       ├── ReadonlyHosxpModel.php
│       ├── HosxpPatient.php
│       ├── HosxpVisit.php
│       ├── HosxpDrug.php
│       └── HosxpDiagnosis.php
│
├── Services/
│   ├── DatabaseConfigService.php
│   ├── RduQueryService.php
│   ├── RduCalculationService.php
│   └── RduDatabaseConnectionFactory.php
│
├── Http/
│   ├── Controllers/
│   │   └── Admin/
│   │       └── DatabaseConfigController.php
│   │
│   └── Requests/
│       └── DatabaseConfigRequest.php
│
└── Exceptions/
    └── ReadonlyDatabaseException.php
```

---

## XAMPP Architecture

```
Browser
   ↓
Apache
   ↓
Laravel
   ↓
PHP 8.3+
   ↓
PostgreSQL
```
