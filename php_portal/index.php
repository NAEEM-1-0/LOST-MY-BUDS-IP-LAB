<?php
/**
 * ==============================================================================================
 * ফাইল পরিচিতি: index.php (Commercial E-Commerce Storefront Engine)
 * কোর্স: Internet Programming Lab (IP Lab) / Web Engineering
 * প্রজেক্ট: BudsRecover — Commercial PHP E-Commerce Store
 * ==============================================================================================
 * 
 * 🎓 ম্যামের সম্ভাব্য প্রশ্ন ও উত্তর (Viva Q&A):
 * ১. প্রশ্ন: ইকমার্স শপের প্রোডাক্ট ফিল্টারিং এবং সার্চ পিএইচপিতে কীভাবে করা হয়েছে?
 *    উত্তর: ইউজারের পাঠানো GET প্যারামিটার ($_GET['q'], $_GET['brand'], $_GET['category'], $_GET['sort'])
 *          পড়ে নিয়ে PDO প্রিপেয়ার্ড স্টেটমেন্ট ($pdo->prepare()) এ ডাইনামিক WHERE ক্লজ এবং :params বাইন্ড
 *          করে ডাটাবেজ থেকে ফিল্টার করা হয়েছে।
 * ২. প্রশ্ন: কার্টে যোগ করার পর কীভাবে সেশন আপডেট হচ্ছে?
 *    উত্তর: যখন ইউজার 'কার্টে যোগ করুন' বাটনে চাপ দেয়, index.php?action=add&id=... হ্যান্ডল করে
 *          $_SESSION['cart'][$id] এ পরিমাণ বাড়িয়ে দেয় এবং PRG প্যাটার্নে রিডাইরেক্ট করে।
 * ==============================================================================================
 */

// ১. পেজ টাইটেল ও ডাটাবেজ কানেকশন
$page_title = "হোম স্টোর — অরিজিনাল সিঙ্গেল বাড রিপ্লেসমেন্ট";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/cart_functions.php';

// ২. কার্টে প্রোডাক্ট যোগ করার রিকোয়েস্ট হ্যান্ডলিং (GET ?action=add&id=...)
if (isset($_GET['action']) && $_GET['action'] === 'add' && isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    if ($product_id > 0) {
        // ডাটাবেজে প্রোডাক্টটি সক্রিয় এবং স্টকে আছে কিনা চেক করা
        $stmt_check = $pdo->prepare("SELECT id, name, price, stock FROM products WHERE id = :id LIMIT 1");
        $stmt_check->bindValue(':id', $product_id, PDO::PARAM_INT);
        $stmt_check->execute();
        $p_check = $stmt_check->fetch();

        if ($p_check && $p_check['stock'] > 0) {
            add_to_cart($product_id, 1);
            $_SESSION['flash_success'] = "🎉 '" . htmlspecialchars($p_check['name']) . "' আপনার শপিং ব্যাগে যুক্ত হয়েছে!";
        } else {
            $_SESSION['flash_error'] = "দুঃখিত, এই পণ্যটির পর্যাপ্ত স্টক নেই।";
        }
    }
    // PRG প্যাটার্ন: রিডাইরেক্ট করে কার্ট পেজ অথবা হোমপেজে ফিরিয়ে আনা
    $redirect_url = isset($_GET['redirect']) && $_GET['redirect'] === 'cart' ? 'cart.php' : 'index.php#shop';
    header("Location: " . $redirect_url);
    exit;
}

// ৩. সার্চ এবং ফিল্টারিং প্যারামিটার সংগ্রহ ও স্যানিটাইজেশন
$query = isset($_GET['q']) ? trim(htmlspecialchars($_GET['q'])) : '';
$selected_brand = isset($_GET['brand']) ? trim(htmlspecialchars($_GET['brand'])) : '';
$selected_category = isset($_GET['category']) ? trim(htmlspecialchars($_GET['category'])) : '';
$selected_model = isset($_GET['model']) ? trim(htmlspecialchars($_GET['model'])) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';

// ৪. ডাইনামিক SQL কুয়েরি তৈরি (PDO Prepared Statements)
$sql = "SELECT * FROM products WHERE 1=1";
$params = [];

if (!empty($query)) {
    $sql .= " AND (name LIKE :q1 OR model LIKE :q2 OR brand LIKE :q3 OR description LIKE :q4)";
    $search_term = "%" . $query . "%";
    $params[':q1'] = $search_term;
    $params[':q2'] = $search_term;
    $params[':q3'] = $search_term;
    $params[':q4'] = $search_term;
}

