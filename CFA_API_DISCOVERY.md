# CFA Fire Danger API Discovery Documentation

## Executive Summary

This document details the reverse-engineering of the Country Fire Authority (CFA) Victoria's internal fire danger rating API. Through analysis of the CFA website's JavaScript, we successfully identified the API endpoint, request format, and response structure. However, the API is protected by bot detection/IP blocking that prevents server-side access.

**Status:** API discovered and validated ✅ | Server access blocked ❌

---

## API Endpoint Discovery

### Background
The CFA website (https://www.cfa.vic.gov.au/warnings-restrictions/fire-bans-ratings-and-restrictions/total-fire-bans-fire-danger-ratings/) loads fire danger ratings dynamically via JavaScript after page load. Static HTML scraping fails because rating elements are empty placeholders:

```html
<span class="fdr-rating d-block bold smallest uppercase"></span>
```

### Discovery Process

1. **JavaScript Analysis**
   - Examined: `/cfa/include/js/cfafirebansandratingsdistrictforecast.js`
   - Found API call in `loadFireBansandRatingsByDistrict()` function (line 61-88)

2. **Key Files Analyzed**
   - `cfafirebansandratingsdistrictforecast.js` - Main API consumer
   - `cfafirebansandratingscommon.js` - Date formatting utilities

---

## API Specification

### Endpoint
```
POST https://www.cfa.vic.gov.au/api/cfa/tfbfdr/district
```

### Request Headers
```http
Content-Type: application/json
```

### Request Payload
```json
{
  "IssueDate": "2025-10-01 00:00:00",
  "DistrictName": "North Central",
  "AdminEmailAddress": "digitalworkflow@cfa.vic.gov.au"
}
```

### Field Specifications

#### IssueDate
- **Format:** `YYYY-MM-DD HH:MM:SS`
- **Function:** Date formatting extracted from `jsonDateFormat()`:
  ```javascript
  function jsonDateFormat(dt) {
      var sMonth = padValue(dt.getMonth() + 1);
      var sDay = padValue(dt.getDate());
      var sYear = dt.getFullYear();
      var sHour = dt.getHours();
      var sMinute = padValue(dt.getMinutes());
      var sSeconds = padValue(dt.getSeconds());
      return sYear + "-" + sMonth + "-" + sDay + " " + sHour + ":" + sMinute + ":" + sSeconds;
  }
  ```
- **Example:** `"2025-10-01 00:00:00"`

#### DistrictName
- **Format:** Proper case string (not URL slug)
- **Source:** Extracted from hidden field `#hdDistrictName` on district pages
- **District Mapping:**

| URL Slug | API District Name |
|----------|------------------|
| `central-fire-district` | `Central` |
| `mallee-fire-district` | `Mallee` |
| `north-central-fire-district` | `North Central` |
| `north-east-fire-district` | `North East` |
| `northern-country-fire-district` | `Northern Country` |
| `south-west-fire-district` | `South West` |
| `west-and-south-gippsland-fire-district` | `West and South Gippsland` |
| `wimmera-fire-district` | `Wimmera` |

#### AdminEmailAddress
- **Value:** `"digitalworkflow@cfa.vic.gov.au"`
- **Source:** Extracted from hidden field `#hdFBRDistrictAdminEmail`

---

## API Response

### Success Response (HTTP 200)
```json
[
  {
    "IssueDate": "2025-10-01T00:00:00",
    "IssueAt": "2025-10-01T05:30:00",
    "ForeCastInformation": "Today, Wed, 1 Oct 2025 is not currently a day of Total Fire Ban.",
    "DistrictName": "North Central",
    "DistrictData": "NO - RESTRICTIONS MAY APPLY",
    "DistrictRating": "MODERATE",
    "Status": "N",
    "ArticleURL": null,
    "FDPArea": null,
    "AdminEmailAddress": null,
    "WSFireRestrictionsResponse": [
      {
        "name": "CENTRAL GOLDFIELDS",
        "dtRestrictionStart": "2024-11-11T00:00:00",
        "dtRestrictionStop": "2025-04-22T00:00:00",
        "friendlyName": "Central goldfields",
        "status": "NOT ACTIVE",
        "notActiveStatusMessage": "No restrictions in force yet - check back for updates",
        "fdrDistrict": "North Central"
      }
    ]
  }
]
```

### Key Response Fields

| Field | Description | Example Values |
|-------|-------------|----------------|
| `DistrictRating` | Fire danger rating | `"MODERATE"`, `"HIGH"`, `"EXTREME"`, `"CATASTROPHIC"`, `"LOW-MODERATE"`, `"NO RATING"` |
| `DistrictData` | Total Fire Ban status and restrictions | `"YES - ..."` (TFB active), `"NO - ..."` (no TFB) |
| `ForeCastInformation` | Human-readable forecast message | `"Today is not currently a day of Total Fire Ban."` |
| `IssueAt` | When forecast was issued | `"2025-10-01T05:30:00"` |
| `WSFireRestrictionsResponse` | Municipality-level fire restrictions | Array of municipality objects |

### Total Fire Ban Detection
```javascript
// TFB is active if DistrictData starts with "YES"
if (districtData.indexOf('YES') === 0) {
    totalFireBan = true;
}
```

---

## Validation Test Results

### Successful Local Test
```bash
curl -X POST -H "Content-Type: application/json" \
  "https://www.cfa.vic.gov.au/api/cfa/tfbfdr/district" \
  -d '{
    "IssueDate":"2025-10-01 00:00:00",
    "DistrictName":"North Central",
    "AdminEmailAddress":"digitalworkflow@cfa.vic.gov.au"
  }'
```

**Result:** ✅ HTTP 200 - Returns full forecast data including `"DistrictRating": "MODERATE"`

### Server-Side Test (PHP/Replit)
```php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://www.cfa.vic.gov.au/api/cfa/tfbfdr/district');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'IssueDate' => '2025-10-01 00:00:00',
    'DistrictName' => 'North Central',
    'AdminEmailAddress' => 'digitalworkflow@cfa.vic.gov.au'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
```

**Result:** ❌ HTTP 403 Forbidden - Access blocked from server environment

---

## Access Restrictions

### Bot Detection / IP Blocking
The API successfully responds to:
- ✅ Local machine requests
- ✅ Browser-based requests
- ❌ Cloud server requests (Replit, AWS, etc.)
- ❌ Automated PHP/Python requests from servers

### Blocking Mechanism
- Likely IP-based geofencing or reputation scoring
- May include User-Agent analysis
- Standard HTTP headers do not bypass the block
- Same request parameters work locally but fail from servers

### Evidence
```
Local curl:  HTTP 200 ✅
Replit PHP:  HTTP 403 ❌
(Same endpoint, same payload, same headers)
```

---

## Technical Constraints

### Why Static Scraping Fails
1. **JavaScript Rendering Required:** Ratings loaded client-side after page load
2. **Empty HTML Placeholders:** Server-side HTML contains no rating data
3. **Dynamic API Calls:** Page JavaScript makes POST requests after DOM ready

### Why Direct API Access Fails
1. **403 Forbidden:** CFA blocks server-origin requests
2. **IP Blocking:** Likely based on cloud provider IP ranges
3. **Bot Detection:** Automated tools detected and blocked

---

## Alternative Solutions

### Option 1: Headless Browser Microservice (Recommended)
**Approach:** Run headless Chrome/Playwright to execute JavaScript and extract ratings

**Pros:**
- Bypasses bot detection (appears as real browser)
- Executes JavaScript to get rendered data
- Can use IP rotation services

**Cons:**
- Requires external infrastructure (Apify, Browserless, etc.)
- Additional costs and maintenance
- More complex architecture

**Implementation:**
```javascript
// Pseudocode for headless browser service
async function getCFAData(district) {
  const page = await browser.newPage();
  await page.goto(`https://www.cfa.vic.gov.au/.../$(district)`);
  await page.waitForSelector('.fdr-rating');
  const rating = await page.$eval('.fdr-rating', el => el.textContent);
  return { rating };
}
```

### Option 2: Official API Access (Best Long-term)
**Approach:** Contact CFA for official API access or IP whitelisting

**Contact:**
- Email: `digitalworkflow@cfa.vic.gov.au`
- Request: API access for WordPress plugin displaying fire danger ratings

**Pros:**
- Official, supported access
- No blocking issues
- Free and reliable

**Cons:**
- May take time to arrange
- Approval not guaranteed

### Option 3: Alternative Data Source
**Bureau of Meteorology (BOM):**
- URL: `http://www.bom.gov.au/fwo/IDV60801/IDV60801.99817.json`
- Updates: Every 10 minutes
- Coverage: Portable AWS stations operated by CFA

