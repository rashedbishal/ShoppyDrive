# Meta Pixel + Conversions API: GTM setup and verification

This guide is for whoever manages the Google Tag Manager container
`GTM-PRKGGNHM`. The website code is already done. It pushes every event
into GTM's `dataLayer` and sends the same event, with the **same event ID**,
from the server to Meta's Conversions API. GTM only has to fire the
browser Pixel event with that ID so Meta can match the two and count the
action once.

Audited from the container export, version 2 ("Add ShoppyDrive Meta Pixel
Code tag"). That version has one tag (the Meta Pixel base code, Pixel ID
`1395344526007424`) and no event tags, so nothing here duplicates an
existing tag.

---

## 0. Before you start

1. **Admin › Marketing › Facebook Conversions API** on the website:
   - **Pixel ID** must be `1395344526007424`, the same ID as the GTM tag.
     If they differ, browser and server events go to different datasets
     and are never matched.
   - Access token: a Conversions API token from Events Manager ›
     Settings › Conversions API › Generate access token.
   - Switch **Enabled** on.
2. The production deploy must have run its migration (the
   `meta_tracking_events` table).

---

## 1. Fix the existing base Pixel tag

The tag "ShoppyDrive Meta Pixel Code" currently fires on **three**
triggers: Consent Initialization – All Pages, Initialization – All Pages,
and All Pages. Each firing runs `fbq('track', 'PageView')`, so a page load
can send PageView up to three times.

1. Tags › **ShoppyDrive Meta Pixel Code** › Triggering.
2. Remove **Consent Initialization – All Pages** and **All Pages**.
3. Keep only **Initialization – All Pages**.
4. Save.

### PageView on in-site navigation

The store is a single-page app: moving between pages doesn't reload the
page, so the base tag only sends PageView on the first page of a visit.

1. Triggers › New › **History Change**, "All History Changes". Name it
   `History Change`.
2. Tags › New › **Custom HTML**, name `Meta Pixel – PageView (navigation)`:

   ```html
   <script>
     if (typeof fbq === 'function') { fbq('track', 'PageView'); }
   </script>
   ```

3. Triggering: `History Change`. Save.

---

## 2. Data Layer Variables

Variables › User-Defined Variables › New › **Data Layer Variable**, Data
Layer Version 2. Create one per row. Use the exact names in the first
column, because the tag in step 4 refers to them.

| Variable name | Data Layer Variable Name |
|---|---|
| `DLV - meta_event_name` | `meta_event_name` |
| `DLV - event_id` | `event_id` |
| `DLV - product_id` | `product_id` |
| `DLV - product_title` | `product_title` |
| `DLV - value` | `value` |
| `DLV - currency` | `currency` |
| `DLV - quantity` | `quantity` |
| `DLV - num_items` | `num_items` |
| `DLV - content_ids` | `content_ids` |
| `DLV - content_type` | `content_type` |
| `DLV - contents` | `contents` |
| `DLV - transaction_id` | `transaction_id` |
| `DLV - coupon` | `coupon` |
| `DLV - search_keyword` | `search_keyword` |
| `DLV - search_string` | `search_string` |

---

## 3. One trigger for all seven events

Triggers › New › **Custom Event**:

- Name: `Meta events (7)`
- Event name: `^meta_(view_content|add_to_cart|remove_from_cart|add_to_wishlist|initiate_checkout|purchase|search)$`
- Tick **Use regex matching**.
- This trigger fires on: All Custom Events.

The website pushes these GTM event names:

| GTM `event` | Meta event | Pixel call |
|---|---|---|
| `meta_view_content` | ViewContent | standard (`track`) |
| `meta_add_to_cart` | AddToCart | standard (`track`) |
| `meta_remove_from_cart` | RemoveFromCart | **custom** (`trackCustom`); not a Meta standard event |
| `meta_add_to_wishlist` | AddToWishlist | standard (`track`) |
| `meta_initiate_checkout` | InitiateCheckout | standard (`track`) |
| `meta_purchase` | Purchase | standard (`track`) |
| `meta_search` | Search | standard (`track`) |

---

## 4. One tag that sends all seven to the Pixel

Tags › New › **Custom HTML**, name `Meta Pixel – Events (deduplicated)`:

