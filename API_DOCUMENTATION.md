# ABED IDM Hub - API Documentation

## Overview

The ABED IDM Hub provides RESTful APIs for managing all system resources. All endpoints require authentication via session and return JSON responses.

## Base URL

```
/api/
```

## Authentication

All API endpoints require an active PHP session with `$_SESSION['user_id']` set.

---

## Projects API

### List Projects

**Endpoint:** `/api/projects.php?action=list`
**Methods:** GET
**Parameters:**

- `type` (string) - Project type: fspf, idp, afme
- `year` (int) - Filter by year
- `status` (string) - Filter by status/stage
- `location` (string) - Filter by location
- `limit` (int) - Max records (default: 100, max: 500)
- `offset` (int) - Pagination offset

**Example:**

```
GET /api/projects.php?action=list&type=fspf&year=2026&status=Implementation
```

**Response:**

```json
[
  {
    "id": 1,
    "project_code": "FSPF-2026-001",
    "project_title": "Project Name",
    "municipality": "Location",
    "proposed_amount": 1000000,
    "allocated_amount": 800000,
    "current_stage": "Implementation",
    "created_date": "2026-01-15",
    "updated_at": "2026-02-20"
  }
]
```

---

### Get Project Details

**Endpoint:** `/api/projects.php?action=detail`
**Methods:** GET
**Parameters:**

- `type` (string) - Project type: fspf, idp, afme (required)
- `id` (int) - Project ID (required)

**Example:**

```
GET /api/projects.php?action=detail&type=fspf&id=5
```

---

### Create Project

**Endpoint:** `/api/projects.php?action=create`
**Methods:** POST
**Parameters (Form Data):**

- `type` (string) - fspf, idp, afme (required)
- `project_code` (string) - Unique project code (required)
- `project_title` (string) - Project title (required)
- `province` (string) - Province
- `municipality` (string) - Municipality
- `barangay` (string) - Barangay
- `proposed_amount` (float) - Proposed budget
- `allocated_amount` (float) - Allocated budget

---

### Update Project

**Endpoint:** `/api/projects.php?action=update`
**Methods:** PUT
**Parameters:**

- `type` (string) - Project type
- `id` (int) - Project ID

---

### Progress Update Project

**Endpoint:** `/api/projects.php?action=update-progress`
**Methods:** POST
**Parameters (Form Data):**

- `type` (string) - Project type
- `id` (int) - Project ID
- `physical_progress` (float) - Physical progress percentage (0-100)
- `financial_progress` (float) - Financial progress percentage (0-100)

---

### Change Project Stage

**Endpoint:** `/api/projects.php?action=stage`
**Methods:** PUT
**JSON Body:**

```json
{
  "type": "fspf",
  "id": 1,
  "stage": "Implementation"
}
```

---

### Archive Project

**Endpoint:** `/api/projects.php?action=archive` or `DELETE /api/projects.php`
**Methods:** DELETE
**Parameters:**

- `id` (int) - Project ID
- `type` (string) - Project type

---

### Detect Duplicates

**Endpoint:** `/api/projects.php?action=duplicates`
**Methods:** GET
**Parameters:**

- `type` (string) - Project type
- `search` (string) - Search query (title/code)

---

## Machinery API

### List Machinery

**Endpoint:** `/api/machinery.php?action=list`
**Methods:** GET
**Parameters:**

- `afme_id` (int) - Filter by AFME project ID
- `status` (string) - Filter by status
- `type` (string) - Filter by machinery type
- `limit` (int) - Max records (default: 100)
- `offset` (int) - Pagination offset

---

### Get Machinery Details

**Endpoint:** `/api/machinery.php?action=detail`
**Methods:** GET
**Parameters:**

- `id` (int) - Machinery ID

---

### Create Machinery

**Endpoint:** `/api/machinery.php?action=create`
**Methods:** POST
**Parameters (Form Data):**

- `afme_project_id` (int) - AFME Project ID (required)
- `machinery_type` (string) - Type of machinery (required)
- `unit_quantity` (int) - Number of units (required)
- `unit_cost` (float) - Cost per unit (required)
- `specifications` (string) - Technical specifications

---

### Update Machinery

**Endpoint:** `/api/machinery.php?action=update`
**Methods:** PUT
**Parameters:**

- `id` (int) - Machinery ID

---

### Validate Machinery

**Endpoint:** `/api/machinery.php?action=validate`
**Methods:** PUT
**JSON Body:**

```json
{
  "id": 1,
  "validation_status": "Approved",
  "validator_remarks": "Machinery specs verified"
}
```

---

### Delete/Archive Machinery

**Endpoint:** `/api/machinery.php?action=delete`
**Methods:** DELETE
**Parameters:**

- `id` (int) - Machinery ID

---

## Financial Tracking API

### List Financial Records

**Endpoint:** `/api/financial.php?action=list`
**Methods:** GET
**Parameters:**