**Limitation:** District mapping doesn't match CFA's exact fire districts

---

## Code Examples

### Working cURL Request (Local)
```bash
TODAY=$(date +"%Y-%m-%d 00:00:00")

curl -X POST \
  -H "Content-Type: application/json" \
  -d "{
    \"IssueDate\":\"$TODAY\",
    \"DistrictName\":\"North Central\",
    \"AdminEmailAddress\":\"digitalworkflow@cfa.vic.gov.au\"
  }" \
  https://www.cfa.vic.gov.au/api/cfa/tfbfdr/district
```

### PHP Implementation (Blocked on Servers)
```php
function fetch_cfa_rating($district_name, $date) {
    $api_url = 'https://www.cfa.vic.gov.au/api/cfa/tfbfdr/district';
    
    $payload = json_encode([
        'IssueDate' => $date,
        'DistrictName' => $district_name,
        'AdminEmailAddress' => 'digitalworkflow@cfa.vic.gov.au'
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        return json_decode($response, true);
    }
    
    return null; // Returns 403 on servers
}
```

### Multi-Day Forecast Request
```php
// Fetch 4-day forecast
for ($i = 0; $i < 4; $i++) {
    $date = date('Y-m-d H:i:s', strtotime("+$i days"));
    $data = fetch_cfa_rating('North Central', $date);
    // Process $data[0]['DistrictRating']
}
```