if (!empty($selected_brand)) {
    $sql .= " AND brand = :brand";
    $params[':brand'] = $selected_brand;
}

if (!empty($selected_category)) {
    $sql .= " AND category = :cat";
    $params[':cat'] = $selected_category;
}

if (!empty($selected_model)) {
    $sql .= " AND model = :model";
    $params[':model'] = $selected_model;
}

// সর্টিং লজিক
switch ($sort) {
    case 'price_low':
        $sql .= " ORDER BY price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY price DESC";
        break;
    case 'rating':
        $sql .= " ORDER BY rating DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY id DESC";
        break;
}

// কুয়েরি এক্সিকিউট করা
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val, PDO::PARAM_STR);
}
$stmt->execute();
$products = $stmt->fetchAll();

// ফিল্টার ড্রপডাউনের জন্য ব্র্যান্ড এবং ক্যাটাগরি তালিকা নিয়ে আসা
$all_brands = $pdo->query("SELECT DISTINCT brand FROM products ORDER BY brand ASC")->fetchAll(PDO::FETCH_COLUMN);
$all_models = $pdo->query("SELECT DISTINCT model, brand FROM products ORDER BY model ASC")->fetchAll();

// ফ্ল্যাশ ডিলসের জন্য ৪টি স্পেশাল প্রোডাক্ট লোড করা
$flash_deals = $pdo->query("SELECT * FROM products WHERE original_price > price ORDER BY (original_price - price) DESC LIMIT 4")->fetchAll();

// ৫. হেডার ইনক্লুড করা
require_once __DIR__ . '/includes/header.php';
?>

<!-- ======================= ১. কমার্শিয়াল হিরো ব্যানার সেকশন ======================= -->
<section style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0b0f19 100%); border: 1px solid var(--border-subtle); border-radius: 20px; padding: 44px 36px; margin-bottom: 40px; box-shadow: var(--shadow-md); position: relative; overflow: hidden;">
    
    <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 40px; align-items: center; position: relative; z-index: 2;">
        
        <!-- বাম পাশের টেক্সট ও কল-টু-অ্যাকশন -->
        <div>
            <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.3); padding: 6px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; color: #38bdf8; text-transform: uppercase;">
                <span style="width: 8px; height: 8px; border-radius: 50%; background: #38bdf8; display: inline-block;"></span>
                দ্য সিঙ্গেল ইয়ারবাড রিপ্লেসমেন্ট প্ল্যাটফর্ম
            </div>

            <h1 style="font-size: 2.5rem; font-weight: 800; line-height: 1.2; color: #ffffff; margin-top: 16px; margin-bottom: 14px; letter-spacing: -0.5px;">
                একটি বাড হারিয়ে গেছে?<br>
                <span style="background: linear-gradient(90deg, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    পুরো সেট কেনার দিন শেষ।
                </span>
            </h1>

            <p style="font-size: 1.05rem; color: #94a3b8; line-height: 1.6; margin-bottom: 24px; max-width: 580px;">
                আপনার পছন্দের <strong>Apple AirPods, Samsung Galaxy Buds, Sony WF</strong> কিংবা <strong>Nothing Ear</strong>-এর হারিয়ে যাওয়া Left Bud, Right Bud বা Charging Case পেয়ে যান আলাদাভাবে। ১০০% আসল OEM কোয়ালিটি ও ৯০ দিনের রিপ্লেসমেন্ট ওয়ারেন্টিসহ।
            </p>

            <!-- বুলেট সুবিধা -->
            <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 28px; font-size: 0.9rem; color: #cbd5e1;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="color: #34d399; font-weight: 800;">✓</span> ১০০% অরিজিনাল সার্টিফাইড OEM পার্টস (ল্যাবে টেস্ট করা)
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="color: #34d399; font-weight: 800;">✓</span> সারা দেশে ক্যাশ অন ডেলিভারি (পার্সেল চেক করে পেমেন্ট)
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="color: #34d399; font-weight: 800;">✓</span> ৯০ দিনের ফুল আনকন্ডিশনাল রিপ্লেসমেন্ট গ্যারান্টি
                </div>
            </div>

            <!-- হিরো অ্যাকশন বাটন -->
            <div style="display: flex; gap: 14px; flex-wrap: wrap;">
                <a href="#shop" style="background: linear-gradient(135deg, #0284c7, #2563eb); color: white; padding: 14px 28px; border-radius: 10px; font-weight: 700; font-size: 1rem; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 16px rgba(2, 132, 199, 0.4);">
                    🛒 সব পার্টস ব্রাউজ করুন (Shop Now)
                </a>
                <a href="#finder" style="background: var(--bg-elevated); color: #cbd5e1; border: 1px solid var(--border-subtle); padding: 14px 24px; border-radius: 10px; font-weight: 700; font-size: 1rem; display: inline-flex; align-items: center; gap: 8px;">
                    🔍 বাড ফাইন্ডার টুল
                </a>
            </div>
        </div>

        <!-- ডান পাশের প্রিমিয়াম কমার্শিয়াল প্রোডাক্ট ভিজ্যুয়াল কার্ড -->
        <div style="display: flex; justify-content: center;">
            <div style="background: rgba(17, 24, 39, 0.85); backdrop-filter: blur(12px); border: 2px solid rgba(56, 189, 248, 0.3); border-radius: 20px; padding: 24px; width: 100%; max-width: 380px; box-shadow: 0 16px 36px rgba(0, 0, 0, 0.5); text-align: center;">
                <span style="background: #10b981; color: #022c22; font-size: 0.72rem; font-weight: 800; padding: 4px 12px; border-radius: 20px; text-transform: uppercase;">
                    ✓ ১০০% ম্যাচিং গ্যারান্টি
                </span>
                
                <!-- স্টুডিও ইমেজ -->
                <div style="margin: 20px 0; height: 200px; display: flex; align-items: center; justify-content: center; background: radial-gradient(circle, rgba(56, 189, 248, 0.15) 0%, rgba(15, 23, 42, 0.8) 70%); border-radius: 14px; padding: 10px;">
                    <img src="static/images/products/airpods-pro-left.svg" alt="AirPods Pro Replacement" style="max-height: 180px; width: auto; filter: drop-shadow(0 10px 20px rgba(0,0,0,0.5));">
                </div>

                <div style="font-size: 0.82rem; color: #38bdf8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                    Apple • AirPods Pro (2nd Gen)
                </div>
                <div style="font-size: 1.15rem; font-weight: 800; color: #ffffff; margin-top: 4px;">
                    Original Left Earbud (OEM)
                </div>
                <div style="margin-top: 10px; font-size: 1.25rem; font-weight: 800; color: #34d399;">
                    ৳ ৫,৮৯০ <span style="font-size: 0.85rem; color: #94a3b8; text-decoration: line-through;">৳৭,৫০০</span>
                </div>
                <a href="index.php?action=add&id=1&redirect=cart" style="display: block; margin-top: 14px; background: #0284c7; color: white; padding: 10px; border-radius: 8px; font-weight: 700; font-size: 0.9rem;">
                    🛒 সরাসরি অর্ডার করুন &rarr;
                </a>
            </div>
        </div>

    </div>