- `project_id` (int) - Filter by project
- `project_type` (string) - fspf, idp, afme
- `status` (string) - Filter by status
- `limit` (int) - Max records
- `offset` (int) - Pagination offset

---

### Get Financial Summary

**Endpoint:** `/api/financial.php?action=summary`
**Methods:** GET
**Parameters:**

- `project_id` (int) - Optional, get aggregate if not provided
- `project_type` (string) - Project type

**Response:**

```json
{
  "total_obligations": 1000000,
  "total_disbursed": 750000,
  "total_liquidated": 500000,
  "disbursement_rate": 75.0,
  "liquidation_rate": 66.67
}
```

---

### Create Financial Record

**Endpoint:** `/api/financial.php?action=create`
**Methods:** POST
**Parameters (Form Data):**

- `fspf_project_id` or `idp_project_id` or `afme_project_id` (int) - Project ID (required)
- `record_type` (string) - Obligation, Disbursement, Liquidation (required)
- `amount` (float) - Amount (required)
- `reference_number` (string) - Optional
- `particulars` (string) - Optional

---

### Update Financial Record

**Endpoint:** `/api/financial.php?action=update`
**Methods:** PUT
**Parameters:**

- `id` (int) - Record ID

---

### Delete Financial Record

**Endpoint:** `/api/financial.php?action=delete`
**Methods:** DELETE
**Parameters:**

- `id` (int) - Record ID

---

## Documents API

### List Documents

**Endpoint:** `/api/documents.php?action=list`
**Methods:** GET
**Parameters:**

- `project_id` (int) - Filter by project
- `project_type` (string) - fspf, idp, afme
- `document_type` (string) - Filter by type
- `limit` (int) - Max records
- `offset` (int) - Pagination offset

---

### Upload Document

**Endpoint:** `/api/documents.php?action=upload`
**Methods:** POST (multipart/form-data)
**Parameters:**

- `document` (file) - File to upload (required)
- `project_id` (int) - Project ID (required)
- `project_type` (string) - fspf, idp, afme (required)
- `document_type` (string) - Document classification
- `description` (string) - Optional description

**Allowed File Types:**

- PDF, Image (JPEG, PNG, GIF), Word, Excel

**Max File Size:** 10MB (configurable in database.php)

---

### Download Document

**Endpoint:** `/api/documents.php?action=download&id=DOC_ID`
**Methods:** GET
**Parameters:**

- `id` (int) - Document ID

---

### Preview Document

**Endpoint:** `/api/documents.php?action=preview&id=DOC_ID`
**Methods:** GET
**Parameters:**

- `id` (int) - Document ID

---

### Delete Document

**Endpoint:** `/api/documents.php?action=delete&id=DOC_ID`
**Methods:** DELETE
**Parameters:**

- `id` (int) - Document ID

---

## Error Handling

All endpoints return errors in this format:

```json
{
  "error": "Error message description"
}
```

**HTTP Status Codes:**

- 200 - Success
- 400 - Bad Request / Invalid Parameters
- 401 - Unauthorized (No Session)
- 404 - Resource Not Found
- 405 - Method Not Allowed

---

## Response Format

All successful responses return data or a success message:

```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {} or []
}
```

---

## Usage Examples

### JavaScript (Fetch)

**List FSPF Projects from 2026:**

```javascript
fetch("/api/projects.php?action=list&type=fspf&year=2026")
  .then((r) => r.json())
  .then((data) => console.log(data));
```

**Create Project:**

```javascript
const formData = new FormData();
formData.append("type", "fspf");
formData.append("project_code", "FSPF-2026-001");
formData.append("project_title", "My Project");
formData.append("proposed_amount", "1000000");

fetch("/api/projects.php?action=create", {
  method: "POST",
  body: formData,
})
  .then((r) => r.json())
  .then((data) => console.log(data));
```

**Update Project Stage:**

```javascript
fetch("/api/projects.php?action=stage", {
  method: "PUT",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({
    type: "fspf",
    id: 1,
    stage: "Implementation",
  }),
})
  .then((r) => r.json())
  .then((data) => console.log(data));
```

**Upload Document:**

```javascript
const formData = new FormData();
formData.append("document", fileInput.files[0]);
formData.append("project_id", "1");
formData.append("project_type", "fspf");
formData.append("document_type", "Proposal");

fetch("/api/documents.php?action=upload", {
  method: "POST",
  body: formData,
})
  .then((r) => r.json())
  .then((data) => console.log(data));
```

---

## Rate Limiting

No rate limiting currently implemented. Production deployment should add rate limiting for API security.

## Pagination

For large datasets, use `limit` and `offset` parameters:

```
?action=list&limit=50&offset=100  // Returns records 100-150
```

## Filtering Best Practices

- Always specify `project_type` when querying projects or related resources
- Use exact values for enums (status, stage, type)
- For text searches, wildcards are automatically added
- Dates should be in ISO format (YYYY-MM-DD)
