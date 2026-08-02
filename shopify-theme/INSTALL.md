# Sheeralateen Store Pickup Widget — Installation Guide

> Bypasses Shopify Basic 10-location limit with a full 28-branch picker,
> Mapbox GL map, geolocation, and Shopify Cart Attributes integration.

---

## File Overview

```
sheeralateen/
├── database/
│   └── migration_add_coords.sql          ← Step 1: Run this first
├── api/
│   ├── branches.php                      ← Updated (full details + lat/lng)
│   └── nearest_branches.php              ← New endpoint (Haversine sort)
└── shopify-theme/
    ├── assets/
    │   └── store-pickup.css              ← Step 3: Upload to Shopify
    ├── snippets/
    │   └── store-pickup-widget.liquid    ← Step 4: Upload to Shopify
    └── sections/
        └── cart-pickup.liquid            ← Step 5: Upload to Shopify
```

---

## Step 1 — Run the Database Migration

1. Log in to **Hostinger hPanel** → **Databases** → **phpMyAdmin**
2. Select your database: `u265225504_sheeralateen`
3. Click **SQL** tab
4. Open `database/migration_add_coords.sql` and paste the full contents
5. Click **Go**

**Verify success:**
```sql
SELECT branch_code, branch_name, latitude, longitude
FROM branches
WHERE latitude IS NOT NULL AND status = 'active'
ORDER BY branch_name;
```
Should return all 28 active branches with coordinates.

> **Coordinate accuracy note:** The seeded coordinates are district-level approximations.
> To pin-point exact store locations, right-click each store entrance in Google Maps,
> click **"Copy coordinates"**, and update:
> ```sql
> UPDATE branches SET latitude = 21.XXXX, longitude = 39.XXXX
> WHERE branch_code = 'ALAJAWEED';
> ```

---

## Step 2 — Deploy PHP Files to Hostinger

Upload via **Hostinger File Manager** or FTP:

| Local Path | Upload To (on server) |
|---|---|
| `api/branches.php` | `/public_html/api/branches.php` |
| `api/nearest_branches.php` | `/public_html/api/nearest_branches.php` |

**Test the endpoints:**
- All branches: `https://sheeralateen.fix4.in/api/branches.php`
- Nearest to Jeddah centre: `https://sheeralateen.fix4.in/api/nearest_branches.php?lat=21.3891&lng=39.8579`

Expected response:
```json
{
  "status": "success",
  "count": 28,
  "sorted_by": "distance",
  "data": [
    {
      "id": 3,
      "branch_code": "SAFA",
      "name": "Safa",
      "address": "Jeddah",
      "phone": "0500015716",
      "maps_url": "https://maps.app.goo.gl/...",
      "latitude": 21.4808,
      "longitude": 39.1935,
      "distance_km": 1.23
    },
    ...
  ]
}
```

---

## Step 3 — Get a Free Mapbox Token