---

## JavaScript Source Code Reference

### API Call Function (from cfafirebansandratingsdistrictforecast.js)
```javascript
loadFireBansandRatingsByDistrict: function (issueDate, districtName, day) {
    var objFirebansandratings = {};
    objFirebansandratings.IssueDate = cfafirebansandratingscommon.jsonDateFormat(new Date(issueDate));
    objFirebansandratings.DistrictName = districtName;
    objFirebansandratings.AdminEmailAddress = $('#hdFBRDistrictAdminEmail').val();

    var jsonString = JSON.stringify(objFirebansandratings);
    url = "/api/cfa/tfbfdr/district";

    $.ajax({
        type: "POST",
        url: url,
        data: jsonString,
        dataType: 'json',
        contentType: "application/json; charset=utf-8",
        async: false,
        success: function (d, s, x) {
            if (d) {
                cfafirebansandratingsdistrictforecast.displayResultsByDistrict(d, day, issueDate);
            }
        },
        error: function (x, s, e) {
            cfafirebansandratingsdistrictforecast.displayMessage("This service is currently unavailable");
        }
    });
}
```

### Response Processing (from cfafirebansandratingsdistrictforecast.js)
```javascript
displayResultsByDistrict: function (response, day, issueDate) {
    if (response !== null) {
        if (response.length > 0) {
            $.each(response, function (idx, val) {
                // Display fire danger rating
                if (val.DistrictRating !== '' && val.DistrictRating.toLowerCase() !== 'no forecast') {
                    $('#fdr-forecast .fdrRating').addClass(val.DistrictRating.replace(/ /g, '').toLowerCase());
                    $('#fdr-forecast .fdrRating').html(val.DistrictRating.toUpperCase());
                } else {
                    $('#fdr-forecast .fdrRating').addClass("no-rating");
                    $('#fdr-forecast .fdrRating').html("NO RATING");
                }
                
                // Check for Total Fire Ban
                if (districtDataProper !== '') {
                    _statusAry = districtDataProper.split('-');
                    if (_statusAry[0] !== '') {
                        _status = _statusAry[0].substring(0, 3);
                        _status = _status.trim();
                    }
                }
                if (_status.toLowerCase() === 'yes') {
                    $('#imgTFBIcon').show();
                } else {
                    $('#imgTFBIcon').hide();
                }
            });
        }
    }
}
```

---

## Recommendations

### Immediate Action
1. **Document this API discovery** for future reference ✅
2. **Contact CFA** requesting official API access or IP whitelisting
3. **Email template** (draft below)

### Short-term Solution
- Implement headless browser microservice if official access unavailable
- Use service like Apify or Browserless with IP rotation
- Cache results aggressively (6 AM and 6 PM Melbourne time updates)

### Long-term Solution
- Establish official partnership with CFA for API access
- Contribute to open data initiatives for fire safety information
- Support CFA's digital infrastructure goals

---

## Contact Template for CFA

```
To: digitalworkflow@cfa.vic.gov.au
Subject: Request for Fire Danger Rating API Access - WordPress Plugin

Dear CFA Digital Team,

I am developing a WordPress plugin to display CFA fire danger ratings 
and total fire ban information to help Victorian residents stay informed 
about fire safety.

Through analysis of your website, I have identified your internal API 
endpoint (/api/cfa/tfbfdr/district) that provides this data. The API 
works correctly when tested locally, but appears to be protected by 
IP-based access controls that block server-side requests.

I would like to request:
1. Official API access for this WordPress plugin, OR
2. IP whitelisting for our plugin hosting infrastructure

Plugin Details:
- Purpose: Display fire danger ratings on community websites
- Update frequency: Twice daily (6 AM and 6 PM Melbourne time)
- Caching: 30-60 minute transients to minimize load
- Districts: User-configurable (single or multiple districts)

I am happy to discuss attribution requirements, usage limits, or any 
technical specifications needed.

Thank you for your consideration.

[Your contact details]
```

---

## Conclusion

**Successfully Discovered:**
- ✅ CFA internal API endpoint and specification
- ✅ Request format, headers, and payload structure
- ✅ Response format and data extraction methods
- ✅ District name mapping from URL slugs

**Technical Limitation:**
- ❌ API access blocked from server environments (HTTP 403)
- ❌ Requires browser context or official API access

**Next Steps:**
1. Contact CFA for official access
2. Implement headless browser service as fallback
3. Continue development with mock data until access resolved

---

## File Metadata

- **Created:** October 1, 2025
- **API Endpoint:** `POST https://www.cfa.vic.gov.au/api/cfa/tfbfdr/district`
- **Discovery Method:** Reverse engineering JavaScript source
- **Status:** Documented, awaiting production access solution
- **Project:** CFA Fire Danger Forecast WordPress Plugin
