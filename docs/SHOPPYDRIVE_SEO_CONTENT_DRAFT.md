# ShoppyDrive — SEO Content Cleanup Draft Package

Draft for owner review, 28 Nov 2026. Nothing here has been entered into the CMS.

## How to read this

- **VERIFIED** = read from the live site on 27–28 Nov 2026 (public pages) or from the code.
- **DRAFT** = suggested wording, ready to paste.
- **CHECK** = a fact only you can confirm before publishing.
- Field limits in the CMS: SEO title max 70, meta description max 160, search keyword max 60.
- **Title rule (live since DISC-95):** if an SEO title does not contain "ShoppyDrive", the site adds " - ShoppyDrive" (14 characters). So keep such titles to about 46 characters for a ~60-character result, or include "ShoppyDrive" yourself.
- Character counts below were measured, not estimated.

## Verified facts used in the drafts

| Fact | Source |
|---|---|
| Catalogue: 5 products, 4 categories, 16 sub-categories | sitemap + category pages |
| 11 of 16 sub-categories currently show **0 products** | live category pages |
| Delivery charge: ৳70 inside Dhaka, ৳120 outside Dhaka | live site settings |
| Prices in taka (৳) | live |
| Phone +880 1871400787, WhatsApp +8801871400787, email support.shoppydrive@gmail.com | Contact page |
| Address "Mirpur 12 Block D Dhaka 1216"; hours "Every day, 9 AM–9 PM" | Contact page |
| bKash/Nagad payment is currently **switched off** on the site | live (`mobile_banking_available: false`) |
| Announcement bar says "Free delivery on orders over ৳999", but the free-delivery setting reads 0 | live — **CHECK** which is true |
| Legal business name, trade licence number: **empty** | live settings |

Not used anywhere (unverified): cash on delivery availability, delivery times, warranty, materials, customer numbers, ratings, district coverage. **CHECK** cash on delivery before adding it to any copy.

---

## Priority order

