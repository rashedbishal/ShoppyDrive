<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Unsplash image sets per subcategory slug.
     * Each entry has 2 images (primary + secondary).
     */
    private array $images = [
        // ── Men's Clothing ───────────────────────────────────────────
        't-shirts-polos' => [
            ['https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=800&q=80', 'https://images.unsplash.com/photo-1503341455253-b2e723bb3dbb?w=800&q=80'],
            ['https://images.unsplash.com/photo-1576566588028-4147f3842f27?w=800&q=80', 'https://images.unsplash.com/photo-1562157873-818bc0726f68?w=800&q=80'],
            ['https://images.unsplash.com/photo-1586790170083-2f9ceadc732d?w=800&q=80', 'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?w=800&q=80'],
            ['https://images.unsplash.com/photo-1620799140188-3b2a02fd9a77?w=800&q=80', 'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?w=800&q=80'],
            ['https://images.unsplash.com/photo-1527719327859-c6ce80353573?w=800&q=80', 'https://images.unsplash.com/photo-1503342217505-b0a15ec3261c?w=800&q=80'],
        ],
        'shirts-casual-formal' => [
            ['https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?w=800&q=80', 'https://images.unsplash.com/photo-1620012253295-c15cc3e65df4?w=800&q=80'],
            ['https://images.unsplash.com/photo-1598033129183-c4f50c736f10?w=800&q=80', 'https://images.unsplash.com/photo-1607345366928-199ea26cfe3e?w=800&q=80'],
            ['https://images.unsplash.com/photo-1596755094514-f87e34085b2c?w=800&q=80', 'https://images.unsplash.com/photo-1625910513413-41f5a80b8bb8?w=800&q=80'],
            ['https://images.unsplash.com/photo-1621072156002-e2fccdc0b176?w=800&q=80', 'https://images.unsplash.com/photo-1594938291221-94f18cbb5660?w=800&q=80'],
            ['https://images.unsplash.com/photo-1506629082955-511b1aa562c8?w=800&q=80', 'https://images.unsplash.com/photo-1603252109303-2751441dd157?w=800&q=80'],
        ],
        'jeans-trousers' => [
            ['https://images.unsplash.com/photo-1542272604-787c3835535d?w=800&q=80', 'https://images.unsplash.com/photo-1475178626620-a4d074967452?w=800&q=80'],
            ['https://images.unsplash.com/photo-1604176354204-9268737828e4?w=800&q=80', 'https://images.unsplash.com/photo-1541099649105-f69ad21f3246?w=800&q=80'],
            ['https://images.unsplash.com/photo-1582552938357-32b906df40cb?w=800&q=80', 'https://images.unsplash.com/photo-1598522325074-042db73aa4e6?w=800&q=80'],
            ['https://images.unsplash.com/photo-1568251892573-76f3b83f5e18?w=800&q=80', 'https://images.unsplash.com/photo-1560243563-062bfc001d68?w=800&q=80'],
            ['https://images.unsplash.com/photo-1624378439575-d8705ad7ae80?w=800&q=80', 'https://images.unsplash.com/photo-1593030761757-71fae45fa0e7?w=800&q=80'],
        ],
        'hoodies-sweatshirts' => [
            ['https://images.unsplash.com/photo-1556821840-3a63f15732ce?w=800&q=80', 'https://images.unsplash.com/photo-1578768079052-aa76e52ff62e?w=800&q=80'],
            ['https://images.unsplash.com/photo-1620799140408-edc6dcb6d633?w=800&q=80', 'https://images.unsplash.com/photo-1509942774463-acf339cf87d5?w=800&q=80'],
            ['https://images.unsplash.com/photo-1631048500821-0b5a9b3af1e3?w=800&q=80', 'https://images.unsplash.com/photo-1591047139829-d91aecb6caea?w=800&q=80'],
            ['https://images.unsplash.com/photo-1516478177764-9fe5bd7e9717?w=800&q=80', 'https://images.unsplash.com/photo-1585386959984-a4155224a1ad?w=800&q=80'],
            ['https://images.unsplash.com/photo-1565693413579-8ff3fdc1b03b?w=800&q=80', 'https://images.unsplash.com/photo-1608744882201-52a7f7f3dd60?w=800&q=80'],
        ],
        'jackets-coats' => [
            ['https://images.unsplash.com/photo-1551028719-00167b16eac5?w=800&q=80', 'https://images.unsplash.com/photo-1548883354-7622d03aca27?w=800&q=80'],
            ['https://images.unsplash.com/photo-1591047139829-d91aecb6caea?w=800&q=80', 'https://images.unsplash.com/photo-1520975661595-6453be3f7070?w=800&q=80'],
            ['https://images.unsplash.com/photo-1521223890158-f9f7c3d5d504?w=800&q=80', 'https://images.unsplash.com/photo-1509956120565-36b3ef93c018?w=800&q=80'],
            ['https://images.unsplash.com/photo-1542840410-7e3d3e4eb0e3?w=800&q=80', 'https://images.unsplash.com/photo-1539533018447-63fcce2678e3?w=800&q=80'],
            ['https://images.unsplash.com/photo-1544923246-77307dd654cb?w=800&q=80', 'https://images.unsplash.com/photo-1608515811696-5f3e8cb9b4a6?w=800&q=80'],
        ],
        'innerwear-sleepwear' => [
            ['https://images.unsplash.com/photo-1604176354204-9268737828e4?w=800&q=80', 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=800&q=80'],
            ['https://images.unsplash.com/photo-1594938374182-9d7b59f7aa6a?w=800&q=80', 'https://images.unsplash.com/photo-1580735949872-9df9a0c6b394?w=800&q=80'],
            ['https://images.unsplash.com/photo-1625910513413-41f5a80b8bb8?w=800&q=80', 'https://images.unsplash.com/photo-1434389677669-e08b4cac3105?w=800&q=80'],
            ['https://images.unsplash.com/photo-1558769132-cb1aea458c5e?w=800&q=80', 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?w=800&q=80'],
            ['https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?w=800&q=80', 'https://images.unsplash.com/photo-1512374382149-233c42b6a83b?w=800&q=80'],
        ],
        'traditional-wear' => [
            ['https://images.unsplash.com/photo-1583391733956-6c78276477e1?w=800&q=80', 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?w=800&q=80'],
            ['https://images.unsplash.com/photo-1617551307578-1e7d42b9e7ba?w=800&q=80', 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=800&q=80'],
            ['https://images.unsplash.com/photo-1583391733956-6c78276477e1?w=800&q=80', 'https://images.unsplash.com/photo-1592159371936-10ed95e0c92e?w=800&q=80'],
            ['https://images.unsplash.com/photo-1614252369475-531eba835eb1?w=800&q=80', 'https://images.unsplash.com/photo-1529374255404-311a2a4f1fd9?w=800&q=80'],
            ['https://images.unsplash.com/photo-1567401893414-76b7b1e5a7a5?w=800&q=80', 'https://images.unsplash.com/photo-1588117305388-c2631a279f82?w=800&q=80'],
        ],
        'womens-traditional-wear' => [
            ['https://images.unsplash.com/photo-1583391733956-6c78276477e1?w=800&q=80', 'https://images.unsplash.com/photo-1617551307578-1e7d42b9e7ba?w=800&q=80'],
            ['https://images.unsplash.com/photo-1610030469983-98e550d6193c?w=800&q=80', 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=800&q=80'],
            ['https://images.unsplash.com/photo-1592159371936-10ed95e0c92e?w=800&q=80', 'https://images.unsplash.com/photo-1614252369475-531eba835eb1?w=800&q=80'],
            ['https://images.unsplash.com/photo-1529374255404-311a2a4f1fd9?w=800&q=80', 'https://images.unsplash.com/photo-1567401893414-76b7b1e5a7a5?w=800&q=80'],
            ['https://images.unsplash.com/photo-1588117305388-c2631a279f82?w=800&q=80', 'https://images.unsplash.com/photo-1583391733956-6c78276477e1?w=800&q=80'],
        ],
        // ── Women's Clothing ─────────────────────────────────────────
        'dresses' => [
            ['https://images.unsplash.com/photo-1595777457583-95e059d581b8?w=800&q=80', 'https://images.unsplash.com/photo-1572804013309-59a88b7e92f1?w=800&q=80'],
            ['https://images.unsplash.com/photo-1539008835657-9e8e9680c956?w=800&q=80', 'https://images.unsplash.com/photo-1566174053879-31528523f8ae?w=800&q=80'],
            ['https://images.unsplash.com/photo-1612336307429-8a898d10e223?w=800&q=80', 'https://images.unsplash.com/photo-1518331483807-f6adb0e1ad23?w=800&q=80'],
            ['https://images.unsplash.com/photo-1515372039744-b8f02a3ae446?w=800&q=80', 'https://images.unsplash.com/photo-1496747611176-843222e1e57c?w=800&q=80'],
            ['https://images.unsplash.com/photo-1601762603339-fd61e28b698a?w=800&q=80', 'https://images.unsplash.com/photo-1585487000160-6ebcfceb0d03?w=800&q=80'],
        ],
        'tops-tees-blouses' => [
            ['https://images.unsplash.com/photo-1554568218-0f1715e72254?w=800&q=80', 'https://images.unsplash.com/photo-1503342217505-b0a15ec3261c?w=800&q=80'],
            ['https://images.unsplash.com/photo-1583744946564-b52ac1c389c8?w=800&q=80', 'https://images.unsplash.com/photo-1562572159-4efc207f5aff?w=800&q=80'],
            ['https://images.unsplash.com/photo-1598554747436-c9293d6a588f?w=800&q=80', 'https://images.unsplash.com/photo-1559563458-527698bf5295?w=800&q=80'],
            ['https://images.unsplash.com/photo-1434389677669-e08b4cac3105?w=800&q=80', 'https://images.unsplash.com/photo-1548549557-dbe9946621da?w=800&q=80'],
            ['https://images.unsplash.com/photo-1485462537746-965f33f7f6a7?w=800&q=80', 'https://images.unsplash.com/photo-1502716119720-b23a93e5fe1b?w=800&q=80'],
        ],
        'jeans-pants-skirts' => [
            ['https://images.unsplash.com/photo-1475180098004-ca77a66827be?w=800&q=80', 'https://images.unsplash.com/photo-1541099649105-f69ad21f3246?w=800&q=80'],
            ['https://images.unsplash.com/photo-1506629082955-511b1aa562c8?w=800&q=80', 'https://images.unsplash.com/photo-1584370848010-d7fe6bc767ec?w=800&q=80'],
            ['https://images.unsplash.com/photo-1591369822096-ffd140ec948f?w=800&q=80', 'https://images.unsplash.com/photo-1594938374182-9d7b59f7aa6a?w=800&q=80'],
            ['https://images.unsplash.com/photo-1551854838-212c9a5dd000?w=800&q=80', 'https://images.unsplash.com/photo-1548624313-0396c75e4b1a?w=800&q=80'],
            ['https://images.unsplash.com/photo-1542574271-7f3b92e6c821?w=800&q=80', 'https://images.unsplash.com/photo-1559181567-c3190ca9959b?w=800&q=80'],
        ],
        'hoodies-sweaters' => [
            ['https://images.unsplash.com/photo-1511298341002-15d31a4b00a2?w=800&q=80', 'https://images.unsplash.com/photo-1469334031218-e382a71b716b?w=800&q=80'],
            ['https://images.unsplash.com/photo-1434389677669-e08b4cac3105?w=800&q=80', 'https://images.unsplash.com/photo-1598033129183-c4f50c736f10?w=800&q=80'],
            ['https://images.unsplash.com/photo-1605763240000-7e93b172d754?w=800&q=80', 'https://images.unsplash.com/photo-1532543491484-63e29b3c1f5d?w=800&q=80'],
            ['https://images.unsplash.com/photo-1487222477894-8943e31ef7b2?w=800&q=80', 'https://images.unsplash.com/photo-1548623831-0a5ba9bdb5b9?w=800&q=80'],
            ['https://images.unsplash.com/photo-1574180045827-681f8a1a9622?w=800&q=80', 'https://images.unsplash.com/photo-1523381294911-8d3cead13475?w=800&q=80'],
        ],
        'lingerie-sleepwear' => [
            ['https://images.unsplash.com/photo-1596462502278-27bfdc403348?w=800&q=80', 'https://images.unsplash.com/photo-1512374382149-233c42b6a83b?w=800&q=80'],
            ['https://images.unsplash.com/photo-1558769132-cb1aea458c5e?w=800&q=80', 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=800&q=80'],
            ['https://images.unsplash.com/photo-1617019114583-affb34d1b3cd?w=800&q=80', 'https://images.unsplash.com/photo-1591047139829-d91aecb6caea?w=800&q=80'],
            ['https://images.unsplash.com/photo-1518331483807-f6adb0e1ad23?w=800&q=80', 'https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?w=800&q=80'],
            ['https://images.unsplash.com/photo-1583744946564-b52ac1c389c8?w=800&q=80', 'https://images.unsplash.com/photo-1467043237213-65f2da53396f?w=800&q=80'],
        ],
        // ── Shoes ────────────────────────────────────────────────────
        'mens-sneakers-sports-shoes' => [
            ['https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=800&q=80', 'https://images.unsplash.com/photo-1608231387042-66d1773070a5?w=800&q=80'],
            ['https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?w=800&q=80', 'https://images.unsplash.com/photo-1605408499391-6368c628ef42?w=800&q=80'],
            ['https://images.unsplash.com/photo-1518894781321-630e638d0742?w=800&q=80', 'https://images.unsplash.com/photo-1527010154944-f2241763d806?w=800&q=80'],
            ['https://images.unsplash.com/photo-1491553895911-0055eca6402d?w=800&q=80', 'https://images.unsplash.com/photo-1539185441755-769473a23570?w=800&q=80'],
            ['https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=800&q=80', 'https://images.unsplash.com/photo-1604671801908-6f0c6a092c05?w=800&q=80'],
        ],
        'mens-formal-casual-shoes' => [
            ['https://images.unsplash.com/photo-1533867617858-e7b97e060509?w=800&q=80', 'https://images.unsplash.com/photo-1614252235316-8c857196f400?w=800&q=80'],
            ['https://images.unsplash.com/photo-1560343090-f0409e92791a?w=800&q=80', 'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=800&q=80'],
            ['https://images.unsplash.com/photo-1505163245248-35c51f9be66e?w=800&q=80', 'https://images.unsplash.com/photo-1582588678413-dbf45f4823e9?w=800&q=80'],
            ['https://images.unsplash.com/photo-1616502870785-c86da4d0d4ef?w=800&q=80', 'https://images.unsplash.com/photo-1619447726918-c1f8d21a7c9a?w=800&q=80'],
            ['https://images.unsplash.com/photo-1517438476312-10d79c077509?w=800&q=80', 'https://images.unsplash.com/photo-1571600063279-dfafae171aac?w=800&q=80'],
        ],
        'womens-sneakers' => [
            ['https://images.unsplash.com/photo-1560769629-975ec94e6a86?w=800&q=80', 'https://images.unsplash.com/photo-1597248881519-db089d3744a5?w=800&q=80'],
            ['https://images.unsplash.com/photo-1515955656352-a1fa3ffcd111?w=800&q=80', 'https://images.unsplash.com/photo-1607522370275-f14206abe5d3?w=800&q=80'],
            ['https://images.unsplash.com/photo-1587563871167-1ee9c731aefb?w=800&q=80', 'https://images.unsplash.com/photo-1600269452121-4f2416e55c28?w=800&q=80'],
            ['https://images.unsplash.com/photo-1603787081207-362bcef7c144?w=800&q=80', 'https://images.unsplash.com/photo-1575537302964-96cd47c06b1b?w=800&q=80'],
            ['https://images.unsplash.com/photo-1580818144870-f1f3ac04a88f?w=800&q=80', 'https://images.unsplash.com/photo-1478186735798-2e7d0e9a3da8?w=800&q=80'],
        ],
        'heels-flats' => [
            ['https://images.unsplash.com/photo-1518049362265-d5b2a6467637?w=800&q=80', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80'],
            ['https://images.unsplash.com/photo-1515347619252-60a4bf4fff4f?w=800&q=80', 'https://images.unsplash.com/photo-1583496661160-fb5218beaefd?w=800&q=80'],
            ['https://images.unsplash.com/photo-1596703263926-eb0762ee17e4?w=800&q=80', 'https://images.unsplash.com/photo-1535043934128-cf0b28d52f95?w=800&q=80'],
            ['https://images.unsplash.com/photo-1566454419290-57a64afe30ac?w=800&q=80', 'https://images.unsplash.com/photo-1592671869345-6a4c0cda1cd4?w=800&q=80'],
            ['https://images.unsplash.com/photo-1520639888713-7851133b1ed0?w=800&q=80', 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=800&q=80'],
        ],
        'sandals-slippers' => [
            ['https://images.unsplash.com/photo-1603487742131-4160ec999306?w=800&q=80', 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=800&q=80'],
            ['https://images.unsplash.com/photo-1617501716032-7af4e9f16b49?w=800&q=80', 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=800&q=80'],
            ['https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=800&q=80', 'https://images.unsplash.com/photo-1583744946564-b52ac1c389c8?w=800&q=80'],
            ['https://images.unsplash.com/photo-1562273138-f46be4ebdf33?w=800&q=80', 'https://images.unsplash.com/photo-1560769629-975ec94e6a86?w=800&q=80'],
            ['https://images.unsplash.com/photo-1572635196237-14b3f281503f?w=800&q=80', 'https://images.unsplash.com/photo-1523217582562-09d0def993a6?w=800&q=80'],
        ],
        'boots' => [
            ['https://images.unsplash.com/photo-1520639888713-7851133b1ed0?w=800&q=80', 'https://images.unsplash.com/photo-1608256246200-53e635b5b65f?w=800&q=80'],
            ['https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=800&q=80', 'https://images.unsplash.com/photo-1605812860427-4024433a70fd?w=800&q=80'],
            ['https://images.unsplash.com/photo-1614252369475-531eba835eb1?w=800&q=80', 'https://images.unsplash.com/photo-1511556532299-8f662fc26c06?w=800&q=80'],
            ['https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=800&q=80', 'https://images.unsplash.com/photo-1600878459108-617a253537e9?q=80&w=800'],
            ['https://images.unsplash.com/photo-1603808033192-082d6919d3e1?w=800&q=80', 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=800&q=80'],
        ],
        // ── Bags & Luggage ───────────────────────────────────────────
        'backpacks' => [
            ['https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=800&q=80', 'https://images.unsplash.com/photo-1547949003-9792a18a2601?w=800&q=80'],
            ['https://images.unsplash.com/photo-1491553895911-0055eca6402d?w=800&q=80', 'https://images.unsplash.com/photo-1581605405669-fcdf81165afa?w=800&q=80'],
            ['https://images.unsplash.com/photo-1622560480654-d96214fdc887?w=800&q=80', 'https://images.unsplash.com/photo-1506629082955-511b1aa562c8?w=800&q=80'],
            ['https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=800&q=80', 'https://images.unsplash.com/photo-1491553895911-0055eca6402d?w=800&q=80'],
            ['https://images.unsplash.com/photo-1504276048855-f3d60e69632f?w=800&q=80', 'https://images.unsplash.com/photo-1566454419290-57a64afe30ac?w=800&q=80'],
        ],
        'womens-handbags-crossbody-bags' => [
            ['https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=800&q=80', 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=800&q=80'],
            ['https://images.unsplash.com/photo-1566150905458-1bf1fc113f0d?w=800&q=80', 'https://images.unsplash.com/photo-1591561954557-26941169b49e?w=800&q=80'],
            ['https://images.unsplash.com/photo-1590874103328-eac38a683ce7?w=800&q=80', 'https://images.unsplash.com/photo-1544816155-12df9643f363?w=800&q=80'],
            ['https://images.unsplash.com/photo-1510901030449-fe0c284af016?w=800&q=80', 'https://images.unsplash.com/photo-1614252235316-8c857196f400?w=800&q=80'],
            ['https://images.unsplash.com/photo-1594938374182-9d7b59f7aa6a?w=800&q=80', 'https://images.unsplash.com/photo-1543168256-418811576931?w=800&q=80'],
        ],
        'wallets-purses' => [
            ['https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=800&q=80', 'https://images.unsplash.com/photo-1627123424574-724758594e93?w=800&q=80'],
            ['https://images.unsplash.com/photo-1575391379049-f7ebf2f26c26?w=800&q=80', 'https://images.unsplash.com/photo-1612199765-ef37c6c33e71?w=800&q=80'],
            ['https://images.unsplash.com/photo-1563013544-824ae1b704d3?w=800&q=80', 'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?w=800&q=80'],
            ['https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=800&q=80', 'https://images.unsplash.com/photo-1608731267464-c0c889c2b667?w=800&q=80'],
            ['https://images.unsplash.com/photo-1591561954557-26941169b49e?w=800&q=80', 'https://images.unsplash.com/photo-1523217582562-09d0def993a6?w=800&q=80'],
        ],
        'travel-bags-luggage' => [
            ['https://images.unsplash.com/photo-1521401830884-6c03c1c87ebb?w=800&q=80', 'https://images.unsplash.com/photo-1596461404969-9ae70f2830c1?w=800&q=80'],
            ['https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=800&q=80', 'https://images.unsplash.com/photo-1565026057447-bc90a3dceb87?w=800&q=80'],
            ['https://images.unsplash.com/photo-1578575437130-527eed3abbec?w=800&q=80', 'https://images.unsplash.com/photo-1473625247510-8ceb1760943f?w=800&q=80'],
            ['https://images.unsplash.com/photo-1488646953014-85cb44e25828?w=800&q=80', 'https://images.unsplash.com/photo-1510279770292-4b34de9f5c23?w=800&q=80'],
            ['https://images.unsplash.com/photo-1596461404969-9ae70f2830c1?w=800&q=80', 'https://images.unsplash.com/photo-1528360983277-13d401cdc186?w=800&q=80'],
        ],
        'laptop-bags-briefcases' => [
            ['https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=800&q=80', 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=800&q=80'],
            ['https://images.unsplash.com/photo-1622560480654-d96214fdc887?w=800&q=80', 'https://images.unsplash.com/photo-1581605405669-fcdf81165afa?w=800&q=80'],
            ['https://images.unsplash.com/photo-1547949003-9792a18a2601?w=800&q=80', 'https://images.unsplash.com/photo-1565026057447-bc90a3dceb87?w=800&q=80'],
            ['https://images.unsplash.com/photo-1491553895911-0055eca6402d?w=800&q=80', 'https://images.unsplash.com/photo-1590874103328-eac38a683ce7?w=800&q=80'],
            ['https://images.unsplash.com/photo-1504276048855-f3d60e69632f?w=800&q=80', 'https://images.unsplash.com/photo-1512436991641-6745cdb1723f?w=800&q=80'],
        ],
        // ── Jewelry ──────────────────────────────────────────────────
        'necklaces-pendants' => [
            ['https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=800&q=80', 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=800&q=80'],
            ['https://images.unsplash.com/photo-1611085583191-a3b181a88401?w=800&q=80', 'https://images.unsplash.com/photo-1602173574767-37ac01994b2a?w=800&q=80'],
            ['https://images.unsplash.com/photo-1573408301185-9519f94815c6?w=800&q=80', 'https://images.unsplash.com/photo-1618403088890-3d9ff6f4c8b1?w=800&q=80'],
            ['https://images.unsplash.com/photo-1584302179602-e4c3d3fd629d?w=800&q=80', 'https://images.unsplash.com/photo-1596944924591-a1e46e62adab?w=800&q=80'],
            ['https://images.unsplash.com/photo-1617038220319-276d3cfab638?w=800&q=80', 'https://images.unsplash.com/photo-1603974372039-adc49044b6bd?w=800&q=80'],
        ],
        'earrings-rings' => [
            ['https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=800&q=80', 'https://images.unsplash.com/photo-1617038220319-276d3cfab638?w=800&q=80'],
            ['https://images.unsplash.com/photo-1602751584552-8ba73aad10e1?w=800&q=80', 'https://images.unsplash.com/photo-1608031271543-28dbd7e6ab49?w=800&q=80'],
            ['https://images.unsplash.com/photo-1596944924591-a1e46e62adab?w=800&q=80', 'https://images.unsplash.com/photo-1603974372039-adc49044b6bd?w=800&q=80'],
            ['https://images.unsplash.com/photo-1611085583191-a3b181a88401?w=800&q=80', 'https://images.unsplash.com/photo-1584302179602-e4c3d3fd629d?w=800&q=80'],
            ['https://images.unsplash.com/photo-1573408301185-9519f94815c6?w=800&q=80', 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=800&q=80'],
        ],
        'bracelets-bangles' => [
            ['https://images.unsplash.com/photo-1603974372039-adc49044b6bd?w=800&q=80', 'https://images.unsplash.com/photo-1618403088890-3d9ff6f4c8b1?w=800&q=80'],
            ['https://images.unsplash.com/photo-1611085583191-a3b181a88401?w=800&q=80', 'https://images.unsplash.com/photo-1602173574767-37ac01994b2a?w=800&q=80'],
            ['https://images.unsplash.com/photo-1615655096345-61a54750068d?w=800&q=80', 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=800&q=80'],
            ['https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=800&q=80', 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=800&q=80'],
            ['https://images.unsplash.com/photo-1584302179602-e4c3d3fd629d?w=800&q=80', 'https://images.unsplash.com/photo-1596944924591-a1e46e62adab?w=800&q=80'],
        ],
        'sunglasses-eyewear' => [
            ['https://images.unsplash.com/photo-1572635196237-14b3f281503f?w=800&q=80', 'https://images.unsplash.com/photo-1511499767150-a48a237f0083?w=800&q=80'],
            ['https://images.unsplash.com/photo-1508296695146-257a814070b4?w=800&q=80', 'https://images.unsplash.com/photo-1584714268709-c3dd9c92b378?w=800&q=80'],
            ['https://images.unsplash.com/photo-1556306535-0f09a537f0a3?w=800&q=80', 'https://images.unsplash.com/photo-1524591329-97d36f5b5f70?w=800&q=80'],
            ['https://images.unsplash.com/photo-1486704155675-e4c07f8ad160?w=800&q=80', 'https://images.unsplash.com/photo-1602173574767-37ac01994b2a?w=800&q=80'],
            ['https://images.unsplash.com/photo-1473496169904-658ba7574b0d?w=800&q=80', 'https://images.unsplash.com/photo-1602751584552-8ba73aad10e1?w=800&q=80'],
        ],
        'belts-ties-hats' => [
            ['https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=800&q=80', 'https://images.unsplash.com/photo-1575537302964-96cd47c06b1b?w=800&q=80'],
            ['https://images.unsplash.com/photo-1514327605112-b887c0e61c0a?w=800&q=80', 'https://images.unsplash.com/photo-1534215754734-18e55d13e346?w=800&q=80'],
            ['https://images.unsplash.com/photo-1521369909029-2afed882baee?w=800&q=80', 'https://images.unsplash.com/photo-1618517351616-38fb9c5210c6?w=800&q=80'],
            ['https://images.unsplash.com/photo-1527010154944-f2241763d806?w=800&q=80', 'https://images.unsplash.com/photo-1611611158876-41699b77a059?w=800&q=80'],
            ['https://images.unsplash.com/photo-1556306535-0f09a537f0a3?w=800&q=80', 'https://images.unsplash.com/photo-1556821840-3a63f15732ce?w=800&q=80'],
        ],
        // ── Watches ──────────────────────────────────────────────────
        'mens-watches' => [
            ['https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=800&q=80', 'https://images.unsplash.com/photo-1594534475808-b18fc33b045e?w=800&q=80'],
            ['https://images.unsplash.com/photo-1542496658-e33a6d0d50f6?w=800&q=80', 'https://images.unsplash.com/photo-1612817288484-6f916006741a?w=800&q=80'],
            ['https://images.unsplash.com/photo-1587836374828-4dbafa94cf0e?w=800&q=80', 'https://images.unsplash.com/photo-1618517351616-38fb9c5210c6?w=800&q=80'],
            ['https://images.unsplash.com/photo-1614164185128-e4ec99c436d7?w=800&q=80', 'https://images.unsplash.com/photo-1547996160-81dfa63595aa?w=800&q=80'],
            ['https://images.unsplash.com/photo-1524592094714-0f0654e20314?w=800&q=80', 'https://images.unsplash.com/photo-1582979512210-99b6a53386f9?w=800&q=80'],
        ],
        'womens-watches' => [
            ['https://images.unsplash.com/photo-1434493789847-2f02dc6ca35d?w=800&q=80', 'https://images.unsplash.com/photo-1619134778706-7015533a6150?w=800&q=80'],
            ['https://images.unsplash.com/photo-1526045431048-f857369baa09?w=800&q=80', 'https://images.unsplash.com/photo-1508685096489-7aacd43bd3b1?w=800&q=80'],
            ['https://images.unsplash.com/photo-1587836374828-4dbafa94cf0e?w=800&q=80', 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=800&q=80'],
            ['https://images.unsplash.com/photo-1615655096345-61a54750068d?w=800&q=80', 'https://images.unsplash.com/photo-1611085583191-a3b181a88401?w=800&q=80'],
            ['https://images.unsplash.com/photo-1612817288484-6f916006741a?w=800&q=80', 'https://images.unsplash.com/photo-1542496658-e33a6d0d50f6?w=800&q=80'],
        ],
        'smart-watches' => [
            ['https://images.unsplash.com/photo-1546868871-7041f2a55e12?w=800&q=80', 'https://images.unsplash.com/photo-1617043786394-f977fa12eddf?w=800&q=80'],
            ['https://images.unsplash.com/photo-1579586337278-3befd40fd17a?w=800&q=80', 'https://images.unsplash.com/photo-1551816230-ef5deaed4a26?w=800&q=80'],
            ['https://images.unsplash.com/photo-1508685096489-7aacd43bd3b1?w=800&q=80', 'https://images.unsplash.com/photo-1544117519-31a4b719223d?w=800&q=80'],
            ['https://images.unsplash.com/photo-1617043786394-f977fa12eddf?w=800&q=80', 'https://images.unsplash.com/photo-1546868871-7041f2a55e12?w=800&q=80'],
            ['https://images.unsplash.com/photo-1551816230-ef5deaed4a26?w=800&q=80', 'https://images.unsplash.com/photo-1579586337278-3befd40fd17a?w=800&q=80'],
        ],
        'couple-watches' => [
            ['https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=800&q=80', 'https://images.unsplash.com/photo-1434493789847-2f02dc6ca35d?w=800&q=80'],
            ['https://images.unsplash.com/photo-1594534475808-b18fc33b045e?w=800&q=80', 'https://images.unsplash.com/photo-1526045431048-f857369baa09?w=800&q=80'],
            ['https://images.unsplash.com/photo-1612817288484-6f916006741a?w=800&q=80', 'https://images.unsplash.com/photo-1619134778706-7015533a6150?w=800&q=80'],
            ['https://images.unsplash.com/photo-1582979512210-99b6a53386f9?w=800&q=80', 'https://images.unsplash.com/photo-1614164185128-e4ec99c436d7?w=800&q=80'],
            ['https://images.unsplash.com/photo-1524592094714-0f0654e20314?w=800&q=80', 'https://images.unsplash.com/photo-1587836374828-4dbafa94cf0e?w=800&q=80'],
        ],
        // ── Electronics ──────────────────────────────────────────────
        'earbuds-headphones-speakers' => [
            ['https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800&q=80', 'https://images.unsplash.com/photo-1484704849700-f032a568e944?w=800&q=80'],
            ['https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=800&q=80', 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=800&q=80'],
            ['https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=800&q=80', 'https://images.unsplash.com/photo-1631281956016-3cdc1b2fe5fb?w=800&q=80'],
            ['https://images.unsplash.com/photo-1585386959984-a4155224a1ad?w=800&q=80', 'https://images.unsplash.com/photo-1572536147248-ac59a8abfa4b?w=800&q=80'],
            ['https://images.unsplash.com/photo-1611532736597-de2d4265fba3?w=800&q=80', 'https://images.unsplash.com/photo-1558089687-f282ffcbc126?w=800&q=80'],
        ],
        'mobile-phone-accessories' => [
            ['https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?w=800&q=80', 'https://images.unsplash.com/photo-1609692814858-f7cd2f0afa4f?w=800&q=80'],
            ['https://images.unsplash.com/photo-1562967914-608f82629710?w=800&q=80', 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=800&q=80'],
            ['https://images.unsplash.com/photo-1585771724684-38269d6639fd?w=800&q=80', 'https://images.unsplash.com/photo-1572275271972-4b253dd9f976?w=800&q=80'],
            ['https://images.unsplash.com/photo-1542751110-97427bbecfd6?w=800&q=80', 'https://images.unsplash.com/photo-1517420704952-d9f39e95b43e?w=800&q=80'],
            ['https://images.unsplash.com/photo-1628815113969-0487917e8b76?w=800&q=80', 'https://images.unsplash.com/photo-1604671801908-6f0c6a092c05?w=800&q=80'],
        ],
        'power-banks-chargers' => [
            ['https://images.unsplash.com/photo-1609692814858-f7cd2f0afa4f?w=800&q=80', 'https://images.unsplash.com/photo-1585771724684-38269d6639fd?w=800&q=80'],
            ['https://images.unsplash.com/photo-1583863788434-e58a36330cf0?w=800&q=80', 'https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?w=800&q=80'],
            ['https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&q=80', 'https://images.unsplash.com/photo-1542751110-97427bbecfd6?w=800&q=80'],
            ['https://images.unsplash.com/photo-1640955014216-75201056c829?w=800&q=80', 'https://images.unsplash.com/photo-1609592806596-b186f1b4d0e8?w=800&q=80'],
            ['https://images.unsplash.com/photo-1572275271972-4b253dd9f976?w=800&q=80', 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?w=800&q=80'],
        ],
        'smart-home-devices' => [
            ['https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&q=80', 'https://images.unsplash.com/photo-1585771724684-38269d6639fd?w=800&q=80'],
            ['https://images.unsplash.com/photo-1543269865-cbf427effbad?w=800&q=80', 'https://images.unsplash.com/photo-1558089687-f282ffcbc126?w=800&q=80'],
            ['https://images.unsplash.com/photo-1551650975-87deedd944c3?w=800&q=80', 'https://images.unsplash.com/photo-1531297484001-80022131f5a1?w=800&q=80'],
            ['https://images.unsplash.com/photo-1593642632559-0c6d3fc62b89?w=800&q=80', 'https://images.unsplash.com/photo-1555664424-778a1e5e1b48?w=800&q=80'],
            ['https://images.unsplash.com/photo-1518770660439-4636190af475?w=800&q=80', 'https://images.unsplash.com/photo-1587202372583-49330a15584d?w=800&q=80'],
        ],
        'computer-accessories' => [
            ['https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=800&q=80', 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=800&q=80'],
            ['https://images.unsplash.com/photo-1586936893354-362ad6ae47ba?w=800&q=80', 'https://images.unsplash.com/photo-1593642632559-0c6d3fc62b89?w=800&q=80'],
            ['https://images.unsplash.com/photo-1547394765-185e1e68f34e?w=800&q=80', 'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=800&q=80'],
            ['https://images.unsplash.com/photo-1545127398-14699f92334b?w=800&q=80', 'https://images.unsplash.com/photo-1561154464-82e9adf32764?w=800&q=80'],
            ['https://images.unsplash.com/photo-1518770660439-4636190af475?w=800&q=80', 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800&q=80'],
        ],
        // ── Beauty & Health ──────────────────────────────────────────
        'makeup-cosmetics' => [
            ['https://images.unsplash.com/photo-1512496015851-a90fb38ba796?w=800&q=80', 'https://images.unsplash.com/photo-1522338242992-e1a54906a8da?w=800&q=80'],
            ['https://images.unsplash.com/photo-1586495777744-4e6232bf5c0a?w=800&q=80', 'https://images.unsplash.com/photo-1599733594230-6b823ccd1e94?w=800&q=80'],
            ['https://images.unsplash.com/photo-1583241800698-e8ab01830a6b?w=800&q=80', 'https://images.unsplash.com/photo-1630344990049-6f25e4b07c0f?w=800&q=80'],
            ['https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?w=800&q=80', 'https://images.unsplash.com/photo-1619451334792-150fd785ee74?w=800&q=80'],
            ['https://images.unsplash.com/photo-1571781926291-c477ebfd024b?w=800&q=80', 'https://images.unsplash.com/photo-1621354300855-1a4e61eac7e2?w=800&q=80'],
        ],
        'skincare-products' => [
            ['https://images.unsplash.com/photo-1556228578-8c89e6adf883?w=800&q=80', 'https://images.unsplash.com/photo-1617897903246-719242758050?w=800&q=80'],
            ['https://images.unsplash.com/photo-1620916566398-39f1143ab7be?w=800&q=80', 'https://images.unsplash.com/photo-1599305445671-ac291c95aaa9?w=800&q=80'],
            ['https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?w=800&q=80', 'https://images.unsplash.com/photo-1615397349754-cfa2066a298e?w=800&q=80'],
            ['https://images.unsplash.com/photo-1611080626919-7cf5a9dbab12?w=800&q=80', 'https://images.unsplash.com/photo-1631729371254-42c2892f0e6e?w=800&q=80'],
            ['https://images.unsplash.com/photo-1596462502278-27bfdc403348?w=800&q=80', 'https://images.unsplash.com/photo-1590439471364-192aa70c0b53?w=800&q=80'],
        ],
        'haircare-styling' => [
            ['https://images.unsplash.com/photo-1527799820374-87036dcd41a1?w=800&q=80', 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=800&q=80'],
            ['https://images.unsplash.com/photo-1519699470814-48868cdf6f46?w=800&q=80', 'https://images.unsplash.com/photo-1526045612212-70caf35c14df?w=800&q=80'],
            ['https://images.unsplash.com/photo-1560066984-138dadb4c035?w=800&q=80', 'https://images.unsplash.com/photo-1522337660859-02fbefca4702?w=800&q=80'],
            ['https://images.unsplash.com/photo-1600948836101-f9ffda59d250?w=800&q=80', 'https://images.unsplash.com/photo-1512290923902-8a9f81dc236c?w=800&q=80'],
            ['https://images.unsplash.com/photo-1522338242992-e1a54906a8da?w=800&q=80', 'https://images.unsplash.com/photo-1589710751893-f9a6770ad71b?w=800&q=80'],
        ],
        'fragrances-perfumes' => [
            ['https://images.unsplash.com/photo-1592945403244-b3fbafd7f539?w=800&q=80', 'https://images.unsplash.com/photo-1541643600914-78b084683702?w=800&q=80'],
            ['https://images.unsplash.com/photo-1588776814546-daab30f310ce?w=800&q=80', 'https://images.unsplash.com/photo-1523293182086-7651a899d37f?w=800&q=80'],
            ['https://images.unsplash.com/photo-1594035910387-fea47794261f?w=800&q=80', 'https://images.unsplash.com/photo-1592945403244-b3fbafd7f539?w=800&q=80'],
            ['https://images.unsplash.com/photo-1615634260167-c8cdede054de?w=800&q=80', 'https://images.unsplash.com/photo-1590736704728-f4730bb30770?w=800&q=80'],
            ['https://images.unsplash.com/photo-1601584115197-04ecc0da31d7?w=800&q=80', 'https://images.unsplash.com/photo-1588776814546-daab30f310ce?w=800&q=80'],
        ],
        'grooming-tools' => [
            ['https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=800&q=80', 'https://images.unsplash.com/photo-1512290923902-8a9f81dc236c?w=800&q=80'],
            ['https://images.unsplash.com/photo-1621607512022-6aecc4fed814?w=800&q=80', 'https://images.unsplash.com/photo-1519699470814-48868cdf6f46?w=800&q=80'],
            ['https://images.unsplash.com/photo-1590439471364-192aa70c0b53?w=800&q=80', 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=800&q=80'],
            ['https://images.unsplash.com/photo-1589710751893-f9a6770ad71b?w=800&q=80', 'https://images.unsplash.com/photo-1621607512022-6aecc4fed814?w=800&q=80'],
            ['https://images.unsplash.com/photo-1522337660859-02fbefca4702?w=800&q=80', 'https://images.unsplash.com/photo-1527799820374-87036dcd41a1?w=800&q=80'],
        ],
        // ── Home & Living ────────────────────────────────────────────
        'home-decor' => [
            ['https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=800&q=80', 'https://images.unsplash.com/photo-1484101403633-562f891dc89a?w=800&q=80'],
            ['https://images.unsplash.com/photo-1581539250439-c96689b516dd?w=800&q=80', 'https://images.unsplash.com/photo-1506439773649-6e0eb8cfb237?w=800&q=80'],
            ['https://images.unsplash.com/photo-1493809842364-78817add7ffb?w=800&q=80', 'https://images.unsplash.com/photo-1501045661006-fcebe0257c3f?w=800&q=80'],
            ['https://images.unsplash.com/photo-1513519245088-0e12902e5a38?w=800&q=80', 'https://images.unsplash.com/photo-1529408632839-a54952c491e5?w=800&q=80'],
            ['https://images.unsplash.com/photo-1565538810643-b5bdb714032a?w=800&q=80', 'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?w=800&q=80'],
        ],
        'kitchen-dining' => [
            ['https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=800&q=80', 'https://images.unsplash.com/photo-1484101403633-562f891dc89a?w=800&q=80'],
            ['https://images.unsplash.com/photo-1588854337221-4cf9fa96059c?w=800&q=80', 'https://images.unsplash.com/photo-1506439773649-6e0eb8cfb237?w=800&q=80'],
            ['https://images.unsplash.com/photo-1565538810643-b5bdb714032a?w=800&q=80', 'https://images.unsplash.com/photo-1599785209707-a456fc1337bb?w=800&q=80'],
            ['https://images.unsplash.com/photo-1571902943202-507ec2618e8f?w=800&q=80', 'https://images.unsplash.com/photo-1581539250439-c96689b516dd?w=800&q=80'],
            ['https://images.unsplash.com/photo-1574269909862-7e1d70bb8078?w=800&q=80', 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=800&q=80'],
        ],
        'bedding-bath' => [
            ['https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?w=800&q=80', 'https://images.unsplash.com/photo-1583845112203-29329902332e?w=800&q=80'],
            ['https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=800&q=80', 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=800&q=80'],
            ['https://images.unsplash.com/photo-1584622650111-993a426fbf0a?w=800&q=80', 'https://images.unsplash.com/photo-1540518614846-7eded433c457?w=800&q=80'],
            ['https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?w=800&q=80', 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=800&q=80'],
            ['https://images.unsplash.com/photo-1613977257365-aaae5a9817ff?w=800&q=80', 'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?w=800&q=80'],
        ],
        'storage-organization' => [
            ['https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&q=80', 'https://images.unsplash.com/photo-1567538096630-e0c55bd6374c?w=800&q=80'],
            ['https://images.unsplash.com/photo-1513519245088-0e12902e5a38?w=800&q=80', 'https://images.unsplash.com/photo-1493809842364-78817add7ffb?w=800&q=80'],
            ['https://images.unsplash.com/photo-1497366811353-6870744d04b2?w=800&q=80', 'https://images.unsplash.com/photo-1565538810643-b5bdb714032a?w=800&q=80'],
            ['https://images.unsplash.com/photo-1484101403633-562f891dc89a?w=800&q=80', 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=800&q=80'],
            ['https://images.unsplash.com/photo-1583845112203-29329902332e?w=800&q=80', 'https://images.unsplash.com/photo-1513519245088-0e12902e5a38?w=800&q=80'],
        ],
        'tools-home-improvement' => [
            ['https://images.unsplash.com/photo-1504148455328-c376907d081c?w=800&q=80', 'https://images.unsplash.com/photo-1581092583537-20d51b4b4f1b?w=800&q=80'],
            ['https://images.unsplash.com/photo-1572981779307-38b8cabb2407?w=800&q=80', 'https://images.unsplash.com/photo-1504148455328-c376907d081c?w=800&q=80'],
            ['https://images.unsplash.com/photo-1581092160607-ee22621dd758?w=800&q=80', 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&q=80'],
            ['https://images.unsplash.com/photo-1530124566582-a618bc2615dc?w=800&q=80', 'https://images.unsplash.com/photo-1581092583537-20d51b4b4f1b?w=800&q=80'],
            ['https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=800&q=80', 'https://images.unsplash.com/photo-1572981779307-38b8cabb2407?w=800&q=80'],
        ],
        // ── Mother & Kids ────────────────────────────────────────────
        'boys-clothing' => [
            ['https://images.unsplash.com/photo-1519457431-44ccd64a579b?w=800&q=80', 'https://images.unsplash.com/photo-1508214751196-bcfd4ca60f91?w=800&q=80'],
            ['https://images.unsplash.com/photo-1503944583220-79d8926ad5e2?w=800&q=80', 'https://images.unsplash.com/photo-1519457431-44ccd64a579b?w=800&q=80'],
            ['https://images.unsplash.com/photo-1509114397022-ed747cca3f65?w=800&q=80', 'https://images.unsplash.com/photo-1503944583220-79d8926ad5e2?w=800&q=80'],
            ['https://images.unsplash.com/photo-1471286174890-9c112ac6476d?w=800&q=80', 'https://images.unsplash.com/photo-1509114397022-ed747cca3f65?w=800&q=80'],
            ['https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=800&q=80', 'https://images.unsplash.com/photo-1471286174890-9c112ac6476d?w=800&q=80'],
        ],
        'girls-clothing' => [
            ['https://images.unsplash.com/photo-1518831959646-742c3a14ebf7?w=800&q=80', 'https://images.unsplash.com/photo-1471286174890-9c112ac6476d?w=800&q=80'],
            ['https://images.unsplash.com/photo-1508214751196-bcfd4ca60f91?w=800&q=80', 'https://images.unsplash.com/photo-1518831959646-742c3a14ebf7?w=800&q=80'],
            ['https://images.unsplash.com/photo-1503944583220-79d8926ad5e2?w=800&q=80', 'https://images.unsplash.com/photo-1508214751196-bcfd4ca60f91?w=800&q=80'],
            ['https://images.unsplash.com/photo-1519457431-44ccd64a579b?w=800&q=80', 'https://images.unsplash.com/photo-1503944583220-79d8926ad5e2?w=800&q=80'],
            ['https://images.unsplash.com/photo-1509114397022-ed747cca3f65?w=800&q=80', 'https://images.unsplash.com/photo-1519457431-44ccd64a579b?w=800&q=80'],
        ],
        'baby-clothes-shoes' => [
            ['https://images.unsplash.com/photo-1515488042361-ee00e0ddd4e4?w=800&q=80', 'https://images.unsplash.com/photo-1519689680058-324335c77eba?w=800&q=80'],
            ['https://images.unsplash.com/photo-1519689680058-324335c77eba?w=800&q=80', 'https://images.unsplash.com/photo-1515488042361-ee00e0ddd4e4?w=800&q=80'],
            ['https://images.unsplash.com/photo-1573167243872-43c6433b9d40?w=800&q=80', 'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=800&q=80'],
            ['https://images.unsplash.com/photo-1471286174890-9c112ac6476d?w=800&q=80', 'https://images.unsplash.com/photo-1573167243872-43c6433b9d40?w=800&q=80'],
            ['https://images.unsplash.com/photo-1508214751196-bcfd4ca60f91?w=800&q=80', 'https://images.unsplash.com/photo-1471286174890-9c112ac6476d?w=800&q=80'],
        ],
        'toys-hobbies' => [
            ['https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&q=80', 'https://images.unsplash.com/photo-1596461404969-9ae70f2830c1?w=800&q=80'],
            ['https://images.unsplash.com/photo-1515488042361-ee00e0ddd4e4?w=800&q=80', 'https://images.unsplash.com/photo-1560348980-ec29bfa9a70b?w=800&q=80'],
            ['https://images.unsplash.com/photo-1566576912321-d58ddd7a6088?w=800&q=80', 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&q=80'],
            ['https://images.unsplash.com/photo-1560348980-ec29bfa9a70b?w=800&q=80', 'https://images.unsplash.com/photo-1519689680058-324335c77eba?w=800&q=80'],
            ['https://images.unsplash.com/photo-1596461404969-9ae70f2830c1?w=800&q=80', 'https://images.unsplash.com/photo-1566576912321-d58ddd7a6088?w=800&q=80'],
        ],
        'maternity-wear-care' => [
            ['https://images.unsplash.com/photo-1555252333-9f8e92e65df9?w=800&q=80', 'https://images.unsplash.com/photo-1519689680058-324335c77eba?w=800&q=80'],
            ['https://images.unsplash.com/photo-1515488042361-ee00e0ddd4e4?w=800&q=80', 'https://images.unsplash.com/photo-1555252333-9f8e92e65df9?w=800&q=80'],
            ['https://images.unsplash.com/photo-1556228578-8c89e6adf883?w=800&q=80', 'https://images.unsplash.com/photo-1515488042361-ee00e0ddd4e4?w=800&q=80'],
            ['https://images.unsplash.com/photo-1620916566398-39f1143ab7be?w=800&q=80', 'https://images.unsplash.com/photo-1556228578-8c89e6adf883?w=800&q=80'],
            ['https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?w=800&q=80', 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?w=800&q=80'],
        ],
        // ── Sports ───────────────────────────────────────────────────
        'activewear-gym-wear' => [
            ['https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=800&q=80', 'https://images.unsplash.com/photo-1574680096145-d05b474e2155?w=800&q=80'],
            ['https://images.unsplash.com/photo-1518459031867-a89b944bffe4?w=800&q=80', 'https://images.unsplash.com/photo-1552674605-db6ffd4facb5?w=800&q=80'],
            ['https://images.unsplash.com/photo-1519505907962-0a6cb0167c73?w=800&q=80', 'https://images.unsplash.com/photo-1518459031867-a89b944bffe4?w=800&q=80'],
            ['https://images.unsplash.com/photo-1535914254981-b5012eebbd15?w=800&q=80', 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=800&q=80'],
            ['https://images.unsplash.com/photo-1574680096145-d05b474e2155?w=800&q=80', 'https://images.unsplash.com/photo-1535914254981-b5012eebbd15?w=800&q=80'],
        ],
        'sports-shoes' => [
            ['https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=800&q=80', 'https://images.unsplash.com/photo-1491553895911-0055eca6402d?w=800&q=80'],
            ['https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=800&q=80', 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=800&q=80'],
            ['https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?w=800&q=80', 'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=800&q=80'],
            ['https://images.unsplash.com/photo-1608231387042-66d1773070a5?w=800&q=80', 'https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?w=800&q=80'],
            ['https://images.unsplash.com/photo-1491553895911-0055eca6402d?w=800&q=80', 'https://images.unsplash.com/photo-1608231387042-66d1773070a5?w=800&q=80'],
        ],
        'fitness-gym-equipment' => [
            ['https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=800&q=80', 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?w=800&q=80'],
            ['https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80', 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=800&q=80'],
            ['https://images.unsplash.com/photo-1517836357463-d25dfeac3438?w=800&q=80', 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80'],
            ['https://images.unsplash.com/photo-1540497077202-7c8a3999166f?w=800&q=80', 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=800&q=80'],
            ['https://images.unsplash.com/photo-1583454110551-21f2fa2afe61?w=800&q=80', 'https://images.unsplash.com/photo-1540497077202-7c8a3999166f?w=800&q=80'],
        ],
        'outdoor-gear' => [
            ['https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?w=800&q=80', 'https://images.unsplash.com/photo-1504280390367-361c6d9f38f4?w=800&q=80'],
            ['https://images.unsplash.com/photo-1504280390367-361c6d9f38f4?w=800&q=80', 'https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?w=800&q=80'],
            ['https://images.unsplash.com/photo-1527786356703-4b100091cd2c?w=800&q=80', 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&q=80'],
            ['https://images.unsplash.com/photo-1537225228614-56cc3556d7ed?w=800&q=80', 'https://images.unsplash.com/photo-1527786356703-4b100091cd2c?w=800&q=80'],
            ['https://images.unsplash.com/photo-1551632811-561732d1e306?w=800&q=80', 'https://images.unsplash.com/photo-1537225228614-56cc3556d7ed?w=800&q=80'],
        ],
    ];

    /** Product name templates per subcategory. 5 per subcategory. */
    private array $products = [
        't-shirts-polos' => [
            ['Classic Slim Fit Polo T-Shirt', 890, 1200],
            ['Premium Cotton Round Neck Tee', 650, null],
            ['Striped Polo Shirt', 1100, 1400],
            ['Graphic Print T-Shirt', 550, 750],
            ['Pique Polo Regular Fit', 980, null],
        ],
        'shirts-casual-formal' => [
            ['Oxford Button-Down Shirt', 1490, 1800],
            ['Slim Fit Formal Dress Shirt', 1650, null],
            ['Linen Casual Shirt', 1200, 1500],
            ['Check Flannel Shirt', 990, 1200],
            ['Solid Twill Business Shirt', 1750, null],
        ],
        'jeans-trousers' => [
            ['Slim Fit Dark Blue Jeans', 1890, 2200],
            ['Regular Fit Chino Trousers', 1450, null],
            ['Stretch Skinny Jeans', 1600, 2000],
            ['Cargo Trousers Multi-Pocket', 1350, 1600],
            ['Classic Straight Leg Jeans', 1700, null],
        ],
        'hoodies-sweatshirts' => [
            ['Fleece Pullover Hoodie', 1590, 1900],
            ['Zip-Up Sweatshirt', 1400, null],
            ['Oversized Tie-Dye Hoodie', 1650, 2000],
            ['Essential Crewneck Sweatshirt', 1100, 1350],
            ['Heavyweight Cotton Hoodie', 1800, null],
        ],
        'jackets-coats' => [
            ['Bomber Jacket Lightweight', 2490, 2900],
            ['Denim Trucker Jacket', 2200, null],
            ['Windbreaker Track Jacket', 1990, 2400],
            ['Puffer Insulated Jacket', 3200, 3800],
            ['Classic Trench Coat', 3900, null],
        ],
        'innerwear-sleepwear' => [
            ['Cotton Boxer Brief 3-Pack', 790, null],
            ['Sleepwear Set Soft Cotton', 1100, 1400],
            ['Thermal Underwear Set', 1350, null],
            ['Modal Trunk Briefs 2-Pack', 650, 850],
            ['Relaxed Fit Pajama Set', 1250, null],
        ],
        'traditional-wear' => [
            ['Premium Panjabi Eid Collection', 1890, 2200],
            ['Embroidered Kurta Set', 2100, null],
            ['Classic Fatua Casual Wear', 990, 1200],
            ['Silk Sherwani Party Wear', 4500, 5200],
            ['Cotton Dhoti Kurta Set', 1650, null],
        ],
        'womens-traditional-wear' => [
            ['Embroidered Saree Festive Wear', 3500, 4200],
            ['Silk Salwar Kameez Set', 2900, null],
            ['Designer Kurti Cotton', 1200, 1500],
            ['Bridal Lehenga Choli Set', 8500, null],
            ['Anarkali Suit Churidar', 2200, 2600],
        ],
        'dresses' => [
            ['Floral Midi Wrap Dress', 1590, 1900],
            ['Off-Shoulder Bodycon Dress', 1750, null],
            ['Casual Shirt Dress Linen', 1400, 1700],
            ['Evening Maxi Dress Chiffon', 2200, 2600],
            ['Mini Skater Dress Cotton', 1100, 1350],
        ],
        'tops-tees-blouses' => [
            ['Flowy Chiffon Blouse', 890, 1100],
            ['Cropped Cotton T-Shirt', 650, null],
            ['Ruffled Sleeve Top', 990, 1200],
            ['Satin Cami Blouse', 1100, null],
            ['Oversized Graphic Tee', 750, 950],
        ],
        'jeans-pants-skirts' => [
            ['High-Waist Skinny Jeans', 1750, 2100],
            ['A-Line Midi Skirt', 1200, null],
            ['Wide-Leg Palazzo Pants', 1450, 1700],
            ['Denim Mini Skirt', 990, 1200],
            ['Jogger-Style Trousers', 1100, null],
        ],
        'hoodies-sweaters' => [
            ['Knit Cable Sweater Oversized', 1890, 2200],
            ['Pastel Zip Hoodie Women', 1550, null],
            ['Turtleneck Ribbed Sweater', 1700, 2000],
            ['Cropped Hoodie Cotton', 1300, 1550],
            ['V-Neck Cashmere-Blend Sweater', 2400, null],
        ],
        'lingerie-sleepwear' => [
            ['Lace-Trim Satin Slip Dress', 1350, null],
            ['Cotton Pajama Set Printed', 1100, 1350],
            ['Comfortable Bralette Set', 890, null],
            ['Floral Nightgown Satin', 1450, 1700],
            ['Sleep Shorts & Top Set', 950, 1200],
        ],
        'mens-sneakers-sports-shoes' => [
            ['Air Cushion Running Sneakers', 2890, 3400],
            ['Classic White Canvas Shoes', 1590, null],
            ['Sports Training Shoes', 2200, 2600],
            ['Chunky Sole Sneakers Trend', 2450, null],
            ['Breathable Mesh Running Shoes', 1990, 2300],
        ],
        'mens-formal-casual-shoes' => [
            ['Oxford Leather Brogue Shoes', 3200, null],
            ['Slip-On Loafer Dress Shoes', 2400, 2800],
            ['Derby Cap Toe Formal Shoes', 2900, null],
            ['Suede Casual Moccasins', 2100, 2500],
            ['Chelsea Boots Leather', 3500, 4000],
        ],
        'womens-sneakers' => [
            ['Platform Chunky Sneakers', 2400, 2800],
            ['Classic Low-Top Canvas', 1400, null],
            ['Knit Sock Sneakers', 1890, null],
            ['Metallic Shimmer Trainers', 2100, 2500],
            ['Retro Runner Sneakers', 2200, null],
        ],
        'heels-flats' => [
            ['Block Heel Ankle Strap Sandal', 1890, 2200],
            ['Pointed-Toe Kitten Heels', 2100, null],
            ['Strappy Stiletto Heels', 2400, 2800],
            ['Ballet Flats Leather', 1500, null],
            ['Platform Wedge Heels', 2200, 2600],
        ],
        'sandals-slippers' => [
            ['Leather Slide Sandals', 1100, 1350],
            ['Foam Comfort Slippers', 590, null],
            ['Strappy Gladiator Sandals', 1350, 1600],
            ['Memory Foam House Slippers', 790, 950],
            ['Beach Flip-Flop Casual', 490, null],
        ],
        'boots' => [
            ['Ankle Chelsea Boots Suede', 2900, 3400],
            ['Knee-High Riding Boots', 3800, null],
            ['Combat Lace-Up Boots', 2600, 3100],
            ['Rain Boots Waterproof', 2100, null],
            ['Western Cowboy Boots', 3400, 4000],
        ],
        'backpacks' => [
            ['Urban Daypack 20L Waterproof', 2200, 2600],
            ['School Backpack Large 30L', 1890, null],
            ['Hiking Trekking Backpack 40L', 3200, 3800],
            ['Mini Backpack Trendy', 1350, 1600],
            ['Laptop Backpack 15.6" Padded', 2500, null],
        ],
        'womens-handbags-crossbody-bags' => [
            ['Structured Tote Bag PU Leather', 2100, 2500],
            ['Chain Strap Crossbody Bag', 1850, null],
            ['Mini Satchel Shoulder Bag', 1650, 2000],
            ['Quilted Flap Clutch', 1400, null],
            ['Bucket Bag Drawstring', 1750, 2100],
        ],
        'wallets-purses' => [
            ['Slim Bifold Leather Wallet', 990, 1200],
            ['RFID Blocking Card Holder', 790, null],
            ['Ladies Zip-Around Purse', 1100, 1350],
            ['Men Genuine Leather Wallet', 1250, null],
            ['Coin Purse Mini Cute', 450, 600],
        ],
        'travel-bags-luggage' => [
            ['Hard Shell Cabin Trolley 20"', 4900, 5800],
            ['Softshell Rolling Luggage 24"', 5500, null],
            ['Duffel Bag Weekend Travel', 2200, 2600],
            ['Travel Organizer Packing Cubes', 890, null],
            ['Carry-On Spinner Luggage 22"', 6200, 7200],
        ],
        'laptop-bags-briefcases' => [
            ['Slim Laptop Sleeve 15.6"', 990, 1200],
            ['Executive Briefcase Leather', 3200, null],
            ['Canvas Messenger Bag', 1650, 2000],
            ['Rolling Laptop Trolley Bag', 2900, null],
            ['Padded Laptop Shoulder Bag', 1800, 2200],
        ],
        'necklaces-pendants' => [
            ['Gold-Plated Chain Necklace', 1590, null],
            ['Sterling Silver Heart Pendant', 1890, 2200],
            ['Layered Statement Necklace', 1200, 1500],
            ['Pearl Strand Necklace Classic', 2100, null],
            ['Rose Gold Letter Pendant', 1350, 1600],
        ],
        'earrings-rings' => [
            ['Crystal Drop Earrings', 890, null],
            ['Gold Hoop Earrings Set', 1100, 1350],
            ['Stackable Ring Set Silver', 990, null],
            ['Stud Earrings Pearl Classic', 750, 950],
            ['Twisted Band Ring Gold', 1250, 1500],
        ],
        'bracelets-bangles' => [
            ['Charm Bracelet Silver Set', 1100, 1350],
            ['Gold Bangle Stack Set 6pcs', 1590, null],
            ['Beaded Friendship Bracelet', 650, null],
            ['Tennis Bracelet Crystal', 1890, 2200],
            ['Leather Cuff Bracelet Men', 890, null],
        ],
        'sunglasses-eyewear' => [
            ['Polarized Aviator Sunglasses', 1890, 2200],
            ['Cat-Eye Retro Sunglasses', 1400, null],
            ['Wayfarer UV400 Sunglasses', 1650, 2000],
            ['Sporty Wrap-Around Shades', 1200, 1500],
            ['Oversized Square Sunglasses', 1550, null],
        ],
        'belts-ties-hats' => [
            ['Genuine Leather Belt Black', 990, 1200],
            ['Woven Fabric Canvas Belt', 590, null],
            ['Slim Silk Formal Tie', 850, 1050],
            ['Baseball Cap Embroidered', 750, null],
            ['Wool Fedora Hat Classic', 1350, 1600],
        ],
        'mens-watches' => [
            ['Classic Analog Dress Watch', 3490, 4200],
            ['Chronograph Stainless Steel', 5200, null],
            ['Minimalist Leather Strap Watch', 2900, 3400],
            ['Skeleton Automatic Watch', 6800, 8000],
            ['Sports Dive Watch 100M', 4100, null],
        ],
        'womens-watches' => [
            ['Rose Gold Bracelet Watch', 3200, 3800],
            ['Marble Dial Fashion Watch', 2500, null],
            ['Diamond-Studded Quartz Watch', 4500, 5300],
            ['Floral Print Band Watch', 1900, 2300],
            ['Minimalist Mesh Strap Watch', 2800, null],
        ],
        'smart-watches' => [
            ['Smart Watch Fitness Tracker', 3990, 4800],
            ['AMOLED Display Smart Band', 5200, null],
            ['GPS Sports Smart Watch', 7500, 8900],
            ['Health Monitor Smart Watch', 4500, null],
            ['Kids Smart Watch GPS Tracker', 2900, 3400],
        ],
        'couple-watches' => [
            ['His & Hers Watch Set Silver', 5900, 7000],
            ['Rose Gold Couple Watch Pair', 6500, null],
            ['Matching Minimal Watch Set', 4900, 5800],
            ['Classic Stainless Couple Set', 5400, null],
            ['Leather Strap Couple Watches', 4600, 5500],
        ],
        'earbuds-headphones-speakers' => [
            ['TWS Earbuds Active Noise Cancel', 3490, 4200],
            ['Over-Ear Wireless Headphones', 4900, null],
            ['Portable Bluetooth Speaker', 2200, 2600],
            ['Gaming Headset Surround Sound', 3200, null],
            ['Sports Earbuds Waterproof', 1990, 2400],
        ],
        'mobile-phone-accessories' => [
            ['Tempered Glass Screen Protector', 290, null],
            ['Magsafe Wireless Charger', 1890, 2200],
            ['Clear Shockproof Phone Case', 450, 600],
            ['Pop Socket Phone Grip Holder', 390, null],
            ['Ring Light Selfie Attachment', 790, 950],
        ],
        'power-banks-chargers' => [
            ['20000mAh Power Bank Fast Charge', 2890, 3400],
            ['65W GaN USB-C Charger', 2200, null],
            ['10000mAh Slim Power Bank', 1990, null],
            ['Wireless Charging Pad 15W', 1450, 1750],
            ['Car Charger Dual USB 30W', 690, 850],
        ],
        'smart-home-devices' => [
            ['Smart LED Bulb RGB WiFi', 690, null],
            ['Smart Plug Energy Monitor', 890, 1100],
            ['Voice Assistant Smart Speaker', 3500, null],
            ['Smart Doorbell Camera WiFi', 4200, 5000],
            ['Smart Power Strip Surge', 1900, 2300],
        ],
        'computer-accessories' => [
            ['Mechanical Gaming Keyboard', 3900, 4600],
            ['Wireless Ergonomic Mouse', 2100, null],
            ['USB-C Hub 7-in-1 Adapter', 1890, 2200],
            ['Gaming Mouse Pad XXL', 890, null],
            ['Webcam 1080p HD Autofocus', 2500, 3000],
        ],
        'makeup-cosmetics' => [
            ['Matte Liquid Lipstick Set', 890, null],
            ['Foundation Full Coverage', 1490, 1800],
            ['Eyeshadow Palette 18 Colors', 1200, 1500],
            ['Mascara Volume & Length', 650, null],
            ['BB Cream SPF30 Tinted', 990, 1200],
        ],
        'skincare-products' => [
            ['Vitamin C Brightening Serum', 1890, 2200],
            ['Hyaluronic Acid Moisturizer', 1590, null],
            ['SPF50 Sunscreen Daily Wear', 990, 1200],
            ['Retinol Night Cream Anti-Age', 2100, null],
            ['Niacinamide Toning Toner', 1100, 1350],
        ],
        'haircare-styling' => [
            ['Argan Oil Hair Serum 100ml', 890, null],
            ['Keratin Shampoo & Conditioner', 1200, 1450],
            ['Ionic Hair Dryer 2200W', 3500, 4200],
            ['Hair Straightener Ceramic', 2900, null],
            ['Deep Conditioning Hair Mask', 750, 950],
        ],
        'fragrances-perfumes' => [
            ['Oud Rose Eau de Parfum 50ml', 2490, 2900],
            ['Fresh Citrus EDT 100ml', 1890, null],
            ['Floral Bouquet EDP Women', 2200, 2600],
            ['Black Oud Men EDP 75ml', 2800, null],
            ['Vanilla Musk EDP 50ml', 1990, 2400],
        ],
        'grooming-tools' => [
            ['Electric Shaver Waterproof', 3200, null],
            ['Beard Trimmer 20 Settings', 2500, 3000],
            ['Nose & Ear Hair Trimmer', 990, null],
            ['Face Massager Vibrating', 1650, 2000],
            ['Precision Hair Clipper', 2100, 2500],
        ],
        'home-decor' => [
            ['Scented Candle Set 3pcs', 890, null],
            ['Macrame Wall Hanging Art', 1350, 1600],
            ['Ceramic Vase Set Minimalist', 1100, 1350],
            ['Boho Dream Catcher Decor', 650, null],
            ['LED Fairy Lights 10m String', 790, 950],
        ],
        'kitchen-dining' => [
            ['Non-Stick Cookware Set 5pcs', 3900, 4600],
            ['Stainless Steel Knife Set', 2200, null],
            ['Electric Kettle 1.7L Glass', 1890, 2200],
            ['Bamboo Cutting Board Set', 990, null],
            ['Ceramic Dinner Set 12pcs', 3500, 4200],
        ],
        'bedding-bath' => [
            ['Cotton Bedsheet Set King', 2200, 2600],
            ['Memory Foam Pillow Pair', 1890, null],
            ['Fluffy Bath Towel Set 3pcs', 1400, 1700],
            ['Weighted Blanket 5kg', 3200, null],
            ['Microfiber Duvet Cover Set', 1750, 2100],
        ],
        'storage-organization' => [
            ['Under-Bed Storage Box Set', 990, null],
            ['Hanging Closet Organizer', 790, 950],
            ['Stackable Drawer Organizer', 650, null],
            ['Over-Door Shoe Organizer', 890, 1100],
            ['Bamboo Drawer Divider Set', 750, null],
        ],
        'tools-home-improvement' => [
            ['Cordless Drill Driver 18V', 4900, null],
            ['Tool Kit 100-Piece Set', 2200, 2600],
            ['Laser Level Self-Leveling', 2900, null],
            ['Stud Finder Wall Scanner', 1500, 1800],
            ['Utility Knife Heavy Duty', 590, 750],
        ],
        'boys-clothing' => [
            ['Dinosaur Print T-Shirt Kids', 490, null],
            ['Denim Shorts Boys Summer', 650, null],
            ['School Uniform Shirt & Pant', 990, 1200],
            ['Tracksuit Set Boys Sport', 1100, 1350],
            ['Winter Jacket Boys Warm', 1890, null],
        ],
        'girls-clothing' => [
            ['Princess Tutu Dress Girls', 890, null],
            ['Floral Leggings & Top Set', 750, 950],
            ['School Uniform Girls Skirt', 890, null],
            ['Denim Pinafore Dress', 990, 1200],
            ['Cardigan Knit Girls Winter', 1100, null],
        ],
        'baby-clothes-shoes' => [
            ['Organic Cotton Onesie 3-Pack', 790, null],
            ['Baby Romper Elephant Print', 650, 800],
            ['Soft Sole Baby Shoes', 590, null],
            ['Baby Sleepsuit Fleece', 750, 950],
            ['Muslin Swaddle Blanket Set', 890, null],
        ],
        'toys-hobbies' => [
            ['LEGO Classic Building Blocks', 1890, null],
            ['Remote Control Car Kids', 2200, 2600],
            ['Puzzle 1000 Pieces Adults', 890, null],
            ['Wooden Stacking Toy Baby', 750, 950],
            ['Slime Kit DIY Creative', 590, null],
        ],
        'maternity-wear-care' => [
            ['Maternity Nursing Dress', 1590, null],
            ['Belly Support Band Adjustable', 990, 1200],
            ['Maternity Leggings Stretch', 1100, null],
            ['Nursing Bra Comfortable', 890, 1100],
            ['Pregnancy Pillow U-Shape', 3200, null],
        ],
        'activewear-gym-wear' => [
            ['Compression Gym Leggings', 1590, null],
            ['Dry-Fit Training T-Shirt', 890, 1100],
            ['Sports Bra High Impact', 1200, null],
            ['Gym Shorts Quick-Dry', 790, 950],
            ['Yoga Pants Wide Leg', 1350, 1600],
        ],
        'sports-shoes' => [
            ['Running Shoes Lightweight', 2890, 3400],
            ['Basketball High-Top Shoes', 3200, null],
            ['Football Turf Shoes Indoor', 2200, 2600],
            ['Training Cross Trainers', 2500, null],
            ['Tennis Court Shoes Clay', 2800, 3300],
        ],
        'fitness-gym-equipment' => [
            ['Resistance Bands Set 5pcs', 890, null],
            ['Adjustable Dumbbell 20kg', 4500, 5400],
            ['Yoga Mat Non-Slip 6mm', 1290, null],
            ['Pull-Up Bar Doorway', 1890, 2200],
            ['Foam Roller Deep Tissue', 990, 1200],
        ],
        'outdoor-gear' => [
            ['Camping Tent 2-Person', 5900, null],
            ['Hiking Water Bottle 1L', 890, 1100],
            ['Trekking Poles Collapsible', 1890, 2200],
            ['Camping Sleeping Bag', 2900, null],
            ['Headlamp LED 300 Lumens', 1100, 1350],
        ],
    ];

    /** Maps actual DB slug → key used in $this->products / $this->images */
    private array $slugAliases = [
        'mens-shirts' => 'shirts-casual-formal',
        'mens-hoodies' => 'hoodies-sweatshirts',
        'mens-innerwear' => 'innerwear-sleepwear',
        'mens-traditional' => 'traditional-wear',
        'womens-jeans-pants' => 'jeans-pants-skirts',
        'womens-hoodies' => 'hoodies-sweaters',
        'womens-traditional' => 'womens-traditional-wear',
        'womens-lingerie' => 'lingerie-sleepwear',
        'mens-sneakers' => 'mens-sneakers-sports-shoes',
        'mens-formal-shoes' => 'mens-formal-casual-shoes',
        'handbags' => 'womens-handbags-crossbody-bags',
        'travel-bags' => 'travel-bags-luggage',
        'laptop-bags' => 'laptop-bags-briefcases',
        'necklaces' => 'necklaces-pendants',
        'sunglasses' => 'sunglasses-eyewear',
        'audio' => 'earbuds-headphones-speakers',
        'phone-accessories' => 'mobile-phone-accessories',
        'power-banks' => 'power-banks-chargers',
        'smart-home' => 'smart-home-devices',
        'makeup' => 'makeup-cosmetics',
        'skincare' => 'skincare-products',
        'haircare' => 'haircare-styling',
        'fragrances' => 'fragrances-perfumes',
        'grooming' => 'grooming-tools',
        'storage' => 'storage-organization',
        'tools' => 'tools-home-improvement',
        'baby-clothes' => 'baby-clothes-shoes',
        'maternity' => 'maternity-wear-care',
        'activewear' => 'activewear-gym-wear',
        'gym-equipment' => 'fitness-gym-equipment',
    ];

    public function run(): void
    {
        $brands = Brand::pluck('id', 'name');
        $brandIds = $brands->values()->toArray();

        $subcategories = Category::whereNotNull('parent_id')
            ->with('parent')
            ->get()
            ->keyBy(fn ($c) => $c->slug);

        $created = 0;

        foreach ($subcategories as $slug => $category) {
            $key = $this->slugAliases[$slug] ?? $slug;
            $productList = $this->products[$key] ?? null;
            $imageList = $this->images[$key] ?? null;

            if (! $productList || ! $imageList) {
                continue;
            }

            foreach ($productList as $i => [$name, $price, $comparePrice]) {
                // Skip if this product already exists
                if (Product::where('name', $name)->exists()) {
                    continue;
                }

                $brandId = $brandIds[array_rand($brandIds)];
                $sku = strtoupper(Str::random(3)).'-'.rand(1000, 9999);

                $product = Product::create([
                    'name' => $name,
                    'slug' => Str::slug($name).'-'.Str::random(4),
                    'sku' => $sku,
                    'description' => "High quality {$name}. Perfect for everyday use. Made with premium materials for comfort and durability.",
                    'short_description' => "Premium quality {$name} at an unbeatable price.",
                    'category_id' => $category->id,
                    'brand_id' => $brandId,
                    'price' => $price,
                    'compare_price' => $comparePrice,
                    'stock_quantity' => rand(15, 150),
                    'is_active' => true,
                    'is_featured' => ($i === 0),
                    'published_at' => now()->subDays(rand(1, 60)),
                ]);

                $imgs = $imageList[$i];

                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => $imgs[0],
                    'alt' => $name,
                    'sort_order' => 0,
                    'is_primary' => true,
                ]);

                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => $imgs[1],
                    'alt' => $name.' - view 2',
                    'sort_order' => 1,
                    'is_primary' => false,
                ]);

                $created++;
            }
        }

        $this->command->info("Created {$created} products across ".count($subcategories).' subcategories.');
    }
}
