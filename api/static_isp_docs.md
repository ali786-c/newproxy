# Evomi Static Residential (ISP) Proxies - Integration Guide

This document outlines the product details, pricing, and technical integration steps for Evomi's Static Residential (ISP) proxies.

---

## 1. Product Overview
Static Residential (ISP) proxies are IP addresses hosted on servers but assigned by real Internet Service Providers (like AT&T, Comcast, etc.). 

**Key Benefits:**
- **Unlimited Bandwidth:** Users are billed per IP, not per GB.
- **Persistent IP:** The IP address remains the same for the duration of the subscription (monthly).
- **Highest Trust Score:** Identified as "Residential" by target websites, making them nearly impossible to block.
- **High Speed:** Datacenter-grade speed with Residential-grade reputation.

---

## 2. Pricing & Tiers (Reseller Rates)
Billing is calculated **monthly per IP address**.

| Tier | Price / IP / Month | Description |
| :--- | :--- | :--- |
| **Shared IPs** | $1.00 | Shared with up to 3 users. Most cost-effective. |
| **Private IPs** | $2.50 | Dedicated only to one user. Highly secure. |
| **Virgin IPs** | $4.50 | Never used as proxies before. Guaranteed 0 fraud score. |

---

## 3. Technical Integration Details

### A. Checking Real-time Stock & Local Pricing
Before allowing a user to buy, you must check if the specific location/ISP is in stock.

**Endpoint:** `GET /v2/reseller/sub_users/isp/stock`
**Auth:** `X-API-KEY` header
**Required Query Param:** `username` (The reseller's master username or a subuser's username)

**PHP Example:**
```php
$resellerUsername = 'up_4_jxycmc';
$response = $http->get("https://reseller.evomi.com/v2/reseller/sub_users/isp/stock?username=$resellerUsername");
$stockData = $response->json()['data'];
```

### B. Ordering an ISP Package
To assign an IP to a user, you "order a package" for their subuser account.

**Endpoint:** `POST /v2/reseller/sub_users/isp/order`
**Request Body:**
```json
{
  "username": "subuser_123",    // Target subuser username
  "months": 1,                  // Duration (Minimum 1)
  "countryCode": "US",          // From stock info
  "city": "losangeles",         // From stock info
  "isp": "astound",             // From stock info
  "ips": 3,                     // Total IPs (Minimum 3 usually)
  "sharedType": "dedicated",    // 'shared' or 'dedicated'
  "virgin": false,              // true only if dedicated
  "highConcurrency": true       // Enable 5000 threads limit
}
```

### C. Managing Subuser Packages
You can list active ISP packages for a subuser to show them their expiry dates and IP details.

**Endpoint:** `GET /v2/reseller/sub_users/{username}/isp/packages`

---

## 4. Integration Strategy for UpgradedProxy
1. **Database Update:** Add a new table `isp_packages` to track user orders, expiry dates, and IP counts.
2. **Frontend UI:** Create a "Static Proxies" tab in the user dashboard.
3. **Billing Logic:** Since it's monthly, we need to handle recurring payments or deduct from credit balance once a month.
4. **IP Delivery:** Once ordered, Evomi provides the credentials. We should display these in the "Proxy List" under a "Static" category.

---

## 5. Summary of Current Inventory (Sample)
As of last scan, the best available stock is:
- **Location:** USA -> Los Angeles
- **ISP:** Astound
- **Capacity:** ~1350 Dedicated IPs, ~260 Shared IPs.

---
*Document prepared for future integration into UpgradedProxy backend.*
