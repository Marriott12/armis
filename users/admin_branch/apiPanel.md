# API Access

You can access analytics programmatically via the API.

**Example: Get units staff count as JSON**
```
GET /path/to/api.php?report=units
```

**With filters:**
```
GET /path/to/api.php?report=units&unitID=1,2&rankID=3
```

**Supported reports:**  
- `units`
- `ranks`
- `courses`
- `data_quality`

**Authentication:**  
You must be logged in with sufficient permissions.

See the API docs or contact your admin for more.