</section>


<!-- ======================= ২. ব্র্যান্ড সিলেকশন স্ট্রিপ (Shop by Brand) ======================= -->
<section style="margin-bottom: 40px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-title); display: flex; align-items: center; gap: 8px;">
            <span>🏷️</span> ব্র্যান্ড অনুযায়ী পার্টস ব্রাউজ করুন (Shop By Brand)
        </h3>
        <a href="index.php#shop" style="font-size: 0.85rem; color: #38bdf8; font-weight: 600;">সবগুলো দেখুন &rarr;</a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px;">
        <?php 
        $brand_icons = [
            'Apple' => ['icon' => '🍏', 'desc' => 'AirPods Pro & 3'],
            'Samsung' => ['icon' => '🌌', 'desc' => 'Galaxy Buds 2 Pro'],
            'Sony' => ['icon' => '🎵', 'desc' => 'WF-1000XM5 / XM4'],
            'Nothing' => ['icon' => '⚪', 'desc' => 'Nothing Ear (2)'],
            'OnePlus' => ['icon' => '🔴', 'desc' => 'Buds Pro 2 & Nord'],
            'Google' => ['icon' => '🔵', 'desc' => 'Pixel Buds Pro'],
            'JBL' => ['icon' => '🟧', 'desc' => 'Live Pro 2 TWS']
        ];
        foreach ($brand_icons as $bname => $bdata): 
            $is_active = ($selected_brand === $bname);
        ?>
            <a href="index.php?brand=<?php echo urlencode($bname); ?>#shop" 
               style="background: <?php echo $is_active ? 'rgba(56, 189, 248, 0.15)' : 'var(--bg-card)'; ?>; 
                      border: 1px solid <?php echo $is_active ? '#38bdf8' : 'var(--border-subtle)'; ?>; 
                      border-radius: 12px; padding: 14px 10px; text-align: center; transition: all 0.2s; display: block;"
               onmouseover="this.style.borderColor='#38bdf8'; this.style.transform='translateY(-2px)';"
               onmouseout="this.style.borderColor='<?php echo $is_active ? '#38bdf8' : 'var(--border-subtle)'; ?>'; this.style.transform='translateY(0)';">
                <div style="font-size: 1.8rem; margin-bottom: 4px;"><?php echo $bdata['icon']; ?></div>
                <div style="font-weight: 700; color: var(--text-title); font-size: 0.95rem;"><?php echo $bname; ?></div>
                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;"><?php echo $bdata['desc']; ?></div>
            </a>
        <?php endforeach; ?>
    </div>