| # | Item | Why first |
|---|---|---|
| 1 | Contact intro (§4) | Live meta description repeats itself |
| 2 | RGB Night Lights "dd", Toys & Games "toys, toys" (§2) | Visible typos in search snippets |
| 3 | TOMI product SEO title (§3) | Cut off mid-word in Google |
| 4 | Homepage SEO title + description (§1) | Site default text mentions "electronics" you don't sell |
| 5 | Placeholder category descriptions (§2: Home & Living, Watches, Home Decor, Men's Watches) | Weak snippets on main pages |
| 6 | Smart & Fitness Tracker Watch (§2) | No description and no products |
| 7 | Empty sub-categories decision (§2.3) | 11 thin pages |
| 8 | Search keywords (§6) | Missing watch and lighting terms |
| 9 | About page (§5) | Unsupported stats |
| 10 | Blog (§7) | 0 posts; ongoing work |

---

## 1. Homepage

Where: Admin › Homepage (SEO title, SEO description, heading, intro).

| Field | DRAFT | Chars |
|---|---|---|
| SEO title | `ShoppyDrive – Watches, Night Lights, Toys & Gifts in Bangladesh` | 63 |
| Meta description | `Shop watches, cute night lights, fidget toys, plush toys and gift items online in Bangladesh. Prices in taka, with delivery inside and outside Dhaka.` | 149 |
| H1 | `Watches, Night Lights, Toys & Gifts in Bangladesh` | 49 |
| Intro | `Discover stylish watches, cute night lamps, fun toys and thoughtful gifts at ShoppyDrive. Every price is in taka, and we deliver inside and outside Dhaka.` | 154 |

The SEO title contains "ShoppyDrive", so no suffix is added.

**Section headings (DRAFT):**

| Section | Current | DRAFT |
|---|---|---|
| Featured products | Featured products | Featured Watches, Toys & Gifts |
| Flash sale | Weekend flash sale | Flash Sale (keep "Weekend" only when it really runs on weekends) |
| New arrivals | New arrivals | New Arrivals |
| Best sellers | Best sellers | Best Sellers |
| Brands | Our brands | Shop by Brand |
| Categories | Shop by category | Shop by Category |
| Bundle deals | Bundle deals | Bundle Deals & Gift Sets |

---

## 2. Categories and sub-categories

Where: Admin › Categories / Sub-categories (SEO title, SEO description, Description).

The "Description" field is shown on the category page. Write it as plain paragraphs; the site no longer needs a heading line in it.

### 2.1 Priority fixes

#### Home & Living (`/category/home-living`) — 1 product
- **Issue:** SEO title "Home & Living | ShoppyDrive"; meta description is a placeholder ("Decor, kitchen, bedding and home improvement." — no kitchen or bedding products exist).
- **SEO title:** `Home & Living Decor and Night Lights in Bangladesh` (50 → 64 with suffix)
- **Meta description:** `Shop home decor and cute night lights for bedrooms and desks in Bangladesh. Add a cozy, playful touch to your space with ShoppyDrive.` (133)
- **Description:**
  > Make your room feel cozy and personal with decorative lighting and room accents. Explore cute animal night lamps and other home decor pieces for bedrooms, bedside tables, desks and shelves.
  >
  > Every price is in taka, with delivery inside and outside Dhaka.
- **Search intent:** browse home decor / night lights to buy.
- **Keywords:** home decor Bangladesh, night lamp, bedroom decor, cute night light.

#### Watches & Fashion Accessories (`/category/watches-fashion-accessories`) — 1 product
- **Issue:** SEO title "Watches | ShoppyDrive" (drops "Fashion Accessories"); meta description "Premium watches for every wrist." is a placeholder.
- **SEO title:** `Watches & Fashion Accessories in Bangladesh` (43 → 57)
- **Meta description:** `Shop analog wrist watches and fashion accessories online in Bangladesh. Stylish designs for everyday wear and gifting, priced in taka.` (134)
- **Description:**
  > Find wrist watches and accessories for everyday outfits, special occasions and gifting. Browse men's watches and distinctive dial designs, some with gift-ready packaging.
  >
  > Prices are in taka, with delivery inside and outside Dhaka.
- **Search intent:** buy a watch online; gift shopping.
- **Keywords:** watch price in Bangladesh, analog watch, men's watch BD, gift watch.

#### Men's Watches (`/category/mens-watches`) — 1 product
- **Issue:** title "Men's Watches | ShoppyDrive"; meta "Men's Watches products." is a placeholder. The description's first paragraph is missing its final full stop.
- **SEO title:** `Men's Watches Online in Bangladesh` (34 → 48)
- **Meta description:** `Shop men's analog wrist watches in Bangladesh. Distinctive dial designs for everyday wear, outings and gifting, with prices in taka.` (132)
- **Description:**
  > Discover men's watches for casual outfits, everyday wear and special occasions. Compare dial styles and strap designs to find one that suits you.
  >
  > Looking for a gift? Some watches come in a presentation box.
- **Search intent:** buy a men's watch.
- **Keywords:** men's watch Bangladesh, analog watch for men, gift watch for him.

#### Home Decor (`/category/home-decor`) — 0 products
- **Issue:** title "Home Decor | ShoppyDrive"; meta "Home Decor products." is a placeholder. No products yet (see §2.3).
- **SEO title:** `Home Decor in Bangladesh` (24 → 38)
- **Meta description:** `Browse home decor at ShoppyDrive: decorative lamps and room accents for bedrooms, desks and shelves, with prices in taka.` (121)
- **Description:**
  > Decorative pieces and lighting that add warmth and personality to bedrooms, desks and shelves.
  >
  > Prices are in taka, with delivery inside and outside Dhaka.

#### Toys & Games (`/category/toys-games`) — 2 products
- **Issue:** "Discover toys, toys, and interactive play products"; "toys games"; "children and young and families".
- **SEO title:** `Toys & Games Online in Bangladesh` (33 → 47)
- **Meta description:** `Shop fidget toys and skill-based toys online in Bangladesh. Colorful, hands-on toys for playtime and gifting, priced in taka.` (125)
- **Description:**
  > Explore fun toys for playtime and everyday enjoyment. Discover fidget toys, skill-based toys and other hands-on play products in colorful designs.
  >
  > A great choice for kids, families and gifting, with prices in taka and delivery inside and outside Dhaka.
- **Search intent:** buy toys online.
- **Keywords:** toys in Bangladesh, fidget toy, khelna, kids toys online.

#### RGB Night Lights (`/category/rgb-night-lights`) — 0 products
- **Issue:** the description starts "dd colorful lighting…" (should be "Add").
- **SEO title:** `RGB Night Lights in Bangladesh` (30 → 44)
- **Meta description:** `Browse RGB night lights for bedrooms, gaming corners and room decor at ShoppyDrive, with prices in taka.` (104)
- **Description:**
  > Add colorful lighting to your room with RGB night lights in vibrant colors and eye-catching designs. Ideal for bedrooms, gaming spaces and decorative setups.

#### Smart & Fitness Tracker Watch (`/category/smart-fitness-tracker-watch`) — 0 products
- **Issue:** no description at all (shows the site default) and no products.
- **Recommendation:** deactivate it until products are listed (§2.3). If you keep it live:
- **SEO title:** `Smart Watches & Fitness Trackers in Bangladesh` (46 → 60)
- **Meta description:** `Browse smart watches and fitness tracker watches at ShoppyDrive, with prices in taka and delivery inside and outside Dhaka.` (123)
- **Description:**
  > Smart watches and fitness tracker watches for everyday use.
  >
  > Prices are in taka, with delivery inside and outside Dhaka.

  Add features (step counting, heart rate, etc.) only per product, from the product's own specifications.

#### Bubu Dudu items (`/category/bubu-dudu-items`) — 1 product
- **Issue:** the description is a keyword list ("Viral Bubu Dudu doll, keychain, charms…") with a typo ("Bubududu").
- **Name (suggested capitalisation):** `Bubu Dudu Items`
- **SEO title:** `Bubu Dudu Plush Toys & Gifts in Bangladesh` (42 → 56)
- **Meta description:** `Shop Bubu Dudu plush toys and couple gift sets at ShoppyDrive. Cute gifts for birthdays, anniversaries and special occasions, priced in taka.` (141)
- **Description:**
  > Cute Bubu Dudu plush toys and gift sets for someone special. Choose a single character or a couple set for birthdays, anniversaries and other occasions.
- **CHECK:** Bubu Dudu is a third-party character. The product page calls it "Bubu Dudu-inspired". Don't describe it as official or licensed unless it is.

### 2.2 Categories whose description is fine but has no SEO description

Since DISC-95 these already produce clean fallback snippets. Adding an SEO description is optional but gives you control.

| Page | Products | SEO title (DRAFT) | Meta description (DRAFT) | Chars |
|---|---|---|---|---|
| Gifts & Cute Accessories | 1 | Gifts & Cute Accessories in Bangladesh | Find cute gifts, plush toys and small accessories for birthdays and special occasions at ShoppyDrive, with prices in taka. | 122 |
| Decorative LED Lamps | 0 | Decorative LED Lamps in Bangladesh | Browse decorative LED lamps for bedrooms, desks and home interiors at ShoppyDrive, with prices in taka. | 103 |
| Cute Animal Night Lamps | 1 | Cute Animal Night Lamps in Bangladesh | Shop cute animal night lamps for bedrooms and bedside tables in Bangladesh. A playful decor piece and a lovely gift idea. | 121 |
| Bedroom & Bedside Lighting | 0 | Bedroom & Bedside Lights in Bangladesh | Browse bedside lamps and decorative bedroom lights for reading corners and nightstands at ShoppyDrive, with prices in taka. | 123 |
| Stress Relief Toys | 0 | Stress Relief Toys in Bangladesh | Browse squeeze, press and spin toys for hands-on play during breaks and leisure time at ShoppyDrive, priced in taka. | 116 |
| Fidget Toys | 1 | Fidget Toys in Bangladesh | Shop colorful fidget toys in Bangladesh: compact, hands-on toys to press, twist and play with, priced in taka. | 110 |
| Skill Based Toys | 1 | Skill Based Toys in Bangladesh | Shop skill-based toys in Bangladesh for imaginative, hands-on play. Fun toy sets for kids and gifting, priced in taka. | 118 |
| Balance & Coordination Toys | 0 | Balance & Coordination Toys in Bangladesh | Browse balance and coordination toys for hands-on, active play at ShoppyDrive, with prices in taka. | 99 |
| Leather Strap Watches | 0 | Leather Strap Watches in Bangladesh | Browse leather strap watches with classic styling for everyday wear and special occasions at ShoppyDrive, priced in taka. | 121 |
| Casual & Fashion Watches | 0 | Casual & Fashion Watches in Bangladesh | Browse casual and fashion watches for daily wear, outings and gifting at ShoppyDrive, with prices in taka. | 106 |
| Plush Keychains | 0 | Plush Keychains in Bangladesh | Browse soft plush keychains and bag charms in cute designs at ShoppyDrive. Small gifts for friends and family, priced in taka. | 126 |
| Gift Items | 0 | Gift Items Online in Bangladesh | Find gift items for birthdays, anniversaries and special occasions at ShoppyDrive, with prices in taka. | 103 |

### 2.3 Empty sub-categories (decision needed)

These 11 sub-categories are live but list **0 products**: Decorative LED Lamps, Bedroom & Bedside Lighting, RGB Night Lights, Home Decor, Stress Relief Toys, Balance & Coordination Toys, Leather Strap Watches, Casual & Fashion Watches, Smart & Fitness Tracker Watch, Plush Keychains, Gift Items.

Empty listing pages are "thin content" for Google. Recommendation: **deactivate each one until it has at least one product**, then reactivate. Deactivating removes it from the menu, the sitemap and the no-JavaScript links automatically. Old links then return 404, which is acceptable for pages with nothing on them. Alternatively, assign existing products to them where they genuinely fit.

**CHECK:** a product might fit several sub-categories but can usually sit in one; decide per product.

---

## 3. Products

Where: Admin › Products › Edit (SEO title, SEO description, Short description).

### TOMI T-106 Face Gear Analog Wrist Watch with Gift Box — **fix first**
- **Current SEO title:** "TOMI T106 Face Gear Watch with Gift Box | Buy in Banglades" (cut off).
- **DRAFT SEO title:** `TOMI T106 Face Gear Analog Watch with Gift Box` (46 → 60)
- **Meta description:** keep (158 characters, accurate, verified against the product text).

### Cute Cat Night Light
- **Issue:** the SEO title says "with Wooden Legs", while the description says "wooden-style legs" and "natural-look wooden legs".
- **CHECK:** are the legs real wood?
  - If yes: keep the title.
  - If not: DRAFT SEO title `Cute Cat Night Light Lamp with Wooden-Style Legs` (48 → 62).

### Press & Twist Decompression Ball
- **Issue:** the SEO title says "for Kids", which the product text doesn't state. The short description is one sentence.
- **DRAFT SEO title:** `Press & Twist Fidget Ball – Colorful Sensory Toy` (48 → 62)
- **DRAFT meta description:** `Shop the Press & Twist Fidget Ball in Bangladesh. Press the top and rotate the colorful segments; compact enough for a desk, bag or pocket.` (139)
- **DRAFT short description:**
  > Press it, twist it, and enjoy a satisfying fidget experience. This compact ball has a colorful segmented design you press and rotate, and it fits easily on a desk, in a bag or in a pocket. Available in four colour combinations.
- **CHECK:** add an age recommendation only if the supplier states one.

### Bubu Dudu Cute Plush Toy Soft Teddy Couple Gift Set
- **Issues:**
  - The product name has a double space ("Plush Toy␣␣Soft").
  - The meta description quotes prices (৳399 / ৳750), which go stale when prices change.
- **DRAFT name:** `Bubu Dudu Cute Plush Toy – Soft Teddy Couple Gift Set`
- **DRAFT meta description:** `Shop the cute Bubu Dudu plush toy in Bangladesh. Choose a single character or the couple gift set, a sweet gift for birthdays and special days.` (143)
- **SEO title:** keep, 62 characters, already contains "ShoppyDrive".
- **CHECK:** licensing wording, as in §2.1.

### Bamboo Kung Fu Battle Toy Set with 2 Figures & Battle Platform
- SEO title and meta description are fine.
- **CHECK:** the name says "Bamboo", but the text says "bamboo-style" and "bamboo-inspired". If the figures aren't bamboo, consider the name `Kung Fu Battle Toy Set with 2 Figures & Battle Platform` and keep "bamboo-inspired design" in the text. If you rename it, the old URL redirects automatically (DISC-91).

---

## 4. Contact page

Where: Admin › Settings › Contact Page.

| Field | DRAFT | Chars |
|---|---|---|
| Intro (hero description) | `Have a question about an order, a product or delivery? Call, WhatsApp or email us — we're here every day from 9 AM to 9 PM.` | 123 |
| SEO title | `Contact ShoppyDrive – Order & Delivery Help` | 43 |
| Meta description | `Contact ShoppyDrive for help with orders, products and delivery. Call or WhatsApp +880 1871400787, or email us. Open every day, 9 AM–9 PM.` | 138 |

- This **replaces the 4× pasted intro**, which is the cause of the repeated live description.
- The hours come from your saved "Every day, 9 AM–9 PM". If those change, update the intro too.
- **Legal business name and trade licence number:** your input is needed; enter them in Settings. They then appear in the footer and the Organization schema. Not drafted, by design.

---

## 5. About page

Where: Admin › Settings › About Page. Empty sections are hidden automatically (verified in code).

- **Hero description (DRAFT):**
  > ShoppyDrive is an online shop in Bangladesh for stylish watches, cute night lights, fun toys and thoughtful gifts. Every price is in taka, and we deliver inside and outside Dhaka.
- **Mission title (DRAFT):** `Making gift and lifestyle shopping simple in Bangladesh`
- **Mission body (DRAFT):**
  > We started ShoppyDrive to make it easy to find well-chosen watches, decor, toys and gifts online — with clear photos, honest descriptions and prices in taka.
  >
  > If you have a question before or after you order, our team is available every day from 9 AM to 9 PM by phone, WhatsApp or email.
- **Core values (DRAFT, 4 items):**

  | Title | Description |
  |---|---|
  | Clear Information | Product photos, descriptions and prices in taka, so you know what you're ordering. |
  | Easy to Reach | Call, WhatsApp or email us every day from 9 AM to 9 PM. |
  | Thoughtful Selection | Watches, decor, toys and gifts chosen for everyday use and gifting. |
  | Made for Bangladesh | Taka pricing and delivery inside and outside Dhaka. |

- **Feature badges (DRAFT; only verifiable ones):**

  | Badge | Note |
  |---|---|
  | Prices in Taka | verified |
  | Delivery Across Bangladesh | ৳70 inside / ৳120 outside Dhaka are set; **CHECK** you deliver to all areas |
  | Support 9 AM–9 PM Daily | verified (saved hours) |
  | Gift-Ready Picks | wording only; no claim |

  Add "Cash on Delivery" only after you **CHECK** it's enabled. Don't add bKash/Nagad while mobile banking is switched off.
- **Stats:** remove them (leave the list empty so the section hides). Currently "3,000+ Happy Customers", "100+ Products Listed" (the site has 5), "64 Districts Covered" and "99% Satisfaction Rate" are unverified or wrong. Re-add only numbers you can back up.
- **Team:** leave it empty (the section hides) unless you want to show real people. No invented members.
- **CTA description (DRAFT):** `Browse our latest watches, night lights, toys and gifts.` Replaces "Join thousands of happy customers… most trusted online store", an unsupported claim.

---

## 6. Search keywords

Where: Admin › Popular Keywords (max 60 characters each).

| Current | Action | Reason |
|---|---|---|
| toy | Keep → `toys` | matches Toys & Games |
| best toy in bd | Remove | superlative; "toys" covers it |
| unique toys | Keep | fits the catalogue |
| khelna | Keep | common Bangla search term for toys |
| car | **Remove** | no car products or category |
| bubu dudu | Keep | matching product |
| dudu bubu | Remove | duplicate wording; search still finds the product |
| bubu dudu gift item | Remove | covered by "bubu dudu" |

**Add:**

| Keyword | Matches |
|---|---|
| watch | Watches category |
| men's watch | Men's Watches |
| night light | Cute Cat Night Light |
| cat lamp | Cute Cat Night Light |
| fidget toy | Press & Twist Fidget Ball |
| plush toy | Bubu Dudu plush |
| gift for her | Bubu Dudu set, cat lamp |
| gift box watch | TOMI with gift box |

Add `smart watch`, `fitness tracker`, `rgb light` and `led lamp` only once those sub-categories have products.

---

## 7. Blog plan: 10 articles

All of these are informational. Link only to products and categories that exist, and keep claims to what the product pages state.

1. **How to Choose a Men's Watch for Everyday Wear**
   - **Keyword:** men's watch Bangladesh
   - **Intent:** informational → commercial
   - **Outline:** dial styles; analog vs smart; strap types; matching outfits; gifting tips
   - **Links:** /category/mens-watches, TOMI T106
2. **Watch Gift Ideas: What to Look for in a Gift Watch**
   - **Keyword:** gift watch for men
   - **Intent:** commercial
   - **Outline:** budget, style, packaging, occasions
   - **Links:** TOMI T106 (gift box), /category/watches-fashion-accessories
3. **Cute Night Lights for Bedrooms: Ideas for a Cozy Room**
   - **Keyword:** night light for bedroom
   - **Intent:** informational
   - **Outline:** warm vs colorful light, placement, bedside styling, gifting
   - **Links:** Cute Cat Night Light, /category/cute-animal-night-lamps
4. **Bedroom Decor Ideas on a Budget in Bangladesh**
   - **Keyword:** bedroom decor ideas
   - **Intent:** informational
   - **Outline:** lighting, shelves, small accents, colour themes
   - **Links:** /category/home-living, Cute Cat Night Light
5. **What Is a Fidget Toy? Types and How People Use Them**
   - **Keyword:** fidget toy
   - **Intent:** informational
   - **Outline:** press, twist and spin types; desk use; choosing one
   - **Links:** Press & Twist Fidget Ball, /category/fidget-toys
   - Avoid health or therapy claims.
6. **Fun Skill Toys for Kids: Imaginative Play Ideas**
   - **Keyword:** skill toys for kids
   - **Intent:** informational
   - **Outline:** role play, tabletop play, gifting by interest
   - **Links:** Kung Fu Battle Toy Set, /category/skill-based-toys
7. **Bubu Dudu Gifts: Cute Ideas for Couples**
   - **Keyword:** bubu dudu gift
   - **Intent:** commercial
   - **Outline:** single vs couple set; occasions; presentation ideas
   - **Links:** Bubu Dudu plush, /category/bubu-dudu-items
   - **CHECK** licensing wording.
8. **Birthday Gift Ideas Under ৳1,000**
   - **Keyword:** birthday gift ideas Bangladesh
   - **Intent:** commercial
   - **Outline:** gifts by recipient, using current prices
   - **Links:** fidget ball, Bubu Dudu plush, Kung Fu set
   - Re-check the prices before publishing.
9. **Anniversary & Valentine's Gift Guide**
   - **Keyword:** anniversary gift for wife / husband
   - **Intent:** commercial
   - **Outline:** watches, plush couple sets, decor lamps
   - **Links:** TOMI, Bubu Dudu set, Cute Cat lamp
10. **How to Order from ShoppyDrive: Delivery Charges and Tips**
    - **Keyword:** online shopping in Bangladesh
    - **Intent:** navigational / informational
    - **Outline:** placing an order, delivery charges (৳70 inside / ৳120 outside Dhaka), tracking an order, contacting support
    - **Links:** /contact, /faq, /shipping-policy
    - **CHECK** payment options and delivery times before publishing.

For each post, fill: title, slug, excerpt (used as the description if the SEO description is empty), featured image, author name, SEO title, SEO description.

---

## 8. Corrections from live verification (checklist)

- [ ] **Contact A-B-A-B repeat:** replace the intro with the §4 draft.
- [ ] **RGB Night Lights:** "dd colorful lighting" → "Add colorful lighting" (§2.1).
- [ ] **Toys & Games:** remove "toys, toys", "toys games" and "children and young and families" (§2.1).
- [ ] **Placeholder descriptions:** Home & Living, Watches & Fashion Accessories, Home Decor, Men's Watches (§2.1).
- [ ] **Default descriptions:** homepage (§1), Smart & Fitness Tracker Watch (§2.1, or deactivate it).
- [ ] **Truncated product title:** TOMI T106 (§3).

## Your input needed

1. Legal business name and trade licence number.
2. Is cash on delivery enabled? Do you deliver to every district?
3. Free delivery: the announcement bar says over ৳999, but the setting reads 0. Which is correct?
4. Cute Cat lamp legs: real wood or wooden-style? Bamboo set: real bamboo or bamboo-style?
5. Bubu Dudu: licensed or inspired?
6. Empty sub-categories: deactivate, or assign products?
7. About: show real team members, or leave the section hidden?
8. Any age recommendations from suppliers for the toys?
