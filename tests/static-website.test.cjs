const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs');
const html=fs.readFileSync('docs/index.html','utf8');
test('product is static with no public reservation simulator or network execution',()=>{
 assert.match(html,/script-src 'none'/);assert.match(html,/connect-src 'none'/);
 assert.doesNotMatch(html,/<(?:script|form|input|select|textarea|button)\b/i);assert(!fs.existsSync('docs/app.js'));
 for(const s of ['No live booking or payment service','not interactive booking controls','no checkout or sandbox'])assert(html.includes(s),s);
});
test('product identity, source and architecture are explicit',()=>{
 for(const s of ['Travel Tour Reservation Bot','A structured booking workflow, from request to review.','href="#architecture">Explore the Architecture','PHP','MySQL','GPL-3.0-only','https://github.com/AlirezaBelal/travel-tour-reservation-bot'])assert(html.includes(s),s);
});
test('lifecycle matches source permissions and capacity states',()=>{
 const rules=fs.readFileSync('src/BookingRules.php','utf8'),inventory=fs.readFileSync('src/TourInventory.php','utf8');
 assert(rules.includes("'pending' => ['confirmed', 'rejected', 'cancelled']"));assert(rules.includes("'confirmed' => ['cancelled', 'completed']"));
 assert(inventory.includes("status IN ('pending','confirmed','completed')"));
 for(const s of ['1–20','Pending → confirmed, rejected or cancelled','Confirmed → cancelled or completed','Confirmation requires a paid payment record','only before departure','tour to have ended','One booking identity remains','Unpaid pending holds expire'])assert(html.includes(s),s);
});
test('payment and outbox claims retain real limitations',()=>{
 for(const s of ['No live payment gateway or automatic refund','does not itself confirm','at least once, not exactly once','outside the claim transaction','five-minute leases','Shared 429 cooldown'])assert(html.includes(s),s);
});
test('installation and privacy remain operator controlled',()=>{
 for(const s of ['Docker Compose','PHP 8.0–8.4','Copy-Item .env.example .env','cp .env.example .env','bin/install.php','bin/restrict-runtime.php --confirm','127.0.0.1:8080','public/','migrations 001–004','maintenance account','do not establish legal identity','5,000 rows','opt-in'])assert(html.includes(s),s);
 assert.doesNotMatch(html,/set-webhook\.php[^<\n]*--confirm|docker compose down -v|[0-9]{8,12}:[A-Za-z0-9_-]{30,}/);
 assert.match(html,/lang="fa" dir="rtl"/);
});
test('all anchors resolve uniquely and assets exist',()=>{
 const ids=[...html.matchAll(/\bid="([^"]+)"/g)].map(m=>m[1]);assert.equal(ids.length,new Set(ids).size);
 for(const [,id]of html.matchAll(/href="#([^"]+)"/g))assert(ids.includes(id),id);
 for(const [,asset]of html.matchAll(/(?:href|src)="([^"#:]+\.(?:css|svg))"/g))assert(fs.existsSync('docs/'+asset),asset);
});