1. Sign up at [https://account.mapbox.com/auth/signup/](https://account.mapbox.com/auth/signup/)
   *(or log in if you already have an account)*
2. On the dashboard, copy your **"Default public token"** (starts with `pk.eyJ1...`)
3. Open `shopify-theme/snippets/store-pickup-widget.liquid`
4. Find this line (near the top):
   ```liquid
   {% assign sp_mapbox_token = 'YOUR_MAPBOX_PUBLIC_TOKEN' %}
   ```
5. Replace `YOUR_MAPBOX_PUBLIC_TOKEN` with your actual token

> **Free tier:** 50,000 map loads/month — more than enough for most stores.

---

## Step 4 — Upload Files to Shopify Theme

1. Log in to [Shopify Admin](https://admin.shopify.com)
2. Go to **Online Store** → **Themes**
3. Click **Actions → Edit code** on your active theme

### Upload CSS
- In the left panel, find **Assets**
- Click **Add a new asset** → **Upload a file**
- Upload `shopify-theme/assets/store-pickup.css`

### Upload Snippet
- In the left panel, find **Snippets**
- Click **Add a new snippet** → name it `store-pickup-widget`
- Paste the full contents of `shopify-theme/snippets/store-pickup-widget.liquid`
- Save

### Upload Section (optional — for theme editor control)
- In the left panel, find **Sections**
- Click **Add a new section** → name it `cart-pickup`
- Paste the full contents of `shopify-theme/sections/cart-pickup.liquid`
- Save

---

## Step 5 — Add to Cart Template

### Option A: Direct embed in cart.liquid (recommended for most themes)

Open your cart template. Look for either:
- `templates/cart.liquid`
- `sections/main-cart-footer.liquid`
- `sections/cart-drawer.liquid`

Find where the checkout button is rendered, and add **above** it:
```liquid
{% render 'store-pickup-widget' %}
```

Example:
```liquid
<div class="cart-footer">
  {% render 'store-pickup-widget' %}   ← ADD THIS LINE
  <button type="submit" name="checkout">Checkout</button>
</div>
```

### Option B: Via Theme Editor (if using cart-pickup section)
1. Go to **Online Store** → **Themes** → **Customize**
2. Navigate to the **Cart** template
3. Click **Add section** → find **Store Pickup Selector**
4. Drag it above the checkout button
5. Save

---

## Step 6 — Test Everything

### 1. Backend API Test
```bash
# In browser or Postman:
GET https://sheeralateen.fix4.in/api/nearest_branches.php?lat=21.38&lng=39.85
# Should return 28 branches sorted by distance
```

### 2. Widget Test
1. Add any product to cart and go to Cart page
2. The **"Select Store Pickup Location"** card should appear above the checkout button
3. Click it — the modal should open with:
   - Branch list on the left
   - Mapbox map with pins on the right

### 3. Geolocation Test
1. Click the **"Nearest"** button (GPS icon)
2. Allow location permission when prompted
3. Branch list should re-sort by distance; map should zoom to your area

### 4. Store Selection Test
1. Click a branch in the list or on the map
2. Click **"Select This Branch"**
3. Modal closes; **pickup badge** appears in cart summary
4. Verify cart attributes were saved:
   ```javascript
   // Run in browser console on cart page:
   fetch('/cart.js')
     .then(r => r.json())
     .then(c => console.log(c.attributes));
   ```
   Expected output:
   ```json
   {
     "Pickup_Store_ID": "SAFA",
     "Pickup_Store_Name": "Safa",
     "Pickup_Store_Address": "Jeddah",
     "Pickup_Maps_URL": "https://maps.app.goo.gl/..."
   }
   ```

### 5. Order Verification
After checkout, the order in **Shopify Admin → Orders** will show the cart attributes (pickup store details) under **Additional details** or in the order's note attributes.

---

## Reading Pickup Data on Order (Admin)

In your existing PHP admin system (`admin/orders.php`), when you receive an order via webhook or sync, the `note_attributes` array from Shopify contains:

```json
[
  { "name": "Pickup_Store_ID",      "value": "SAFA" },
  { "name": "Pickup_Store_Name",    "value": "Safa" },
  { "name": "Pickup_Store_Address", "value": "Jeddah" },
  { "name": "Pickup_Maps_URL",      "value": "https://..." }
]
```

You can use `Pickup_Store_ID` (= `branch_code`) to look up the branch in your `branches` table and auto-assign the order to the correct branch.

**Suggested SQL for auto-assignment:**
```sql
-- During order import in ShopifySyncService.php:
UPDATE orders
SET assigned_branch_id = (
    SELECT id FROM branches WHERE branch_code = :pickup_store_id LIMIT 1
)
WHERE shopify_order_id = :shopify_order_id;
```

---

## CORS Troubleshooting

If the widget can't reach the API, check:

1. **Hostinger `.htaccess`** in `public_html/api/` — make sure it doesn't block OPTIONS requests:
   ```apache
   # Add to public_html/api/.htaccess if needed:
   Header always set Access-Control-Allow-Origin "*"
   Header always set Access-Control-Allow-Methods "GET, OPTIONS"
   Header always set Access-Control-Allow-Headers "Content-Type"
   RewriteEngine On
   RewriteCond %{REQUEST_METHOD} OPTIONS
   RewriteRule .* - [R=204,L]
   ```

2. **Test CORS from browser console** (on your Shopify store):
   ```javascript
   fetch('https://sheeralateen.fix4.in/api/branches.php')
     .then(r => r.json())
     .then(console.log);
   ```

---

## Customisation Reference

### Change brand colour
Edit `store-pickup.css` line 12:
```css
--sp-primary: #1a6b4a;   /* Change this to your brand colour */
```

### Change API base URL
Edit `store-pickup-widget.liquid` line ~14:
```liquid
{% assign sp_api_base = 'https://sheeralateen.fix4.in' %}
```

### Show only nearest N branches by default
The API supports a `limit` parameter:
```
GET /api/nearest_branches.php?lat=21.38&lng=39.85&limit=5
```

### Disable map (sidebar only)
In `store-pickup-widget.liquid`, find `.sp-map-panel` and add `display:none` via CSS,
or add a section setting.