</section>


<!-- ======================= ৩. ক্যাটাগরি ফিচার টাইলস (4 Visual Cards) ======================= -->
<section style="margin-bottom: 44px;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px;">
        
        <!-- Left Earbud Card -->
        <a href="index.php?category=Left Bud#shop" style="background: linear-gradient(135deg, #0e1e38, #111827); border: 1px solid rgba(56, 189, 248, 0.3); border-radius: 14px; padding: 22px; display: flex; justify-content: space-between; align-items: center; transition: transform 0.2s; box-shadow: var(--shadow-sm);" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
            <div>
                <span style="background: rgba(56, 189, 248, 0.2); color: #38bdf8; font-size: 0.72rem; font-weight: 800; padding: 3px 8px; border-radius: 4px; text-transform: uppercase;">ক্যাটাগরি</span>
                <h4 style="font-size: 1.2rem; font-weight: 800; color: #ffffff; margin-top: 8px;">Left Earbuds</h4>
                <p style="font-size: 0.8rem; color: #94a3b8; margin-top: 2px;">হারিয়ে যাওয়া বাম কানের আসল বাড</p>
                <div style="margin-top: 10px; font-size: 0.82rem; font-weight: 700; color: #38bdf8;">পার্টস দেখুন &rarr;</div>
            </div>
            <div style="font-size: 2.8rem;">🎧</div>
        </a>

        <!-- Right Earbud Card -->
        <a href="index.php?category=Right Bud#shop" style="background: linear-gradient(135deg, #23153c, #111827); border: 1px solid rgba(168, 85, 247, 0.3); border-radius: 14px; padding: 22px; display: flex; justify-content: space-between; align-items: center; transition: transform 0.2s; box-shadow: var(--shadow-sm);" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
            <div>
                <span style="background: rgba(168, 85, 247, 0.2); color: #c084fc; font-size: 0.72rem; font-weight: 800; padding: 3px 8px; border-radius: 4px; text-transform: uppercase;">ক্যাটাগরি</span>
                <h4 style="font-size: 1.2rem; font-weight: 800; color: #ffffff; margin-top: 8px;">Right Earbuds</h4>
                <p style="font-size: 0.8rem; color: #94a3b8; margin-top: 2px;">হারিয়ে যাওয়া ডান কানের আসল বাড</p>
                <div style="margin-top: 10px; font-size: 0.82rem; font-weight: 700; color: #c084fc;">পার্টস দেখুন &rarr;</div>
            </div>
            <div style="font-size: 2.8rem;">🎧</div>
        </a>

        <!-- Charging Case Card -->
        <a href="index.php?category=Charging Case#shop" style="background: linear-gradient(135deg, #06231d, #111827); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 14px; padding: 22px; display: flex; justify-content: space-between; align-items: center; transition: transform 0.2s; box-shadow: var(--shadow-sm);" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
            <div>
                <span style="background: rgba(16, 185, 129, 0.2); color: #34d399; font-size: 0.72rem; font-weight: 800; padding: 3px 8px; border-radius: 4px; text-transform: uppercase;">ক্যাটাগরি</span>
                <h4 style="font-size: 1.2rem; font-weight: 800; color: #ffffff; margin-top: 8px;">Charging Cases</h4>
                <p style="font-size: 0.8rem; color: #94a3b8; margin-top: 2px;">ম্যাগসেফ ও ওয়্যারলেস অরিজিনাল কেস</p>
                <div style="margin-top: 10px; font-size: 0.82rem; font-weight: 700; color: #34d399;">কেস দেখুন &rarr;</div>
            </div>
            <div style="font-size: 2.8rem;">🔋</div>
        </a>

        <!-- Authenticity Check Hub Card -->
        <a href="verify_serial.php" style="background: linear-gradient(135deg, #2b1704, #111827); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 14px; padding: 22px; display: flex; justify-content: space-between; align-items: center; transition: transform 0.2s; box-shadow: var(--shadow-sm);" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
            <div>
                <span style="background: rgba(245, 158, 11, 0.2); color: #fbbf24; font-size: 0.72rem; font-weight: 800; padding: 3px 8px; border-radius: 4px; text-transform: uppercase;">ল্যাব সিকিউরিটি</span>
                <h4 style="font-size: 1.2rem; font-weight: 800; color: #ffffff; margin-top: 8px;">OEM সত্যতা যাচাই</h4>
                <p style="font-size: 0.8rem; color: #94a3b8; margin-top: 2px;">সিরিয়াল ও কিউআর দিয়ে ১০০% আসল পরীক্ষা</p>
                <div style="margin-top: 10px; font-size: 0.82rem; font-weight: 700; color: #fbbf24;">যাচাই করুন &rarr;</div>
            </div>
            <div style="font-size: 2.8rem;">🛡️</div>
        </a>

    </div>
