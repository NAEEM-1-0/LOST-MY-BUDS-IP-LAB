<?php
/**
 * ==============================================================================================
 * ফাইল পরিচিতি: cart.php (Commercial E-Commerce Cart Engine)
 * কোর্স: Internet Programming Lab (IP Lab) / Web Engineering
 * প্রজেক্ট: BudsRecover — Commercial PHP E-Commerce Store
 * ==============================================================================================
 * 
 * 🎓 ম্যামের সম্ভাব্য প্রশ্ন ও উত্তর (Viva Q&A):
 * ১. প্রশ্ন: কার্টের আইটেম রিমুভ বা সংখ্যা পরিবর্তন কীভাবে হ্যান্ডেল করা হয়েছে?
 *    উত্তর: ইউজারের অ্যাকশন (update, remove) অনুযায়ী $_SESSION['cart'][$pid] মডিফাই করা হয়েছে। 
 *          পরিমাণ ০ বা ঋণাত্মক হলে unset() দিয়ে সেশন অ্যারে থেকে মুছে ফেলা হয়।
 * ২. প্রশ্ন: কুপন কোড প্রয়োগ কীভাবে কাজ করে?
 *    উত্তর: ফর্ম সাবমিট হলে $_POST['coupon_code'] সেশনে ($_SESSION['coupon_code']) সেট করা হয় এবং
 *          get_cart_totals() ফাংশনে মোট সাবটোটাল থেকে ডিসকাউন্ট কেটে নেওয়া হয়।
 * ==============================================================================================
 */

$page_title = "আপনার শপিং ব্যাগ";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/cart_functions.php';

// ১. কার্ট অ্যাকশন হ্যান্ডলিং (আপডেট ও রিমুভ)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $pid = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($action === 'remove' && $pid > 0) {
        remove_from_cart($pid);
        $_SESSION['flash_success'] = "পণ্যটি আপনার ব্যাগ থেকে মুছে ফেলা হয়েছে।";
        header("Location: cart.php");
        exit;
    }

    if ($action === 'update' && $pid > 0 && isset($_GET['qty'])) {
        $qty = (int)$_GET['qty'];
        update_cart_quantity($pid, $qty);
        header("Location: cart.php");
        exit;
    }

    if ($action === 'clear') {
        $_SESSION['cart'] = [];
        unset($_SESSION['coupon_code']);
        $_SESSION['flash_success'] = "আপনার ব্যাগ খালি করা হয়েছে।";
        header("Location: cart.php");
        exit;
    }

    if ($action === 'remove_coupon') {
        unset($_SESSION['coupon_code']);
        $_SESSION['flash_success'] = "কুপন কোড প্রত্যাহার করা হয়েছে।";
        header("Location: cart.php");
        exit;
    }
}

// ২. কুপন কোড প্রয়োগ হ্যান্ডলিং ($_POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_coupon'])) {
    $code = strtoupper(trim($_POST['coupon_code']));
    if (in_array($code, ['BUDS10', 'SAVE500', 'FREESHIP'])) {
        $_SESSION['coupon_code'] = $code;
        $_SESSION['flash_success'] = "🎉 অভিনন্দন! কুপন কোড '$code' সফলভাবে প্রযোজ্য হয়েছে!";
    } else {
        $_SESSION['flash_error'] = "দুঃখিত, কুপন কোডটি সঠিক নয়। ডেমো কুপন ব্যবহার করুন: BUDS10, SAVE500 বা FREESHIP";
    }
    header("Location: cart.php");
    exit;
}

// ৩. কার্টের আইটেম ও মোট হিসাব লোড করা
$cart_items = get_cart_items($pdo);
$totals = get_cart_totals($pdo, 'ঢাকা');