```html
<script>
(function () {
  var name = {{DLV - meta_event_name}};
  var eventId = {{DLV - event_id}};
  if (!name || !eventId || typeof fbq !== 'function') { return; }

  var standard = ['ViewContent', 'AddToCart', 'AddToWishlist', 'InitiateCheckout', 'Search', 'Purchase'];
  var params = {};
  function put(key, value) {
    if (value !== undefined && value !== null && value !== '') { params[key] = value; }
  }

  put('value', {{DLV - value}});
  put('currency', {{DLV - currency}});
  put('content_ids', {{DLV - content_ids}});
  put('content_type', {{DLV - content_type}});
  put('contents', {{DLV - contents}});
  put('content_name', {{DLV - product_title}});
  put('num_items', {{DLV - num_items}});
  put('search_string', {{DLV - search_string}});
  put('order_id', {{DLV - transaction_id}});

  // The 4th argument is what makes deduplication work: Meta matches this
  // eventID with the server event's event_id. As a normal parameter it
  // would not deduplicate.
  fbq(standard.indexOf(name) >= 0 ? 'track' : 'trackCustom', name, params, { eventID: eventId });
})();
</script>
```

- Triggering: `Meta events (7)`.
- Advanced Settings › **Tag Sequencing** › tick "Fire a tag before
  Meta Pixel – Events fires" › choose **ShoppyDrive Meta Pixel Code** ›
  tick "Don't fire … if ShoppyDrive Meta Pixel Code fails or is paused".
- Save.

The tag reads the Meta event name from the page (`meta_event_name`), so
the browser name always matches the server name exactly. Don't use a
template that has its own event dropdown.

**Don't install a second Pixel base code**, in GTM or in Admin › Custom
Header/Footer Code. The one base tag above is the only one.

The website still pushes its older `event: 'purchase'` (with
`fb_event_id`) on the order confirmation page. No tag in the container uses
it, so it's harmless. Don't build a second Purchase tag on it.

---

## 5. Publish

Preview first (step 6), then **Submit › Publish**, with a version name like
"Meta events + dedup".

---

## 6. Verify in Meta Events Manager › Test Events

1. Website: Admin › Marketing › Facebook Conversions API › **Test event
   code**: paste the code shown on Events Manager › your dataset › **Test
   events** (e.g. `TEST12345`). Save.
2. Events Manager › Test events › **Confirm your website's events** ›
   enter the shop URL › Open website. This browser's Pixel events now show
   up in Test events.
3. In that browser, do each action below and check the result in Test events.

| # | Do this | Expect in Test events |
|---|---|---|
| 1 | Open any product page | **ViewContent**: Browser and Server rows, same Event ID, "Deduplicated" |
| 2 | Add it to the cart | **AddToCart**: value = price × quantity, content_ids = product ID |
| 3 | Remove it from the cart | **RemoveFromCart** (custom event) |
| 4 | Heart a product (logged in, then as a guest) | **AddToWishlist** |
| 5 | Search for a word and press Enter | **Search**: search_string = the word |
| 6 | Go to checkout | **InitiateCheckout**: num_items, value |
| 7 | Place a COD order | **Purchase**: Event ID `order-<order number>`, value = order total |
| 8 | Refresh the confirmation page | No second browser Purchase |

The server event can arrive up to about a minute after the browser event:
the queue is processed once a minute.

For each event, open it and check:
- **Event ID**: identical on the Browser and Server rows.
- **Deduplicated** label (or "Received from: Browser and Server").
- **Parameters**: value and currency `BDT`, content IDs.
- **Customer information** on the server row: hashed email/phone etc. for
  logged-in customers and orders, plus IP address, user agent, fbp/fbc.

4. **Remove the test event code** from Admin › Marketing afterwards and save.
   While it's set, server events appear only in Test events, not in your
   real reports.

Record what you saw (screenshots of the 7 deduplicated events) in the
remediation tracker. Automated tests only prove the payloads with a faked
Meta API, not real delivery.

---

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| Only Browser rows, no Server rows | Admin › Marketing not enabled, wrong token, or the queue isn't running (check Admin › Failed jobs and the `meta_tracking_events` table's `status`/`last_error`). |
| Server rows only | The GTM tag isn't published, or the trigger regex doesn't match. Use GTM Preview. |
| Both rows but not deduplicated | The tag sends `event_id` as a normal parameter instead of `{ eventID: … }`, or the Pixel IDs differ. |
| PageView 2–3× per load | Step 1 not done. |