</section>


<!-- ======================= ৪. ৩-ক্লিক বাড ফাইন্ডার উইজার্ড (Finder Tool) ======================= -->
<section id="finder" style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 30px; margin-bottom: 44px; box-shadow: var(--shadow-sm);">
    <div style="text-align: center; margin-bottom: 24px;">
        <span style="background: rgba(56, 189, 248, 0.15); color: #38bdf8; font-weight: 700; font-size: 0.75rem; padding: 4px 12px; border-radius: 20px; text-transform: uppercase;">
            স্মার্ট ম্যাচিং টুল
        </span>
        <h2 style="font-size: 1.8rem; font-weight: 800; color: var(--text-title); margin-top: 8px;">
            ৩ ক্লিকে আপনার পার্টটি খুঁজে বের করুন
        </h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 4px;">
            ব্র্যান্ড, মডেল ও আপনার প্রয়োজনীয় অংশটি বাছাই করুন; আমাদের সিস্টেম সঠিক পার্টটি সাজেস্ট করবে।
        </p>
    </div>

    <form action="index.php#shop" method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) 160px; gap: 14px; align-items: flex-end;">
        
        <!-- ধাপ ১: ব্র্যান্ড -->
        <div>
            <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 6px;">১. ব্র্যান্ড সিলেক্ট করুন</label>
            <select name="brand" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-subtle); background: var(--bg-body); color: var(--text-title); font-size: 0.9rem; font-weight: 600;">
                <option value="">-- সব ব্র্যান্ড --</option>
                <?php foreach ($all_brands as $b): ?>
                    <option value="<?php echo htmlspecialchars($b); ?>" <?php echo ($selected_brand === $b) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($b); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- ধাপ ২: মডেল -->
        <div>
            <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 6px;">২. মডেল নির্ধারণ করুন</label>
            <select name="model" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-subtle); background: var(--bg-body); color: var(--text-title); font-size: 0.9rem; font-weight: 600;">
                <option value="">-- সব মডেল --</option>
                <?php foreach ($all_models as $m): ?>
                    <option value="<?php echo htmlspecialchars($m['model']); ?>" <?php echo ($selected_model === $m['model']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($m['brand'] . ' — ' . $m['model']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- ধাপ ৩: কোন অংশ প্রয়োজন -->
        <div>
            <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 6px;">৩. কোন অংশটি হারিয়েছে?</label>
            <select name="category" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-subtle); background: var(--bg-body); color: var(--text-title); font-size: 0.9rem; font-weight: 600;">
                <option value="">-- সব অংশ --</option>
                <option value="Left Bud" <?php echo ($selected_category === 'Left Bud') ? 'selected' : ''; ?>>🎧 Left Earbud (বাম)</option>
                <option value="Right Bud" <?php echo ($selected_category === 'Right Bud') ? 'selected' : ''; ?>>🎧 Right Earbud (ডান)</option>
                <option value="Charging Case" <?php echo ($selected_category === 'Charging Case') ? 'selected' : ''; ?>>🔋 Charging Case (কেস)</option>
            </select>
        </div>

        <!-- সাবমিট বাটন -->
        <div>
            <button type="submit" style="width: 100%; background: linear-gradient(135deg, #0284c7, #2563eb); color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 700; font-size: 0.95rem; cursor: pointer;">
                ⚡ পার্টটি দেখুন
            </button>
        </div>

    </form>
</section>


<!-- ======================= ৫. কমার্শিয়াল প্রোডাক্ট ক্যাটালগ ও গ্রিড ======================= -->
<section id="shop" style="margin-bottom: 50px;">
    
    <!-- ক্যাটালগ হেডার ও ফিল্টার বার -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <span style="font-size: 0.8rem; color: #38bdf8; font-weight: 700; text-transform: uppercase;">অরিজিনাল রিপ্লেসমেন্ট পার্টস</span>
            <h2 style="font-size: 1.8rem; font-weight: 800; color: var(--text-title); margin-top: 4px;">
                পছন্দের পার্টস বেছে নিন
            </h2>
            <p style="font-size: 0.85rem; color: var(--text-muted);">
                মোট <strong><?php echo count($products); ?></strong> টি অরিজিনাল সার্টিফাইড প্রোডাক্ট পাওয়া গেছে
            </p>
        </div>

        <!-- সর্টিং ড্রপডাউন -->
        <form action="index.php#shop" method="GET" style="display: flex; align-items: center; gap: 8px;">
            <?php if (!empty($selected_brand)): ?><input type="hidden" name="brand" value="<?php echo htmlspecialchars($selected_brand); ?>"><?php endif; ?>
            <?php if (!empty($selected_category)): ?><input type="hidden" name="category" value="<?php echo htmlspecialchars($selected_category); ?>"><?php endif; ?>
            <?php if (!empty($query)): ?><input type="hidden" name="q" value="<?php echo htmlspecialchars($query); ?>"><?php endif; ?>
            
            <label style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600;">সর্ট করুন:</label>
            <select name="sort" onchange="this.form.submit()" style="background: var(--bg-elevated); color: var(--text-title); border: 1px solid var(--border-subtle); padding: 8px 12px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                <option value="newest" <?php echo ($sort === 'newest') ? 'selected' : ''; ?>>নতুন আগে (Newest)</option>
                <option value="price_low" <?php echo ($sort === 'price_low') ? 'selected' : ''; ?>>দাম: কম থেকে বেশি</option>
                <option value="price_high" <?php echo ($sort === 'price_high') ? 'selected' : ''; ?>>দাম: বেশি থেকে কম</option>
                <option value="rating" <?php echo ($sort === 'rating') ? 'selected' : ''; ?>>সর্বোচ্চ রেটিং</option>
            </select>
        </form>
    </div>

    <!-- প্রোডাক্ট গ্রিড (Ultra-Clean Studio E-Commerce Cards) -->
    <?php if (!empty($products)): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px;">
            
            <?php foreach ($products as $p): 
                $discount_pct = ($p['original_price'] > $p['price']) ? round((($p['original_price'] - $p['price']) / $p['original_price']) * 100) : 0;
                $savings = $p['original_price'] - $p['price'];
            ?>
                <!-- ইন্ডিভিজুয়াল প্রোডাক্ট কার্ড -->
                <article style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; transition: all 0.3s ease; box-shadow: var(--shadow-sm); position: relative;"
                         onmouseover="this.style.transform='translateY(-6px)'; this.style.borderColor='rgba(56, 189, 248, 0.4)'; this.style.boxShadow='var(--shadow-md)';"
                         onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='var(--border-subtle)'; this.style.boxShadow='var(--shadow-sm)';">

                    <!-- কার্ড হেডার ব্যাজেস -->
                    <div style="position: absolute; top: 12px; left: 12px; right: 12px; display: flex; justify-content: space-between; align-items: center; z-index: 5; pointer-events: none;">
                        <?php if ($discount_pct > 0): ?>
                            <span style="background: linear-gradient(135deg, #f43f5e, #e11d48); color: white; font-size: 0.72rem; font-weight: 800; padding: 3px 8px; border-radius: 6px; box-shadow: 0 2px 6px rgba(244,63,94,0.4);">
                                -<?php echo $discount_pct; ?>% ছাড়
                            </span>
                        <?php else: ?>
                            <span></span>
                        <?php endif; ?>

                        <span style="background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3); font-size: 0.72rem; font-weight: 700; padding: 3px 8px; border-radius: 6px;">
                            <?php echo htmlspecialchars($p['category']); ?>
                        </span>
                    </div>

                    <!-- প্রিমিয়াম স্টুডিও ইমেজ বক্স (Zero-lag Vector SVG Display) -->
                    <div style="background: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%); height: 210px; display: flex; align-items: center; justify-content: center; padding: 20px; border-bottom: 1px solid var(--border-subtle); overflow: hidden;">
                        <img src="<?php echo htmlspecialchars($p['image']); ?>" 
                             alt="<?php echo htmlspecialchars($p['name']); ?>" 
                             loading="lazy"
                             style="max-height: 170px; width: auto; object-fit: contain; filter: drop-shadow(0 10px 18px rgba(0,0,0,0.45)); transition: transform 0.3s ease;"
                             onmouseover="this.style.transform='scale(1.08)'"
                             onmouseout="this.style.transform='scale(1)'">
                    </div>

                    <!-- প্রোডাক্ট ইনফরমেশন ও বিবরণ -->
                    <div style="padding: 18px; display: flex; flex-direction: column; flex: 1;">
                        
                        <div style="font-size: 0.72rem; color: #38bdf8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">
                            <?php echo htmlspecialchars($p['brand']); ?> • <?php echo htmlspecialchars($p['model']); ?>
                        </div>

                        <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-title); line-height: 1.4; margin-bottom: 8px; height: 44px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                            <?php echo htmlspecialchars($p['name']); ?>
                        </h3>

                        <!-- জেনুইন OEM ও ওয়ারেন্টি ব্যাজ -->
                        <div style="font-size: 0.75rem; color: #34d399; display: flex; align-items: center; gap: 5px; margin-bottom: 8px;">
                            <span>✓</span>
                            <span>১০০% আসল OEM জেনুইন · ৯০ দিন ওয়ারেন্টি</span>
                        </div>

                        <!-- রেটিং -->
                        <div style="display: flex; align-items: center; gap: 6px; font-size: 0.78rem; margin-bottom: 14px;">
                            <span style="color: #f59e0b; letter-spacing: 1px;">★★★★★</span>
                            <strong style="color: var(--text-title);"><?php echo $p['rating']; ?></strong>
                            <span style="color: var(--text-muted);">(<?php echo $p['reviews_count']; ?>)</span>
                        </div>

                        <!-- প্রাইস ও স্টক রো -->
                        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: auto; padding-top: 12px; border-top: 1px solid var(--border-subtle); margin-bottom: 16px;">
                            <div>
                                <div style="font-size: 1.35rem; font-weight: 800; color: #38bdf8; line-height: 1;">
                                    ৳ <?php echo number_format($p['price']); ?>
                                </div>
                                <?php if ($p['original_price'] > $p['price']): ?>
                                    <div style="font-size: 0.8rem; color: var(--text-muted); text-decoration: line-through; margin-top: 3px;">
                                        ৳ <?php echo number_format($p['original_price']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div>
                                <?php if ($p['stock'] > 0): ?>
                                    <span style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-size: 0.72rem; font-weight: 700; padding: 3px 8px; border-radius: 4px;">
                                        ● স্টকে আছে (<?php echo $p['stock']; ?>টি)
                                    </span>
                                <?php else: ?>
                                    <span style="background: rgba(244, 63, 94, 0.15); color: #fb7185; font-size: 0.72rem; font-weight: 700; padding: 3px 8px; border-radius: 4px;">
                                        স্টক আউট
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- ফুল-সাইজ কমার্শিয়াল বাটন: কার্টে যোগ করুন -->
                        <div style="display: grid; grid-template-columns: 1fr 48px; gap: 8px;">
                            <?php if ($p['stock'] > 0): ?>
                                <a href="index.php?action=add&id=<?php echo $p['id']; ?>" 
                                   style="background: linear-gradient(135deg, #0284c7, #2563eb); color: white; text-align: center; padding: 11px; border-radius: 8px; font-weight: 700; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; gap: 6px; box-shadow: 0 2px 8px rgba(2, 132, 199, 0.3);">
                                    <span>🛒</span>
                                    <span>কার্টে যোগ করুন</span>
                                </a>
                            <?php else: ?>
                                <button disabled style="background: #334155; color: #94a3b8; border: none; padding: 11px; border-radius: 8px; font-weight: 700; font-size: 0.9rem; cursor: not-allowed;">
                                    স্টক নেই
                                </button>
                            <?php endif; ?>

                            <!-- সিরিয়াল ভেরিফিকেশন কুইক লিঙ্ক -->
                            <a href="verify_serial.php?serial=<?php echo urlencode($p['serial_number']); ?>" 
                               title="এই পার্টের OEM সার্টিফিকেট দেখুন"
                               style="background: var(--bg-elevated); border: 1px solid var(--border-subtle); color: #38bdf8; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                                🛡️
                            </a>
                        </div>

                    </div>
                </article>
            <?php endforeach; ?>

        </div>
    <?php else: ?>
        <div style="background: var(--bg-card); border: 1px dashed var(--border-subtle); border-radius: 16px; padding: 60px 20px; text-align: center;">
            <div style="font-size: 3rem;">🔍</div>
            <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--text-title); margin-top: 10px;">কোনো পার্টস পাওয়া যায়নি!</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 4px;">অনুগ্রহ করে ফিল্টার পরিবর্তন করুন বা অন্য কোনো মডেলের নাম দিয়ে সার্চ করুন।</p>
            <div style="margin-top: 18px;">
                <a href="index.php#shop" style="background: #0284c7; color: white; padding: 10px 20px; border-radius: 8px; font-weight: 700; font-size: 0.88rem;">
                    সব পার্টস ফিল্টার রিসেট করুন
                </a>
            </div>
        </div>
    <?php endif; ?>