// হেডার লোড
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 1100px; margin: 0 auto; display: flex; flex-direction: column; gap: 28px;">

    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border-subtle); padding-bottom: 16px; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1 style="font-size: 1.8rem; font-weight: 800; color: var(--text-title);">
                🛒 আপনার শপিং ব্যাগ (Shopping Cart)
            </h1>
            <p style="font-size: 0.85rem; color: var(--text-muted);">
                ব্যাগে মোট <strong><?php echo get_cart_count(); ?></strong> টি আইটেম রয়েছে
            </p>
        </div>

        <?php if (!empty($cart_items)): ?>
            <a href="cart.php?action=clear" onclick="return confirm('আপনি কি নিশ্চিত যে সম্পূর্ণ ব্যাগ খালি করতে চান?');" 
               style="color: #f43f5e; font-size: 0.85rem; font-weight: 600; text-decoration: underline;">
                ব্যাগ খালি করুন ✕
            </a>
        <?php endif; ?>
    </div>

    <?php if (!empty($cart_items)): ?>
        
        <!-- ফ্রি ডেলিভারি প্রোগ্রেস বার -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 12px; padding: 16px 20px;">
            <?php if ($totals['subtotal'] >= 5000): ?>
                <div style="color: #34d399; font-weight: 700; font-size: 0.92rem; display: flex; align-items: center; gap: 8px;">
                    <span>🎉</span>
                    <span>অভিনন্দন! আপনি এই অর্ডারে <strong>১০০% ফ্রি হোম ডেলিভারি</strong> পাচ্ছেন!</span>
                </div>
            <?php else: 
                $needed = 5000 - $totals['subtotal'];
                $pct = min(100, round(($totals['subtotal'] / 5000) * 100));
            ?>
                <div style="color: #cbd5e1; font-size: 0.88rem; margin-bottom: 8px;">
                    🚚 আর মাত্র <strong>৳ <?php echo number_format($needed); ?></strong> টাকার পার্টস যোগ করলেই পাচ্ছেন <strong>ফ্রি হোম ডেলিভারি!</strong>
                </div>
                <div style="background: #1e293b; height: 8px; border-radius: 4px; overflow: hidden;">
                    <div style="background: linear-gradient(90deg, #0284c7, #10b981); height: 100%; width: <?php echo $pct; ?>%; border-radius: 4px;"></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- কার্ট লেআউট গ্রিড: বামে আইটেম টেবিল, ডানে অর্ডার সামারি -->
        <div style="display: grid; grid-template-columns: 1fr 360px; gap: 28px; align-items: flex-start;">
            
            <!-- কার্ট আইটেম তালিকা -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 14px; overflow: hidden; box-shadow: var(--shadow-sm);">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                    <thead>
                        <tr style="background: var(--bg-elevated); color: var(--text-muted); border-bottom: 1px solid var(--border-subtle);">
                            <th style="padding: 14px;">পণ্য</th>
                            <th style="padding: 14px; text-align: center;">একক মূল্য</th>
                            <th style="padding: 14px; text-align: center;">পরিমাণ</th>
                            <th style="padding: 14px; text-align: right;">মোট টাকা</th>
                            <th style="padding: 14px; text-align: center;">অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody style="color: var(--text-title);">
                        <?php foreach ($cart_items as $item): ?>
                            <tr style="border-bottom: 1px solid var(--border-subtle);">
                                <td style="padding: 16px;">
                                    <div style="display: flex; gap: 14px; align-items: center;">
                                        <div style="width: 60px; height: 60px; background: radial-gradient(circle, #1e293b, #0f172a); border-radius: 8px; display: flex; align-items: center; justify-content: center; padding: 4px; flex-shrink: 0;">
                                            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="max-height: 50px; width: auto; object-fit: contain;">
                                        </div>
                                        <div>
                                            <div style="font-size: 0.75rem; color: #38bdf8; font-weight: 700; text-transform: uppercase;">
                                                <?php echo htmlspecialchars($item['brand']); ?> • <?php echo htmlspecialchars($item['category']); ?>
                                            </div>
                                            <div style="font-weight: 700; font-size: 0.95rem; margin-top: 2px;">
                                                <?php echo htmlspecialchars($item['name']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                
                                <td style="padding: 16px; text-align: center; font-weight: 600; color: var(--text-muted);">
                                    ৳ <?php echo number_format($item['price']); ?>
                                </td>

                                <!-- পরিমাণ পরিবর্তন বাটন (+ / -) -->
                                <td style="padding: 16px; text-align: center;">
                                    <div style="display: inline-flex; align-items: center; border: 1px solid var(--border-subtle); border-radius: 6px; overflow: hidden; background: var(--bg-body);">
                                        <a href="cart.php?action=update&id=<?php echo $item['id']; ?>&qty=<?php echo $item['quantity'] - 1; ?>" 
                                           style="padding: 4px 10px; color: var(--text-muted); font-weight: 700; text-decoration: none;">-</a>
                                        <span style="padding: 4px 10px; font-weight: 700; font-size: 0.9rem;"><?php echo $item['quantity']; ?></span>
                                        <a href="cart.php?action=update&id=<?php echo $item['id']; ?>&qty=<?php echo $item['quantity'] + 1; ?>" 
                                           style="padding: 4px 10px; color: var(--text-muted); font-weight: 700; text-decoration: none;">+</a>
                                    </div>
                                </td>

                                <td style="padding: 16px; text-align: right; font-weight: 800; color: #34d399; font-size: 1.05rem;">
                                    ৳ <?php echo number_format($item['line_total']); ?>
                                </td>

                                <td style="padding: 16px; text-align: center;">
                                    <a href="cart.php?action=remove&id=<?php echo $item['id']; ?>" 
                                       title="মুছে ফেলুন"
                                       style="color: #f43f5e; font-size: 1.1rem; text-decoration: none; padding: 4px 8px; border-radius: 4px;">
                                        ✕
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- ডানপাশের অর্ডার সামারি ও কুপন ইঞ্জিন -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                
                <!-- কুপন কোড ফর্ম -->
                <div style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 14px; padding: 20px; box-shadow: var(--shadow-sm);">
                    <h4 style="font-size: 0.92rem; font-weight: 700; color: var(--text-title); margin-bottom: 10px;">
                        🏷️ কুপন বা প্রোমো কোড প্রয়োগ করুন
                    </h4>

                    <?php if (!empty($totals['coupon_code'])): ?>
                        <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); padding: 10px 14px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <span style="font-size: 0.8rem; color: #34d399; font-weight: 700;">প্রযোজ্য কুপন:</span>
                                <strong style="font-family: monospace; color: #ffffff;"><?php echo htmlspecialchars($totals['coupon_code']); ?></strong>
                            </div>
                            <a href="cart.php?action=remove_coupon" style="color: #f43f5e; font-size: 0.8rem; font-weight: 700; text-decoration: underline;">বাতিল করুন</a>
                        </div>
                    <?php else: ?>
                        <form action="cart.php" method="POST" style="display: flex; gap: 8px;">
                            <input type="text" name="coupon_code" placeholder="যেমন: BUDS10" required
                                   style="flex: 1; padding: 10px 12px; border-radius: 6px; border: 1px solid var(--border-subtle); background: var(--bg-body); color: #fff; font-family: monospace; text-transform: uppercase;">
                            <button type="submit" name="apply_coupon" 
                                    style="background: #0284c7; color: white; border: none; padding: 10px 16px; border-radius: 6px; font-weight: 700; cursor: pointer;">
                                প্রয়োগ
                            </button>
                        </form>
                        <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 6px;">
                            💡 ডেমো কুপন: <code>BUDS10</code> (১০% ছাড়) অথবা <code>SAVE500</code>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- মূল্য সামারি বক্স -->
                <div style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 14px; padding: 24px; box-shadow: var(--shadow-sm);">
                    <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-title); margin-bottom: 16px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 10px;">
                        অর্ডার বিবরণী (Summary)
                    </h3>

                    <div style="display: flex; flex-direction: column; gap: 10px; font-size: 0.9rem;">
                        <div style="display: flex; justify-content: space-between; color: var(--text-muted);">
                            <span>সাবটোটাল (পণ্য মূল্য):</span>
                            <span style="font-weight: 700; color: var(--text-title);">৳ <?php echo number_format($totals['subtotal']); ?></span>
                        </div>

                        <?php if ($totals['discount'] > 0): ?>
                            <div style="display: flex; justify-content: space-between; color: #34d399;">
                                <span>কুপন ছাড় (<?php echo htmlspecialchars($totals['coupon_code']); ?>):</span>
                                <span style="font-weight: 700;">- ৳ <?php echo number_format($totals['discount']); ?></span>
                            </div>
                        <?php endif; ?>

                        <div style="display: flex; justify-content: space-between; color: var(--text-muted);">
                            <span>হোম ডেলিভারি ফি:</span>
                            <?php if ($totals['delivery_fee'] == 0): ?>
                                <span style="color: #34d399; font-weight: 700;">ফ্রি (FREE)</span>
                            <?php else: ?>
                                <span style="font-weight: 700; color: var(--text-title);">৳ <?php echo number_format($totals['delivery_fee']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div style="border-top: 2px solid var(--border-subtle); margin-top: 6px; padding-top: 12px; display: flex; justify-content: space-between; font-size: 1.3rem; font-weight: 800; color: #38bdf8;">
                            <span>সর্বমোট প্রদেয়:</span>
                            <span>৳ <?php echo number_format($totals['grand_total']); ?></span>
                        </div>
                    </div>

                    <!-- চেকআউটে যাওয়ার বাটন -->
                    <a href="checkout.php" 
                       style="display: block; width: 100%; margin-top: 24px; background: linear-gradient(135deg, #10b981, #059669); color: white; text-align: center; padding: 14px; border-radius: 10px; font-weight: 800; font-size: 1.05rem; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);">
                        চেকআউটে যান &rarr;
                    </a>

                    <div style="text-align: center; margin-top: 12px;">
                        <a href="index.php#shop" style="font-size: 0.82rem; color: #38bdf8; text-decoration: underline;">
                            &larr; আরও পার্টস যোগ করুন
                        </a>
                    </div>
                </div>

            </div>

        </div>

    <?php else: ?>
        <!-- ব্যাগ ফাঁকা থাকলে প্রদর্শন -->
        <div style="background: var(--bg-card); border: 1px dashed var(--border-subtle); border-radius: 16px; padding: 60px 20px; text-align: center;">
            <div style="font-size: 3.5rem;">🛍️</div>
            <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--text-title); margin-top: 10px;">
                আপনার শপিং ব্যাগ বর্তমানে সম্পূর্ণ ফাঁকা!
            </h2>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 6px; max-width: 500px; margin-left: auto; margin-right: auto;">
                আপনার পছন্দের ব্র্যান্ড ও মডেল নির্বাচন করে হারিয়ে যাওয়া বাডের অরিজিনাল রিপ্লেসমেন্ট ব্যাগে যোগ করুন।
            </p>
            <div style="margin-top: 24px;">
                <a href="index.php#shop" style="background: linear-gradient(135deg, #0284c7, #2563eb); color: white; padding: 12px 28px; border-radius: 8px; font-weight: 700; font-size: 0.95rem;">
                    🛒 শপ ক্যাটালগ ব্রাউজ করুন
                </a>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