</section>


<!-- ======================= ৬. কাস্টমারদের ভেরিফায়েড রিভিউ (Social Proof) ======================= -->
<section style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 36px 30px; margin-bottom: 40px; box-shadow: var(--shadow-sm);">
    <div style="text-align: center; margin-bottom: 30px;">
        <span style="color: #10b981; font-weight: 700; font-size: 0.75rem; background: rgba(16, 185, 129, 0.15); padding: 4px 12px; border-radius: 20px; text-transform: uppercase;">
            গ্রাহক সন্তুষ্টি ও আস্থা
        </span>
        <h2 style="font-size: 1.8rem; font-weight: 800; color: var(--text-title); margin-top: 8px;">
            সন্তুষ্ট গ্রাহকদের বাস্তব অভিজ্ঞতা
        </h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 4px;">
            ৫০০+ কাস্টমার আমাদের মাধ্যমে তাদের হারিয়ে যাওয়া বাড সফলভাবে জোড়া লাগিয়ে সম্পূর্ণ সেট উদ্ধার করেছেন।
        </p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
        
        <div style="background: var(--bg-body); border: 1px solid var(--border-subtle); padding: 22px; border-radius: 12px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <div>
                    <div style="font-weight: 700; color: var(--text-title);">তানভীর আহমেদ</div>
                    <div style="font-size: 0.72rem; color: #10b981; font-weight: 600;">✓ যাচাইকৃত ক্রেতা (Verified Buyer)</div>
                </div>
                <div style="color: #f59e0b; font-size: 0.85rem;">★★★★★</div>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-body); line-height: 1.5;">
                "আমার AirPods Pro 2 এর লেফট বাডটি রিকশায় পড়ে হারিয়ে গিয়েছিল। অ্যাপল শপে গিয়ে শুনলাম নতুন কিনতে হবে ২৮ হাজার টাকায়! BudsRecover থেকে মাত্র ৫,৮৯০ টাকায় অরিজিনাল বাড পেয়েছি। সাথে সাথেই কেসে রেখে পেয়ার হয়ে গেছে!"
            </p>
        </div>

        <div style="background: var(--bg-body); border: 1px solid var(--border-subtle); padding: 22px; border-radius: 12px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <div>
                    <div style="font-weight: 700; color: var(--text-title);">সাদিয়া আফরোজ</div>
                    <div style="font-size: 0.72rem; color: #10b981; font-weight: 600;">✓ যাচাইকৃত ক্রেতা (Verified Buyer)</div>
                </div>
                <div style="color: #f59e0b; font-size: 0.85rem;">★★★★★</div>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-body); line-height: 1.5;">
                "Galaxy Buds 2 Pro এর রাইট বাড নিয়ে দুশ্চিন্তায় ছিলাম। সিরিয়াল নম্বর দিয়ে ওদের সাইটে চেক করে নিশ্চিত হলাম এটা আসল OEM। ক্যাশ অন ডেলিভারিতে হাতে পেয়ে চেক করে টাকা দিয়েছি। বেস্ট সার্ভিস!"
            </p>
        </div>

        <div style="background: var(--bg-body); border: 1px solid var(--border-subtle); padding: 22px; border-radius: 12px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <div>
                    <div style="font-weight: 700; color: var(--text-title);">ফারহান মাহমুদ</div>
                    <div style="font-size: 0.72rem; color: #10b981; font-weight: 600;">✓ যাচাইকৃত ক্রেতা (Verified Buyer)</div>
                </div>
                <div style="color: #f59e0b; font-size: 0.85rem;">★★★★★</div>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-body); line-height: 1.5;">
                "Sony WF-1000XM5 এর অ্যাক্টিভ নয়েজ ক্যান্সেলেশন নিখুঁতভাবে কাজ করছে। পেয়ারিং গাইডের স্টেপ ফলো করে এক মিনিটেই সিঙ্ক হয়ে গেল। ৯০ দিনের ওয়ারেন্টি কার্ড সাথে দেওয়া হয়েছিল।"
            </p>
        </div>

    </div>
</section>

<?php
// ফুটার ইনক্লুড করা
require_once __DIR__ . '/includes/footer.php';
?>
